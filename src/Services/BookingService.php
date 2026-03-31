<?php
namespace ReuseIT\Services;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use PDO;
use ReuseIT\Repositories\BookingEventRepository;
use ReuseIT\Repositories\BookingRepository;
use ReuseIT\Repositories\ConversationRepository;
use ReuseIT\Repositories\ListingRepository;
use ReuseIT\Repositories\PickupWindowRepository;
use RuntimeException;
use Throwable;

/**
 * BookingService
 *
 * Authoritative booking workflow state machine with transaction orchestration.
 */
class BookingService {

    private const STATUS_PENDING = 'pending';
    private const STATUS_CONFIRMED = 'confirmed';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_CANCELLED = 'cancelled';

    private const PICKUP_PROPOSED = 'proposed';
    private const PICKUP_COUNTERED = 'countered';
    private const PICKUP_ACCEPTED = 'accepted';

    private const EVENT_CREATED = 'created';
    private const EVENT_CONFIRMED = 'confirmed';
    private const EVENT_PICKUP_PROPOSED = 'pickup_proposed';
    private const EVENT_PICKUP_COUNTERED = 'pickup_countered';
    private const EVENT_PICKUP_ACCEPTED = 'pickup_accepted';
    private const EVENT_CANCELLED = 'cancelled';
    private const EVENT_EXPIRED = 'expired';
    private const EVENT_COMPLETED = 'completed';

    private PDO $pdo;
    private BookingRepository $bookingRepo;
    private BookingEventRepository $bookingEventRepo;
    private PickupWindowRepository $pickupWindowRepo;
    private ConversationRepository $conversationRepo;
    private ListingRepository $listingRepo;
    private BookingNotificationService $notificationService;

    /** @var array<string, array<string>> */
    private array $transitionGuards = [
        self::STATUS_PENDING => [
            'seller_confirm',
            'participant_cancel',
            'auto_expire'
        ],
        self::STATUS_CONFIRMED => [
            'participant_cancel',
            'participant_complete',
            'pickup_propose',
            'pickup_counter',
            'pickup_accept'
        ],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    /** @var string[] */
    private array $allowedCancelReasons = [
        'changed_mind',
        'item_unavailable',
        'seller_unresponsive',
        'buyer_unresponsive',
        'schedule_conflict',
        'safety_concern',
        'other',
        'expired'
    ];

    public function __construct(
        PDO $pdo,
        BookingRepository $bookingRepo,
        BookingEventRepository $bookingEventRepo,
        PickupWindowRepository $pickupWindowRepo,
        ConversationRepository $conversationRepo,
        ListingRepository $listingRepo,
        BookingNotificationService $notificationService
    ) {
        $this->pdo = $pdo;
        $this->bookingRepo = $bookingRepo;
        $this->bookingEventRepo = $bookingEventRepo;
        $this->pickupWindowRepo = $pickupWindowRepo;
        $this->conversationRepo = $conversationRepo;
        $this->listingRepo = $listingRepo;
        $this->notificationService = $notificationService;
    }

    public function createBooking(int $listingId, int $buyerId): array {
        try {
            $this->pdo->beginTransaction();

            $listing = $this->lockListingForUpdate($listingId);
            if (!$listing) {
                throw new InvalidArgumentException('Listing not found');
            }

            if (($listing['status'] ?? null) !== 'active') {
                throw new InvalidArgumentException('Only active listings can be booked');
            }

            $sellerId = (int) $listing['seller_id'];
            if ($buyerId === $sellerId) {
                throw new InvalidArgumentException('You cannot book your own listing');
            }

            $activeBooking = $this->bookingRepo->findActiveByListingForUpdate($listingId);
            if ($activeBooking) {
                if ((int) $activeBooking['buyer_id'] === $buyerId) {
                    $conversationId = $this->conversationRepo->createOrTouchForBooking($listingId, $buyerId, $sellerId);
                    $this->pdo->commit();

                    return [
                        'booking' => $activeBooking,
                        'conversation_id' => $conversationId,
                        'respond_by' => $activeBooking['expires_at'] ?? null,
                    ];
                }

                throw new RuntimeException('Listing already has an active booking', 409);
            }

            $now = date('Y-m-d H:i:s');
            $respondBy = date('Y-m-d H:i:s', strtotime('+12 hours')); // DATE_ADD(... INTERVAL 12 HOUR)

            $bookingId = $this->bookingRepo->create([
                'listing_id' => $listingId,
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'booking_status' => self::STATUS_PENDING,
                'booking_date' => $now,
                'expires_at' => $respondBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $conversationId = $this->conversationRepo->createOrTouchForBooking($listingId, $buyerId, $sellerId);

            $this->bookingEventRepo->create([
                'booking_id' => $bookingId,
                'event_type' => self::EVENT_CREATED,
                'actor_user_id' => $buyerId,
                'reason_code' => null,
                'note' => json_encode(['respond_by' => $respondBy]),
                'event_at' => $now,
            ]);

            $booking = $this->bookingRepo->find($bookingId);

            $this->pdo->commit();

            return [
                'booking' => $booking,
                'conversation_id' => $conversationId,
                'respond_by' => $respondBy,
            ];
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw $e;
        }
    }

    public function confirmBooking(int $bookingId, int $sellerId): array {
        try {
            $this->pdo->beginTransaction();

            $booking = $this->getBookingForTransition($bookingId);
            $this->assertTransition((string) $booking['booking_status'], 'seller_confirm');

            if ((int) $booking['seller_id'] !== $sellerId) {
                throw new InvalidArgumentException('Only seller can confirm this booking');
            }

            if (!empty($booking['expires_at']) && strtotime((string) $booking['expires_at']) <= time()) {
                throw new InvalidArgumentException('Booking already expired');
            }

            $now = date('Y-m-d H:i:s');
            $this->bookingRepo->update($bookingId, [
                'booking_status' => self::STATUS_CONFIRMED,
                'updated_at' => $now,
            ]);

            $this->bookingEventRepo->create([
                'booking_id' => $bookingId,
                'event_type' => self::EVENT_CONFIRMED,
                'actor_user_id' => $sellerId,
                'event_at' => $now,
            ]);

            $updated = $this->bookingRepo->find($bookingId);
            $this->pdo->commit();

            return $updated ?? [];
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw $e;
        }
    }

    public function completeBooking(int $bookingId, int $actorUserId): array {
        try {
            $this->pdo->beginTransaction();

            $booking = $this->getBookingForTransition($bookingId);
            $this->assertParticipant($booking, $actorUserId);
            $this->assertTransition((string) $booking['booking_status'], 'participant_complete');

            $now = date('Y-m-d H:i:s');
            $this->bookingRepo->update($bookingId, [
                'booking_status' => self::STATUS_COMPLETED,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);

            $this->bookingEventRepo->create([
                'booking_id' => $bookingId,
                'event_type' => self::EVENT_COMPLETED,
                'actor_user_id' => $actorUserId,
                'event_at' => $now,
            ]);

            $updated = $this->bookingRepo->find($bookingId);
            $this->pdo->commit();

            return $updated ?? [];
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw $e;
        }
    }

    public function cancelBooking(int $bookingId, int $actorUserId, string $reasonCode, ?string $note): array {
        $reasonCode = trim($reasonCode);
        if ($reasonCode === '') {
            throw new InvalidArgumentException('Cancellation reason code is required');
        }

        if (!in_array($reasonCode, $this->allowedCancelReasons, true)) {
            throw new InvalidArgumentException('Invalid cancellation reason code');
        }

        try {
            $this->pdo->beginTransaction();

            $booking = $this->getBookingForTransition($bookingId);
            $this->assertParticipant($booking, $actorUserId);
            $this->assertTransition((string) $booking['booking_status'], 'participant_cancel');

            $now = date('Y-m-d H:i:s');
            $this->bookingRepo->update($bookingId, [
                'booking_status' => self::STATUS_CANCELLED,
                'cancelled_at' => $now,
                'cancelled_by_user_id' => $actorUserId,
                'cancel_reason_code' => $reasonCode,
                'cancel_reason_note' => $note,
                'updated_at' => $now,
            ]);

            $this->bookingEventRepo->create([
                'booking_id' => $bookingId,
                'event_type' => self::EVENT_CANCELLED,
                'actor_user_id' => $actorUserId,
                'reason_code' => $reasonCode,
                'note' => $note,
                'event_at' => $now,
            ]);

            $updated = $this->bookingRepo->find($bookingId) ?? [];
            $this->pdo->commit();

            $this->notificationService->notifyBookingCancelled($updated, $reasonCode, $note);

            return $updated;
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw $e;
        }
    }

    public function expirePendingBookings(int $batchSize = 100): int {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be positive');
        }

        try {
            $this->pdo->beginTransaction();

            $selectSql = "
                SELECT *
                FROM bookings
                WHERE booking_status = 'pending'
                  AND expires_at IS NOT NULL
                  AND expires_at <= NOW()
                  AND deleted_at IS NULL
                ORDER BY expires_at ASC
                LIMIT ?
                FOR UPDATE
            ";

            $selectStmt = $this->pdo->prepare($selectSql);
            $selectStmt->execute([$batchSize]);
            $expiredBookings = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$expiredBookings) {
                $this->pdo->commit();
                return 0;
            }

            $now = date('Y-m-d H:i:s');
            foreach ($expiredBookings as $booking) {
                $bookingId = (int) $booking['id'];
                $this->bookingRepo->update($bookingId, [
                    'booking_status' => self::STATUS_CANCELLED,
                    'cancelled_at' => $now,
                    'cancel_reason_code' => 'expired',
                    'updated_at' => $now,
                ]);

                $this->bookingEventRepo->create([
                    'booking_id' => $bookingId,
                    'event_type' => self::EVENT_EXPIRED,
                    'reason_code' => 'expired',
                    'event_at' => $now,
                ]);
            }

            $this->pdo->commit();

            foreach ($expiredBookings as $booking) {
                $booking['booking_status'] = self::STATUS_CANCELLED;
                $booking['cancel_reason_code'] = 'expired';
                $booking['cancelled_at'] = $now;
                $this->notificationService->notifyBookingExpired($booking);
            }

            return count($expiredBookings);
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw $e;
        }
    }

    public function proposePickupWindow(int $bookingId, int $buyerId, string $startAt, string $endAt): array {
        [$start, $end] = $this->validateWindowBounds($startAt, $endAt);

        try {
            $this->pdo->beginTransaction();

            $booking = $this->getBookingForTransition($bookingId);
            $this->assertTransition((string) $booking['booking_status'], 'pickup_propose');

            if ((int) $booking['buyer_id'] !== $buyerId) {
                throw new InvalidArgumentException('Only buyer can propose pickup windows');
            }

            $proposalId = $this->pickupWindowRepo->create([
                'booking_id' => $bookingId,
                'proposed_by_user_id' => $buyerId,
                'window_start_at' => $start->format('Y-m-d H:i:s'),
                'window_end_at' => $end->format('Y-m-d H:i:s'),
                'proposal_status' => self::PICKUP_PROPOSED,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->bookingEventRepo->create([
                'booking_id' => $bookingId,
                'event_type' => self::EVENT_PICKUP_PROPOSED,
                'actor_user_id' => $buyerId,
                'note' => json_encode([
                    'proposal_id' => $proposalId,
                    'window_start_at' => $start->format('Y-m-d H:i:s'),
                    'window_end_at' => $end->format('Y-m-d H:i:s')
                ]),
                'event_at' => date('Y-m-d H:i:s'),
            ]);

            $this->pdo->commit();

            return [
                'proposal_id' => $proposalId,
                'booking_id' => $bookingId,
                'window_start_at' => $start->format('Y-m-d H:i:s'),
                'window_end_at' => $end->format('Y-m-d H:i:s'),
                'proposal_status' => self::PICKUP_PROPOSED,
            ];
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw $e;
        }
    }

    public function counterPickupWindow(int $bookingId, int $sellerId, string $startAt, string $endAt): array {
        [$start, $end] = $this->validateWindowBounds($startAt, $endAt);

        try {
            $this->pdo->beginTransaction();

            $booking = $this->getBookingForTransition($bookingId);
            $this->assertTransition((string) $booking['booking_status'], 'pickup_counter');

            if ((int) $booking['seller_id'] !== $sellerId) {
                throw new InvalidArgumentException('Only seller can counter pickup windows');
            }

            $windows = $this->pickupWindowRepo->findByBookingId($bookingId);
            if (count($windows) === 0) {
                throw new InvalidArgumentException('Buyer must propose first before seller can counter');
            }

            $proposalId = $this->pickupWindowRepo->create([
                'booking_id' => $bookingId,
                'proposed_by_user_id' => $sellerId,
                'window_start_at' => $start->format('Y-m-d H:i:s'),
                'window_end_at' => $end->format('Y-m-d H:i:s'),
                'proposal_status' => self::PICKUP_COUNTERED,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->bookingEventRepo->create([
                'booking_id' => $bookingId,
                'event_type' => self::EVENT_PICKUP_COUNTERED,
                'actor_user_id' => $sellerId,
                'note' => json_encode([
                    'proposal_id' => $proposalId,
                    'window_start_at' => $start->format('Y-m-d H:i:s'),
                    'window_end_at' => $end->format('Y-m-d H:i:s')
                ]),
                'event_at' => date('Y-m-d H:i:s'),
            ]);

            $this->pdo->commit();

            return [
                'proposal_id' => $proposalId,
                'booking_id' => $bookingId,
                'window_start_at' => $start->format('Y-m-d H:i:s'),
                'window_end_at' => $end->format('Y-m-d H:i:s'),
                'proposal_status' => self::PICKUP_COUNTERED,
            ];
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw $e;
        }
    }

    public function acceptPickupWindow(int $bookingId, int $actorUserId, int $proposalId): array {
        try {
            $this->pdo->beginTransaction();

            $booking = $this->getBookingForTransition($bookingId);
            $this->assertTransition((string) $booking['booking_status'], 'pickup_accept');
            $this->assertParticipant($booking, $actorUserId);

            $proposal = $this->findPickupProposalForUpdate($bookingId, $proposalId);
            if (!$proposal) {
                throw new InvalidArgumentException('Pickup proposal not found');
            }

            $proposalStatus = (string) $proposal['proposal_status'];
            if (!in_array($proposalStatus, [self::PICKUP_PROPOSED, self::PICKUP_COUNTERED], true)) {
                throw new InvalidArgumentException('Only open pickup proposals can be accepted');
            }

            if ((int) $proposal['proposed_by_user_id'] === $actorUserId) {
                throw new InvalidArgumentException('Proposal creator cannot accept own proposal');
            }

            $this->pickupWindowRepo->invalidatePendingForBooking($bookingId);

            $now = date('Y-m-d H:i:s');
            $updateSql = "
                UPDATE booking_pickup_windows
                SET proposal_status = ?, responded_by_user_id = ?, responded_at = ?, updated_at = ?
                WHERE id = ?
            ";

            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([
                self::PICKUP_ACCEPTED,
                $actorUserId,
                $now,
                $now,
                $proposalId,
            ]);

            $this->bookingRepo->update($bookingId, [
                'scheduled_pickup_date' => $proposal['window_start_at'],
                'updated_at' => $now,
            ]);

            $this->bookingEventRepo->create([
                'booking_id' => $bookingId,
                'event_type' => self::EVENT_PICKUP_ACCEPTED,
                'actor_user_id' => $actorUserId,
                'note' => json_encode([
                    'proposal_id' => $proposalId,
                    'window_start_at' => $proposal['window_start_at'],
                    'window_end_at' => $proposal['window_end_at']
                ]),
                'event_at' => $now,
            ]);

            $latestAccepted = $this->pickupWindowRepo->findLatestAcceptedByBookingId($bookingId);

            $this->pdo->commit();

            return [
                'booking_id' => $bookingId,
                'active_pickup_window' => $latestAccepted,
            ];
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw $e;
        }
    }

    public function listBookingsByRole(int $userId, string $role, ?string $status, int $limit, int $offset): array {
        if ($limit < 1 || $offset < 0) {
            throw new InvalidArgumentException('Invalid pagination parameters');
        }

        if (!in_array($role, ['buyer', 'seller'], true)) {
            throw new InvalidArgumentException('Role must be buyer or seller');
        }

        if ($status !== null && !in_array($status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ], true)) {
            throw new InvalidArgumentException('Invalid booking status filter');
        }

        $rows = $this->bookingRepo->findByRoleAndStatus($userId, $role, $status, $limit, $offset);

        $buckets = [
            self::STATUS_PENDING => [],
            self::STATUS_CONFIRMED => [],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];

        foreach ($rows as $row) {
            $state = (string) ($row['booking_status'] ?? '');
            if (!isset($buckets[$state])) {
                continue;
            }

            $buckets[$state][] = $row;
        }

        return [
            'role' => $role,
            'filters' => [
                'status' => $status,
                'limit' => $limit,
                'offset' => $offset,
            ],
            'pending' => $buckets[self::STATUS_PENDING],
            'confirmed' => $buckets[self::STATUS_CONFIRMED],
            'completed' => $buckets[self::STATUS_COMPLETED],
            'cancelled' => $buckets[self::STATUS_CANCELLED],
        ];
    }

    private function lockListingForUpdate(int $listingId): ?array {
        $sql = "
            SELECT *
            FROM listings
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        return $listing ?: null;
    }

    private function getBookingForTransition(int $bookingId): array {
        $booking = $this->bookingRepo->findForUpdate($bookingId);
        if (!$booking) {
            throw new InvalidArgumentException('Booking not found');
        }

        return $booking;
    }

    private function assertTransition(string $currentStatus, string $action): void {
        $allowedActions = $this->transitionGuards[$currentStatus] ?? [];
        if (!in_array($action, $allowedActions, true)) {
            throw new InvalidArgumentException("Invalid booking transition: {$currentStatus} cannot perform {$action}");
        }
    }

    private function assertParticipant(array $booking, int $actorUserId): void {
        $isBuyer = (int) $booking['buyer_id'] === $actorUserId;
        $isSeller = (int) $booking['seller_id'] === $actorUserId;

        if (!$isBuyer && !$isSeller) {
            throw new InvalidArgumentException('Only booking participants can perform this action');
        }
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function validateWindowBounds(string $startAt, string $endAt): array {
        try {
            $start = new DateTimeImmutable($startAt);
            $end = new DateTimeImmutable($endAt);
        } catch (Exception $e) {
            throw new InvalidArgumentException('Invalid pickup window datetime format');
        }

        if ($end <= $start) {
            throw new InvalidArgumentException('Pickup window end must be after start');
        }

        return [$start, $end];
    }

    private function findPickupProposalForUpdate(int $bookingId, int $proposalId): ?array {
        $sql = "
            SELECT *
            FROM booking_pickup_windows
            WHERE id = ?
              AND booking_id = ?
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$proposalId, $bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function rollbackIfNeeded(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}

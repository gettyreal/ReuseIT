<?php
namespace ReuseIT\Controllers;

use InvalidArgumentException;
use ReuseIT\Repositories\BookingEventRepository;
use ReuseIT\Repositories\BookingRepository;
use ReuseIT\Repositories\PickupWindowRepository;
use ReuseIT\Response;
use ReuseIT\Services\BookingService;
use RuntimeException;
use Throwable;

/**
 * BookingController
 *
 * Handles booking REST endpoints and delegates workflow logic to BookingService.
 */
class BookingController {

    private BookingService $bookingService;
    private BookingRepository $bookingRepository;
    private BookingEventRepository $bookingEventRepository;
    private PickupWindowRepository $pickupWindowRepository;

    public function __construct(
        BookingService $bookingService,
        BookingRepository $bookingRepository,
        BookingEventRepository $bookingEventRepository,
        PickupWindowRepository $pickupWindowRepository
    ) {
        $this->bookingService = $bookingService;
        $this->bookingRepository = $bookingRepository;
        $this->bookingEventRepository = $bookingEventRepository;
        $this->pickupWindowRepository = $pickupWindowRepository;
    }

    public function create(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $userId = (int) $_SESSION['user_id'];
        $input = $this->parseJsonInput();
        $listingId = (int) ($input['listing_id'] ?? 0);

        if ($listingId <= 0) {
            return Response::validationErrors([
                ['field' => 'listing_id', 'message' => 'listing_id is required and must be a positive integer']
            ], 400);
        }

        try {
            $result = $this->bookingService->createBooking($listingId, $userId);
            return Response::success($result, 201);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function list(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $userId = (int) $_SESSION['user_id'];
        $role = isset($get['role']) ? (string) $get['role'] : 'seller';
        $status = isset($get['status']) && $get['status'] !== '' ? (string) $get['status'] : null;
        $limit = isset($get['limit']) ? (int) $get['limit'] : 20;
        $offset = isset($get['offset']) ? (int) $get['offset'] : 0;

        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        try {
            $result = $this->bookingService->listBookingsByRole($userId, $role, $status, $limit, $offset);
            $enriched = $this->enrichDashboardBuckets($result, $role, $userId);
            return Response::success($enriched, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function show(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $userId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            return Response::error('Booking not found', 404);
        }

        if (!$this->bookingRepository->isParticipant($bookingId, $userId)) {
            return Response::error('Forbidden', 403);
        }

        $events = $this->bookingEventRepository->findByBookingId($bookingId);
        $activePickupWindow = $this->pickupWindowRepository->findLatestAcceptedByBookingId($bookingId);
        $role = ((int) ($booking['seller_id'] ?? 0) === $userId) ? 'seller' : 'buyer';
        $booking = $this->enrichBookingRow($booking, $role, $userId, $activePickupWindow);
        $timeline = $this->formatTimelineEvents($events);

        return Response::success([
            'booking' => $booking,
            'timeline' => $timeline,
            'active_pickup_window' => $activePickupWindow,
        ], 200);
    }

    public function confirm(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $sellerId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        try {
            $booking = $this->bookingService->confirmBooking($bookingId, $sellerId);
            return Response::success($booking, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function complete(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        try {
            $booking = $this->bookingService->completeBooking($bookingId, $actorUserId);
            return Response::success($booking, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function cancel(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $input = $this->parseJsonInput();
        $reasonCode = trim((string) ($input['reason_code'] ?? ''));
        $note = isset($input['note']) ? trim((string) $input['note']) : null;

        if ($reasonCode === '') {
            return Response::validationErrors([
                ['field' => 'reason_code', 'message' => 'reason_code is required']
            ], 400);
        }

        try {
            $booking = $this->bookingService->cancelBooking($bookingId, $actorUserId, $reasonCode, $note);
            return Response::success($booking, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function proposePickup(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $buyerId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $input = $this->parseJsonInput();
        $startAt = (string) ($input['window_start_at'] ?? '');
        $endAt = (string) ($input['window_end_at'] ?? '');

        if ($startAt === '' || $endAt === '') {
            return Response::validationErrors([
                ['field' => 'window_start_at', 'message' => 'window_start_at is required'],
                ['field' => 'window_end_at', 'message' => 'window_end_at is required']
            ], 400);
        }

        try {
            $result = $this->bookingService->proposePickupWindow($bookingId, $buyerId, $startAt, $endAt);
            return Response::success($result, 201);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function counterPickup(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $sellerId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $input = $this->parseJsonInput();
        $startAt = (string) ($input['window_start_at'] ?? '');
        $endAt = (string) ($input['window_end_at'] ?? '');

        if ($startAt === '' || $endAt === '') {
            return Response::validationErrors([
                ['field' => 'window_start_at', 'message' => 'window_start_at is required'],
                ['field' => 'window_end_at', 'message' => 'window_end_at is required']
            ], 400);
        }

        try {
            $result = $this->bookingService->counterPickupWindow($bookingId, $sellerId, $startAt, $endAt);
            return Response::success($result, 201);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    public function acceptPickup(array $get, array $post, array $files, array $params): string {
        if (empty($_SESSION['user_id'])) {
            return Response::error('Unauthorized', 401);
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $bookingId = (int) ($params['id'] ?? 0);
        if ($bookingId <= 0) {
            return Response::error('Invalid booking ID', 400);
        }

        $input = $this->parseJsonInput();
        $proposalId = (int) ($input['proposal_id'] ?? 0);

        if ($proposalId <= 0) {
            return Response::validationErrors([
                ['field' => 'proposal_id', 'message' => 'proposal_id is required and must be a positive integer']
            ], 400);
        }

        try {
            $result = $this->bookingService->acceptPickupWindow($bookingId, $actorUserId, $proposalId);
            return Response::success($result, 200);
        } catch (Throwable $e) {
            return $this->mapBookingException($e);
        }
    }

    private function parseJsonInput(): array {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function enrichDashboardBuckets(array $result, string $role, int $userId): array {
        $activePickupByBookingId = $this->getActivePickupMapFromBuckets($result);

        foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $bucket) {
            $rows = $result[$bucket] ?? [];
            $enrichedRows = [];

            foreach ($rows as $row) {
                $bookingId = (int) ($row['id'] ?? 0);
                $activePickupWindow = $activePickupByBookingId[$bookingId] ?? null;
                $enrichedRows[] = $this->enrichBookingRow($row, $role, $userId, $activePickupWindow);
            }

            $result[$bucket] = $enrichedRows;
        }

        return $result;
    }

    private function enrichBookingRow(array $booking, string $role, int $userId, ?array $activePickupWindow): array {
        $status = (string) ($booking['booking_status'] ?? '');
        $isSeller = $role === 'seller';
        $isBuyer = $role === 'buyer';

        $respondBy = $booking['expires_at'] ?? null;
        $secondsUntilExpiry = null;
        $isExpired = false;

        if ($respondBy !== null) {
            $expiryTs = strtotime((string) $respondBy);
            if ($expiryTs !== false) {
                $secondsUntilExpiry = $expiryTs - time();
                $isExpired = $secondsUntilExpiry <= 0;
                if ($secondsUntilExpiry < 0) {
                    $secondsUntilExpiry = 0;
                }
            }
        }

        $booking['respond_by'] = $isSeller && $status === 'pending' ? $respondBy : null;
        $booking['seconds_until_expiry'] = $isSeller && $status === 'pending' ? $secondsUntilExpiry : null;
        $booking['is_expired'] = $isSeller && $status === 'pending' ? $isExpired : false;
        $booking['next_actions'] = $this->computeNextActions($booking, $isBuyer, $isSeller, $activePickupWindow);

        return $booking;
    }

    private function computeNextActions(array $booking, bool $isBuyer, bool $isSeller, ?array $activePickupWindow): array {
        $status = (string) ($booking['booking_status'] ?? '');
        $actions = [];

        if ($status === 'pending') {
            if ($isSeller) {
                $actions = ['confirm', 'cancel'];
            } elseif ($isBuyer) {
                $actions = ['cancel'];
            }
        }

        if ($status === 'confirmed') {
            if ($isBuyer) {
                $actions[] = 'propose_pickup';
            }

            if ($isSeller) {
                $actions[] = 'counter_pickup';
            }

            $actions[] = 'cancel';

            if ($activePickupWindow !== null) {
                $actions[] = 'complete';
            }
        }

        return array_values(array_unique($actions));
    }

    private function getActivePickupMapFromBuckets(array $result): array {
        $map = [];

        foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $bucket) {
            foreach (($result[$bucket] ?? []) as $booking) {
                $bookingId = (int) ($booking['id'] ?? 0);
                if ($bookingId <= 0 || isset($map[$bookingId])) {
                    continue;
                }

                $map[$bookingId] = $this->pickupWindowRepository->findLatestAcceptedByBookingId($bookingId);
            }
        }

        return $map;
    }

    private function formatTimelineEvents(array $events): array {
        $timeline = [];

        foreach ($events as $event) {
            $timeline[] = [
                'id' => (int) ($event['id'] ?? 0),
                'event_type' => $event['event_type'] ?? null,
                'actor_user_id' => isset($event['actor_user_id']) ? (int) $event['actor_user_id'] : null,
                'reason_code' => $event['reason_code'] ?? null,
                'note' => $event['note'] ?? null,
                'event_at' => $event['event_at'] ?? null,
                'is_cancellation_event' => in_array(($event['event_type'] ?? ''), ['cancelled', 'expired'], true),
            ];
        }

        return $timeline;
    }

    private function mapBookingException(Throwable $e): string {
        if ($e instanceof RuntimeException && $e->getCode() === 409) {
            return Response::error($e->getMessage(), 409);
        }

        if ($e instanceof InvalidArgumentException) {
            $message = $e->getMessage();
            $lower = strtolower($message);

            if (strpos($lower, 'not found') !== false) {
                return Response::error($message, 404);
            }

            if (
                strpos($lower, 'only seller') !== false ||
                strpos($lower, 'only buyer') !== false ||
                strpos($lower, 'only booking participants') !== false ||
                strpos($lower, 'cannot book your own listing') !== false
            ) {
                return Response::error($message, 403);
            }

            if (
                strpos($lower, 'invalid booking transition') !== false ||
                strpos($lower, 'already has an active booking') !== false ||
                strpos($lower, 'expired') !== false ||
                strpos($lower, 'only active listings can be booked') !== false
            ) {
                return Response::error($message, 409);
            }

            return Response::error($message, 400);
        }

        return Response::error('Server error', 500);
    }
}

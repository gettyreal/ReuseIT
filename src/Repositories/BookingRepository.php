<?php
namespace ReuseIT\Repositories;

use PDO;
use InvalidArgumentException;

/**
 * BookingRepository
 *
 * Data access layer for booking records.
 * Provides transactional locking queries and role-specific dashboard reads.
 */
class BookingRepository extends BaseRepository {

    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'bookings');
    }

    /**
     * Find booking by ID with soft-delete filtering.
     */
    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        return $booking ?: null;
    }

    /**
     * Find booking by ID and lock selected row for transaction safety.
     */
    public function findForUpdate(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?" . $this->applyDeleteFilter() . " FOR UPDATE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        return $booking ?: null;
    }

    /**
     * Find active booking (pending/confirmed) for listing and lock result set.
     */
    public function findActiveByListingForUpdate(int $listingId): ?array {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE listing_id = ?
              AND booking_status IN ('pending', 'confirmed')
              AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$listingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        return $booking ?: null;
    }

    /**
     * List bookings by role and optional status for dashboard views.
     *
     * - role=buyer uses buyer_id
     * - role=seller uses seller_id
     * - status pending ordered by urgency first (expires_at ASC, created_at DESC)
     * - other statuses newest first (created_at DESC)
     */
    public function findByRoleAndStatus(int $userId, string $role, ?string $status, int $limit, int $offset): array {
        if (!in_array($role, ['buyer', 'seller'], true)) {
            throw new InvalidArgumentException('Role must be buyer or seller');
        }

        $ownerColumn = $role === 'buyer' ? 'buyer_id' : 'seller_id';

        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE {$ownerColumn} = ?
              AND deleted_at IS NULL
        ";

        $params = [$userId];

        if ($status !== null) {
            $sql .= " AND booking_status = ?";
            $params[] = $status;
        }

        if ($status === 'pending') {
            $sql .= " ORDER BY expires_at ASC, created_at DESC";
        } elseif ($status !== null) {
            $sql .= " ORDER BY created_at DESC";
        } else {
            // Status buckets, then urgency for pending, then newest for all others.
            $sql .= "
                ORDER BY
                    CASE booking_status
                        WHEN 'pending' THEN 1
                        WHEN 'confirmed' THEN 2
                        WHEN 'completed' THEN 3
                        WHEN 'cancelled' THEN 4
                        ELSE 5
                    END ASC,
                    CASE WHEN booking_status = 'pending' THEN expires_at ELSE NULL END ASC,
                    created_at DESC
            ";
        }

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create booking record.
     */
    public function create(array $data): int {
        return parent::create($data);
    }

    /**
     * Update booking record.
     */
    public function update(int $id, array $data): bool {
        return parent::update($id, $data);
    }

    /**
     * Validate whether user participates in a booking.
     */
    public function isParticipant(int $bookingId, int $userId): bool {
        $sql = "
            SELECT 1
            FROM {$this->table}
            WHERE id = ?
              AND (buyer_id = ? OR seller_id = ?)
              AND deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$bookingId, $userId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Validate listing ownership for booking flow guards.
     */
    public function isListingOwnedBySeller(int $listingId, int $sellerId): bool {
        $sql = "
            SELECT 1
            FROM listings
            WHERE id = ?
              AND seller_id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$listingId, $sellerId]);

        return (bool) $stmt->fetchColumn();
    }
}

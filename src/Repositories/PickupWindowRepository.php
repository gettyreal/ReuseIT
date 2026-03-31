<?php
namespace ReuseIT\Repositories;

use PDO;

/**
 * PickupWindowRepository
 *
 * Data access layer for booking pickup proposal/counter/accept history.
 */
class PickupWindowRepository extends BaseRepository {

    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'booking_pickup_windows');
    }

    /**
     * Create a pickup window proposal row.
     */
    public function create(array $data): int {
        return parent::create($data);
    }

    /**
     * Find all pickup windows for a booking.
     */
    public function findByBookingId(int $bookingId): array {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE booking_id = ?
              AND deleted_at IS NULL
            ORDER BY created_at ASC, id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$bookingId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find latest mutually accepted pickup window.
     */
    public function findLatestAcceptedByBookingId(int $bookingId): ?array {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE booking_id = ?
              AND proposal_status = 'accepted'
              AND deleted_at IS NULL
            ORDER BY responded_at DESC, updated_at DESC, id DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$bookingId]);
        $pickupWindow = $stmt->fetch(PDO::FETCH_ASSOC);

        return $pickupWindow ?: null;
    }

    /**
     * Invalidate older unresolved proposals after a new response.
     */
    public function invalidatePendingForBooking(int $bookingId): bool {
        $sql = "
            UPDATE {$this->table}
            SET proposal_status = 'superseded',
                updated_at = NOW()
            WHERE booking_id = ?
              AND proposal_status IN ('proposed', 'countered')
              AND deleted_at IS NULL
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$bookingId]);
    }
}

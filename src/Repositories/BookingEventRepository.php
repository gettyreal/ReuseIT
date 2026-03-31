<?php
namespace ReuseIT\Repositories;

use PDO;

/**
 * BookingEventRepository
 *
 * Append-only repository for booking timeline events.
 */
class BookingEventRepository extends BaseRepository {

    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'booking_events');
    }

    /**
     * Create an immutable booking event row.
     */
    public function create(array $data): int {
        $sql = "
            INSERT INTO booking_events
                (booking_id, event_type, actor_user_id, reason_code, note, event_at)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['booking_id'],
            $data['event_type'],
            $data['actor_user_id'] ?? null,
            $data['reason_code'] ?? null,
            $data['note'] ?? null,
            $data['event_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Read booking events in chronological order.
     */
    public function findByBookingId(int $bookingId): array {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE booking_id = ?
              AND deleted_at IS NULL
            ORDER BY event_at ASC, id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$bookingId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

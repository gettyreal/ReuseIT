<?php
namespace ReuseIT\Repositories;

use PDO;

/**
 * ReviewRepository
 *
 * Data access layer for review records.
 * Provides CRUD operations and user statistics queries with soft-delete filtering.
 */
class ReviewRepository extends BaseRepository {

    /**
     * Initialize ReviewRepository with PDO connection.
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'reviews');
    }

    /**
     * Create a new review record.
     *
     * @param array $data Review data (reviewer_user_id, reviewed_user_id, booking_id, rating, comment)
     * @return int Last inserted review ID
     */
    public function create(array $data): int {
        return parent::create($data);
    }

    /**
     * Find a review by ID with soft-delete filtering.
     *
     * @param int $id Review ID
     * @return array|null Review record or null if not found
     */
    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Find review for a specific booking (prevent duplicates).
     * Soft-delete filtered.
     *
     * @param int $bookingId Booking ID
     * @return array|null Review record or null if not found
     */
    public function findByBookingId(int $bookingId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE booking_id = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$bookingId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get all reviews received by a user (newest first).
     * Includes reviewer metadata via JOIN with users table.
     * Soft-delete filtered.
     *
     * @param int $userId User ID (reviewed_user_id)
     * @param int $limit Number of reviews to fetch
     * @param int $offset Offset for pagination
     * @return array Array of review records with reviewer info
     */
    public function findByUserId(int $userId, int $limit, int $offset): array {
        $sql = "
            SELECT
                r.id,
                r.reviewer_user_id,
                r.reviewed_user_id,
                r.booking_id,
                r.rating,
                r.comment,
                r.created_at,
                r.updated_at,
                u.first_name AS reviewer_first_name,
                u.last_name AS reviewer_last_name,
                u.profile_picture_url AS reviewer_avatar
            FROM {$this->table} r
            JOIN users u ON r.reviewer_user_id = u.id AND u.deleted_at IS NULL
            WHERE r.reviewed_user_id = ?
              AND r.deleted_at IS NULL
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total non-deleted reviews received by a user.
     *
     * @param int $userId User ID (reviewed_user_id)
     * @return int Total review count
     */
    public function countByUserId(int $userId): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM {$this->table}
            WHERE reviewed_user_id = ?
              AND deleted_at IS NULL
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Check if a review already exists for a booking and reviewer.
     * Used for rate-limit prevention (one review per booking).
     *
     * @param int $bookingId Booking ID
     * @param int $reviewerId Reviewer user ID
     * @return bool True if review exists, false otherwise
     */
    public function exists(int $bookingId, int $reviewerId): bool {
        $sql = "
            SELECT 1
            FROM {$this->table}
            WHERE booking_id = ?
              AND reviewer_user_id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$bookingId, $reviewerId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

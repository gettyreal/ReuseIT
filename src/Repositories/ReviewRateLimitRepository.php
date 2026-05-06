<?php
namespace ReuseIT\Repositories;

use DateTime;
use PDO;

/**
 * ReviewRateLimitRepository
 *
 * Data access layer for review submission rate limiting.
 * Tracks submission timestamps to enforce 1 review per 24 hours per user.
 */
class ReviewRateLimitRepository extends BaseRepository {

    /**
     * Initialize ReviewRateLimitRepository with PDO connection.
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'review_rate_limits');
    }

    /**
     * Create a new rate limit record.
     *
     * @param int $userId User ID
     * @return int Last inserted record ID
     */
    public function createRecord(int $userId): int {
        return parent::create([
            'user_id' => $userId,
            'submitted_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Find the most recent submission for a user.
     *
     * @param int $userId User ID
     * @return array|null Rate limit record or null if not found
     */
    public function findLatestByUserId(int $userId): ?array {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE user_id = ?
            ORDER BY submitted_at DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Delete all records older than a given cutoff date.
     *
     * Used for cleanup to keep the table small.
     *
     * @param DateTime $cutoff Cutoff date (delete records older than this)
     * @return int Number of records deleted
     */
    public function deleteOlderThan(DateTime $cutoff): int {
        $sql = "DELETE FROM {$this->table} WHERE submitted_at < ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cutoff->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }
}

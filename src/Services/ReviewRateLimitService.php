<?php
namespace ReuseIT\Services;

use DateTime;
use ReuseIT\Repositories\ReviewRateLimitRepository;

/**
 * ReviewRateLimitService
 *
 * Enforces rate limiting on review submissions to prevent spam.
 * Enforces 1 review per 24 hours per user.
 */
class ReviewRateLimitService {

    private ReviewRateLimitRepository $rateLimitRepo;

    // Configuration constants
    private const RATE_LIMIT_HOURS = 24;  // One review per 24 hours

    /**
     * Initialize ReviewRateLimitService with dependencies.
     *
     * @param ReviewRateLimitRepository $rateLimitRepo Data access for review rate limits
     */
    public function __construct(ReviewRateLimitRepository $rateLimitRepo) {
        $this->rateLimitRepo = $rateLimitRepo;
    }

    /**
     * Check if a user is currently rate-limited from submitting reviews.
     *
     * Returns true if user has submitted a review within the last 24 hours (blocked).
     * Returns false if user is allowed to submit a review.
     *
     * @param int $userId User ID
     * @return bool True if rate-limited (blocked), false if allowed
     */
    public function isReviewLimited(int $userId): bool {
        $latest = $this->rateLimitRepo->findLatestByUserId($userId);

        if (!$latest) {
            // No submission record, user is allowed
            return false;
        }

        // Check if latest submission is within 24 hours
        $submittedAt = strtotime($latest['submitted_at']);
        $cutoffTime = time() - (self::RATE_LIMIT_HOURS * 3600);  // 24 hours ago

        return $submittedAt > $cutoffTime;
    }

    /**
     * Record a review submission for a user.
     *
     * @param int $userId User ID
     * @return void
     */
    public function recordReviewSubmission(int $userId): void {
        $this->rateLimitRepo->createRecord($userId);
    }

    /**
     * Clean up old rate limit records (optional maintenance task).
     *
     * Deletes records older than 24 hours.
     * Can be called periodically (e.g., via cron job).
     *
     * @return int Number of records deleted
     */
    public function cleanup(): int {
        $cutoff = new DateTime('-' . self::RATE_LIMIT_HOURS . ' hours');
        return $this->rateLimitRepo->deleteOlderThan($cutoff);
    }
}

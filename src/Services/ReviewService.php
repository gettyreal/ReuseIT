<?php
namespace ReuseIT\Services;

use DateTime;
use InvalidArgumentException;
use LogicException;
use PDO;
use ReuseIT\Repositories\BookingRepository;
use ReuseIT\Repositories\ReviewRepository;
use ReuseIT\Repositories\UserRepository;
use ReuseIT\ValueObjects\Rating;

/**
 * ReviewService
 *
 * Service layer for review submission with validation, atomicity, and denormalization.
 * Enforces business rules: booking completed, no duplicate, rate limiting, valid rating, comment length.
 */
class ReviewService {

    private PDO $pdo;
    private ReviewRepository $reviewRepo;
    private BookingRepository $bookingRepo;
    private UserRepository $userRepo;
    private ReviewRateLimitService $rateLimitService;

    /**
     * Initialize ReviewService with dependencies.
     *
     * @param PDO $pdo Database connection for transactions
     * @param ReviewRepository $reviewRepo Review data access
     * @param BookingRepository $bookingRepo Booking data access
     * @param UserRepository $userRepo User data access
     * @param ReviewRateLimitService $rateLimitService Rate limit enforcement
     */
    public function __construct(
        PDO $pdo,
        ReviewRepository $reviewRepo,
        BookingRepository $bookingRepo,
        UserRepository $userRepo,
        ReviewRateLimitService $rateLimitService
    ) {
        $this->pdo = $pdo;
        $this->reviewRepo = $reviewRepo;
        $this->bookingRepo = $bookingRepo;
        $this->userRepo = $userRepo;
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * Submit a review for a completed booking.
     *
     * Validation chain:
     * 1. Booking exists and status is 'completed'
     * 2. Reviewer is a participant (buyer or seller)
     * 3. No existing review for this booking
     * 4. Rate limit: one review per 24 hours
     * 5. Rating: 1-5 via Rating value object
     * 6. Comment: max 500 chars, plain text only
     *
     * Atomic transaction:
     * - INSERT review
     * - SELECT AVG(rating) for reviewed user
     * - SELECT COUNT(*) for reviewed user
     * - UPDATE users denormalization (avg_rating, total_reviews)
     * - RECORD rate limit event
     *
     * @param int $bookingId Booking ID
     * @param int $reviewerId Reviewer user ID (buyer or seller)
     * @param int $rating Rating (1-5)
     * @param ?string $comment Optional comment (max 500 chars)
     * @return array Review record with updated user stats
     * @throws InvalidArgumentException If validation fails
     * @throws LogicException If non-recoverable error occurs
     */
    public function submitReview(
        int $bookingId,
        int $reviewerId,
        int $rating,
        ?string $comment = null
    ): array {
        // Validate booking exists and is completed
        $booking = $this->bookingRepo->find($bookingId);
        if (!$booking) {
            throw new InvalidArgumentException("Booking not found");
        }
        if ($booking['booking_status'] !== 'completed') {
            throw new InvalidArgumentException("Booking must be completed to submit a review");
        }

        // Validate reviewer is a participant in the booking
        $isParticipant = $this->bookingRepo->isParticipant($bookingId, $reviewerId);
        if (!$isParticipant) {
            throw new InvalidArgumentException("Reviewer is not a participant in this booking");
        }

        // Validate no duplicate review exists for this booking
        if ($this->reviewRepo->findByBookingId($bookingId) !== null) {
            throw new InvalidArgumentException("A review already exists for this booking");
        }

        // Check rate limit: one review per 24 hours
        if ($this->rateLimitService->isReviewLimited($reviewerId)) {
            throw new InvalidArgumentException("You can only submit one review per 24 hours");
        }

        // Validate rating via Rating value object (1-5)
        try {
            $ratingObject = new Rating($rating);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException("Rating must be between 1 and 5");
        }

        // Validate comment if provided
        if ($comment !== null) {
            $comment = trim($comment);
            if (strlen($comment) > 500) {
                throw new InvalidArgumentException("Comment must be 500 characters or less");
            }
            // Plain text validation: ensure no HTML/markdown is evaluated
            // We allow all UTF-8 text; security is via escaping on output
            if (empty($comment)) {
                $comment = null;
            }
        }

        // Begin transaction
        try {
            $this->pdo->beginTransaction();

            // Determine reviewed_user_id (opposite participant)
            if ($booking['buyer_id'] === $reviewerId) {
                $reviewedUserId = $booking['seller_id'];
            } elseif ($booking['seller_id'] === $reviewerId) {
                $reviewedUserId = $booking['buyer_id'];
            } else {
                throw new LogicException("Reviewer is not buyer or seller in booking");
            }

            // INSERT review
            $reviewId = $this->reviewRepo->create([
                'reviewer_user_id' => $reviewerId,
                'reviewed_user_id' => $reviewedUserId,
                'booking_id' => $bookingId,
                'rating' => $ratingObject->getValue(),
                'comment' => $comment
            ]);

            // Recalculate avg_rating: SELECT AVG(rating) FROM reviews WHERE reviewed_user_id = ? AND deleted_at IS NULL
            $avgRating = $this->calculateAverageRating($reviewedUserId);

            // Recalculate total_reviews: SELECT COUNT(*) FROM reviews WHERE reviewed_user_id = ? AND deleted_at IS NULL
            $totalReviews = $this->reviewRepo->countByUserId($reviewedUserId);

            // UPDATE users table with atomic denormalization
            $this->userRepo->update($reviewedUserId, [
                'avg_rating' => $avgRating,
                'total_reviews' => $totalReviews
            ]);

            // Record rate limit event
            $this->rateLimitService->recordReviewSubmission($reviewerId);

            // Commit transaction
            $this->pdo->commit();

            // Fetch and return the created review with updated user stats
            $review = $this->reviewRepo->find($reviewId);
            if (!$review) {
                throw new LogicException("Review was created but could not be retrieved");
            }

            return $review;

        } catch (InvalidArgumentException | LogicException $e) {
            // Re-throw validation and logic exceptions
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (\Exception $e) {
            // Rollback on any other error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new LogicException("Failed to submit review: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get all reviews received by a user (newest first).
     *
     * @param int $userId User ID (reviewed_user_id)
     * @param int $limit Number of reviews to fetch (default 10)
     * @param int $offset Pagination offset (default 0)
     * @return array Array of review records with reviewer metadata
     */
    public function getUserReviews(int $userId, int $limit = 10, int $offset = 0): array {
        return $this->reviewRepo->findByUserId($userId, $limit, $offset);
    }

    /**
     * Get statistics for a user's received reviews.
     *
     * Returns: {avg_rating, total_reviews, distribution: {5: count, 4: count, 3: count, 2: count, 1: count}}
     *
     * @param int $userId User ID
     * @return array Statistics including avg_rating, total_reviews, and distribution
     */
    public function getUserStats(int $userId): array {
        // Query users table for denormalized values
        $user = $this->userRepo->find($userId);
        if (!$user) {
            return [
                'avg_rating' => null,
                'total_reviews' => 0,
                'distribution' => ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0]
            ];
        }

        // Query distribution via SELECT rating, COUNT(*) FROM reviews WHERE reviewed_user_id = ? AND deleted_at IS NULL GROUP BY rating
        $distribution = $this->calculateRatingDistribution($userId);

        return [
            'avg_rating' => $user['avg_rating'],
            'total_reviews' => $user['total_reviews'],
            'distribution' => $distribution
        ];
    }

    /**
     * Calculate average rating for a user.
     *
     * @param int $userId User ID
     * @return ?float Average rating or null if no reviews
     */
    private function calculateAverageRating(int $userId): ?float {
        $sql = "
            SELECT AVG(rating) as avg_rating
            FROM reviews
            WHERE reviewed_user_id = ?
              AND deleted_at IS NULL
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgRating = $result['avg_rating'] ?? null;
        return $avgRating !== null ? round((float) $avgRating, 2) : null;
    }

    /**
     * Calculate rating distribution for a user.
     *
     * @param int $userId User ID
     * @return array Distribution keyed by rating (1-5)
     */
    private function calculateRatingDistribution(int $userId): array {
        $sql = "
            SELECT rating, COUNT(*) as count
            FROM reviews
            WHERE reviewed_user_id = ?
              AND deleted_at IS NULL
            GROUP BY rating
            ORDER BY rating DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialize distribution with zeros
        $distribution = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];

        // Populate with actual counts
        foreach ($results as $row) {
            $distribution[(string) $row['rating']] = (int) $row['count'];
        }

        return $distribution;
    }
}

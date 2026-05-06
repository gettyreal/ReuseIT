<?php
namespace ReuseIT\Controllers;

use InvalidArgumentException;
use ReuseIT\Repositories\BookingRepository;
use ReuseIT\Repositories\ReviewRepository;
use ReuseIT\Response;
use ReuseIT\Services\ReviewService;

/**
 * ReviewController
 *
 * Handles review REST endpoints for submission and retrieval.
 *
 * Public endpoints:
 * - GET /api/reviews/user/:id - Get reviews for a user (public)
 * - GET /api/users/:id/stats - Get review statistics (public)
 *
 * Protected endpoints (require authentication):
 * - POST /api/reviews - Submit a review (with authorization check)
 */
class ReviewController {

    private ReviewService $reviewService;
    private ReviewRepository $reviewRepository;
    private BookingRepository $bookingRepository;

    /**
     * Initialize controller with service dependencies.
     *
     * @param ReviewService $reviewService Service layer for review operations
     * @param ReviewRepository $reviewRepository Review data access
     * @param BookingRepository $bookingRepository Booking data access
     */
    public function __construct(
        ReviewService $reviewService,
        ReviewRepository $reviewRepository,
        BookingRepository $bookingRepository
    ) {
        $this->reviewService = $reviewService;
        $this->reviewRepository = $reviewRepository;
        $this->bookingRepository = $bookingRepository;
    }

    /**
     * POST /api/reviews
     *
     * Submit a review for a completed booking.
     * Protected endpoint - requires authentication.
     *
     * Request body:
     * {
     *   "booking_id": 42,
     *   "rating": 4,
     *   "comment": "Great item! Fast pickup."
     * }
     *
     * Success response (201):
     * {
     *   "status": "success",
     *   "data": {
     *     "id": 15,
     *     "reviewer_id": 2,
     *     "reviewed_user_id": 1,
     *     "booking_id": 42,
     *     "rating": 4,
     *     "comment": "Great item! Fast pickup.",
     *     "created_at": "2026-05-06T14:30:00Z",
     *     "reviewer": {
     *       "id": 2,
     *       "name": "John Buyer",
     *       "avatar_url": "..."
     *     }
     *   }
     * }
     *
     * Error responses:
     * - 400: Validation error (invalid rating, booking not completed, duplicate review, etc.)
     * - 403: Forbidden (user not participant in booking)
     * - 429: Rate limit exceeded (one review per 24 hours)
     *
     * @param array $get Query parameters
     * @param array $post POST parameters (not used - parsed from body)
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function submitReview(array $get, array $post, array $files, array $params): string {
        try {
            // Check authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $reviewerId = (int) $_SESSION['user_id'];

            // Parse JSON request body
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            // Validate required fields
            $bookingId = (int) ($input['booking_id'] ?? 0);
            if ($bookingId <= 0) {
                return Response::validationErrors([
                    ['field' => 'booking_id', 'message' => 'booking_id is required and must be a positive integer']
                ], 400);
            }

            $rating = (int) ($input['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) {
                return Response::validationErrors([
                    ['field' => 'rating', 'message' => 'rating must be between 1 and 5']
                ], 400);
            }

            $comment = isset($input['comment']) ? trim((string) $input['comment']) : null;
            if ($comment !== null && strlen($comment) > 500) {
                return Response::validationErrors([
                    ['field' => 'comment', 'message' => 'comment must be 500 characters or less']
                ], 400);
            }

            // Call service to submit review
            $review = $this->reviewService->submitReview($bookingId, $reviewerId, $rating, $comment);

            return Response::success($review, 201);

        } catch (InvalidArgumentException $e) {
            // Validation or authorization error from service
            $message = $e->getMessage();

            // Map specific error messages to HTTP status codes
            if (str_contains($message, 'not a participant')) {
                return Response::error('Forbidden', 403);
            } elseif (str_contains($message, '24 hours')) {
                return Response::error('Too many requests', 429);
            }

            // Generic validation error
            return Response::error($message, 400);

        } catch (\Exception $e) {
            // Server error
            return Response::error('Failed to submit review', 500);
        }
    }

    /**
     * GET /api/reviews/user/:id
     *
     * Get paginated list of reviews received by a user (newest-first).
     * Public endpoint - no authentication required.
     *
     * Query parameters:
     * - limit: number of reviews per page (default 10, max 100)
     * - offset: pagination offset (default 0)
     *
     * Success response (200):
     * {
     *   "status": "success",
     *   "data": [
     *     {
     *       "id": 15,
     *       "reviewer": {
     *         "id": 2,
     *         "name": "John Buyer",
     *         "avatar_url": "..."
     *       },
     *       "rating": 4,
     *       "comment": "Great item! Fast pickup.",
     *       "created_at": "2026-05-06T14:30:00Z",
     *       "comment_preview": "Great item! Fast pickup.",
     *       "comment_truncated": false
     *     },
     *     ...
     *   ]
     * }
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function getReviewsByUser(array $get, array $post, array $files, array $params): string {
        try {
            $userId = (int) ($params['id'] ?? 0);

            if ($userId <= 0) {
                return Response::error('Invalid user ID', 400);
            }

            // Parse pagination parameters
            $limit = isset($get['limit']) ? (int) $get['limit'] : 10;
            $offset = isset($get['offset']) ? (int) $get['offset'] : 0;

            // Validate pagination
            if ($limit < 1 || $limit > 100) {
                $limit = 10;
            }
            if ($offset < 0) {
                $offset = 0;
            }

            // Get reviews from service
            $reviews = $this->reviewService->getUserReviews($userId, $limit, $offset);

            // Add truncation metadata for frontend
            foreach ($reviews as &$review) {
                if ($review['comment'] && strlen($review['comment']) > 100) {
                    $review['comment_preview'] = substr($review['comment'], 0, 100);
                    $review['comment_truncated'] = true;
                } else {
                    $review['comment_preview'] = $review['comment'];
                    $review['comment_truncated'] = false;
                }
            }

            return Response::success($reviews, 200);

        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * GET /api/users/:id/stats
     *
     * Get review statistics for a user.
     * Public endpoint - no authentication required.
     *
     * Success response (200):
     * {
     *   "status": "success",
     *   "data": {
     *     "avg_rating": 4.5,
     *     "total_reviews": 23,
     *     "distribution": {
     *       "5": 10,
     *       "4": 8,
     *       "3": 4,
     *       "2": 1,
     *       "1": 0
     *     }
     *   }
     * }
     *
     * If user has no reviews:
     * {
     *   "status": "success",
     *   "data": {
     *     "avg_rating": null,
     *     "total_reviews": 0,
     *     "distribution": {
     *       "5": 0,
     *       "4": 0,
     *       "3": 0,
     *       "2": 0,
     *       "1": 0
     *     }
     *   }
     * }
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function getUserStats(array $get, array $post, array $files, array $params): string {
        try {
            $userId = (int) ($params['id'] ?? 0);

            if ($userId <= 0) {
                return Response::error('Invalid user ID', 400);
            }

            // Get stats from service
            $stats = $this->reviewService->getUserStats($userId);

            return Response::success($stats, 200);

        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
}

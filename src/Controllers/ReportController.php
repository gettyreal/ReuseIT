<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;
use ReuseIT\Services\ReportService;

/**
 * ReportController
 * 
 * Handles reporting HTTP endpoints.
 * 
 * Protected endpoints (require authentication):
 * - POST /api/listings/:id/report - Report a listing
 * - POST /api/users/:id/report - Report a user
 * - GET /api/admin/reports - Get admin report queue (admin-only)
 */
class ReportController {
    private ReportService $reportService;
    
    /**
     * Initialize controller with service dependencies.
     * 
     * @param ReportService $reportService Service layer for reporting operations
     */
    public function __construct(ReportService $reportService) {
        $this->reportService = $reportService;
    }
    
    /**
     * POST /api/listings/:id/report
     * 
     * Submit a report for a listing.
     * User must provide a reason and optional comment.
     * 
     * Request body:
     * {
     *   "reason": "spam|fake|illegal|harmful",
     *   "comment": "optional text (max 1000 chars)"
     * }
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id' for listing_id)
     * @return string JSON response with 201 Created status
     */
    public function reportListing(array $get, array $post, array $files, array $params): string {
        try {
            // Extract listing ID from URL parameter
            $listingId = (int)($params['id'] ?? 0);
            
            if ($listingId <= 0) {
                return Response::error('Invalid listing ID', 400);
            }
            
            // Get authenticated user ID from session
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }
            $userId = (int)$_SESSION['user_id'];
            
            // Parse JSON request body
            $body = json_decode(file_get_contents('php://input'), true);
            
            if (!$body) {
                return Response::error('Invalid JSON body', 400);
            }
            
            $reason = trim($body['reason'] ?? '');
            $comment = trim($body['comment'] ?? '');
            
            // Validate inputs
            if (empty($reason)) {
                return Response::error('Reason is required', 400);
            }
            
            // Submit report
            $result = $this->reportService->submitReport(
                $userId,
                'listing',
                $listingId,
                $reason,
                $comment
            );
            
            // Return 201 Created
            $data = [
                'report_id' => $result['id'],
                'status' => $result['status'],
            ];
            
            return Response::success($data, 201, 'Listing reported successfully');
        } catch (\InvalidArgumentException $e) {
            // Validation errors from service layer
            $message = $e->getMessage();
            
            if (str_contains($message, 'not found')) {
                return Response::error($message, 404);
            } elseif (str_contains($message, 'duplicate') || str_contains($message, 'already reported')) {
                return Response::error($message, 409);
            } elseif (str_contains($message, 'Invalid')) {
                return Response::error($message, 400);
            }
            
            return Response::error($message, 422);
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * POST /api/users/:id/report
     * 
     * Submit a report for a user.
     * User must provide a reason and optional comment.
     * Cannot report yourself.
     * 
     * Request body:
     * {
     *   "reason": "spam|fake|illegal|harmful",
     *   "comment": "optional text (max 1000 chars)"
     * }
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * - Cannot report yourself (422 if attempting)
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id' for user_id)
     * @return string JSON response with 201 Created status
     */
    public function reportUser(array $get, array $post, array $files, array $params): string {
        try {
            // Extract user ID from URL parameter (the user being reported)
            $reportedUserId = (int)($params['id'] ?? 0);
            
            if ($reportedUserId <= 0) {
                return Response::error('Invalid user ID', 400);
            }
            
            // Get authenticated user ID from session (the reporter)
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }
            $reporterId = (int)$_SESSION['user_id'];
            
            // Check if attempting to report self
            if ($reporterId === $reportedUserId) {
                return Response::error('Cannot report yourself', 422);
            }
            
            // Parse JSON request body
            $body = json_decode(file_get_contents('php://input'), true);
            
            if (!$body) {
                return Response::error('Invalid JSON body', 400);
            }
            
            $reason = trim($body['reason'] ?? '');
            $comment = trim($body['comment'] ?? '');
            
            // Validate inputs
            if (empty($reason)) {
                return Response::error('Reason is required', 400);
            }
            
            // Submit report
            $result = $this->reportService->submitReport(
                $reporterId,
                'user',
                $reportedUserId,
                $reason,
                $comment
            );
            
            // Return 201 Created
            $data = [
                'report_id' => $result['id'],
                'status' => $result['status'],
            ];
            
            return Response::success($data, 201, 'User reported successfully');
        } catch (\InvalidArgumentException $e) {
            // Validation errors from service layer
            $message = $e->getMessage();
            
            if (str_contains($message, 'not found')) {
                return Response::error($message, 404);
            } elseif (str_contains($message, 'duplicate') || str_contains($message, 'already reported')) {
                return Response::error($message, 409);
            } elseif (str_contains($message, 'yourself')) {
                return Response::error($message, 422);
            } elseif (str_contains($message, 'Invalid')) {
                return Response::error($message, 400);
            }
            
            return Response::error($message, 422);
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * GET /api/admin/reports
     * 
     * Get paginated list of reports in admin queue.
     * Admin-only endpoint - requires is_admin = true.
     * 
     * Query parameters:
     * - status: Filter by status ('pending', 'approved', 'rejected', default 'pending')
     * - limit: Number of results (default 20, max 100)
     * - offset: Pagination offset (default 0)
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * - Must have is_admin = true in user profile
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response with reports and pagination
     */
    public function getReports(array $get, array $post, array $files, array $params): string {
        try {
            // Get authenticated user from session
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }
            
            // Check admin role (if available in session)
            if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
                return Response::error('Forbidden', 403);
            }
            
            // Parse query parameters
            $status = $get['status'] ?? 'pending';
            $limit = (int)($get['limit'] ?? 20);
            $offset = (int)($get['offset'] ?? 0);
            
            // Validate status parameter
            $validStatuses = ['pending', 'approved', 'rejected'];
            if (!in_array($status, $validStatuses)) {
                return Response::error('Invalid status - must be pending, approved, or rejected', 400);
            }
            
            // Validate pagination parameters
            if ($limit <= 0 || $limit > 100) {
                return Response::error('Limit must be between 1 and 100', 400);
            }
            if ($offset < 0) {
                return Response::error('Offset cannot be negative', 400);
            }
            
            // Get reports from service
            $result = $this->reportService->getReportQueue($status, $limit, $offset);
            
            // Format response with pagination metadata
            $data = [
                'reports' => $result['reports'],
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $result['total'],
                ],
            ];
            
            return Response::success($data, 200, 'Reports retrieved');
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
}

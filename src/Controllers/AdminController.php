<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;
use ReuseIT\Services\AdminService;
use ReuseIT\Services\ReportService;
use ReuseIT\Repositories\ReportRepository;

/**
 * AdminController
 * 
 * Handles admin moderation HTTP endpoints.
 * 
 * Protected endpoints (require admin role):
 * - GET /api/admin/reports - Get pending reports with action buttons
 * - PATCH /api/admin/reports/:id/approve - Approve and hide/ban content
 * - PATCH /api/admin/reports/:id/reject - Reject report without action
 * - GET /api/admin/stats - Dashboard statistics
 */
class AdminController {
    private AdminService $adminService;
    private ReportService $reportService;
    private ReportRepository $reportRepository;
    
    /**
     * Initialize controller with service dependencies.
     * 
     * @param AdminService $adminService Service layer for admin actions
     * @param ReportService $reportService Service layer for report queries
     * @param ReportRepository $reportRepository Repository for report counts
     */
    public function __construct(AdminService $adminService, ReportService $reportService, ReportRepository $reportRepository) {
        $this->adminService = $adminService;
        $this->reportService = $reportService;
        $this->reportRepository = $reportRepository;
    }
    
    /**
     * GET /api/admin/reports
     * 
     * Retrieve pending/approved/rejected reports with pagination.
     * Returns paginated list with action buttons metadata.
     * 
     * Query parameters:
     * - status: "pending", "approved", or "rejected" (default: "pending")
     * - limit: results per page (default: 20, max: 100)
     * - offset: results to skip (default: 0)
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * - Must be admin (is_admin == true in session)
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response with paginated reports
     */
    public function getReports(array $get, array $post, array $files, array $params): string {
        try {
            // Authorization: check admin role
            if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
                return Response::error('Forbidden: admin access required', 403);
            }
            
            // Parse query parameters
            $status = trim($get['status'] ?? 'pending');
            $limit = (int)($get['limit'] ?? 20);
            $offset = (int)($get['offset'] ?? 0);
            
            // Validate pagination
            if ($limit < 1 || $limit > 100) {
                $limit = 20;
            }
            if ($offset < 0) {
                $offset = 0;
            }
            
            // Validate status
            if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
                return Response::error('Invalid status parameter', 400);
            }
            
            // Get reports from service
            $reports = $this->reportService->getReportQueue($status, $limit, $offset);
            
            // Enrich reports with action metadata
            $enrichedReports = array_map(function($report) {
                return [
                    'id' => $report['id'],
                    'reporter_id' => $report['reporter_id'],
                    'reported_type' => $report['reported_type'],
                    'reported_id' => $report['reported_id'],
                    'reason' => $report['reason'],
                    'comment' => $report['comment'],
                    'status' => $report['status'],
                    'created_at' => $report['created_at'],
                    'actions' => $this->getReportActions($report['status'])
                ];
            }, $reports);
            
            // Get total count for pagination metadata
            $total = $this->reportRepository->countByStatus($status);
            
            $data = [
                'reports' => $enrichedReports,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total
                ]
            ];
            
            return Response::success($data, 200, 'Reports retrieved');
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * PATCH /api/admin/reports/:id/approve
     * 
     * Approve a report and hide/ban the reported content.
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * - Must be admin (is_admin == true in session)
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id' for report_id)
     * @return string JSON response with approval status
     */
    public function approveReport(array $get, array $post, array $files, array $params): string {
        try {
            // Authorization: check admin role
            if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
                return Response::error('Forbidden: admin access required', 403);
            }
            
            // Extract report ID from URL parameter
            $reportId = (int)($params['id'] ?? 0);
            
            if ($reportId <= 0) {
                return Response::error('Invalid report ID', 400);
            }
            
            $adminId = (int)$_SESSION['user_id'];
            
            // Approve report via service
            $result = $this->adminService->approveReport($adminId, $reportId);
            
            $data = [
                'status' => $result['status'],
                'action' => $result['action_taken'],
                'report_id' => $result['report_id']
            ];
            
            return Response::success($data, 200, 'Report approved and content hidden');
        } catch (\InvalidArgumentException $e) {
            // Validation errors from service layer
            $message = $e->getMessage();
            
            if (str_contains($message, 'not found')) {
                return Response::error($message, 404);
            } elseif (str_contains($message, 'not pending')) {
                return Response::error($message, 422);
            }
            
            return Response::error($message, 400);
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * PATCH /api/admin/reports/:id/reject
     * 
     * Reject a report without taking action on the reported content.
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * - Must be admin (is_admin == true in session)
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id' for report_id)
     * @return string JSON response with rejection status
     */
    public function rejectReport(array $get, array $post, array $files, array $params): string {
        try {
            // Authorization: check admin role
            if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
                return Response::error('Forbidden: admin access required', 403);
            }
            
            // Extract report ID from URL parameter
            $reportId = (int)($params['id'] ?? 0);
            
            if ($reportId <= 0) {
                return Response::error('Invalid report ID', 400);
            }
            
            $adminId = (int)$_SESSION['user_id'];
            
            // Reject report via service
            $result = $this->adminService->rejectReport($adminId, $reportId);
            
            $data = [
                'status' => $result['status'],
                'report_id' => $result['report_id']
            ];
            
            return Response::success($data, 200, 'Report rejected');
        } catch (\InvalidArgumentException $e) {
            // Validation errors from service layer
            $message = $e->getMessage();
            
            if (str_contains($message, 'not found')) {
                return Response::error($message, 404);
            } elseif (str_contains($message, 'not pending')) {
                return Response::error($message, 422);
            }
            
            return Response::error($message, 400);
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * GET /api/admin/stats
     * 
     * Get admin dashboard statistics.
     * Returns counts of pending reports, hidden listings, and banned users.
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * - Must be admin (is_admin == true in session)
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response with admin statistics
     */
    public function getAdminStats(array $get, array $post, array $files, array $params): string {
        try {
            // Authorization: check admin role
            if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
                return Response::error('Forbidden: admin access required', 403);
            }
            
            // Get stats from service
            $stats = $this->adminService->getAdminStats();
            
            return Response::success($stats, 200, 'Stats retrieved');
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * Helper: Get available actions for a report based on its status.
     * 
     * @param string $status Report status
     * @return array Array of action objects for frontend buttons
     */
    private function getReportActions(string $status): array {
        if ($status === 'pending') {
            return [
                ['label' => 'Approve', 'action' => 'approve', 'method' => 'PATCH'],
                ['label' => 'Reject', 'action' => 'reject', 'method' => 'PATCH']
            ];
        }
        
        // Already processed - no actions available
        return [];
    }
}

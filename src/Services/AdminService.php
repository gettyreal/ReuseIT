<?php
namespace ReuseIT\Services;

use InvalidArgumentException;
use ReuseIT\Repositories\ListingRepository;
use ReuseIT\Repositories\UserRepository;
use ReuseIT\Repositories\ReportRepository;

/**
 * AdminService
 * 
 * Service layer for admin content moderation actions.
 * Handles hiding listings, banning users, and processing reports.
 * All authorization checks are performed at the controller level.
 */
class AdminService {
    
    private ListingRepository $listingRepository;
    private UserRepository $userRepository;
    private ReportRepository $reportRepository;
    
    /**
     * Initialize AdminService with required repositories.
     * 
     * @param ListingRepository $listingRepository
     * @param UserRepository $userRepository
     * @param ReportRepository $reportRepository
     */
    public function __construct(
        ListingRepository $listingRepository,
        UserRepository $userRepository,
        ReportRepository $reportRepository
    ) {
        $this->listingRepository = $listingRepository;
        $this->userRepository = $userRepository;
        $this->reportRepository = $reportRepository;
    }
    
    /**
     * Hide a listing or ban a user based on report type.
     * 
     * @param int $adminId Admin user ID (for audit trail)
     * @param string $reportedType Type of content: 'listing' or 'user'
     * @param int $reportedId ID of the listing or user to hide/ban
     * @return array Response with action and content info
     * @throws InvalidArgumentException If type or ID is invalid
     */
    public function hideContent(int $adminId, string $reportedType, int $reportedId): array {
        $this->validateReportedType($reportedType);
        $this->validateUUID($reportedId);
        
        if ($reportedType === 'listing') {
            $this->listingRepository->hideListing($reportedId);
            return [
                'action' => 'hidden',
                'reported_type' => 'listing',
                'reported_id' => $reportedId
            ];
        } else {
            $this->userRepository->banUser($reportedId);
            return [
                'action' => 'banned',
                'reported_type' => 'user',
                'reported_id' => $reportedId
            ];
        }
    }
    
    /**
     * Approve a report and take action on the reported content.
     * 
     * @param int $adminId Admin user ID
     * @param int $reportId Report ID to approve
     * @return array Response with approval status and action taken
     * @throws InvalidArgumentException If report not found or already processed
     */
    public function approveReport(int $adminId, int $reportId): array {
        $this->validateUUID($reportId);
        
        // Find the report
        $report = $this->reportRepository->findById($reportId);
        if (!$report) {
            throw new InvalidArgumentException('Report not found');
        }
        
        // Ensure report is pending
        $this->ensureReportPending($report);
        
        // Extract reported type and ID
        $reportedType = $report->getReportedType();
        $reportedId = $report->getReportedId();
        
        // Hide/ban the content
        $this->hideContent($adminId, $reportedType, $reportedId);
        
        // Update report status to approved
        $this->reportRepository->updateStatus($reportId, 'approved');
        
        return [
            'status' => 'approved',
            'action_taken' => 'hidden',
            'report_id' => $reportId
        ];
    }
    
    /**
     * Reject a report without taking action.
     * 
     * @param int $adminId Admin user ID
     * @param int $reportId Report ID to reject
     * @return array Response with rejection status
     * @throws InvalidArgumentException If report not found or already processed
     */
    public function rejectReport(int $adminId, int $reportId): array {
        $this->validateUUID($reportId);
        
        // Find the report
        $report = $this->reportRepository->findById($reportId);
        if (!$report) {
            throw new InvalidArgumentException('Report not found');
        }
        
        // Ensure report is pending
        $this->ensureReportPending($report);
        
        // Update report status to rejected
        $this->reportRepository->updateStatus($reportId, 'rejected');
        
        return [
            'status' => 'rejected',
            'report_id' => $reportId
        ];
    }
    
    /**
     * Get admin dashboard statistics.
     * 
     * @return array Statistics including pending reports and hidden/banned counts
     */
    public function getAdminStats(): array {
        $pendingReports = $this->reportRepository->countByStatus('pending');
        $hiddenListings = $this->listingRepository->countHiddenListings();
        $bannedUsers = $this->userRepository->countBannedUsers();
        
        return [
            'pending_reports' => $pendingReports,
            'hidden_listings' => $hiddenListings,
            'banned_users' => $bannedUsers
        ];
    }
    
    /**
     * Validate reported type is either 'listing' or 'user'.
     * 
     * @param string $type Type to validate
     * @throws InvalidArgumentException If type is invalid
     */
    private function validateReportedType(string $type): void {
        if (!in_array($type, ['listing', 'user'], true)) {
            throw new InvalidArgumentException('Invalid reported type: must be "listing" or "user"');
        }
    }
    
    /**
     * Validate UUID format (currently positive integer validation).
     * 
     * @param mixed $value Value to validate
     * @throws InvalidArgumentException If not a valid positive integer
     */
    private function validateUUID(mixed $value): void {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException('Invalid ID format: must be positive integer');
        }
    }
    
    /**
     * Ensure report is in pending status.
     * 
     * @param object $report Report object with getStatus method
     * @throws InvalidArgumentException If report is not pending
     */
    private function ensureReportPending(object $report): void {
        if ($report->getStatus() !== 'pending') {
            throw new InvalidArgumentException('Report is not pending (already ' . $report->getStatus() . ')');
        }
    }
}

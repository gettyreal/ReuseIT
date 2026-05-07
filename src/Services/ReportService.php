<?php
namespace ReuseIT\Services;

use InvalidArgumentException;
use ReuseIT\Repositories\ReportRepository;
use ReuseIT\Repositories\ListingRepository;
use ReuseIT\Repositories\UserRepository;

/**
 * ReportService
 *
 * Service layer for content reporting and admin moderation.
 * Encapsulates business logic: submit reports, validate submissions, prevent duplicates,
 * and manage admin workflows (approve/reject reports).
 * Enforces authorization and comprehensive validation.
 */
class ReportService {

    private ReportRepository $reportRepo;
    private ListingRepository $listingRepo;
    private UserRepository $userRepo;

    /**
     * Initialize ReportService with dependencies.
     *
     * @param ReportRepository $reportRepo Report data access layer
     * @param ListingRepository $listingRepo Listing data access layer
     * @param UserRepository $userRepo User data access layer
     */
    public function __construct(
        ReportRepository $reportRepo,
        ListingRepository $listingRepo,
        UserRepository $userRepo
    ) {
        $this->reportRepo = $reportRepo;
        $this->listingRepo = $listingRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Submit a new report for moderation.
     *
     * Business logic validation:
     * 1. Validate: reporter_id is valid UUID and authenticated
     * 2. Validate: reported_type is 'listing' or 'user'
     * 3. Validate: reported_id is valid UUID
     * 4. Validate: reason is one of ['spam', 'fake', 'illegal', 'harmful']
     * 5. Validate: comment is string, max 1000 characters
     * 6. Check: if reported_type='user', reporter_id != reported_id (can't report self)
     * 7. Check: if reported_type='listing', listing exists and not deleted
     * 8. Check: if reported_type='user', user exists and not deleted
     * 9. Prevent duplicates: Check if pending report already exists from same reporter
     *    on same content within 24 hours (no duplicate reports)
     * 10. Create report via ReportRepository.create() with status='pending'
     * 11. Return: {"report_id": id, "status": "pending", "message": "Report submitted successfully"}
     *
     * Error handling: Throw InvalidArgumentException for validation errors with user-friendly messages
     *
     * @param int $reporterId User ID submitting the report
     * @param string $reportedType Type of content ('listing' or 'user')
     * @param int $reportedId ID of reported content
     * @param string $reason Reason for report (spam, fake, illegal, harmful)
     * @param string|null $comment Optional comment (max 1000 chars)
     * @return array Array with keys: report_id, status, message
     * @throws InvalidArgumentException If validation fails
     */
    public function submitReport(
        int $reporterId,
        string $reportedType,
        int $reportedId,
        string $reason,
        ?string $comment = null
    ): array {
        // Validate: reporter_id is valid
        $this->validateUUID($reporterId, 'Reporter ID');

        // Validate: reported_type is valid enum
        $this->validateReportedType($reportedType);

        // Validate: reported_id is valid
        $this->validateUUID($reportedId, 'Reported ID');

        // Validate: reason is valid enum
        $this->validateReason($reason);

        // Validate: comment is valid (if provided)
        $this->validateComment($comment);

        // Check: user cannot report themselves
        if ($reportedType === 'user' && $reporterId === $reportedId) {
            throw new InvalidArgumentException('You cannot report yourself');
        }

        // Check: reported content exists
        if ($reportedType === 'listing') {
            $this->validateListingExists($reportedId);
        } elseif ($reportedType === 'user') {
            $this->validateUserExists($reportedId);
        }

        // Prevent duplicates: check for existing pending report from same reporter in last 24 hours
        $this->validateNoDuplicateReport($reporterId, $reportedType, $reportedId);

        // Create report in repository
        $report = $this->reportRepo->createReport($reporterId, $reportedType, $reportedId, $reason, $comment);

        return [
            'report_id' => $report['id'],
            'status' => $report['status'],
            'message' => 'Report submitted successfully'
        ];
    }

    /**
     * Retrieve reports by status for admin moderation queue.
     *
     * Business logic:
     * 1. Validate: status is 'pending', 'approved', or 'rejected'
     * 2. Fetch reports via ReportRepository.findByStatus(status, limit, offset)
     * 3. Enrich each report: Load reported listing/user data and reporter user data
     * 4. Return: Array of enriched report objects with full context
     * 5. Order: created_at DESC (newest first)
     *
     * Note: Authorization check (admin-only) deferred to controller middleware
     *
     * @param string $status Report status ('pending', 'approved', 'rejected')
     * @param int $limit Number of results per page (default: 20)
     * @param int $offset Results to skip (default: 0)
     * @return array Array of enriched report objects
     * @throws InvalidArgumentException If validation fails
     */
    public function getReportQueue(string $status = 'pending', int $limit = 20, int $offset = 0): array {
        // Validate: status is valid
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Invalid status: ' . $status);
        }

        // Validate pagination
        if ($limit <= 0 || $limit > 100) {
            throw new InvalidArgumentException('Limit must be between 1 and 100');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be non-negative');
        }

        // Fetch reports from repository
        $reports = $this->reportRepo->findByStatus($status, $limit, $offset);

        // Enrich each report with reporter and reported content data
        $enriched = [];
        foreach ($reports as $report) {
            $reporterData = $this->userRepo->find($report['reporter_id']);
            $reportedData = null;

            if ($report['reported_type'] === 'listing') {
                $reportedData = $this->listingRepo->find($report['reported_id']);
            } elseif ($report['reported_type'] === 'user') {
                $reportedData = $this->userRepo->find($report['reported_id']);
            }

            $enriched[] = [
                'id' => $report['id'],
                'reported_type' => $report['reported_type'],
                'reported_id' => $report['reported_id'],
                'reported_content' => $reportedData,
                'reporter_id' => $report['reporter_id'],
                'reporter_name' => $reporterData['display_name'] ?? $reporterData['email'] ?? 'Unknown',
                'reason' => $report['reason'],
                'comment' => $report['comment'],
                'status' => $report['status'],
                'created_at' => $report['created_at'],
                'updated_at' => $report['updated_at']
            ];
        }

        return $enriched;
    }

    /**
     * Retrieve all reports for a specific piece of content.
     *
     * Business logic:
     * 1. Validate: reported_type is 'listing' or 'user'
     * 2. Validate: reported_id is valid UUID
     * 3. Fetch all reports (including soft-deleted) for this content
     * 4. Enrich with reporter names and report details
     * 5. Return: Array of reports for this specific listing/user
     * 6. Sort: status='pending' first, then by created_at DESC
     *
     * @param string $reportedType Type of content ('listing' or 'user')
     * @param int $reportedId ID of the content
     * @return array Array of enriched report objects
     * @throws InvalidArgumentException If validation fails
     */
    public function getReportsByContent(string $reportedType, int $reportedId): array {
        // Validate: reported_type is valid
        $this->validateReportedType($reportedType);

        // Validate: reported_id is valid
        $this->validateUUID($reportedId, 'Reported ID');

        // Fetch reports from repository
        $reports = $this->reportRepo->findByReportedContent($reportedType, $reportedId);

        // Enrich each report with reporter data
        $enriched = [];
        foreach ($reports as $report) {
            $reporterData = $this->userRepo->find($report['reporter_id']);

            $enriched[] = [
                'id' => $report['id'],
                'reported_type' => $report['reported_type'],
                'reported_id' => $report['reported_id'],
                'reporter_id' => $report['reporter_id'],
                'reporter_name' => $reporterData['display_name'] ?? $reporterData['email'] ?? 'Unknown',
                'reason' => $report['reason'],
                'comment' => $report['comment'],
                'status' => $report['status'],
                'created_at' => $report['created_at'],
                'updated_at' => $report['updated_at']
            ];
        }

        // Sort: pending first, then by created_at DESC
        usort($enriched, function($a, $b) {
            // Pending reports first
            if ($a['status'] === 'pending' && $b['status'] !== 'pending') {
                return -1;
            }
            if ($a['status'] !== 'pending' && $b['status'] === 'pending') {
                return 1;
            }
            // Then sort by created_at DESC (newest first)
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $enriched;
    }

    /**
     * Approve a pending report (mark content for hiding).
     *
     * Business logic:
     * 1. Validate: report exists and is 'pending'
     * 2. Update status to 'approved' via ReportRepository.updateStatus()
     * 3. Return: {"status": "approved", "message": "Report approved. Content will be hidden."}
     *
     * Note: Actual content hiding deferred to Plan 04
     *
     * @param int $reportId Report ID to approve
     * @return array Array with status and message
     * @throws InvalidArgumentException If validation fails
     */
    public function approveReport(int $reportId): array {
        // Validate: report exists
        $this->validateUUID($reportId, 'Report ID');

        $report = $this->reportRepo->findById($reportId);
        if (!$report) {
            throw new InvalidArgumentException('Report not found');
        }

        // Validate: report is pending
        if ($report['status'] !== 'pending') {
            throw new InvalidArgumentException('Only pending reports can be approved');
        }

        // Update status to approved
        $this->reportRepo->updateStatus($reportId, 'approved');

        return [
            'status' => 'approved',
            'message' => 'Report approved. Content will be hidden.'
        ];
    }

    /**
     * Reject a pending report (no action needed).
     *
     * Business logic:
     * 1. Validate: report exists and is 'pending'
     * 2. Update status to 'rejected' via ReportRepository.updateStatus()
     * 3. Return: {"status": "rejected", "message": "Report rejected."}
     *
     * @param int $reportId Report ID to reject
     * @return array Array with status and message
     * @throws InvalidArgumentException If validation fails
     */
    public function rejectReport(int $reportId): array {
        // Validate: report exists
        $this->validateUUID($reportId, 'Report ID');

        $report = $this->reportRepo->findById($reportId);
        if (!$report) {
            throw new InvalidArgumentException('Report not found');
        }

        // Validate: report is pending
        if ($report['status'] !== 'pending') {
            throw new InvalidArgumentException('Only pending reports can be rejected');
        }

        // Update status to rejected
        $this->reportRepo->updateStatus($reportId, 'rejected');

        return [
            'status' => 'rejected',
            'message' => 'Report rejected.'
        ];
    }

    /**
     * Validate that a value is a valid UUID (positive integer).
     *
     * @param int $value Value to validate
     * @param string $fieldName Field name for error message
     * @return void
     * @throws InvalidArgumentException If validation fails
     */
    private function validateUUID(int $value, string $fieldName = 'ID'): void {
        if ($value <= 0) {
            throw new InvalidArgumentException("{$fieldName} must be a valid positive integer");
        }
    }

    /**
     * Validate that reported_type is 'listing' or 'user'.
     *
     * @param string $type Type to validate
     * @return void
     * @throws InvalidArgumentException If validation fails
     */
    private function validateReportedType(string $type): void {
        if (!in_array($type, ['listing', 'user'], true)) {
            throw new InvalidArgumentException('Reported type must be "listing" or "user"');
        }
    }

    /**
     * Validate that reason is one of the valid enum values.
     *
     * @param string $reason Reason to validate
     * @return void
     * @throws InvalidArgumentException If validation fails
     */
    private function validateReason(string $reason): void {
        $validReasons = ['spam', 'fake', 'illegal', 'harmful'];
        if (!in_array($reason, $validReasons, true)) {
            throw new InvalidArgumentException('Reason must be one of: ' . implode(', ', $validReasons));
        }
    }

    /**
     * Validate that comment is a string with max 1000 characters.
     *
     * @param string|null $comment Comment to validate
     * @return void
     * @throws InvalidArgumentException If validation fails
     */
    private function validateComment(?string $comment): void {
        if ($comment === null || $comment === '') {
            return; // Comment is optional
        }

        if (!is_string($comment)) {
            throw new InvalidArgumentException('Comment must be a string');
        }

        if (strlen($comment) > 1000) {
            throw new InvalidArgumentException('Comment must not exceed 1000 characters');
        }
    }

    /**
     * Validate that a listing exists and is not deleted.
     *
     * @param int $listingId Listing ID to validate
     * @return void
     * @throws InvalidArgumentException If listing not found or deleted
     */
    private function validateListingExists(int $listingId): void {
        $listing = $this->listingRepo->find($listingId);

        if (!$listing) {
            throw new InvalidArgumentException('Listing not found or has been deleted');
        }
    }

    /**
     * Validate that a user exists and is not deleted.
     *
     * @param int $userId User ID to validate
     * @return void
     * @throws InvalidArgumentException If user not found or deleted
     */
    private function validateUserExists(int $userId): void {
        $user = $this->userRepo->find($userId);

        if (!$user) {
            throw new InvalidArgumentException('User not found or has been deleted');
        }
    }

    /**
     * Check that no pending report exists from same reporter on same content within 24 hours.
     *
     * Prevents spam report flooding by requiring 24-hour window between duplicate reports.
     *
     * @param int $reporterId Reporter user ID
     * @param string $reportedType Type of content
     * @param int $reportedId ID of content
     * @return void
     * @throws InvalidArgumentException If duplicate pending report found
     */
    private function validateNoDuplicateReport(int $reporterId, string $reportedType, int $reportedId): void {
        // Fetch all reports for this content
        $reports = $this->reportRepo->findByReportedContent($reportedType, $reportedId);

        // Check for pending reports from same reporter within 24 hours
        $now = time();
        foreach ($reports as $report) {
            if ($report['reporter_id'] === $reporterId && $report['status'] === 'pending') {
                $createdTime = strtotime($report['created_at']);
                $hoursSince = ($now - $createdTime) / 3600;

                if ($hoursSince < 24) {
                    throw new InvalidArgumentException('You have already submitted a pending report on this content. Please wait before submitting another.');
                }
            }
        }
    }
}

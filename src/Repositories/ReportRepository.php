<?php
namespace ReuseIT\Repositories;

use PDO;
use InvalidArgumentException;
use ReuseIT\ValueObjects\Report;

/**
 * ReportRepository
 * 
 * Data access layer for content reports (listings or users).
 * Handles CRUD operations for moderation workflows.
 * Implements soft-delete filtering for all queries.
 */
class ReportRepository extends BaseRepository {

    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'reports');
    }

    /**
     * Find a single report by ID.
     * 
     * @param int $id Report ID
     * @return array|null Raw database row or null if not found
     */
    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ?: null;
    }

    /**
     * Find a single report by ID and convert to value object.
     * 
     * @param int $id Report ID
     * @return Report|null The report or null if not found
     */
    public function findAsValueObject(int $id): ?Report {
        $row = $this->find($id);
        return $row ? Report::fromDatabase($row) : null;
    }

    /**
     * Alias for findAsValueObject for API convenience.
     * Find a single report by ID and return as value object.
     * 
     * @param int $id Report ID
     * @return Report|null The report or null if not found
     */
    public function findById(int $id): ?Report {
        return $this->findAsValueObject($id);
    }

    /**
     * Find reports by status (for admin moderation queue).
     * 
     * @param string $status Report status ('pending', 'approved', 'rejected')
     * @param int $limit Number of results to return (default: 20)
     * @param int $offset Number of results to skip (default: 0)
     * @return Report[] Array of report objects ordered by newest first
     */
    public function findByStatus(string $status, int $limit = 20, int $offset = 0): array {
        if (!Report::isValidStatus($status)) {
            throw new InvalidArgumentException('Invalid status: ' . $status);
        }

        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE status = ?
              " . $this->applyDeleteFilter() . "
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $status, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => Report::fromDatabase($row), $rows);
    }

    /**
     * Find all reports for a specific piece of content.
     * 
     * @param string $reportedType Type of content ('listing' or 'user')
     * @param int $reportedId ID of the content being reported
     * @return Report[] Array of report objects
     */
    public function findByReportedContent(string $reportedType, int $reportedId): array {
        if (!Report::isValidType($reportedType)) {
            throw new InvalidArgumentException('Invalid reported type: ' . $reportedType);
        }

        if ($reportedId <= 0) {
            throw new InvalidArgumentException('Reported ID must be positive');
        }

        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE reported_type = ? AND reported_id = ?
              " . $this->applyDeleteFilter() . "
            ORDER BY created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$reportedType, $reportedId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => Report::fromDatabase($row), $rows);
    }

    /**
     * Create a new report.
     * 
     * @param int $reporterId User submitting the report
     * @param string $reportedType Type of content being reported ('listing' or 'user')
     * @param int $reportedId ID of the content being reported
     * @param string $reason Reason for report (spam, fake, illegal, harmful)
     * @param string|null $comment Optional user comment on report
     * @return Report The newly created report
     */
    public function createReport(int $reporterId, string $reportedType, int $reportedId, string $reason, ?string $comment = null): Report {
        if ($reporterId <= 0) {
            throw new InvalidArgumentException('Reporter ID must be positive');
        }

        if (!Report::isValidType($reportedType)) {
            throw new InvalidArgumentException('Invalid reported type: ' . $reportedType);
        }

        if ($reportedId <= 0) {
            throw new InvalidArgumentException('Reported ID must be positive');
        }

        if (!Report::isValidReason($reason)) {
            throw new InvalidArgumentException('Invalid reason: ' . $reason);
        }

        // Validate: users cannot report themselves
        if ($reportedType === 'user' && $reporterId === $reportedId) {
            throw new InvalidArgumentException('Users cannot report themselves');
        }

        $sql = "
            INSERT INTO {$this->table} (reporter_id, reported_type, reported_id, reason, comment, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $reporterId,
            $reportedType,
            $reportedId,
            $reason,
            $comment,
            Report::STATUS_PENDING
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findAsValueObject($id);
    }

    /**
     * Update the status of a report (used by admin).
     * 
     * @param int $id Report ID
     * @param string $newStatus New status ('approved' or 'rejected')
     * @return Report|null The updated report or null if not found
     */
    public function updateStatus(int $id, string $newStatus): ?Report {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID must be positive');
        }

        if (!Report::isValidStatus($newStatus)) {
            throw new InvalidArgumentException('Invalid status: ' . $newStatus);
        }

        // Check if report exists
        $report = $this->find($id);
        if (!$report) {
            return null;
        }

        $sql = "UPDATE {$this->table} SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$newStatus, $id]);

        return $this->findAsValueObject($id);
    }

    /**
     * Count reports by status.
     * 
     * @param string $status Report status to count
     * @return int Number of reports with that status
     */
    public function countByStatus(string $status): int {
        if (!Report::isValidStatus($status)) {
            throw new InvalidArgumentException('Invalid status: ' . $status);
        }

        $sql = "
            SELECT COUNT(*) as total
            FROM {$this->table}
            WHERE status = ?
              " . $this->applyDeleteFilter() . "
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$status]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['total'] ?? 0);
    }
}

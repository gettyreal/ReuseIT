<?php
namespace ReuseIT\ValueObjects;

/**
 * Report
 * 
 * Immutable domain value object for content reports (listings or users).
 * Enumerates valid reasons and statuses for moderation workflows.
 */
class Report {
    // Reason enum values
    public const REASON_SPAM = 'spam';
    public const REASON_FAKE = 'fake';
    public const REASON_ILLEGAL = 'illegal';
    public const REASON_HARMFUL = 'harmful';

    private static array $validReasons = [
        self::REASON_SPAM,
        self::REASON_FAKE,
        self::REASON_ILLEGAL,
        self::REASON_HARMFUL,
    ];

    // Status enum values
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    private static array $validStatuses = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    // Reported type enum values
    public const TYPE_LISTING = 'listing';
    public const TYPE_USER = 'user';

    private static array $validTypes = [
        self::TYPE_LISTING,
        self::TYPE_USER,
    ];

    private int $id;
    private int $reporterId;
    private string $reportedType;
    private int $reportedId;
    private string $reason;
    private string $status;
    private ?string $comment;
    private string $createdAt;

    /**
     * Create a Report value object.
     * 
     * @param int $id Unique report identifier
     * @param int $reporterId User who submitted the report
     * @param string $reportedType Type of content being reported ('listing' or 'user')
     * @param int $reportedId ID of content being reported
     * @param string $reason Reason for report (spam, fake, illegal, harmful)
     * @param string $status Report status (pending, approved, rejected)
     * @param string|null $comment Optional admin comment on report
     * @param string $createdAt ISO 8601 timestamp when reported
     */
    public function __construct(
        int $id,
        int $reporterId,
        string $reportedType,
        int $reportedId,
        string $reason,
        string $status,
        ?string $comment,
        string $createdAt
    ) {
        $this->id = $id;
        $this->reporterId = $reporterId;
        $this->reportedType = $reportedType;
        $this->reportedId = $reportedId;
        $this->reason = $reason;
        $this->status = $status;
        $this->comment = $comment;
        $this->createdAt = $createdAt;
    }

    /**
     * Get the report's unique identifier.
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * Get the user who submitted the report.
     */
    public function getReporterId(): int {
        return $this->reporterId;
    }

    /**
     * Get the type of content being reported.
     */
    public function getReportedType(): string {
        return $this->reportedType;
    }

    /**
     * Get the ID of content being reported.
     */
    public function getReportedId(): int {
        return $this->reportedId;
    }

    /**
     * Get the reason for the report.
     */
    public function getReason(): string {
        return $this->reason;
    }

    /**
     * Get the current status of the report.
     */
    public function getStatus(): string {
        return $this->status;
    }

    /**
     * Get the optional admin comment on the report.
     */
    public function getComment(): ?string {
        return $this->comment;
    }

    /**
     * Get the timestamp when the report was created.
     */
    public function getCreatedAt(): string {
        return $this->createdAt;
    }

    /**
     * Check if a reason value is valid.
     */
    public static function isValidReason(string $reason): bool {
        return in_array($reason, self::$validReasons, true);
    }

    /**
     * Check if a status value is valid.
     */
    public static function isValidStatus(string $status): bool {
        return in_array($status, self::$validStatuses, true);
    }

    /**
     * Check if a reported type value is valid.
     */
    public static function isValidType(string $type): bool {
        return in_array($type, self::$validTypes, true);
    }

    /**
     * Create a Report from database row.
     * 
     * @param array $row Associative array from database
     * @return self
     */
    public static function fromDatabase(array $row): self {
        return new self(
            (int) $row['id'],
            (int) $row['reporter_id'],
            $row['reported_type'],
            (int) $row['reported_id'],
            $row['reason'],
            $row['status'],
            $row['comment'] ?? null,
            $row['created_at']
        );
    }
}

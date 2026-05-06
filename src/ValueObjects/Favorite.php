<?php
namespace ReuseIT\ValueObjects;

/**
 * Favorite
 * 
 * Immutable domain value object for a user's favorite listing.
 */
class Favorite {
    private int $id;
    private int $userId;
    private int $listingId;
    private string $createdAt;

    /**
     * Create a Favorite value object.
     * 
     * @param int $id Unique favorite identifier
     * @param int $userId User who favorited the listing
     * @param int $listingId Listing being favorited
     * @param string $createdAt ISO 8601 timestamp when favorited
     */
    public function __construct(
        int $id,
        int $userId,
        int $listingId,
        string $createdAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->listingId = $listingId;
        $this->createdAt = $createdAt;
    }

    /**
     * Get the favorite's unique identifier.
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * Get the user who favorited the listing.
     */
    public function getUserId(): int {
        return $this->userId;
    }

    /**
     * Get the listing that was favorited.
     */
    public function getListingId(): int {
        return $this->listingId;
    }

    /**
     * Get the timestamp when the favorite was created.
     */
    public function getCreatedAt(): string {
        return $this->createdAt;
    }

    /**
     * Create a Favorite from database row.
     * 
     * @param array $row Associative array from database
     * @return self
     */
    public static function fromDatabase(array $row): self {
        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            (int) $row['listing_id'],
            $row['created_at']
        );
    }
}

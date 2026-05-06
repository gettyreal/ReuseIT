<?php
namespace ReuseIT\Repositories;

use PDO;
use InvalidArgumentException;
use ReuseIT\ValueObjects\Favorite;

/**
 * FavoriteRepository
 * 
 * Data access layer for favorite records.
 * Handles CRUD operations for user favorites on listings.
 * Implements soft-delete filtering for all queries.
 */
class FavoriteRepository extends BaseRepository {

    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'favorites');
    }

    /**
     * Find a single favorite by ID.
     * 
     * @param int $id Favorite ID
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
     * Find a single favorite by ID and convert to value object.
     * 
     * @param int $id Favorite ID
     * @return Favorite|null The favorite or null if not found
     */
    public function findAsValueObject(int $id): ?Favorite {
        $row = $this->find($id);
        return $row ? Favorite::fromDatabase($row) : null;
    }

    /**
     * Find all favorites for a user, paginated and ordered by newest first.
     * 
     * @param int $userId User ID
     * @param int $limit Number of results to return (default: 20)
     * @param int $offset Number of results to skip (default: 0)
     * @return Favorite[] Array of favorite objects
     */
    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE user_id = ?
              " . $this->applyDeleteFilter() . "
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => Favorite::fromDatabase($row), $rows);
    }

    /**
     * Check if a user has favorited a specific listing.
     * 
     * @param int $userId User ID
     * @param int $listingId Listing ID
     * @return Favorite|null The favorite if it exists, null otherwise
     */
    public function findByUserAndListing(int $userId, int $listingId): ?Favorite {
        if ($userId <= 0 || $listingId <= 0) {
            throw new InvalidArgumentException('User ID and Listing ID must be positive');
        }

        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE user_id = ? AND listing_id = ?
              " . $this->applyDeleteFilter() . "
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $listingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Favorite::fromDatabase($row) : null;
    }

    /**
     * Create a new favorite or return existing favorite (idempotent).
     * 
     * If the favorite already exists (user has already favorited this listing),
     * this method returns the existing favorite without error.
     * 
     * @param int $userId User ID
     * @param int $listingId Listing ID
     * @return Favorite The newly created or existing favorite
     */
    public function createFavorite(int $userId, int $listingId): Favorite {
        if ($userId <= 0 || $listingId <= 0) {
            throw new InvalidArgumentException('User ID and Listing ID must be positive');
        }

        // Check if favorite already exists (idempotent create)
        $existing = $this->findByUserAndListing($userId, $listingId);
        if ($existing) {
            return $existing;
        }

        $sql = "
            INSERT INTO {$this->table} (user_id, listing_id, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $listingId]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findAsValueObject($id);
    }

    /**
     * Soft-delete a favorite by ID.
     * 
     * @param int $id Favorite ID
     * @return bool True if a favorite was deleted, false if not found
     */
    public function delete(int $id): bool {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID must be positive');
        }

        // Check if favorite exists before deleting
        $favorite = $this->find($id);
        if (!$favorite) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return true;
    }

    /**
     * Count total active favorites for a user.
     * 
     * Used for displaying favorite count badge in UI.
     * 
     * @param int $userId User ID
     * @return int Number of active favorites
     */
    public function countByUser(int $userId): int {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        $sql = "
            SELECT COUNT(*) as total
            FROM {$this->table}
            WHERE user_id = ?
              " . $this->applyDeleteFilter() . "
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['total'] ?? 0);
    }
}

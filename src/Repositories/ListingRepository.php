<?php
namespace ReuseIT\Repositories;

use PDO;

/**
 * ListingRepository
 * 
 * Data access layer for listings.
 * Extends BaseRepository with listing-specific query methods.
 */
class ListingRepository extends BaseRepository {
    
    /**
     * Initialize repository with PDO connection.
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'listings');
    }
    
    /**
     * Find all active listings with optional filtering and pagination.
     * 
     * Filters applied:
     * - Soft delete filtering (deleted_at IS NULL)
     * - Category filter (optional)
     * - Status filter (optional)
     * - Seller filter (optional)
     * - Price range filtering (optional)
     * 
     * @param array $filters Filter criteria:
     *   - category_id: int (optional)
     *   - status: string (optional)
     *   - seller_id: int (optional)
     *   - price_min: float (optional)
     *   - price_max: float (optional)
     * @param int $limit Number of results per page (default 20)
     * @param int $offset Number of results to skip (default 0)
     * @return array Array of listing records with pagination
     */
    public function findAll(array $filters = [], int $limit = 20, int $offset = 0): array {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1" . $this->applyDeleteFilter();
        $params = [];
        
        // Apply category filter if provided
        if (isset($filters['category_id'])) {
            $sql .= " AND category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        // Apply status filter if provided
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        // Apply seller filter if provided
        if (isset($filters['seller_id'])) {
            $sql .= " AND seller_id = ?";
            $params[] = $filters['seller_id'];
        }
        
        // Apply price range filtering if provided
        if (isset($filters['price_min'])) {
            $sql .= " AND price >= ?";
            $params[] = $filters['price_min'];
        }
        
        if (isset($filters['price_max'])) {
            $sql .= " AND price <= ?";
            $params[] = $filters['price_max'];
        }
        
        // Add sorting and pagination
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total count of active listings matching filters.
     * Used for pagination metadata.
     * 
     * @param array $filters Same filters as findAll()
     * @return int Total count of matching listings
     */
    public function countAll(array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1" . $this->applyDeleteFilter();
        $params = [];
        
        // Apply same filters as findAll()
        if (isset($filters['category_id'])) {
            $sql .= " AND category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['seller_id'])) {
            $sql .= " AND seller_id = ?";
            $params[] = $filters['seller_id'];
        }
        
        if (isset($filters['price_min'])) {
            $sql .= " AND price >= ?";
            $params[] = $filters['price_min'];
        }
        
        if (isset($filters['price_max'])) {
            $sql .= " AND price <= ?";
            $params[] = $filters['price_max'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }
    
    /**
     * Find listing by ID with full details including seller and photo count.
     * Filters soft-deleted listings.
     * 
     * @param int $id Listing ID
     * @return array|null Listing data or null if not found
     */
    public function find(int $id): ?array {
        $sql = "
            SELECT l.*, 
                   u.first_name as seller_first_name,
                   u.last_name as seller_last_name,
                   u.avatar_url as seller_avatar_url,
                   COUNT(DISTINCT p.id) as photo_count
            FROM {$this->table} l
            LEFT JOIN users u ON l.seller_id = u.id
            LEFT JOIN listing_photos p ON l.id = p.listing_id AND p.deleted_at IS NULL
            WHERE l.id = ?" . $this->applyDeleteFilter('l') . "
            GROUP BY l.id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get listing with all associated photos in display order.
     * Used for full listing view with photo details.
     * 
     * @param int $id Listing ID
     * @return array|null Array with 'listing' and 'photos' keys, or null if not found
     */
    public function findWithPhotos(int $id): ?array {
        // Get listing details
        $listing = $this->find($id);
        if (!$listing) {
            return null;
        }
        
        // Get associated photos in display order
        $photoSql = "
            SELECT * FROM listing_photos 
            WHERE listing_id = ? AND deleted_at IS NULL
            ORDER BY display_order ASC
        ";
        $photoStmt = $this->pdo->prepare($photoSql);
        $photoStmt->execute([$id]);
        $photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'listing' => $listing,
            'photos' => $photos
        ];
    }
    
    /**
     * Increment view count for a listing.
     * Called when listing is viewed.
     * 
     * @param int $id Listing ID
     * @return bool True if increment successful
     */
    public function incrementViewCount(int $id): bool {
        $sql = "UPDATE {$this->table} SET view_count = view_count + 1 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
}

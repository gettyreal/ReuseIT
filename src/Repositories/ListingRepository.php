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
     * Search listings by keyword in title and description.
     * 
     * @param string $keyword Search term
     * @param int $limit Results per page (default 20)
     * @param int $offset Results to skip (default 0)
     * @return array Array of matching listings with soft-delete filtering applied
     */
    public function searchByKeyword(string $keyword, int $limit = 20, int $offset = 0): array {
        $keyword = '%' . $keyword . '%';
        
        $sql = "
            SELECT l.*, 
                   u.first_name, u.last_name, u.avatar_url,
                   COUNT(DISTINCT p.id) as photo_count
            FROM {$this->table} l
            LEFT JOIN users u ON l.seller_id = u.id
            LEFT JOIN listing_photos p ON l.id = p.listing_id AND p.deleted_at IS NULL
            WHERE (l.title LIKE ? OR l.description LIKE ?) AND l.deleted_at IS NULL AND u.deleted_at IS NULL
            GROUP BY l.id
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$keyword, $keyword, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Filter listings by category.
     * 
     * @param int $categoryId Category ID
     * @param int $limit Results per page (default 20)
     * @param int $offset Results to skip (default 0)
     * @return array Array of listings in category with soft-delete filtering
     */
    public function filterByCategory(int $categoryId, int $limit = 20, int $offset = 0): array {
        $sql = "
            SELECT l.*, 
                   u.first_name, u.last_name, u.avatar_url,
                   COUNT(DISTINCT p.id) as photo_count
            FROM {$this->table} l
            LEFT JOIN users u ON l.seller_id = u.id
            LEFT JOIN listing_photos p ON l.id = p.listing_id AND p.deleted_at IS NULL
            WHERE l.category_id = ? AND l.deleted_at IS NULL AND u.deleted_at IS NULL
            GROUP BY l.id
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Filter listings by condition.
     * 
     * Valid conditions: ['Excellent', 'Good', 'Fair', 'Poor']
     * 
     * @param string $condition Item condition
     * @param int $limit Results per page (default 20)
     * @param int $offset Results to skip (default 0)
     * @return array Array of listings with matching condition
     */
    public function filterByCondition(string $condition, int $limit = 20, int $offset = 0): array {
        $validConditions = ['Excellent', 'Good', 'Fair', 'Poor'];
        if (!in_array($condition, $validConditions)) {
            return [];
        }
        
        $sql = "
            SELECT l.*, 
                   u.first_name, u.last_name, u.avatar_url,
                   COUNT(DISTINCT p.id) as photo_count
            FROM {$this->table} l
            LEFT JOIN users u ON l.seller_id = u.id
            LEFT JOIN listing_photos p ON l.id = p.listing_id AND p.deleted_at IS NULL
            WHERE l.condition = ? AND l.deleted_at IS NULL AND u.deleted_at IS NULL
            GROUP BY l.id
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$condition, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Filter listings by price range.
     * 
     * @param float $minPrice Minimum price
     * @param float $maxPrice Maximum price
     * @param int $limit Results per page (default 20)
     * @param int $offset Results to skip (default 0)
     * @return array Array of listings within price range
     */
    public function filterByPrice(float $minPrice, float $maxPrice, int $limit = 20, int $offset = 0): array {
        if ($minPrice > $maxPrice) {
            return [];
        }
        
        $sql = "
            SELECT l.*, 
                   u.first_name, u.last_name, u.avatar_url,
                   COUNT(DISTINCT p.id) as photo_count
            FROM {$this->table} l
            LEFT JOIN users u ON l.seller_id = u.id
            LEFT JOIN listing_photos p ON l.id = p.listing_id AND p.deleted_at IS NULL
            WHERE l.price BETWEEN ? AND ? AND l.deleted_at IS NULL AND u.deleted_at IS NULL
            GROUP BY l.id
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$minPrice, $maxPrice, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Filter listings by multiple criteria combined with AND logic.
     * 
     * Supported filters:
     * - category_id: int
     * - condition: string (enum)
     * - price_min: float
     * - price_max: float
     * - keyword: string (searches title and description)
     * 
     * @param array $filters Filter criteria hash
     * @param int $limit Results per page (default 20)
     * @param int $offset Results to skip (default 0)
     * @return array Array of listings matching all filters
     */
    public function filterCombined(array $filters = [], int $limit = 20, int $offset = 0): array {
        $sql = "
            SELECT l.*, 
                   u.first_name, u.last_name, u.avatar_url,
                   COUNT(DISTINCT p.id) as photo_count
            FROM {$this->table} l
            LEFT JOIN users u ON l.seller_id = u.id
            LEFT JOIN listing_photos p ON l.id = p.listing_id AND p.deleted_at IS NULL
            WHERE 1=1 AND l.deleted_at IS NULL AND u.deleted_at IS NULL
        ";
        
        $params = [];
        
        // Category filter
        if (isset($filters['category_id'])) {
            $sql .= " AND l.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        // Condition filter
        if (isset($filters['condition'])) {
            $sql .= " AND l.condition = ?";
            $params[] = $filters['condition'];
        }
        
        // Price range filter
        if (isset($filters['price_min'])) {
            $sql .= " AND l.price >= ?";
            $params[] = $filters['price_min'];
        }
        
        if (isset($filters['price_max'])) {
            $sql .= " AND l.price <= ?";
            $params[] = $filters['price_max'];
        }
        
        // Keyword search filter
        if (isset($filters['keyword']) && !empty($filters['keyword'])) {
            $keyword = '%' . $filters['keyword'] . '%';
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
        }
        
        // Add grouping, sorting, and pagination
        $sql .= " GROUP BY l.id ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

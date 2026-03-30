<?php
namespace ReuseIT\Services;

use PDO;
use ReuseIT\Repositories\ListingRepository;
use ReuseIT\Repositories\ListingPhotoRepository;
use ReuseIT\Repositories\UserRepository;
use ReuseIT\Utils\GeometryService;
use Exception;
use InvalidArgumentException;

/**
 * ListingService
 * 
 * Encapsulates listing business logic.
 * Handles creation, updates, deletion, and retrieval with validation and authorization.
 * Integrates geocoding for address-to-coordinates conversion.
 */
class ListingService {
    
    private PDO $pdo;
    private ListingRepository $listingRepo;
    private ListingPhotoRepository $photoRepo;
    private GeolocationService $geolocation;
    private UserRepository $userRepo;
    
    /**
     * Initialize ListingService with dependencies.
     * 
     * @param PDO $pdo Database connection
     * @param ListingRepository $listingRepo Listing data access layer
     * @param ListingPhotoRepository $photoRepo Photo data access layer
     * @param GeolocationService $geolocation Address geocoding service
     * @param UserRepository $userRepo User data access layer
     */
    public function __construct(
        PDO $pdo,
        ListingRepository $listingRepo,
        ListingPhotoRepository $photoRepo,
        GeolocationService $geolocation,
        UserRepository $userRepo
    ) {
        $this->pdo = $pdo;
        $this->listingRepo = $listingRepo;
        $this->photoRepo = $photoRepo;
        $this->geolocation = $geolocation;
        $this->userRepo = $userRepo;
    }
    
    /**
     * Create a new listing with validation and geocoding.
     * 
     * Required fields: title, description, category_id, price, condition, address
     * Optional fields: brand, model, year, accessories
     * 
     * If address is ambiguous (multiple candidates), returns candidates for user selection.
     * If address is unambiguous (1 candidate), auto-selects and creates listing.
     * 
     * @param array $data Listing data
     * @param int $userId Current user ID (seller_id)
     * @return array|int If ambiguous: ['candidates' => [...]] (HTTP 200)
     *                   If resolved: listing ID (HTTP 201)
     * @throws Exception On validation or geocoding failure
     */
    public function createListing(array $data, int $userId) {
        // Validate required fields
        $errors = $this->validateListingFields($data);
        if (!empty($errors)) {
            throw new Exception(json_encode($errors), 422);
        }
        
        // Validate category exists
        if (!$this->validateCategoryExists((int)$data['category_id'])) {
            throw new Exception(json_encode(['category_id' => 'Invalid category']), 422);
        }
        
        // Geocode address - get candidates
        $address = $data['address'] ?? '';
        if (empty($address)) {
            throw new Exception(json_encode(['address' => 'Address is required']), 422);
        }
        
        $candidates = $this->geolocation->geocodeAddressWithCandidates($address);
        if ($candidates === null || empty($candidates)) {
            throw new Exception('Address not found. Please try again with exact address.', 400);
        }
        
        // If multiple candidates: return them for user selection
        if (count($candidates) > 1) {
            return [
                'candidates' => $candidates,
                'listing_candidate' => null
            ];
        }
        
        // If single candidate: auto-select and create listing
        $selectedCandidate = $candidates[0];
        
        // Prepare listing data for storage
        $listingData = [
            'seller_id' => $userId,
            'category_id' => (int)$data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'price' => (float)$data['price'],
            'condition' => $data['condition'],
            'latitude' => $selectedCandidate['lat'],
            'longitude' => $selectedCandidate['lng'],
            'location_address' => $selectedCandidate['address'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Add optional fields if provided
        if (!empty($data['brand'])) {
            $listingData['brand'] = $data['brand'];
        }
        if (!empty($data['model'])) {
            $listingData['model'] = $data['model'];
        }
        if (!empty($data['year'])) {
            $listingData['year'] = (int)$data['year'];
        }
        if (!empty($data['accessories'])) {
            $listingData['accessories'] = is_array($data['accessories']) 
                ? json_encode($data['accessories']) 
                : $data['accessories'];
        }
        
        // Create listing record
        return $this->listingRepo->create($listingData);
    }
    
    /**
     * Update an existing listing with authorization check.
     * 
     * Only the listing owner (seller) can modify their listing.
     * 
     * @param int $listingId Listing to update
     * @param array $data Updated data
     * @param int $userId Current user ID
     * @return bool True if update successful
     * @throws Exception On validation, authorization, or not found
     */
    public function updateListing(int $listingId, array $data, int $userId): bool {
        // Verify listing exists and user is owner
        $listing = $this->listingRepo->find($listingId);
        if (!$listing) {
            throw new Exception('Listing not found', 404);
        }
        
        if ($listing['seller_id'] != $userId) {
            throw new Exception('Forbidden - you do not own this listing', 403);
        }
        
        // Validate updateable fields
        $updateData = [];
        
        if (isset($data['title'])) {
            $error = $this->validateTitle($data['title']);
            if ($error) {
                throw new Exception(json_encode(['title' => $error]), 422);
            }
            $updateData['title'] = $data['title'];
        }
        
        if (isset($data['description'])) {
            $error = $this->validateDescription($data['description']);
            if ($error) {
                throw new Exception(json_encode(['description' => $error]), 422);
            }
            $updateData['description'] = $data['description'];
        }
        
        if (isset($data['price'])) {
            $error = $this->validatePrice($data['price']);
            if ($error) {
                throw new Exception(json_encode(['price' => $error]), 422);
            }
            $updateData['price'] = (float)$data['price'];
        }
        
        if (isset($data['condition'])) {
            $error = $this->validateCondition($data['condition']);
            if ($error) {
                throw new Exception(json_encode(['condition' => $error]), 422);
            }
            $updateData['condition'] = $data['condition'];
        }
        
        if (isset($data['address'])) {
            $coordinates = $this->geolocation->geocodeAddress($data['address']);
            if (!$coordinates) {
                throw new Exception('Address geocoding failed', 400);
            }
            $updateData['latitude'] = $coordinates['lat'];
            $updateData['longitude'] = $coordinates['lng'];
            $updateData['location_address'] = $this->formatAddress($data['address']);
        }
        
        // Add optional fields
        if (isset($data['brand'])) {
            $updateData['brand'] = $data['brand'];
        }
        if (isset($data['model'])) {
            $updateData['model'] = $data['model'];
        }
        if (isset($data['year'])) {
            $updateData['year'] = $data['year'];
        }
        if (isset($data['accessories'])) {
            $updateData['accessories'] = is_array($data['accessories'])
                ? json_encode($data['accessories'])
                : $data['accessories'];
        }
        
        // Set updated_at timestamp
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        // Perform update
        return $this->listingRepo->update($listingId, $updateData);
    }
    
    /**
     * Soft delete a listing with authorization check.
     * 
     * Only the listing owner can delete their own listing.
     * 
     * @param int $listingId Listing to delete
     * @param int $userId Current user ID
     * @return bool True if delete successful
     * @throws Exception On authorization failure or not found
     */
    public function deleteListing(int $listingId, int $userId): bool {
        // Verify listing exists and user is owner
        $listing = $this->listingRepo->find($listingId);
        if (!$listing) {
            throw new Exception('Listing not found', 404);
        }
        
        if ($listing['seller_id'] != $userId) {
            throw new Exception('Forbidden - you do not own this listing', 403);
        }
        
        // Soft delete the listing
        return $this->listingRepo->delete($listingId);
    }
    
    /**
     * Get listing by ID with full details.
     * 
     * Includes seller information and photo details.
     * Increments view count.
     * Filters soft-deleted listings.
     * 
     * @param int $listingId Listing ID to retrieve
     * @return array|null Listing data with photos or null if not found
     */
    public function getListingById(int $listingId): ?array {
        // Get listing with photos
        $data = $this->listingRepo->findWithPhotos($listingId);
        if (!$data) {
            return null;
        }
        
        // Increment view count
        $this->listingRepo->incrementViewCount($listingId);
        
        return $data;
    }
    
    /**
     * List all active listings with optional filtering and pagination.
     * 
     * Supports filtering by:
     * - category_id
     * - status
     * - price_min / price_max
     * 
     * @param array $filters Optional filter criteria
     * @param int $limit Number of results per page (default 20)
     * @param int $offset Number of results to skip (default 0)
     * @return array Array with 'listings' and 'total' for pagination
     */
    public function listAllListings(array $filters = [], int $limit = 20, int $offset = 0): array {
        // Get paginated listings
        $listings = $this->listingRepo->findAll($filters, $limit, $offset);
        
        // Get total count for pagination metadata
        $total = $this->listingRepo->countAll($filters);
        
        return [
            'listings' => $listings,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Validate all listing fields against rules.
     * 
     * @param array $data Listing data to validate
     * @return array Array of validation errors (empty if valid)
     */
    private function validateListingFields(array $data): array {
        $errors = [];
        
        // Validate title
        $titleError = $this->validateTitle($data['title'] ?? '');
        if ($titleError) {
            $errors['title'] = $titleError;
        }
        
        // Validate description
        $descError = $this->validateDescription($data['description'] ?? '');
        if ($descError) {
            $errors['description'] = $descError;
        }
        
        // Validate category_id
        if (empty($data['category_id'])) {
            $errors['category_id'] = 'Category is required';
        } elseif (!is_numeric($data['category_id'])) {
            $errors['category_id'] = 'Category must be a number';
        }
        
        // Validate price
        $priceError = $this->validatePrice($data['price'] ?? '');
        if ($priceError) {
            $errors['price'] = $priceError;
        }
        
        // Validate condition
        $conditionError = $this->validateCondition($data['condition'] ?? '');
        if ($conditionError) {
            $errors['condition'] = $conditionError;
        }
        
        return $errors;
    }
    
    /**
     * Validate title field.
     * Required, string, 10-255 chars.
     * 
     * @param string $title Title to validate
     * @return string|null Error message or null if valid
     */
    private function validateTitle(string $title): ?string {
        if (empty($title)) {
            return 'Title is required';
        }
        if (strlen($title) < 10) {
            return 'Title must be at least 10 characters';
        }
        if (strlen($title) > 255) {
            return 'Title must not exceed 255 characters';
        }
        return null;
    }
    
    /**
     * Validate description field.
     * Required, string, 20-5000 chars.
     * 
     * @param string $description Description to validate
     * @return string|null Error message or null if valid
     */
    private function validateDescription(string $description): ?string {
        if (empty($description)) {
            return 'Description is required';
        }
        if (strlen($description) < 20) {
            return 'Description must be at least 20 characters';
        }
        if (strlen($description) > 5000) {
            return 'Description must not exceed 5000 characters';
        }
        return null;
    }
    
    /**
     * Validate price field.
     * Required, numeric, 0.01-999999.99.
     * 
     * @param mixed $price Price to validate
     * @return string|null Error message or null if valid
     */
    private function validatePrice($price): ?string {
        if ($price === '' || $price === null) {
            return 'Price is required';
        }
        if (!is_numeric($price)) {
            return 'Price must be a valid number';
        }
        $price = (float)$price;
        if ($price < 0.01) {
            return 'Price must be at least 0.01';
        }
        if ($price > 999999.99) {
            return 'Price must not exceed 999999.99';
        }
        return null;
    }
    
    /**
     * Validate condition field.
     * Required, enum ['Excellent', 'Good', 'Fair', 'Poor'].
     * 
     * @param string $condition Condition to validate
     * @return string|null Error message or null if valid
     */
    private function validateCondition(string $condition): ?string {
        $validConditions = ['Excellent', 'Good', 'Fair', 'Poor'];
        if (empty($condition)) {
            return 'Condition is required';
        }
        if (!in_array($condition, $validConditions)) {
            return 'Condition must be one of: ' . implode(', ', $validConditions);
        }
        return null;
    }
    
    /**
     * Check if category exists in database.
     * 
     * @param int $categoryId Category ID to check
     * @return bool True if category exists
     */
    private function validateCategoryExists(int $categoryId): bool {
        $sql = "SELECT id FROM categories WHERE id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
    }
    
    /**
     * Search and filter listings by multiple criteria.
     * 
     * Supported filters:
     * - category_id: int
     * - condition: string
     * - price_min: float
     * - price_max: float
     * - keyword: string
     * 
     * @param array $filters Filter criteria
     * @param int $limit Results per page (default 20, max 100)
     * @param int $offset Results to skip (default 0)
     * @return array Array with 'listings', 'total', 'limit', 'offset' for pagination
     * @throws Exception On validation errors
     */
    public function searchListings(array $filters = [], int $limit = 20, int $offset = 0): array {
        // Validate limit and offset
        $limit = min((int)$limit, 100); // Cap at 100
        if ($limit < 1) {
            $limit = 20;
        }
        
        $offset = max((int)$offset, 0);
        
        // Validate price range if provided
        if (isset($filters['price_min']) && isset($filters['price_max'])) {
            if ($filters['price_min'] > $filters['price_max']) {
                throw new Exception(json_encode(['price' => 'Minimum price must not exceed maximum price']), 422);
            }
        }
        
        // Get paginated results
        $listings = $this->listingRepo->filterCombined($filters, $limit, $offset);
        
        // Get total count for pagination metadata
        $total = $this->countFilteredListings($filters);
        
        return [
            'listings' => $listings,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Get listing details with full information.
     * 
     * @param int $listingId Listing ID
     * @return array|null Listing with photos or null if not found
     */
    public function getListingDetails(int $listingId): ?array {
        $listing = $this->listingRepo->findWithPhotos($listingId);
        if (!$listing) {
            return null;
        }
        
        // Increment view count
        $this->listingRepo->incrementViewCount($listingId);
        
        return $listing;
    }
    
    /**
     * Get available filter options for frontend UI.
     * 
     * @return array Array with 'categories', 'conditions', 'priceRange'
     */
    public function getFilterOptions(): array {
        // Get categories
        $categories = $this->getCategories();
        
        // Get condition enum
        $conditions = ['Excellent', 'Good', 'Fair', 'Poor'];
        
        // Get price range
        $priceRange = $this->getPriceRange();
        
        return [
            'categories' => $categories,
            'conditions' => $conditions,
            'priceRange' => $priceRange
        ];
    }
    
    /**
     * Count listings matching given filters.
     * Helper for pagination metadata.
     * 
     * @param array $filters Filter criteria
     * @return int Total count
     */
    private function countFilteredListings(array $filters = []): int {
        // Build same WHERE clause as filterCombined
        $sql = "SELECT COUNT(*) as total FROM listings l 
                LEFT JOIN users u ON l.seller_id = u.id
                WHERE 1=1 AND l.deleted_at IS NULL AND u.deleted_at IS NULL";
        
        $params = [];
        
        if (isset($filters['category_id'])) {
            $sql .= " AND l.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (isset($filters['condition'])) {
            $sql .= " AND l.condition = ?";
            $params[] = $filters['condition'];
        }
        
        if (isset($filters['price_min'])) {
            $sql .= " AND l.price >= ?";
            $params[] = $filters['price_min'];
        }
        
        if (isset($filters['price_max'])) {
            $sql .= " AND l.price <= ?";
            $params[] = $filters['price_max'];
        }
        
        if (isset($filters['keyword']) && !empty($filters['keyword'])) {
            $keyword = '%' . $filters['keyword'] . '%';
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)";
            $params[] = $keyword;
            $params[] = $keyword;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }
    
    /**
     * Get all active categories for filter UI.
     * 
     * @return array Array of categories with id and name
     */
    private function getCategories(): array {
        $sql = "SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get min and max price range for filter UI.
     * 
     * @return array Array with 'min' and 'max' price
     */
    private function getPriceRange(): array {
        $sql = "SELECT MIN(price) as min, MAX(price) as max FROM listings WHERE deleted_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'min' => $result['min'] ? (float)$result['min'] : 0,
            'max' => $result['max'] ? (float)$result['max'] : 999999.99
        ];
    }
    
    /**
     * Format address components into readable string.
      * 
      * @param array $address Address components
      * @return string Formatted address string
      */
     private function formatAddress(array $address): string {
         $parts = [];
         if (!empty($address['street'])) {
             $parts[] = $address['street'];
         }
         if (!empty($address['city'])) {
             $parts[] = $address['city'];
         }
         if (!empty($address['province'])) {
             $parts[] = $address['province'];
         }
         if (!empty($address['postal_code'])) {
             $parts[] = $address['postal_code'];
         }
         if (!empty($address['country'])) {
             $parts[] = $address['country'];
         }
         return implode(', ', $parts);
     }
    
    /**
     * Search listings within a radius of given coordinates, sorted by distance.
     * 
     * Implements distance-based filtering and sorting using Haversine formula.
     * If user location (lat/lng) not provided, uses user's stored profile location.
     * 
     * @param float|null $userLat User's search latitude (nullable)
     * @param float|null $userLng User's search longitude (nullable)
     * @param int $radiusMeters Search radius in meters (0-50000, validated)
     * @param array $filters Optional filters: keyword, category_id, condition, price_min, price_max
     * @param int $limit Results per page (default 20)
     * @param int $offset Results to skip (default 0)
     * @return array Array with keys: listings, total, limit, offset, search_radius_meters, search_center
     * @throws InvalidArgumentException On validation errors
     * @throws Exception On database or business logic errors
     */
    public function searchWithDistance(
        ?float $userLat,
        ?float $userLng,
        int $radiusMeters,
        array $filters = [],
        int $limit = 20,
        int $offset = 0
    ): array {
        // Step 1: Validate radius
        if ($radiusMeters < 0 || $radiusMeters > 50000) {
            throw new InvalidArgumentException('Radius must be between 0 and 50000 meters');
        }
        
        // Step 2: Get user location (fallback if not provided)
        if ($userLat === null || $userLng === null) {
            $fallbackLocation = $this->getUserLocation();
            $userLat = $fallbackLocation['latitude'];
            $userLng = $fallbackLocation['longitude'];
        }
        
        // Step 3: Validate coordinates
        if ($userLat < -90 || $userLat > 90) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90');
        }
        if ($userLng < -180 || $userLng > 180) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180');
        }
        
        // Step 4: Get candidates (up to 1000)
        $candidates = $this->listingRepo->searchCandidatesByFilters($filters, 1000);
        
        // Step 5: Apply distance filtering and calculation
        $filtered = [];
        foreach ($candidates as $listing) {
            // Skip listings with missing coordinates
            if (empty($listing['latitude']) || empty($listing['longitude'])) {
                continue;
            }
            
            try {
                // Calculate distance using Haversine formula
                $distanceKm = GeometryService::haversineDistance(
                    $userLat,
                    $userLng,
                    (float)$listing['latitude'],
                    (float)$listing['longitude']
                );
                
                // Convert km to meters
                $distanceMeters = $distanceKm * 1000;
                
                // Add distance to listing data
                $listing['distance_meters'] = $distanceMeters;
                
                // Only include listings within radius
                if ($distanceMeters <= $radiusMeters) {
                    $filtered[] = $listing;
                }
            } catch (Exception $e) {
                // Skip listing if Haversine calculation fails
                continue;
            }
        }
        
        // Step 6: Sort by distance (nearest first)
        usort($filtered, fn($a, $b) => $a['distance_meters'] <=> $b['distance_meters']);
        
        // Step 7: Apply pagination
        $paginated = array_slice($filtered, $offset, $limit);
        
        // Step 8: Return result with metadata
        return [
            'listings' => $paginated,
            'total' => count($filtered),
            'limit' => $limit,
            'offset' => $offset,
            'search_radius_meters' => $radiusMeters,
            'search_center' => [
                'latitude' => $userLat,
                'longitude' => $userLng
            ]
        ];
    }
    
    /**
     * Get user's location from profile, or throw exception if not set.
     * 
     * Uses session user ID to fetch stored location coordinates.
     * Throws exception if user not authenticated or location not configured.
     * 
     * @return array Array with keys: latitude, longitude
     * @throws InvalidArgumentException On auth failure or missing location
     */
    private function getUserLocation(): array {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            throw new InvalidArgumentException('Authentication required for default location search');
        }
        
        // Fetch user record
        $user = $this->userRepo->find($userId);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        
        // Check user location is set
        if (empty($user['latitude']) || empty($user['longitude'])) {
            throw new InvalidArgumentException('User location not set in profile');
        }
        
        return [
            'latitude' => (float)$user['latitude'],
            'longitude' => (float)$user['longitude']
        ];
    }
}

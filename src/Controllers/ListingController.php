<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;
use ReuseIT\Services\PhotoUploadService;
use ReuseIT\Services\ListingService;
use ReuseIT\Repositories\ListingPhotoRepository;
use ReuseIT\Repositories\ListingRepository;
use InvalidArgumentException;
use RuntimeException;
use PDO;

/**
 * ListingController
 *
 * Handles listing HTTP endpoints including CRUD operations and photo uploads.
 *
 * Protected endpoints (require authentication):
 * - POST /api/listings - Create listing
 * - PATCH /api/listings/:id - Update listing (owner only)
 * - DELETE /api/listings/:id - Delete listing (owner only)
 * - POST /api/listings/:id/photos - Upload photos for listing
 * - POST /api/users/:id/avatar - Upload user avatar
 *
 * Public endpoints:
 * - GET /api/listings - List all active listings (with pagination)
 * - GET /api/listings/:id - Get listing details
 */
class ListingController
{
    private PhotoUploadService $photoUploadService;
    private ListingPhotoRepository $photoRepository;
    private ListingService $listingService;
    private ListingRepository $listingRepository;
    private Response $response;
    private PDO $pdo;

    /**
     * Initialize controller with service dependencies.
     *
     * @param PhotoUploadService $photoUploadService Service for photo handling
     * @param ListingPhotoRepository $photoRepository Repository for photo persistence
     * @param PDO $pdo Database connection
     * @param Response $response Response envelope helper
     * @param ListingService $listingService Service for listing business logic
     * @param ListingRepository $listingRepository Repository for listing data access
     */
    public function __construct(
        PhotoUploadService $photoUploadService,
        ListingPhotoRepository $photoRepository,
        PDO $pdo,
        Response $response = null,
        ListingService $listingService = null,
        ListingRepository $listingRepository = null
    ) {
        $this->photoUploadService = $photoUploadService;
        $this->photoRepository = $photoRepository;
        $this->pdo = $pdo;
        $this->response = $response ?? new Response();
        $this->listingService = $listingService;
        $this->listingRepository = $listingRepository;
    }

    /**
     * POST /api/listings
     *
     * Create a new listing.
     * Only authenticated users can create listings.
     *
     * Request body:
     * {
     *   "title": "iPhone 12 Pro",
     *   "description": "Perfect condition...",
     *   "category_id": 1,
     *   "price": 650.00,
     *   "condition": "Excellent",
     *   "address": {
     *     "street": "123 Main St",
     *     "city": "Toronto",
     *     "province": "ON",
     *     "postal_code": "M5A 1A1",
     *     "country": "Canada"
     *   },
     *   "brand": "Apple",
     *   "model": "iPhone 12 Pro",
     *   "year": 2021,
     *   "accessories": ["charger", "box"]
     * }
     *
     * @param array $get Query parameters
     * @param array $post POST parameters (JSON body)
     * @param array $files $_FILES array
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function create(array $get, array $post, array $files, array $params): string
    {
        try {
            // Verify authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];

            // Get JSON body from raw input
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true) ?? $post;

            // Ensure ListingService is initialized
            if (!$this->listingService) {
                return Response::error('Service not initialized', 500);
            }

            // Create listing via service
            $listingId = $this->listingService->createListing($data, $userId);

            return Response::success(['listing_id' => $listingId], 201);

        } catch (\Exception $e) {
            // Handle validation errors (422 status embedded in exception)
            if ($e->getCode() === 422) {
                $errors = json_decode($e->getMessage(), true);
                return Response::validationErrors($errors, 422);
            }
            
            // Handle geocoding failures (400)
            if ($e->getCode() === 400) {
                return Response::error($e->getMessage(), 400);
            }

            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/listings/:id
     *
     * Get listing details by ID.
     * Public endpoint - no authentication required.
     *
     * Response:
     * {
     *   "id": 123,
     *   "seller_id": 42,
     *   "title": "iPhone 12 Pro",
     *   "price": 650.00,
     *   "condition": "Excellent",
     *   "latitude": 43.6532,
     *   "longitude": -79.3832,
     *   "seller_first_name": "John",
     *   "seller_last_name": "Doe",
     *   "photos": [...]
     * }
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files $_FILES array
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function show(array $get, array $post, array $files, array $params): string
    {
        try {
            $listingId = (int)($params['id'] ?? 0);

            if ($listingId <= 0) {
                return Response::error('Invalid listing ID', 400);
            }

            // Ensure ListingService is initialized
            if (!$this->listingService) {
                return Response::error('Service not initialized', 500);
            }

            // Get listing with photos
            $data = $this->listingService->getListingById($listingId);
            if (!$data) {
                return Response::error('Listing not found', 404);
            }

            return Response::success($data, 200);

        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/listings
     *
     * List all active listings with optional filtering and pagination.
     * Public endpoint - no authentication required.
     *
     * Query parameters:
     * - category_id: int (optional)
     * - status: string (optional)
     * - price_min: float (optional)
     * - price_max: float (optional)
     * - limit: int (default 20)
     * - offset: int (default 0)
     *
     * Response:
     * {
     *   "listings": [...],
     *   "total": 150,
     *   "limit": 20,
     *   "offset": 0
     * }
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files $_FILES array
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function list(array $get, array $post, array $files, array $params): string
    {
        try {
            // Parse pagination parameters
            $limit = isset($get['limit']) ? (int)$get['limit'] : 20;
            $offset = isset($get['offset']) ? (int)$get['offset'] : 0;

            // Validate pagination
            if ($limit < 1 || $limit > 100) {
                $limit = 20;
            }
            if ($offset < 0) {
                $offset = 0;
            }

            // Build filter array
            $filters = [];
            if (isset($get['category_id'])) {
                $filters['category_id'] = (int)$get['category_id'];
            }
            if (isset($get['status'])) {
                $filters['status'] = $get['status'];
            }
            if (isset($get['price_min'])) {
                $filters['price_min'] = (float)$get['price_min'];
            }
            if (isset($get['price_max'])) {
                $filters['price_max'] = (float)$get['price_max'];
            }

            // Ensure ListingService is initialized
            if (!$this->listingService) {
                return Response::error('Service not initialized', 500);
            }

            // Get paginated listings
            $result = $this->listingService->listAllListings($filters, $limit, $offset);

            return Response::success($result, 200);

        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/listings/:id
     *
     * Update an existing listing.
     * Only the listing owner can update their listings.
     * All fields are optional; only provided fields are updated.
     *
     * @param array $get Query parameters
     * @param array $post POST parameters (JSON body)
     * @param array $files $_FILES array
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function update(array $get, array $post, array $files, array $params): string
    {
        try {
            // Verify authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $listingId = (int)($params['id'] ?? 0);

            if ($listingId <= 0) {
                return Response::error('Invalid listing ID', 400);
            }

            // Get JSON body from raw input
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true) ?? $post;

            // Ensure ListingService is initialized
            if (!$this->listingService) {
                return Response::error('Service not initialized', 500);
            }

            // Update listing via service
            $this->listingService->updateListing($listingId, $data, $userId);

            // Retrieve updated listing
            $updatedListing = $this->listingService->getListingById($listingId);

            return Response::success($updatedListing, 200);

        } catch (\Exception $e) {
            // Handle authorization failures (403)
            if (strpos($e->getMessage(), 'Forbidden') !== false) {
                return Response::error($e->getMessage(), 403);
            }

            // Handle not found (404)
            if ($e->getCode() === 404) {
                return Response::error($e->getMessage(), 404);
            }

            // Handle validation errors (422)
            if ($e->getCode() === 422) {
                $errors = json_decode($e->getMessage(), true);
                return Response::validationErrors($errors, 422);
            }

            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/listings/:id
     *
     * Delete (soft delete) a listing.
     * Only the listing owner can delete their own listings.
     *
     * Response:
     * {
     *   "status": "success"
     * }
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files $_FILES array
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function delete(array $get, array $post, array $files, array $params): string
    {
        try {
            // Verify authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $listingId = (int)($params['id'] ?? 0);

            if ($listingId <= 0) {
                return Response::error('Invalid listing ID', 400);
            }

            // Ensure ListingService is initialized
            if (!$this->listingService) {
                return Response::error('Service not initialized', 500);
            }

            // Delete listing via service
            $this->listingService->deleteListing($listingId, $userId);

            return Response::success(['status' => 'deleted'], 200);

        } catch (\Exception $e) {
            // Handle authorization failures (403)
            if (strpos($e->getMessage(), 'Forbidden') !== false) {
                return Response::error($e->getMessage(), 403);
            }

            // Handle not found (404)
            if ($e->getCode() === 404) {
                return Response::error($e->getMessage(), 404);
            }

            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/listings/:id/photos
     *
     * Upload photos for a listing.
     * Only the listing owner can upload photos.
     * Max 10 photos per listing (409 if exceeded).
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files $_FILES array containing 'photos' multipart upload
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function uploadPhotos(array $get, array $post, array $files, array $params): string
    {
        try {
            // Verify authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $listingId = (int)($params['id'] ?? 0);

            if ($listingId <= 0) {
                return Response::error('Invalid listing ID', 400);
            }

            // Verify listing exists and user owns it
            $stmt = $this->pdo->prepare("
                SELECT id, seller_id FROM listings WHERE id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$listingId]);
            $listing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$listing) {
                return Response::error('Listing not found', 404);
            }

            if ((int)$listing['seller_id'] !== $userId) {
                return Response::error('Forbidden - you do not own this listing', 403);
            }

            // Get photos from $_FILES
            $uploadedFiles = $_FILES['photos'] ?? null;
            if (!$uploadedFiles || !is_array($uploadedFiles['tmp_name'])) {
                return Response::error('No files uploaded', 400);
            }

            // Normalize $_FILES structure for multiple file uploads
            $files = $this->normalizeMultipleFiles($uploadedFiles);
            
            if (count($files) > 10) {
                return Response::error('Maximum 10 photos per upload', 409);
            }

            // Check current photo count
            $currentCount = $this->photoRepository->countByListingId($listingId);
            if ($currentCount + count($files) > 10) {
                return Response::error('Maximum 10 photos per listing', 409);
            }

            // Upload each photo
            $uploadedPhotos = [];
            $displayOrder = $currentCount;

            foreach ($files as $file) {
                try {
                    $photoUrl = $this->photoUploadService->reencodeAndStore(
                        $file,
                        $userId,
                        $listingId,
                        $displayOrder
                    );
                    $uploadedPhotos[] = ['photo_url' => $photoUrl];
                    $displayOrder++;
                } catch (InvalidArgumentException $e) {
                    return Response::error('Validation failed: ' . $e->getMessage(), 422);
                } catch (RuntimeException $e) {
                    return Response::error('Upload failed: ' . $e->getMessage(), 500);
                }
            }

            // Update listing photo count (denormalization for performance)
            $this->updateListingPhotoCount($listingId);

            return Response::success([
                'photos_uploaded' => count($uploadedPhotos),
                'photos' => $uploadedPhotos
            ], 201);

        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * POST /api/users/:id/avatar
     *
     * Upload user avatar image.
     * Only the authenticated user can upload their own avatar.
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files $_FILES array containing 'avatar' file
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function uploadAvatar(array $get, array $post, array $files, array $params): string
    {
        try {
            // Verify authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $targetUserId = (int)($params['id'] ?? 0);

            if ($targetUserId <= 0) {
                return Response::error('Invalid user ID', 400);
            }

            // Authorization check: users can only upload their own avatar
            if ($userId !== $targetUserId) {
                return Response::error('Forbidden', 403);
            }

            // Get avatar file
            $avatarFile = $_FILES['avatar'] ?? null;
            if (!$avatarFile || !is_array($avatarFile)) {
                return Response::error('No file uploaded', 400);
            }

            // Validate avatar file
            $validation = $this->photoUploadService->validatePhoto($avatarFile);
            if (!$validation['is_valid']) {
                return Response::error('Validation failed: ' . implode('; ', $validation['errors']), 422);
            }

            try {
                // Upload avatar (no listing_id or display_order for user avatar)
                $avatarUrl = $this->photoUploadService->reencodeAndStore(
                    $avatarFile,
                    $userId,
                    null,
                    null
                );

                // Update user's avatar_url in database
                $this->updateUserAvatar($userId, $avatarUrl);

                return Response::success(['avatar_url' => $avatarUrl], 201);

            } catch (RuntimeException $e) {
                return Response::error('Upload failed: ' . $e->getMessage(), 500);
            }

        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * Normalize $_FILES structure for multiple file uploads.
     *
     * $_FILES['photos'] with multiple files comes as:
     * [
     *   'name' => ['file1.jpg', 'file2.jpg'],
     *   'tmp_name' => ['/tmp/php123', '/tmp/php456'],
     *   'type' => ['image/jpeg', 'image/jpeg'],
     *   'size' => [12345, 67890],
     *   'error' => [0, 0]
     * ]
     *
     * This method normalizes to:
     * [
     *   ['name' => 'file1.jpg', 'tmp_name' => '/tmp/php123', ...],
     *   ['name' => 'file2.jpg', 'tmp_name' => '/tmp/php456', ...]
     * ]
     *
     * @param array $files $_FILES array element
     * @return array Array of normalized file arrays
     */
    private function normalizeMultipleFiles(array $files): array
    {
        if (empty($files['name'])) {
            return [];
        }

        $normalized = [];
        $count = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            if (is_array($files['name'])) {
                // Multiple files uploaded
                $normalized[] = [
                    'name' => $files['name'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'type' => $files['type'][$i] ?? '',
                    'size' => $files['size'][$i] ?? 0,
                    'error' => $files['error'][$i] ?? 4
                ];
            } else {
                // Single file uploaded (shouldn't happen with 'photos' array, but handle it)
                $normalized[] = $files;
            }
        }

        return $normalized;
    }

    /**
     * Update listing photo count for denormalization.
     *
     * @param int $listingId
     * @return void
     */
    private function updateListingPhotoCount(int $listingId): void
    {
        $count = $this->photoRepository->countByListingId($listingId);
        $stmt = $this->pdo->prepare("
            UPDATE listings SET photo_count = ? WHERE id = ?
        ");
        $stmt->execute([$count, $listingId]);
    }

     /**
      * GET /api/listings/search
      *
      * Search and filter listings with distance-based discovery.
      * PUBLIC endpoint (with optional authentication for default location).
      *
      * Query parameters:
      * - lat: float (optional, -90 to 90) - search center latitude
      * - lng: float (optional, -180 to 180) - search center longitude
      * - radius: int (optional, 0-50000 meters, default 10000) - search radius
      * - keyword: string (optional, searches title and description)
      * - category_id: int (optional)
      * - condition: string (optional, enum: Excellent, Good, Fair, Poor)
      * - price_min: float (optional)
      * - price_max: float (optional)
      * - limit: int (optional, default 20, max 100)
      * - offset: int (optional, default 0)
      *
      * If lat/lng not provided: uses authenticated user's stored profile location (fallback).
      * Without lat/lng and not authenticated: returns 401.
      *
      * @param array $get Query parameters
      * @param array $post POST parameters
      * @param array $files $_FILES array
      * @param array $params URI parameters
      * @return string JSON response
      */
     public function search(array $get, array $post, array $files, array $params): string
     {
         try {
             // Extract and parse spatial parameters
             $lat = isset($get['lat']) ? (float)$get['lat'] : null;
             $lng = isset($get['lng']) ? (float)$get['lng'] : null;
             $radius = isset($get['radius']) ? (int)$get['radius'] : 10000; // Default 10km in meters
             
             // Extract filter parameters
             $keyword = $get['keyword'] ?? null;
             $categoryId = isset($get['category_id']) ? (int)$get['category_id'] : null;
             $condition = $get['condition'] ?? null;
             $priceMin = isset($get['price_min']) ? (float)$get['price_min'] : null;
             $priceMax = isset($get['price_max']) ? (float)$get['price_max'] : null;
             
             // Extract pagination parameters
             $limit = isset($get['limit']) ? min((int)$get['limit'], 100) : 20; // Max 100 per page
             $offset = isset($get['offset']) ? (int)$get['offset'] : 0;
             
             // Validation: Radius must be 0-50000 meters
             if ($radius < 0 || $radius > 50000) {
                 return Response::error('Radius must be integer between 0 and 50000 meters', 400);
             }
             
             // Validation: Latitude bounds (-90 to 90)
             if ($lat !== null && ($lat < -90 || $lat > 90)) {
                 return Response::error('Latitude must be between -90 and 90', 400);
             }
             
             // Validation: Longitude bounds (-180 to 180)
             if ($lng !== null && ($lng < -180 || $lng > 180)) {
                 return Response::error('Longitude must be between -180 and 180', 400);
             }
             
             // Validation: Keyword length limit (1-100 chars)
             if ($keyword !== null && strlen($keyword) > 100) {
                 return Response::error('Keyword must be 1-100 characters', 400);
             }
             
             // If no lat/lng provided and user not authenticated: return 401
             if (($lat === null || $lng === null) && empty($_SESSION['user_id'])) {
                 return Response::error('Authentication required when using default location', 401);
             }
             
             // Build filters array - only include provided filters
             $filters = [];
             if ($keyword !== null && $keyword !== '') {
                 $filters['keyword'] = $keyword;
             }
             if ($categoryId !== null) {
                 $filters['category_id'] = $categoryId;
             }
             if ($condition !== null && $condition !== '') {
                 $filters['condition'] = $condition;
             }
             if ($priceMin !== null) {
                 $filters['price_min'] = $priceMin;
             }
             if ($priceMax !== null) {
                 $filters['price_max'] = $priceMax;
             }
             
             // Ensure ListingService is initialized
             if (!$this->listingService) {
                 return Response::error('Service not initialized', 500);
             }
             
             // Call service method with error handling
             try {
                 $result = $this->listingService->searchWithDistance(
                     $lat,
                     $lng,
                     $radius,
                     $filters,
                     $limit,
                     $offset
                 );
             } catch (\InvalidArgumentException $e) {
                 return Response::error($e->getMessage(), 400);
             } catch (\Exception $e) {
                 return Response::error('Search failed: ' . $e->getMessage(), 500);
             }
             
             // Format response with distance information (Task 2)
             // Add distance_km field for UI display on each listing
             $formattedListings = [];
             foreach ($result['listings'] as $listing) {
                 // Add human-readable distance_km field (round to 1 decimal)
                 if (isset($listing['distance_meters'])) {
                     $listing['distance_km'] = round($listing['distance_meters'] / 1000, 1);
                 }
                 $formattedListings[] = $listing;
             }
             
             $responseData = [
                 'listings' => $formattedListings,
                 'search' => [
                     'total' => $result['total'],
                     'limit' => $result['limit'],
                     'offset' => $result['offset'],
                     'radius_meters' => $result['search_radius_meters'],
                     'center' => [
                         'latitude' => $result['search_center']['latitude'],
                         'longitude' => $result['search_center']['longitude']
                     ]
                 ]
             ];
             
             return $this->response->success($responseData, 200);
         } catch (\Exception $e) {
             $code = (int)$e->getCode() ?: 500;
             return $this->response->error($e->getMessage(), $code);
         }
     }

    /**
     * GET /api/listings/filter-options
     *
     * Get available filter values for UI dropdowns and sliders.
     * PUBLIC endpoint - no authentication required.
     *
     * Response includes:
     * - categories: list of active categories
     * - conditions: enum values for condition filter
     * - priceRange: min/max prices across all listings
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files $_FILES array
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function filterOptions(array $get, array $post, array $files, array $params): string
    {
        try {
            $options = $this->listingService->getFilterOptions();
            return $this->response->success($options, 200);
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage(), 500);
        }
    }

    /**
     * Update user avatar URL in database.
     *
     * @param int $userId
     * @param string $avatarUrl
     * @return void
     */
    private function updateUserAvatar(int $userId, string $avatarUrl): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET avatar_url = ? WHERE id = ?
        ");
        $stmt->execute([$avatarUrl, $userId]);
    }
}

<?php
namespace ReuseIT\Services;

use InvalidArgumentException;
use ReuseIT\Repositories\FavoriteRepository;
use ReuseIT\Repositories\ListingRepository;
use ReuseIT\Repositories\UserRepository;

/**
 * FavoritesService
 *
 * Service layer for favorite management.
 * Encapsulates business logic: toggle favorite (add/remove), retrieve user favorites,
 * and check if a listing is favorited. Enforces authorization and validation.
 */
class FavoritesService {

    private FavoriteRepository $favoriteRepo;
    private ListingRepository $listingRepo;
    private UserRepository $userRepo;

    /**
     * Initialize FavoritesService with dependencies.
     *
     * @param FavoriteRepository $favoriteRepo Favorite data access layer
     * @param ListingRepository $listingRepo Listing data access layer
     * @param UserRepository $userRepo User data access layer
     */
    public function __construct(
        FavoriteRepository $favoriteRepo,
        ListingRepository $listingRepo,
        UserRepository $userRepo
    ) {
        $this->favoriteRepo = $favoriteRepo;
        $this->listingRepo = $listingRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Toggle favorite status for a listing (add or remove).
     *
     * Business logic:
     * 1. Validate user_id and listing_id are valid UUIDs (represented as positive integers)
     * 2. Validate listing exists and is not deleted
     * 3. Validate listing.seller_id != user_id (cannot favorite own listings)
     * 4. Check if favorite already exists:
     *    - If exists and deleted: restore (clear deleted_at) or treat as idempotent add
     *    - If exists and not deleted: delete (soft-delete by setting deleted_at)
     *    - If not exists: create new favorite
     * 5. Return: {"favorited": boolean, "message": string} indicating new state
     *
     * Idempotent behavior: calling twice toggles on then off (no error on "already favorited")
     * Soft delete: uses soft-delete, not hard delete (allows historical tracking)
     *
     * @param int $userId User ID (must be authenticated)
     * @param int $listingId Listing ID to toggle favorite on
     * @return array Array with keys: favorited (bool), message (string)
     * @throws InvalidArgumentException If validation fails
     */
    public function toggleFavorite(int $userId, int $listingId): array {
        // Validate: user_id and listing_id are valid
        $this->validateUUID($userId, 'User ID');
        $this->validateUUID($listingId, 'Listing ID');

        // Validate: listing exists and is not deleted
        $this->validateListingExists($listingId);

        // Get listing to check seller_id
        $listing = $this->listingRepo->find($listingId);

        // Validate: user cannot favorite their own listings
        if ($listing['seller_id'] === $userId) {
            throw new InvalidArgumentException('You cannot favorite your own listings');
        }

        // Check if favorite already exists
        $favorite = $this->favoriteRepo->findByUserAndListing($userId, $listingId);

        if ($favorite) {
            // Favorite exists - delete it (soft-delete)
            $this->favoriteRepo->delete($favorite['id']);
            return [
                'favorited' => false,
                'message' => 'Listing removed from favorites'
            ];
        } else {
            // Favorite doesn't exist - create it
            $this->favoriteRepo->createFavorite($userId, $listingId);
            return [
                'favorited' => true,
                'message' => 'Listing added to favorites'
            ];
        }
    }

    /**
     * Retrieve user's favorite listings with pagination.
     *
     * Business logic:
     * 1. Validate user_id is valid
     * 2. Fetch favorites via FavoriteRepository.findByUserId(user_id, limit, offset)
     * 3. For each favorite: Load listing data via ListingRepository.find(listing_id)
     * 4. Return: Array of favorite objects with enriched listing data
     * 5. Order: created_at DESC (newest first)
     * 6. Soft-delete filtering: Repository already handles; service just consumes
     *
     * @param int $userId User ID
     * @param int $limit Number of results per page (default: 20)
     * @param int $offset Results to skip (default: 0)
     * @return array Array of favorite objects with listing enrichment (id, title, price, category, photo_url, seller info, created_at)
     * @throws InvalidArgumentException If validation fails
     */
    public function getUserFavorites(int $userId, int $limit = 20, int $offset = 0): array {
        // Validate: user_id is valid UUID
        $this->validateUUID($userId, 'User ID');

        // Validate pagination parameters
        if ($limit <= 0 || $limit > 100) {
            throw new InvalidArgumentException('Limit must be between 1 and 100');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be non-negative');
        }

        // Fetch favorites from repository
        $favorites = $this->favoriteRepo->findByUserId($userId, $limit, $offset);

        // Enrich each favorite with listing data
        $enriched = [];
        foreach ($favorites as $favorite) {
            $listingId = $favorite['listing_id'];
            $listing = $this->listingRepo->find($listingId);

            if ($listing) {
                // Build enriched favorite object with listing data
                $enriched[] = [
                    'id' => $favorite['id'],
                    'listing_id' => $listing['id'],
                    'title' => $listing['title'],
                    'price' => (float) $listing['price'],
                    'category_id' => $listing['category_id'],
                    'condition' => $listing['condition'],
                    'photo_url' => $listing['primary_photo_url'] ?? null,
                    'seller_id' => $listing['seller_id'],
                    'seller_name' => $listing['seller_name'] ?? null,
                    'location_address' => $listing['location_address'] ?? null,
                    'latitude' => (float) $listing['latitude'],
                    'longitude' => (float) $listing['longitude'],
                    'created_at' => $favorite['created_at'],
                    'favorited_at' => $favorite['created_at']
                ];
            }
        }

        return $enriched;
    }

    /**
     * Check if a specific listing is favorited by a user.
     *
     * Business logic:
     * 1. Validate user_id and listing_id
     * 2. Check if favorite exists and not deleted
     * 3. Return: boolean indicating favorite status
     *
     * @param int $userId User ID
     * @param int $listingId Listing ID to check
     * @return bool True if listing is favorited by user, false otherwise
     * @throws InvalidArgumentException If validation fails
     */
    public function isFavorited(int $userId, int $listingId): bool {
        // Validate: user_id and listing_id are valid
        $this->validateUUID($userId, 'User ID');
        $this->validateUUID($listingId, 'Listing ID');

        // Check if favorite exists
        $favorite = $this->favoriteRepo->findByUserAndListing($userId, $listingId);

        return $favorite !== null;
    }

    /**
     * Get count of user's active favorites.
     *
     * @param int $userId User ID
     * @return int Total active favorites for user
     * @throws InvalidArgumentException If validation fails
     */
    public function getCountByUser(int $userId): int {
        // Validate: user_id is valid UUID
        $this->validateUUID($userId, 'User ID');

        return $this->favoriteRepo->countByUser($userId);
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
}

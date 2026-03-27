<?php
namespace ReuseIT\Services;

use ReuseIT\Repositories\UserRepository;
use Exception;

/**
 * UserService
 * 
 * Handles user profile operations: viewing and editing profiles.
 * Provides business logic layer between controllers and repository.
 * 
 * Whitelist pattern: Only allows editing specific profile fields.
 * Integrates geocoding for address updates: if address fields are provided
 * without explicit coordinates, automatically geocodes to get lat/lng.
 * Statistics fields (active_listings_count, etc.) are deferred to future phases.
 */
class UserService {
    private UserRepository $userRepo;
    private GeolocationService $geoService;
    
    /**
     * Initialize service with user repository and geocoding dependencies.
     * 
     * @param UserRepository $userRepo User repository for database access
     * @param GeolocationService $geoService Address geocoding service
     */
    public function __construct(UserRepository $userRepo, GeolocationService $geoService) {
        $this->userRepo = $userRepo;
        $this->geoService = $geoService;
    }
    
    /**
     * Get complete user profile by user ID.
     * 
     * Returns all profile information including address as nested object
     * and statistics fields (currently returning 0 until Phase 3+).
     * 
     * @param int $userId User ID
     * @return array Complete user profile with nested address and statistics
     * @throws Exception If user not found
     */
    public function getProfile(int $userId): array {
        $user = $this->userRepo->find($userId);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Build response object with all fields
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'bio' => $user['bio'] ?? '',
            'avatar_url' => $user['avatar_url'] ?? null,
            'address' => [
                'street' => $user['address_street'] ?? '',
                'city' => $user['address_city'] ?? '',
                'province' => $user['address_province'] ?? '',
                'postal_code' => $user['address_postal_code'] ?? '',
                'country' => $user['address_country'] ?? ''
            ],
            'statistics' => [
                'active_listings_count' => $this->getActiveListingsCount($userId),
                'completed_sales_count' => $this->getCompletedSalesCount($userId),
                'average_rating' => 0,
                'total_reviews' => 0
            ]
        ];
    }
    
    /**
     * Update user profile with whitelist validation and automatic geocoding.
     * 
     * Only allows editing specific fields:
     * - first_name, last_name
     * - bio
     * - address components (street, city, province, postal_code, country)
     * 
     * Rejects email, password, and other sensitive fields.
     * 
     * If address fields are provided but coordinates are not, automatically
     * geocodes the address using Google Maps API (same behavior as registration).
     * Caches results to minimize API calls.
     * 
     * @param int $userId User ID
     * @param array $updates Field-value pairs to update
     * @return bool True if update successful, false otherwise
     * @throws Exception On geocoding failure
     */
    public function updateProfile(int $userId, array $updates): bool {
        // Whitelist allowed fields
        $allowedFields = [
            'first_name',
            'last_name',
            'bio',
            'address_street',
            'address_city',
            'address_province',
            'address_postal_code',
            'address_country'
        ];
        
        // Filter input - only keep whitelisted fields
        $filtered = array_intersect_key($updates, array_flip($allowedFields));
        
        // If no fields to update after filtering, return success
        if (empty($filtered)) {
            return true;
        }
        
        // Check if address fields are being updated
        $addressFields = ['address_street', 'address_city', 'address_province', 'address_postal_code', 'address_country'];
        $isAddressUpdate = count(array_intersect(array_keys($filtered), $addressFields)) > 0;
        
        // If address is being updated, geocode it to get new coordinates
        if ($isAddressUpdate) {
            // Construct address array from the update data
            $address = [
                'street' => $filtered['address_street'] ?? '',
                'city' => $filtered['address_city'] ?? '',
                'province' => $filtered['address_province'] ?? '',
                'postal_code' => $filtered['address_postal_code'] ?? '',
                'country' => $filtered['address_country'] ?? ''
            ];
            
            // Only geocode if all address components are present
            if (!empty($address['street']) && !empty($address['city']) && 
                !empty($address['province']) && !empty($address['postal_code']) && 
                !empty($address['country'])) {
                
                // Call Google Maps API via GeolocationService
                $coordinates = $this->geoService->geocodeAddress($address);
                
                if (!$coordinates) {
                    throw new Exception('Unable to geocode address. Please check the address and try again.');
                }
                
                // Add geocoded coordinates to update data
                $filtered['latitude'] = $coordinates['lat'];
                $filtered['longitude'] = $coordinates['lng'];
            }
        }
        
        // Call repository to perform update
        return $this->userRepo->update($userId, $filtered);
    }
    
    /**
     * Get count of active listings for a user.
     * 
     * Currently returns 0 (Phase 3 responsibility to implement actual count).
     * This is a placeholder for when listing repository is available.
     * 
     * @param int $userId User ID
     * @return int Count of active listings (0 until Phase 3)
     */
    private function getActiveListingsCount(int $userId): int {
        // Phase 3: Query listings repository for count
        return 0;
    }
    
    /**
     * Get count of completed sales for a user.
     * 
     * Currently returns 0 (Phase 6 responsibility to implement actual count).
     * This is a placeholder for when booking/sales repository is available.
     * 
     * @param int $userId User ID
     * @return int Count of completed sales (0 until Phase 6)
     */
    private function getCompletedSalesCount(int $userId): int {
        // Phase 6: Query bookings/sales repository for count
        return 0;
    }
}

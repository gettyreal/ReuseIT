<?php
namespace ReuseIT\Services;

use PDO;
use Exception;

/**
 * GeolocationService
 * 
 * Handles address-to-coordinates conversion via Google Maps Geocoding API.
 * Implements caching to minimize API calls and quota usage.
 */
class GeolocationService {
    
    private PDO $pdo;
    private string $apiKey;
    
    /**
     * Initialize GeolocationService with PDO connection.
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // Get API key from environment variable
        $this->apiKey = getenv('GOOGLE_MAPS_API_KEY') ?: $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
    }
    
    /**
     * Geocode an address to GPS coordinates with caching.
     * 
     * @param array $address Address components (street, city, province, postal_code, country)
     * @return array|null Array with 'lat' and 'lng' keys, or null on failure
     */
    public function geocodeAddress(array $address): ?array {
        // Step 1: Normalize address string
        $normalizedAddress = $this->normalizeAddress($address);
        if (!$normalizedAddress) {
            return null;
        }
        
        // Step 2: Generate cache key (MD5 hash of normalized address)
        $cacheKey = md5($normalizedAddress);
        
        // Step 3: Check cache table
        $cachedCoordinates = $this->getCachedCoordinates($cacheKey);
        if ($cachedCoordinates !== null) {
            return $cachedCoordinates;
        }
        
        // Step 4: Call Google Maps API
        $coordinates = $this->callGoogleMapsAPI($normalizedAddress);
        if ($coordinates === null) {
            return null;
        }
        
        // Step 5 & 6: Cache the result
        $this->cacheCoordinates($cacheKey, $normalizedAddress, $coordinates);
        
        // Step 7: Return coordinates
        return $coordinates;
    }
    
    /**
     * Normalize address array into a string format for caching/API.
     * 
     * @param array $address Address components
     * @return string Normalized address or empty string if missing components
     */
    private function normalizeAddress(array $address): string {
        $required = ['street', 'city', 'province', 'postal_code', 'country'];
        
        foreach ($required as $component) {
            if (empty($address[$component])) {
                return '';
            }
        }
        
        return implode(', ', [
            $address['street'],
            $address['city'],
            $address['province'],
            $address['postal_code'],
            $address['country']
        ]);
    }
    
    /**
     * Check geocoding_cache table for cached coordinates.
     * 
     * @param string $addressHash MD5 hash of normalized address
     * @return array|null Coordinates array or null if not found
     */
    private function getCachedCoordinates(string $addressHash): ?array {
        $sql = "SELECT latitude, longitude FROM geocoding_cache WHERE address_hash = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$addressHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'lat' => (float) $result['latitude'],
                'lng' => (float) $result['longitude']
            ];
        }
        
        return null;
    }
    
    /**
     * Call Google Maps Geocoding API with provided address.
     * 
     * @param string $address Normalized address string
     * @return array|null Coordinates array with 'lat' and 'lng', or null on failure
     */
    private function callGoogleMapsAPI(string $address): ?array {
        try {
            // API URL with encoded address
            $encodedAddress = urlencode($address);
            $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$encodedAddress}&key={$this->apiKey}";
            
            // Make API call
            $response = @file_get_contents($url, false, stream_context_create([
                'ssl' => ['verify_peer' => false]
            ]));
            
            if ($response === false) {
                return null;
            }
            
            // Parse JSON response
            $data = json_decode($response, true);
            
            // Check for success and results
            if (empty($data['results']) || !isset($data['results'][0]['geometry']['location'])) {
                return null;
            }
            
            $location = $data['results'][0]['geometry']['location'];
            
            return [
                'lat' => (float) $location['lat'],
                'lng' => (float) $location['lng']
            ];
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Cache coordinates in the geocoding_cache table.
     * 
     * @param string $addressHash MD5 hash of normalized address
     * @param string $addressString Full normalized address
     * @param array $coordinates Coordinates with 'lat' and 'lng'
     */
    private function cacheCoordinates(string $addressHash, string $addressString, array $coordinates): void {
        try {
            $sql = "INSERT INTO geocoding_cache (address_hash, address_string, latitude, longitude, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE created_at = NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $addressHash,
                $addressString,
                $coordinates['lat'],
                $coordinates['lng']
            ]);
        } catch (Exception $e) {
            // Silently fail caching - don't block the request
        }
    }
}

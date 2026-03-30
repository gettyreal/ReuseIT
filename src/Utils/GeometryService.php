<?php
namespace ReuseIT\Utils;

/**
 * GeometryService
 * 
 * Utility class for geographic/geometric calculations.
 * Provides distance calculations using the Haversine formula.
 */
class GeometryService {
    
    /**
     * Earth's radius in kilometers
     * Standard value used for Haversine calculations
     */
    private const EARTH_RADIUS_KM = 6371;
    
    /**
     * Calculate great-circle distance between two points using Haversine formula.
     * 
     * The Haversine formula determines the great-circle distance between two points
     * on a sphere given their longitudes and latitudes.
     * 
     * @param float $lat1 Latitude of first point in decimal degrees
     * @param float $lng1 Longitude of first point in decimal degrees
     * @param float $lat2 Latitude of second point in decimal degrees
     * @param float $lng2 Longitude of second point in decimal degrees
     * 
     * @return float Distance between the two points in kilometers
     */
    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        // Convert degrees to radians
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        // Apply Haversine formula
        // a = sin²(Δlat/2) + cos(lat1) × cos(lat2) × sin²(Δlng/2)
        $a = sin($dLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($dLng / 2) ** 2;
        
        // c = 2 × atan2(√a, √(1-a))
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        // Distance in kilometers
        return self::EARTH_RADIUS_KM * $c;
    }
}

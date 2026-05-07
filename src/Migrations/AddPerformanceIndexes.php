<?php
namespace ReuseIT\Migrations;

use PDO;

/**
 * AddPerformanceIndexes Migration
 * 
 * Adds strategic indexes on hot query paths for Phase 8 favorites and reports.
 * Optimizes pagination, filtering, and sorting on frequently-accessed endpoints.
 */
class AddPerformanceIndexes {
    
    public static function up(PDO $pdo): void {
        // Favorites table indexes
        // Purpose: getFavorites(user_id) paginated list
        $pdo->exec("
            ALTER TABLE favorites 
            ADD INDEX idx_user_id_created_at (user_id, created_at DESC)
        ");
        
        // Reports table indexes
        // Purpose: getReportQueue(status) paginated list  
        $pdo->exec("
            ALTER TABLE reports 
            ADD INDEX idx_status_created_at (status, created_at DESC)
        ");
        
        // Purpose: getReportsByReporter(reporter_id)
        $pdo->exec("
            ALTER TABLE reports 
            ADD INDEX idx_reporter_id (reporter_id)
        ");
        
        // Purpose: getReportsByContent(reported_type, reported_id)
        $pdo->exec("
            ALTER TABLE reports 
            ADD INDEX idx_reported_content (reported_type, reported_id)
        ");
        
        // Listings table - verify existing indexes and add missing ones
        // Check if idx_seller_id exists, if not create it
        try {
            $pdo->exec("
                ALTER TABLE listings 
                ADD INDEX idx_user_id_created_at (user_id, created_at DESC)
            ");
        } catch (\Exception $e) {
            // Index might already exist, continue
        }
        
        // Users table - verify indexes
        // Already has idx_email from Phase 2, banned_at from Phase 8 Plan 4
        // No additional indexes needed
    }
    
    public static function down(PDO $pdo): void {
        // Drop favorites indexes
        $pdo->exec("ALTER TABLE favorites DROP INDEX idx_user_id_created_at");
        
        // Drop reports indexes
        $pdo->exec("ALTER TABLE reports DROP INDEX idx_status_created_at");
        $pdo->exec("ALTER TABLE reports DROP INDEX idx_reporter_id");
        $pdo->exec("ALTER TABLE reports DROP INDEX idx_reported_content");
        
        // Drop listings indexes
        try {
            $pdo->exec("ALTER TABLE listings DROP INDEX idx_user_id_created_at");
        } catch (\Exception $e) {
            // Might not exist
        }
    }
}

<?php
namespace ReuseIT\Migrations;

use PDO;

/**
 * AddAdminColumns Migration
 * 
 * Adds hidden_at and banned_at columns for soft-delete admin features.
 * Allows admins to hide listings and ban users without hard deletion.
 */
class AddAdminColumns {
    
    public static function up(PDO $pdo): void {
        // Add hidden_at column to listings table
        $pdo->exec("
            ALTER TABLE listings ADD COLUMN hidden_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Admin hidden this listing'
        ");
        
        // Add index on hidden_at for efficient filtering
        $pdo->exec("
            ALTER TABLE listings ADD INDEX idx_hidden_at (hidden_at)
        ");
        
        // Add banned_at column to users table
        $pdo->exec("
            ALTER TABLE users ADD COLUMN banned_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Admin banned this user'
        ");
        
        // Add index on banned_at for efficient filtering
        $pdo->exec("
            ALTER TABLE users ADD INDEX idx_banned_at (banned_at)
        ");
    }
    
    public static function down(PDO $pdo): void {
        // Drop banned_at column from users
        $pdo->exec("ALTER TABLE users DROP COLUMN banned_at");
        
        // Drop hidden_at column from listings
        $pdo->exec("ALTER TABLE listings DROP COLUMN hidden_at");
    }
}

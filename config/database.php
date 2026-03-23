<?php
/**
 * Database Configuration
 * 
 * Initializes PDO connection with proper error handling and prepared statement settings.
 * Uses environment variables for credentials (never hardcode).
 * 
 * Returns: PDO instance ready for use
 */

// Database connection parameters
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'reuseit';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPassword = getenv('DB_PASSWORD') ?: '';

// Build DSN (Data Source Name)
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

try {
    // Create PDO connection with error handling configuration
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        // Throw exceptions on errors (required for proper error handling)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        
        // Fetch results as associative arrays by default
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        
        // Use native prepared statements (not emulated)
        // Critical for security: prevents SQL injection
        PDO::ATTR_EMULATE_PREPARES => false,
        
        // Set character set for all queries
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    return $pdo;
    
} catch (PDOException $e) {
    // Database connection failed
    // Log error but don't expose details to client
    error_log("Database connection error: " . $e->getMessage());
    throw new Exception("Database connection failed");
}

<?php
/**
 * Front Controller
 * 
 * Entry point for all HTTP requests.
 * 
 * Responsibilities:
 * 1. Error reporting configuration (no client exposure)
 * 2. Session initialization
 * 3. Configuration loading (database, environment)
 * 4. Session validation on every request
 * 5. Request routing
 * 6. Top-level exception handling
 * 
 * All requests flow through this single entry point.
 */

// Configure error reporting - never expose errors to client
error_reporting(E_ALL);
ini_set('display_errors', false);  // Errors logged, not displayed to browser

// Start session superglobal
session_start();

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use ReuseIT\Router;
use ReuseIT\Response;
use ReuseIT\Config\SessionHandler;

try {
     // ========================================
     // 1. Parse Request URI and Method
     // ========================================
     
     // Get HTTP method
     $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
     
     // Get request URI
     // Phase 1: Use query string (?uri=/api/endpoint)
     // Phase 2+: Upgrade to Apache .htaccess rewrite rules for pretty URLs
     $uri = $_GET['uri'] ?? '/';
     
     // ========================================
     // 2. Load Configuration
     // ========================================
     
     // Load environment variables if .env file exists
     // (Optional: only if vlucas/phpdotenv is installed)
     // This enables use of .env file for credentials
     try {
         if (class_exists('Dotenv\Dotenv')) {
             $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
             $dotenv->safeLoad();
         }
     } catch (\Throwable $e) {
         // .env loading is optional; safe to skip if not installed
     }
     
     // Load database configuration (may fail if database unavailable)
     // Special case: /api/health endpoint does NOT require database
     $pdo = null;
     if ($uri !== '/api/health') {
         // Load database for all endpoints except /api/health
         // Returns: PDO instance
         $pdo = require_once __DIR__ . '/../config/database.php';
     }
     
     // ========================================
     // 3. Validate Session on Every Request
     // ========================================
     
     // Only validate session if database is available
     // Health check endpoint does not require authentication
     $authenticated = false;
     if ($pdo !== null) {
         // Initialize session handler
         $sessionHandler = new SessionHandler($pdo);
         
         // Validate current session
         // Sets $_SESSION['user_id'] if valid
         // Returns false if not authenticated (non-protected routes can check this)
         $authenticated = $sessionHandler->validate();
     }
     
     // Make authentication status available to controllers
     $_SESSION['authenticated'] = $authenticated;
     
     // ========================================
     // 4. Dispatch Request to Router
     // ========================================
     
     // Initialize router (registers all routes)
     $router = new Router();
     
     // Dispatch request to appropriate controller action
     // Router returns JSON response string
     $response = $router->dispatch($method, $uri);
     
     // ========================================
     // 5. Output Response
     // ========================================
     
     // Response already includes HTTP status code (set by controller)
     echo $response;
    
// ========================================
// 6. Top-Level Exception Handler
// ========================================
    
} catch (\Throwable $e) {
    // Catch all exceptions (expected: PDOException, routing errors, etc.)
    
    // Set HTTP error status
    http_response_code(500);
    
    // Return generic error response (no internal details exposed)
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
    
    // Log error details to server log (not sent to client)
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
}

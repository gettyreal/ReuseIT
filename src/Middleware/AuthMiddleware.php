<?php
namespace ReuseIT\Middleware;

/**
 * AuthMiddleware
 * 
 * Enforces authentication on protected endpoints.
 * Checks that user is logged in before allowing access to sensitive operations.
 * 
 * Security:
 * - Requires $_SESSION['user_id'] to be set and non-empty
 * - Throws exception if not authenticated
 * - Allows controller layer to catch and return 401 response
 */
class AuthMiddleware {
    /**
     * Require authentication.
     * 
     * Throws exception if user is not logged in.
     * Called before controller actions on protected endpoints.
     * 
     * @return void
     * @throws \Exception If user is not authenticated
     */
    public function requireAuth(): void {
        if (empty($_SESSION['user_id'])) {
            throw new \Exception('Unauthorized');
        }
    }
    
    /**
     * Check if user is authenticated.
     * 
     * Returns true/false without throwing exception.
     * Useful for endpoints that may be partially protected or have different behavior based on auth status.
     * 
     * @return bool True if user is logged in, false otherwise
     */
    public function isAuthenticated(): bool {
        return !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID.
     * 
     * Returns the user ID from session.
     * Should call requireAuth() first to ensure user is logged in.
     * Returns 0 if user is not authenticated.
     * 
     * @return int User ID or 0 if not authenticated
     */
    public function getCurrentUserId(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

<?php
namespace ReuseIT\Config;

use PDO;

/**
 * SessionHandler
 * 
 * Database-backed session management with CSRF protection.
 * Stores sessions in the sessions table for horizontal scaling.
 * 
 * Security features:
 * - SameSite=Strict cookie (CSRF protection)
 * - HttpOnly flag (XSS protection)
 * - Secure flag (HTTPS only)
 * - 30-minute idle timeout with activity refresh
 */
class SessionHandler {
    private PDO $pdo;
    private const SESSION_LIFETIME = 1800; // 30 minutes in seconds
    
    /**
     * Initialize session handler with database connection.
     * 
     * @param PDO $pdo Database connection for session storage
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
     /**
      * Create new session for authenticated user.
      * 
      * Generates random session ID, stores in database, sets secure cookie.
      * Cookie includes SameSite=Strict for CSRF protection.
      * 
      * @param int $userId User ID to associate with session
      * @return string The session ID created
      */
     public function login(int $userId): string {
         // Generate cryptographically secure random session ID
         $sessionId = bin2hex(random_bytes(32));
         
         // Insert session into database
         // expires_at is calculated by MySQL (30 minutes from now, in DB timezone)
         $stmt = $this->pdo->prepare('
             INSERT INTO sessions (session_id, user_id, created_at, expires_at)
             VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
         ');
         $stmt->execute([$sessionId, $userId]);
         
         // Set secure cookie with CSRF protection
         setcookie('PHPSESSID', $sessionId, [
             'expires' => time() + self::SESSION_LIFETIME,  // 30 minutes
             'path' => '/',
             'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
             'secure' => true,           // HTTPS only (production requirement)
             'httponly' => true,         // No JavaScript access (XSS protection)
             'samesite' => 'Strict'      // CSRF protection (cookies not sent on cross-site requests)
         ]);
         
         // Store user ID in session superglobal
         $_SESSION['user_id'] = $userId;
         
         return $sessionId;
     }
    
    /**
     * Validate session on incoming request.
     * 
     * Checks:
     * 1. Session exists in database
     * 2. Session has not expired
     * 3. Sets $_SESSION['user_id'] for controller access
     * 4. Refreshes expiration on activity
     * 
     * @return bool True if session is valid, false otherwise
     */
    public function validate(): bool {
        // Get session ID from cookie
        $sessionId = $_COOKIE['PHPSESSID'] ?? null;
        if (!$sessionId) {
            return false;
        }
        
        // Query database for valid session
        $stmt = $this->pdo->prepare('
            SELECT user_id FROM sessions
            WHERE session_id = ? AND expires_at > NOW()
        ');
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        
        if (!$row) {
            return false;
        }
        
        // Session is valid - set user ID in superglobal
        $_SESSION['user_id'] = $row['user_id'];
        
        // Refresh expiration on activity (extends session while user is active)
        // Use database NOW() to avoid timezone mismatch between PHP and database
        $this->pdo->prepare('
            UPDATE sessions SET expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE session_id = ?
        ')->execute([$sessionId]);
        
        return true;
    }
    
    /**
     * Log out user by deleting session.
     * 
     * Removes session from database and clears cookie.
     * 
     * @param string $sessionId Session ID to delete
     * @return void
     */
    public function logout(string $sessionId): void {
        // Delete session from database
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE session_id = ?');
        $stmt->execute([$sessionId]);
        
        // Clear cookie
        setcookie('PHPSESSID', '', time() - 3600);
        
        // Clear session superglobal
        unset($_SESSION['user_id']);
    }
}

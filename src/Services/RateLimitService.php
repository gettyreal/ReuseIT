<?php
namespace ReuseIT\Services;

use ReuseIT\Repositories\LoginAttemptRepository;
use DateTime;

/**
 * RateLimitService
 * 
 * Enforces rate limiting on login attempts to prevent brute-force attacks.
 * Tracks failed login attempts per email + IP address and locks accounts after threshold.
 */
class RateLimitService {
    
    private LoginAttemptRepository $attemptRepo;
    
    // Configuration constants
    private const MAX_ATTEMPTS = 5;        // Lock after 5 failed attempts
    private const LOCKOUT_MINUTES = 15;    // Lock duration in minutes
    
    /**
     * Initialize RateLimitService with dependencies.
     * 
     * @param LoginAttemptRepository $attemptRepo Data access for login attempts
     */
    public function __construct(LoginAttemptRepository $attemptRepo) {
        $this->attemptRepo = $attemptRepo;
    }
    
    /**
     * Check if an email + IP combination is currently locked.
     * 
     * @param string $email User email address
     * @param string $ipAddress Client IP address
     * @return bool True if account is locked, false otherwise
     */
    public function isLocked(string $email, string $ipAddress): bool {
        $row = $this->attemptRepo->find($email, $ipAddress);
        
        if (!$row) {
            return false;
        }
        
        // Check if locked_until is set and still in the future
        if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Record a failed login attempt.
     * 
     * Increments the attempt counter for the email + IP pair.
     * If attempts reach MAX_ATTEMPTS, sets locked_until to current time + LOCKOUT_MINUTES.
     * 
     * @param string $email User email address
     * @param string $ipAddress Client IP address
     * @return void
     */
    public function recordFailedAttempt(string $email, string $ipAddress): void {
        $row = $this->attemptRepo->find($email, $ipAddress);
        
        if ($row) {
            // Existing record - increment attempt count
            $newCount = $row['attempt_count'] + 1;
            $lockedUntil = null;
            
            // If we've reached max attempts, set lockout time
            if ($newCount >= self::MAX_ATTEMPTS) {
                $lockoutTime = new DateTime();
                $lockoutTime->modify('+' . self::LOCKOUT_MINUTES . ' minutes');
                $lockedUntil = $lockoutTime->format('Y-m-d H:i:s');
            }
            
            $this->attemptRepo->update($email, $ipAddress, [
                'attempt_count' => $newCount,
                'locked_until' => $lockedUntil
            ]);
        } else {
            // New record - create with attempt_count = 1
            $this->attemptRepo->create([
                'email' => $email,
                'ip_address' => $ipAddress,
                'attempt_count' => 1
            ]);
        }
    }
    
    /**
     * Clear failed attempts for an email + IP combination.
     * 
     * Called on successful login to reset the counter for the next attempt cycle.
     * 
     * @param string $email User email address
     * @param string $ipAddress Client IP address
     * @return void
     */
    public function clearAttempts(string $email, string $ipAddress): void {
        $this->attemptRepo->delete($email, $ipAddress);
    }
    
    /**
     * Clean up expired lockout records (optional maintenance task).
     * 
     * Can be called periodically (e.g., via cron job) to remove old lockout records.
     * 
     * @return int Number of records deleted
     */
    public function cleanup(): int {
        return $this->attemptRepo->clearOlderThan(self::LOCKOUT_MINUTES);
    }
    
    /**
     * Get the generic error message for a locked account.
     * 
     * @return string Generic error message (does not reveal lock time to prevent enumeration)
     */
    public static function getLockoutMessage(): string {
        return "Too many login attempts. Try again in 15 minutes.";
    }
}

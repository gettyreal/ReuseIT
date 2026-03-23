<?php
namespace ReuseIT\Repositories;

use PDO;

/**
 * LoginAttemptRepository
 * 
 * Data access layer for managing failed login attempts.
 * Tracks login attempts per email + IP address for rate limiting.
 */
class LoginAttemptRepository {
    
    private PDO $pdo;
    private const TABLE = 'login_attempts';
    
    /**
     * Initialize repository with PDO connection.
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Find login attempt record by email and IP address.
     * 
     * @param string $email User email address
     * @param string $ipAddress Client IP address
     * @return array|null Associative array of record data or null if not found
     */
    public function find(string $email, string $ipAddress): ?array {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE email = ? AND ip_address = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email, $ipAddress]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Create a new login attempt record.
     * 
     * @param array $data Key-value pairs (email, ip_address, optional: attempt_count, locked_until)
     * @return void
     */
    public function create(array $data): void {
        // Set defaults
        $email = $data['email'] ?? '';
        $ipAddress = $data['ip_address'] ?? '';
        $attemptCount = $data['attempt_count'] ?? 1;
        $lockedUntil = $data['locked_until'] ?? null;
        
        $sql = "INSERT INTO " . self::TABLE . " (email, ip_address, attempt_count, locked_until, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email, $ipAddress, $attemptCount, $lockedUntil]);
    }
    
    /**
     * Update an existing login attempt record.
     * 
     * @param string $email User email address
     * @param string $ipAddress Client IP address
     * @param array $fields Key-value pairs of fields to update
     * @return int Number of rows affected
     */
    public function update(string $email, string $ipAddress, array $fields): int {
        if (empty($fields)) {
            return 0;
        }
        
        $setClauses = [];
        $params = [];
        
        foreach ($fields as $column => $value) {
            $setClauses[] = "{$column} = ?";
            $params[] = $value;
        }
        
        // Add email and ipAddress at the end for WHERE clause
        $params[] = $email;
        $params[] = $ipAddress;
        
        $sql = "UPDATE " . self::TABLE . " SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE email = ? AND ip_address = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Delete a login attempt record.
     * 
     * @param string $email User email address
     * @param string $ipAddress Client IP address
     * @return void
     */
    public function delete(string $email, string $ipAddress): void {
        $sql = "DELETE FROM " . self::TABLE . " WHERE email = ? AND ip_address = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email, $ipAddress]);
    }
    
    /**
     * Clean up expired lockout records.
     * 
     * @param int $minutes Number of minutes old records to delete
     * @return int Number of rows deleted
     */
    public function clearOlderThan(int $minutes): int {
        $sql = "DELETE FROM " . self::TABLE . " WHERE locked_until IS NOT NULL AND locked_until < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$minutes]);
        
        return $stmt->rowCount();
    }
}

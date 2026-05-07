<?php
namespace ReuseIT\Repositories;

use PDO;

/**
 * UserRepository
 * 
 * Handles user data persistence and retrieval.
 * Extends BaseRepository for soft-delete support and common CRUD operations.
 */
class UserRepository extends BaseRepository {
    
    /**
     * Initialize UserRepository with PDO connection.
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'users');
    }
    
    /**
     * Find a user by email address.
     * Automatically filters soft-deleted records.
     * Also filters banned users (banned_at IS NULL).
     * 
     * @param string $email User email address
     * @return array|null User record or null if not found
     */
     public function findByEmail(string $email): ?array {
         $sql = "SELECT * FROM {$this->table} WHERE email = ?" . $this->applyDeleteFilter() . " AND banned_at IS NULL";
         $stmt = $this->pdo->prepare($sql);
         $stmt->execute([$email]);
         $result = $stmt->fetch(PDO::FETCH_ASSOC);
         return $result ?: null;
     }
     
     /**
      * Ban a user by setting banned_at timestamp.
      * Prevents user from logging in and viewing their listings.
      * 
      * @param int $id User ID
      * @return void
      */
     public function banUser(int $id): void {
         $sql = "UPDATE {$this->table} SET banned_at = NOW() WHERE id = ?";
         $stmt = $this->pdo->prepare($sql);
         $stmt->execute([$id]);
     }
     
     /**
      * Unban a user by clearing banned_at timestamp.
      * Restores user access (if not deleted).
      * 
      * @param int $id User ID
      * @return void
      */
     public function unbanUser(int $id): void {
         $sql = "UPDATE {$this->table} SET banned_at = NULL WHERE id = ?";
         $stmt = $this->pdo->prepare($sql);
         $stmt->execute([$id]);
     }
     
     /**
      * Count total banned users for admin stats.
      * 
      * @return int Number of banned users
      */
     public function countBannedUsers(): int {
         $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE banned_at IS NOT NULL AND deleted_at IS NULL";
         $stmt = $this->pdo->prepare($sql);
         $stmt->execute();
         $result = $stmt->fetch(PDO::FETCH_ASSOC);
         return $result['total'] ?? 0;
     }
}

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
     * 
     * @param string $email User email address
     * @return array|null User record or null if not found
     */
    public function findByEmail(string $email): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE email = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}

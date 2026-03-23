<?php
namespace ReuseIT\Repositories;

use PDO;
use ReuseIT\Traits\Softdeletable;

/**
 * BaseRepository
 * 
 * Abstract base class for all repository implementations.
 * Provides common CRUD operations with soft-delete support.
 * 
 * Child repositories inherit these methods and can implement
 * entity-specific queries as needed.
 */
abstract class BaseRepository {
    use Softdeletable;
    
    protected PDO $pdo;
    protected string $table;
    
    /**
     * Initialize repository with PDO connection and table name.
     * 
     * @param PDO $pdo Database connection
     * @param string $table Table name (set by child class)
     */
    public function __construct(PDO $pdo, string $table) {
        $this->pdo = $pdo;
        $this->table = $table;
    }
    
    /**
     * Find a single record by ID.
     * Automatically filters soft-deleted records.
     * 
     * @param int $id Record ID
     * @return array|null Associative array of record data or null if not found
     */
    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find all records matching optional filters.
     * Automatically filters soft-deleted records.
     * 
     * @param array $filters Optional key-value pairs for WHERE clause
     * @return array Array of associative arrays (empty array if none found)
     */
    public function findAll(array $filters = []): array {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1" . $this->applyDeleteFilter();
        $params = [];
        
        foreach ($filters as $column => $value) {
            $sql .= " AND {$column} = ?";
            $params[] = $value;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new record.
     * 
     * @param array $data Key-value pairs (column => value)
     * @return int Last inserted ID
     */
    public function create(array $data): int {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }
    
    /**
     * Update an existing record.
     * 
     * @param int $id Record ID
     * @param array $data Key-value pairs (column => value)
     * @return bool True if update successful
     */
    public function update(int $id, array $data): bool {
        if (empty($data)) {
            return true;
        }
        
        $columns = array_keys($data);
        $setClauses = array_map(fn($col) => "{$col} = ?", $columns);
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE id = ?";
        
        $params = array_values($data);
        $params[] = $id;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Soft delete a record (set deleted_at timestamp).
     * 
     * @param int $id Record ID
     * @return bool True if delete successful
     */
    public function delete(int $id): bool {
        $sql = "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Restore a soft-deleted record (clear deleted_at).
     * 
     * @param int $id Record ID
     * @return bool True if restore successful
     */
    public function restore(int $id): bool {
        $sql = "UPDATE {$this->table} SET deleted_at = NULL WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Find only soft-deleted records.
     * Opposite of applyDeleteFilter() - includes only records where deleted_at IS NOT NULL.
     * 
     * @return array Array of soft-deleted records
     */
    public function findDeleted(): array {
        $sql = "SELECT * FROM {$this->table} WHERE deleted_at IS NOT NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

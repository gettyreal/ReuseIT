<?php
namespace ReuseIT\Traits;

/**
 * Softdeletable Trait
 * 
 * Provides soft-delete filtering for repository queries.
 * Used by BaseRepository to automatically exclude soft-deleted records.
 */
trait Softdeletable {
    /**
     * Returns SQL fragment for soft-delete filtering.
     * Appends "AND deleted_at IS NULL" to WHERE clause.
     * 
     * Usage in repository:
     *   $sql = "SELECT * FROM users WHERE id = ?" . $this->applyDeleteFilter();
     *   
     * @return string SQL fragment for filtering soft-deleted records
     */
    protected function applyDeleteFilter(): string {
        return ' AND deleted_at IS NULL';
    }
}

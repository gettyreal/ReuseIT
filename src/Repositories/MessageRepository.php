<?php
namespace ReuseIT\Repositories;

use PDO;

/**
 * MessageRepository
 * 
 * Data access layer for messages.
 * Extends BaseRepository with message-specific query methods.
 * Handles pagination, read tracking, and soft-delete filtering.
 */
class MessageRepository extends BaseRepository {
    
    /**
     * Initialize repository with PDO connection.
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'messages');
    }
    
    /**
     * Find messages for a conversation with pagination (newest first).
     * 
     * Returns messages in DESC order by created_at (newest first).
     * JOINs to users table to include sender name and avatar.
     * 
     * Includes:
     * - sender_id, sender name/avatar
     * - content, is_read, created_at, read_at
     * - Ordered by created_at DESC (newest first per locked decision)
     * 
     * Filters applied:
     * - Soft delete filtering on messages and senders
     * - Pagination via LIMIT/OFFSET
     * 
     * @param int $conversationId Conversation ID
     * @param int $limit Number of results per page (default 20)
     * @param int $offset Number of results to skip (default 0)
     * @return array Array of message records
     */
    public function findByConversationId(int $conversationId, int $limit = 20, int $offset = 0): array {
        $sql = "
            SELECT 
                m.id,
                m.conversation_id,
                m.sender_id,
                m.content,
                m.is_read,
                m.read_at,
                m.created_at,
                u.first_name as sender_first_name,
                u.last_name as sender_last_name,
                u.avatar_url as sender_avatar_url
            FROM {$this->table} m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ?" . $this->applyDeleteFilter() . "
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find new messages since a timestamp (delta query for polling).
     * 
     * Returns messages created after the given timestamp.
     * Ordered by created_at DESC (newest first).
     * JOINs to users for sender information.
     * 
     * Used by polling endpoints to efficiently fetch only new messages
     * since the last poll timestamp.
     * 
     * @param int $conversationId Conversation ID
     * @param string $sinceTimestamp ISO 8601 timestamp (format: Y-m-d H:i:s)
     * @return array Array of new message records
     */
    public function findNewSince(int $conversationId, string $sinceTimestamp): array {
        $sql = "
            SELECT 
                m.id,
                m.conversation_id,
                m.sender_id,
                m.content,
                m.is_read,
                m.read_at,
                m.created_at,
                u.first_name as sender_first_name,
                u.last_name as sender_last_name,
                u.avatar_url as sender_avatar_url
            FROM {$this->table} m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ?
            AND m.created_at > ?" . $this->applyDeleteFilter() . "
            ORDER BY m.created_at DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $sinceTimestamp]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count unread messages in a conversation for a specific user.
     * 
     * Returns count of messages where:
     * - conversation_id matches
     * - is_read = FALSE
     * - sender_id != userId (don't count own messages as unread)
     * 
     * Efficient COUNT(*) query using index on (conversation_id, is_read, sender_id).
     * Filters soft-deleted records.
     * 
     * @param int $conversationId Conversation ID
     * @param int $userId User ID (to exclude own messages)
     * @return int Count of unread messages from other user
     */
    public function countUnread(int $conversationId, int $userId): int {
        $sql = "
            SELECT COUNT(*) as count 
            FROM {$this->table}
            WHERE conversation_id = ?
            AND is_read = FALSE
            AND sender_id != ?" . $this->applyDeleteFilter() . "
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * Mark all messages in a conversation as read (bulk operation).
     * 
     * Updates all messages in conversation where:
     * - is_read = FALSE
     * - sender_id != userId (don't mark own messages as read)
     * 
     * Sets is_read = TRUE and read_at = NOW() for matching messages.
     * Called by ChatService.getConversationHistory() for auto-mark-on-fetch.
     * 
     * @param int $conversationId Conversation ID
     * @param int $userId User ID (to avoid marking own messages)
     * @return bool True if update successful (even if no rows matched)
     */
    public function markConversationRead(int $conversationId, int $userId): bool {
        $sql = "
            UPDATE {$this->table}
            SET is_read = TRUE, read_at = NOW()
            WHERE conversation_id = ?
            AND sender_id != ?
            AND is_read = FALSE
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$conversationId, $userId]);
    }
    
    /**
     * Mark a single message as read (granular operation).
     * 
     * Sets is_read = TRUE and read_at = NOW() for the specified message.
     * Called by PATCH /api/messages/{id}/mark-read endpoint.
     * 
     * @param int $messageId Message ID
     * @return bool True if update successful
     */
    public function markMessageRead(int $messageId): bool {
        $sql = "
            UPDATE {$this->table}
            SET is_read = TRUE, read_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$messageId]);
    }
    
    /**
     * Get latest message timestamp for a conversation.
     * 
     * Returns MAX(created_at) for all messages in conversation.
     * Used for pagination sync and polling next-timestamp response.
     * 
     * @param int $conversationId Conversation ID
     * @return string|null ISO 8601 timestamp (format: Y-m-d H:i:s) or null if no messages
     */
    public function getLatestTimestamp(int $conversationId): ?string {
        $sql = "
            SELECT MAX(m.created_at) as latest
            FROM {$this->table} m
            WHERE m.conversation_id = ?" . $this->applyDeleteFilter() . "
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['latest'] ?? null;
    }
    
    /**
     * Create a new message.
     * 
     * @param array $data Key-value pairs (column => value)
     *                     Required: conversation_id, sender_id, content
     *                     Optional: is_read (default false), created_at
     * @return int Last inserted message ID
     */
    public function create(array $data): int {
        return parent::create($data);
    }
}

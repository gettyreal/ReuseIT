<?php
namespace ReuseIT\Repositories;

use PDO;

/**
 * ConversationRepository
 * 
 * Data access layer for conversations.
 * Extends BaseRepository with conversation-specific query methods.
 * Handles unread tracking, pagination, and soft-delete filtering.
 */
class ConversationRepository extends BaseRepository {
    
    /**
     * Initialize repository with PDO connection.
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'conversations');
    }
    
    /**
     * Find all conversations for a user with pagination and unread counts.
     * 
     * Returns conversations where user is either buyer or seller.
     * Includes:
     * - User details (name, avatar) for the other participant
     * - Listing title
     * - Unread flag (boolean) for this user
     * - Unread count (number of unread messages from other user)
     * - Ordered by last_message_at DESC (most recent first)
     * 
     * Filters applied:
     * - User participation (buyer_id or seller_id)
     * - Soft delete filtering on conversations, users, listings
     * - Pagination via LIMIT/OFFSET
     * 
     * @param int $userId User ID (buyer or seller)
     * @param int $limit Number of results per page (default 20)
     * @param int $offset Number of results to skip (default 0)
     * @return array Array of conversation records with unread info
     */
    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array {
        $sql = "
            SELECT 
                c.id,
                c.listing_id,
                c.buyer_id,
                c.seller_id,
                c.last_message_at,
                c.created_at,
                c.updated_at,
                CASE 
                    WHEN c.buyer_id = ? THEN c.unread_by_buyer
                    WHEN c.seller_id = ? THEN c.unread_by_seller
                    ELSE NULL
                END as unread,
                COALESCE(
                    (SELECT COUNT(*) FROM messages m 
                     WHERE m.conversation_id = c.id 
                     AND m.is_read = FALSE 
                     AND m.sender_id != ? " . $this->applyDeleteFilter('m') . "),
                    0
                ) as unread_count,
                u.id as other_user_id,
                u.first_name as other_user_first_name,
                u.last_name as other_user_last_name,
                u.avatar_url as other_user_avatar_url,
                l.title as listing_title
            FROM {$this->table} c
            LEFT JOIN users u ON (
                (c.buyer_id = ? AND u.id = c.seller_id) OR
                (c.seller_id = ? AND u.id = c.buyer_id)
            )
            LEFT JOIN listings l ON c.listing_id = l.id " . $this->applyDeleteFilter('l') . "
            WHERE (c.buyer_id = ? OR c.seller_id = ?)" . $this->applyDeleteFilter() . "
            ORDER BY c.last_message_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $userId, $userId, $userId,  // CASE and unread_count subquery
            $userId, $userId,  // Other user JOINs
            $userId, $userId,  // WHERE clause
            $limit, $offset
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find a single conversation by ID.
     * Automatically filters soft-deleted records.
     * 
     * @param int $id Conversation ID
     * @return array|null Conversation record or null if not found
     */
    public function find(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find a conversation by ID with unread count for a specific user.
     * 
     * Returns conversation details plus unread message count for the requesting user.
     * Used by ChatService to check unread state for a specific conversation.
     * 
     * @param int $conversationId Conversation ID
     * @param int $userId User ID (to calculate unread count for this user)
     * @return array|null Conversation with unread_count or null if not found
     */
    public function findWithUnreadCount(int $conversationId, int $userId): ?array {
        $sql = "
            SELECT 
                c.*,
                COALESCE(
                    (SELECT COUNT(*) FROM messages m 
                     WHERE m.conversation_id = c.id 
                     AND m.is_read = FALSE 
                     AND m.sender_id != ? " . $this->applyDeleteFilter('m') . "),
                    0
                ) as unread_count
            FROM {$this->table} c
            WHERE c.id = ?" . $this->applyDeleteFilter() . "
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $conversationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find conversations updated since a specific timestamp (for polling).
     * 
     * Returns conversations where user is participant and were updated after timestamp.
     * Includes unread count via subquery.
     * Ordered by updated_at DESC for consistency.
     * 
     * @param int $userId User ID (buyer or seller)
     * @param string $timestamp ISO 8601 timestamp (format: Y-m-d H:i:s)
     * @param int $limit Number of results to return (default 50)
     * @return array Array of updated conversation records
     */
    public function findUpdatedSince(int $userId, string $timestamp, int $limit = 50): array {
        $sql = "
            SELECT 
                c.id,
                c.listing_id,
                c.buyer_id,
                c.seller_id,
                c.last_message_at,
                c.updated_at,
                COALESCE(
                    (SELECT COUNT(*) FROM messages m 
                     WHERE m.conversation_id = c.id 
                     AND m.is_read = FALSE 
                     AND m.sender_id != ? " . $this->applyDeleteFilter('m') . "),
                    0
                ) as unread_count
            FROM {$this->table} c
            WHERE (c.buyer_id = ? OR c.seller_id = ?)
            AND c.updated_at > ?" . $this->applyDeleteFilter() . "
            ORDER BY c.updated_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $timestamp, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark a conversation as read for a specific user.
     * 
     * Determines if user is buyer or seller, updates corresponding unread flag.
     * Also sets updated_at to current timestamp.
     * 
     * @param int $conversationId Conversation ID
     * @param int $userId User ID (buyer or seller)
     * @return bool True if update successful
     */
    public function markConversationRead(int $conversationId, int $userId): bool {
        // First determine if user is buyer or seller
        $selectSql = "SELECT buyer_id, seller_id FROM {$this->table} WHERE id = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($selectSql);
        $stmt->execute([$conversationId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            return false;
        }
        
        // Determine which unread flag to update
        $updateColumn = ($conversation['buyer_id'] == $userId) ? 'unread_by_buyer' : 'unread_by_seller';
        
        // Update the appropriate unread flag
        $updateSql = "
            UPDATE {$this->table} 
            SET {$updateColumn} = FALSE, updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->pdo->prepare($updateSql);
        return $stmt->execute([$conversationId]);
    }
    
    /**
     * Create a new conversation.
     * 
     * @param array $data Key-value pairs (column => value)
     *                     Required: listing_id, buyer_id, seller_id
     *                     Optional: created_at
     * @return int Last inserted conversation ID
     */
    public function create(array $data): int {
        return parent::create($data);
    }
    
    /**
     * Update an existing conversation.
     * 
     * @param int $id Conversation ID
     * @param array $data Key-value pairs (column => value)
     * @return bool True if update successful
     */
    public function update(int $id, array $data): bool {
        return parent::update($id, $data);
    }
}

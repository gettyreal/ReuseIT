# Phase 5: Chat & Messaging - Research

**Researched:** 2026-03-30  
**Domain:** Async peer-to-peer messaging infrastructure with polling-based near-real-time updates  
**Confidence:** HIGH

## Summary

Phase 5 implements core messaging functionality for marketplace communication. Database schema is already in place (conversations and messages tables defined in Phase 1 schema). The implementation focuses on service layer business logic (message validation, unread tracking, conversation management), repository data access patterns (queries with proper pagination and soft-delete filtering), and controller HTTP endpoints.

The standard stack follows existing project patterns: repository-service-controller layering, PDO prepared statements for all queries, Response envelope for consistency, session-based authentication, and soft-delete filtering throughout. Unread tracking uses a hybrid approach (per-conversation booleans + per-message read status) to balance storage efficiency with granular functionality.

**Primary recommendation:** Build ConversationRepository, MessageRepository, ChatService, and ChatController following established patterns. Implement message pagination with newest-first ordering, auto-mark-on-fetch unread tracking, and delta polling endpoints for efficient frontend updates.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Message Display & Pagination**
- Messages ordered newest first (most recent first in API response)
- 20 messages per page (or fewer if conversation has <20 total messages)
- Offset-based pagination using `limit` and `offset` query parameters
- Keep all messages forever (no archival or history depth limits; soft-delete only via conversation deletion)

**Unread Message Tracking**
- Hybrid approach: Per-conversation boolean flags (`unread_by_seller`, `unread_by_buyer`) + per-message read status (`is_read`, `read_at`)
- Two mark-read endpoints:
  - `PATCH /api/conversations/{id}/mark-read` — Mark entire conversation as read (bulk operation)
  - `PATCH /api/messages/{id}/mark-read` — Mark individual message as read (granular)
- Auto-mark on fetch: When `GET /api/conversations/{id}/messages` is called, automatically mark all returned messages as read for the requesting user
- Unread count in responses: Include current unread count in all conversation/message responses

**Conversation Auto-Creation Policy**
- No manual conversation initiation: Users cannot call `POST /api/conversations` to start chats manually
- Conversations only created via bookings (Phase 6): Bookings phase creates conversations when bookings are made
- Reuse conversations: If same buyer books same listing from same seller again, reuse existing conversation (messages persist for context)
- Keep current schema: No modifications to conversations table; UNIQUE constraint on `(listing_id, buyer_id, seller_id)` already enforced

**Polling Behavior & Performance**
- Delta endpoint: `GET /api/conversations/{id}/messages/new?since={timestamp}` returns only messages newer than timestamp
- Delta response includes:
  - `messages[]` — New messages since timestamp
  - `unread_count` — Updated unread count for this conversation
  - `conversation_updated_at` — Timestamp for next poll
- Conversation list polling: `GET /api/conversations?updated_since={timestamp}` returns only conversations with activity since timestamp
- Server-suggested polling interval: `X-Poll-Interval` header (in milliseconds) included in all polling responses

**Message Content Constraints**
- Plain text only: No markdown, HTML, or rich formatting; simple string validation
- Maximum 1000 characters: Messages longer than 1000 chars rejected with 400 validation error
- Reject empty/whitespace-only messages: Backend validates; must have at least 1 non-whitespace character
- Accept all UTF-8 characters: Full Unicode support including emojis, accents, symbols; database charset is `utf8mb4`

### OpenCode's Discretion

- Unread count response format (e.g., `unread_count: 3` vs `has_unread: true` vs both)
- Exact pagination metadata returned with message lists
- Error message wording for validation failures
- Polling interval value in `X-Poll-Interval` header (default: 3000ms suggested)

### Deferred Ideas (OUT OF SCOPE)

- Real-time WebSockets — Defer to v1.1 or v2 (current polling strategy sufficient for MVP)
- Typing indicators — Defer to future phase
- Message reactions/emojis — Defer to future phase
- File/image sharing in chat — Defer to future phase
- Voice/video calls — Out of scope for v1

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CHAT-01 | User can view all their conversations | ConversationRepository.findByUserId() with eager-load of other user info; pagination support |
| CHAT-02 | User can open a conversation and view message history | MessageRepository.findByConversationId() with newest-first ordering and offset pagination |
| CHAT-03 | User can send messages to negotiate pickup details | MessageRepository.create() with content validation (plain text, 1-1000 chars) and auto-mark logic |
| CHAT-04 | Messages show unread count for each conversation | Hybrid unread tracking via conversations.(unread_by_seller, unread_by_buyer) and messages.(is_read, read_at) |
| CHAT-05 | User can mark messages as read | Two endpoints: bulk mark-read via PATCH /conversations/{id}/mark-read and granular via PATCH /messages/{id}/mark-read |

</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.1+ (project using 7.4+) | Runtime environment | Established in Phase 1 |
| PDO | Native | Database abstraction | SQL injection protection via prepared statements |
| MySQL | 8.0+ | Relational database | Schema already defined; utf8mb4 charset ready for chat content |
| Response envelope | Custom (Phase 1) | Consistent API responses | Used in all endpoints: success/validationErrors/error formats |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| BaseRepository | Custom (Phase 1) | CRUD base class | All repositories extend for prepared statements + soft-delete |
| Softdeletable trait | Custom (Phase 1) | Soft delete filtering | Applied to all queries via applyDeleteFilter() |
| Session handling | PHP native + custom SessionHandler | Authentication context | $_SESSION['user_id'] identifies requesting user for authorization |
| AuthMiddleware | Custom (Phase 2) | Authentication enforcement | Protected endpoints check user logged in before controller executes |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Polling-based updates | WebSocket libraries (Ratchet, Laravel Websockets) | Polling: MVP-sufficient, simpler, works everywhere. WebSocket: real-time but requires new infrastructure. Defer to v1.1. |
| Offset pagination | Cursor-based pagination | Offset: Simple, works with MySQL. Cursor: Better for large datasets but requires index tuning. Offset acceptable for MVP. |
| Hybrid unread tracking | Message-only tracking | Hybrid: Faster conversation list query (boolean check vs COUNT(*) of unread). Justified complexity. |
| Plain text only | Markdown processor | Plain text: Prevents XSS, simpler validation. Markdown: Future enhancement if needed. |

**Installation:** No new external dependencies required — use existing PHP/MySQL stack.

## Architecture Patterns

### Recommended Project Structure
```
src/
├── Repositories/
│   ├── ConversationRepository.php     # Query conversations with pagination, unread flags
│   └── MessageRepository.php          # Query messages with pagination, read status
├── Services/
│   └── ChatService.php                # Business logic: validation, unread marking, conversation context
├── Controllers/
│   └── ChatController.php             # HTTP endpoints for all chat operations
└── Middleware/
    └── (AuthMiddleware already in place for protecting endpoints)
```

### Pattern 1: ConversationRepository

**What:** Repository layer for conversation queries with active user context and unread flags.

**When to use:** Loading conversation lists, retrieving single conversation details, checking conversation existence.

**Example:**
```php
// Source: Extends BaseRepository, follows UserRepository pattern
class ConversationRepository extends BaseRepository {
    
    /**
     * Find all conversations for a user (buyer or seller)
     * Returns conversations ordered by most recent message first
     * Includes other user information (name, avatar) via JOIN
     * Includes unread count for this user
     */
    public function findByUserId(int $userId, int $limit = 20, int $offset = 0): array {
        $sql = "SELECT 
                    c.*,
                    CASE 
                        WHEN c.buyer_id = ? THEN u2.first_name
                        ELSE u1.first_name
                    END as other_user_name,
                    CASE 
                        WHEN c.buyer_id = ? THEN u2.profile_picture_url
                        ELSE u1.profile_picture_url
                    END as other_user_avatar,
                    CASE 
                        WHEN c.buyer_id = ? THEN c.unread_by_buyer
                        ELSE c.unread_by_seller
                    END as unread,
                    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = FALSE 
                        AND sender_id != ?) as unread_count,
                    l.title as listing_title
                FROM conversations c
                JOIN users u1 ON c.buyer_id = u1.id
                JOIN users u2 ON c.seller_id = u2.id
                JOIN listings l ON c.listing_id = l.id
                WHERE (c.buyer_id = ? OR c.seller_id = ?) " . $this->applyDeleteFilter() . "
                ORDER BY c.last_message_at DESC NULLS LAST
                LIMIT ? OFFSET ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find conversation by ID with unread count for specific user
     */
    public function findWithUnreadCount(int $conversationId, int $userId): ?array {
        $sql = "SELECT c.*,
                    CASE 
                        WHEN c.buyer_id = ? THEN c.unread_by_buyer
                        ELSE c.unread_by_seller
                    END as has_unread,
                    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = FALSE 
                        AND sender_id != ?) as unread_count
                FROM conversations c
                WHERE c.id = ? " . $this->applyDeleteFilter();
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $conversationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Find or create conversation for (listing, buyer, seller)
     * Returns existing conversation if exists, null otherwise (creation is ChatService responsibility)
     */
    public function findOrGetContext(int $listingId, int $buyerId, int $sellerId): ?array {
        $sql = "SELECT * FROM conversations 
                WHERE listing_id = ? AND buyer_id = ? AND seller_id = ?" . $this->applyDeleteFilter();
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$listingId, $buyerId, $sellerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Update conversation flags for user
     */
    public function markConversationRead(int $conversationId, int $userId): bool {
        // Determine if user is buyer or seller
        $sql = "SELECT buyer_id, seller_id FROM conversations WHERE id = ? " . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conv) return false;
        
        $field = ($userId === (int)$conv['buyer_id']) ? 'unread_by_buyer' : 'unread_by_seller';
        
        $sql = "UPDATE conversations SET {$field} = FALSE, updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$conversationId]);
    }
    
    /**
     * Get conversations updated since timestamp (for polling)
     */
    public function findUpdatedSince(int $userId, string $timestamp, int $limit = 50): array {
        $sql = "SELECT c.*, 
                    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = FALSE 
                        AND sender_id != ?) as unread_count
                FROM conversations c
                WHERE (c.buyer_id = ? OR c.seller_id = ?) 
                AND c.updated_at > ? " . $this->applyDeleteFilter() . "
                ORDER BY c.updated_at DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $timestamp, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

### Pattern 2: MessageRepository

**What:** Repository layer for message queries with pagination and read status tracking.

**When to use:** Fetching message history, retrieving new messages for polling, counting unread messages.

**Example:**
```php
// Source: Extends BaseRepository, mirrors ListingPhotoRepository pagination pattern
class MessageRepository extends BaseRepository {
    
    /**
     * Find messages in conversation with newest-first ordering
     * Returns messages with sender info via JOIN
     */
    public function findByConversationId(int $conversationId, int $limit = 20, int $offset = 0): array {
        $sql = "SELECT m.*, u.first_name, u.profile_picture_url
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.conversation_id = ? " . $this->applyDeleteFilter() . "
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get new messages since timestamp (for delta polling)
     * Returns in DESC order (newest first)
     */
    public function findNewSince(int $conversationId, string $sinceTimestamp): array {
        $sql = "SELECT m.*, u.first_name, u.profile_picture_url
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.conversation_id = ? AND m.created_at > ? " . $this->applyDeleteFilter() . "
                ORDER BY m.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $sinceTimestamp]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count unread messages for user in conversation
     */
    public function countUnread(int $conversationId, int $userId): int {
        $sql = "SELECT COUNT(*) as count FROM messages 
                WHERE conversation_id = ? AND is_read = FALSE AND sender_id != ? " . $this->applyDeleteFilter();
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * Mark all messages in conversation as read for user
     * (User is not the sender, to avoid marking own messages as unread)
     */
    public function markConversationRead(int $conversationId, int $userId): bool {
        $sql = "UPDATE messages SET is_read = TRUE, read_at = NOW() 
                WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$conversationId, $userId]);
    }
    
    /**
     * Mark individual message as read
     */
    public function markMessageRead(int $messageId): bool {
        $sql = "UPDATE messages SET is_read = TRUE, read_at = NOW() WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$messageId]);
    }
    
    /**
     * Get latest message timestamp in conversation (for pagination sync)
     */
    public function getLatestTimestamp(int $conversationId): ?string {
        $sql = "SELECT MAX(created_at) as latest FROM messages 
                WHERE conversation_id = ? " . $this->applyDeleteFilter();
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['latest'] ?? null;
    }
}
```

### Pattern 3: ChatService

**What:** Business logic layer for message validation, conversation context, and unread tracking orchestration.

**When to use:** Sending messages, validating content, coordinating marking as read, fetching paginated history.

**Example:**
```php
// Source: Follows UserService and ListingService patterns
class ChatService {
    
    private ConversationRepository $conversationRepo;
    private MessageRepository $messageRepo;
    private UserRepository $userRepo;
    
    public function __construct(
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
        UserRepository $userRepo
    ) {
        $this->conversationRepo = $conversationRepo;
        $this->messageRepo = $messageRepo;
        $this->userRepo = $userRepo;
    }
    
    /**
     * Send a message with validation and auto-mark-read logic
     * 
     * @throws InvalidArgumentException On validation failure
     */
    public function sendMessage(int $conversationId, int $senderId, string $content): array {
        // Validate message content
        $this->validateMessageContent($content);
        
        // Verify conversation exists and user is participant
        $conversation = $this->conversationRepo->find($conversationId);
        if (!$conversation) {
            throw new InvalidArgumentException('Conversation not found');
        }
        
        if ($conversation['buyer_id'] != $senderId && $conversation['seller_id'] != $senderId) {
            throw new InvalidArgumentException('User not a participant in this conversation');
        }
        
        // Save message
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'content' => $content,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        $messageId = $this->messageRepo->create($messageData);
        
        // Update conversation last_message_at and reset unread flags
        $otherUserId = ($senderId === (int)$conversation['buyer_id']) 
            ? $conversation['seller_id'] 
            : $conversation['buyer_id'];
        
        $field = ($senderId === (int)$conversation['buyer_id']) ? 'unread_by_seller' : 'unread_by_buyer';
        
        $this->conversationRepo->update($conversationId, [
            'last_message_at' => date('Y-m-d H:i:s'),
            $field => true,  // Mark unread for other user
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        return ['id' => $messageId, 'created_at' => date('Y-m-d H:i:s')];
    }
    
    /**
     * Validate message content per locked requirements
     * - Plain text only
     * - 1-1000 characters
     * - No empty/whitespace-only
     * - UTF-8 support enforced at database level
     */
    private function validateMessageContent(string $content): void {
        $trimmed = trim($content);
        
        if (empty($trimmed)) {
            throw new InvalidArgumentException('Message cannot be empty');
        }
        
        if (strlen($content) > 1000) {
            throw new InvalidArgumentException('Message exceeds 1000 character limit');
        }
        
        // HTML tags would indicate attempted markup
        if ($content !== strip_tags($content)) {
            throw new InvalidArgumentException('HTML is not supported');
        }
    }
    
    /**
     * Get conversation history with pagination
     * Auto-marks all returned messages as read for user
     */
    public function getConversationHistory(int $conversationId, int $userId, int $limit = 20, int $offset = 0): array {
        // Verify user is participant
        $conversation = $this->conversationRepo->find($conversationId);
        if (!$conversation || 
            ($conversation['buyer_id'] != $userId && $conversation['seller_id'] != $userId)) {
            throw new InvalidArgumentException('Unauthorized');
        }
        
        // Fetch messages
        $messages = $this->messageRepo->findByConversationId($conversationId, $limit, $offset);
        
        // Auto-mark messages as read
        $this->messageRepo->markConversationRead($conversationId, $userId);
        
        // Update conversation unread flag
        $this->conversationRepo->markConversationRead($conversationId, $userId);
        
        // Get unread count after marking
        $unreadCount = $this->messageRepo->countUnread($conversationId, $userId);
        
        return [
            'messages' => $messages,
            'unread_count' => $unreadCount,
            'conversation_updated_at' => $this->messageRepo->getLatestTimestamp($conversationId),
        ];
    }
    
    /**
     * Get conversations for user with pagination
     */
    public function getUserConversations(int $userId, int $limit = 20, int $offset = 0): array {
        return $this->conversationRepo->findByUserId($userId, $limit, $offset);
    }
    
    /**
     * Get new messages since timestamp (for polling)
     */
    public function getNewMessages(int $conversationId, int $userId, string $sinceTimestamp): array {
        $conversation = $this->conversationRepo->find($conversationId);
        if (!$conversation || 
            ($conversation['buyer_id'] != $userId && $conversation['seller_id'] != $userId)) {
            throw new InvalidArgumentException('Unauthorized');
        }
        
        $newMessages = $this->messageRepo->findNewSince($conversationId, $sinceTimestamp);
        $this->messageRepo->markConversationRead($conversationId, $userId);
        $unreadCount = $this->messageRepo->countUnread($conversationId, $userId);
        
        return [
            'messages' => $newMessages,
            'unread_count' => $unreadCount,
            'conversation_updated_at' => $this->messageRepo->getLatestTimestamp($conversationId),
        ];
    }
}
```

### Anti-Patterns to Avoid

- **Fetching all messages then filtering:** Don't fetch entire conversation history in PHP and filter. Use `ORDER BY created_at DESC LIMIT ? OFFSET ?` in SQL to avoid memory issues.
- **N+1 sender queries:** Don't fetch messages then loop to fetch sender info. Use JOINs to get sender data with messages in one query.
- **Manual unread tracking without auto-mark:** Don't require users to manually mark messages read. Auto-mark when conversation history is fetched (as locked in decisions).
- **Circular data loading:** Don't load conversations then messages then sender users separately. Use JOINs for efficiency.
- **Missing soft-delete filtering:** Every query must include `$this->applyDeleteFilter()` or users/listings won't be properly filtered when soft-deleted.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Message validation | Custom validation class | Simple inline validation in ChatService | 5 rules (empty, length, HTML, UTF-8, whitespace). Inline is 10 lines vs overengineered class. PHP's `strlen()`, `trim()`, `strip_tags()` sufficient. |
| Pagination | Custom paginator class | BaseRepository methods + SQL LIMIT/OFFSET | PHP's native capabilities handle this. Overbuilding adds maintenance burden. Follow existing pattern (ListingPhotoRepository). |
| Unread count aggregation | Cache layer (Redis, memcache) | Direct COUNT(*) query with index | MVP scale (10K users) doesn't justify cache complexity. SQL index on (conversation_id, is_read, sender_id) is sufficient. Add caching if load testing shows bottleneck. |
| Delta polling logic | Custom timestamp sync class | Simple conditional in endpoint + SQL timestamp comparison | "Only send messages newer than X" is a WHERE clause. Overengineering adds complexity. Keep in ChatController.  |
| Soft delete filtering per query | Write filters in each repository | Use Softdeletable trait with applyDeleteFilter() | Trait is already established (Phase 1). Reuse eliminates duplication and ensures consistency. |

**Key insight:** MVP chat is simple; don't add infrastructure for scale that hasn't arrived. Standard repositories with indexed queries + simple service validation is the right level for Phase 5.

## Common Pitfalls

### Pitfall 1: N+1 Query Problem on Conversation Lists
**What goes wrong:** Loading conversations then separately loading sender info for preview/unread display. If user has 20 conversations, 1 query for list + 20 queries for senders = 21 total (N+1).

**Why it happens:** Conversations table only stores user IDs; developer loops to fetch user details instead of JOINing.

**How to avoid:** ConversationRepository.findByUserId() must JOIN to users table to get other_user_name, avatar in single query. Follow the pattern shown in "Pattern 1: ConversationRepository" example.

**Warning signs:** Check ConversationRepository implementation — if findByUserId() query has zero JOINs, it's vulnerable.

### Pitfall 2: Missing Soft Delete Filtering on Message Queries
**What goes wrong:** Deleted users' messages still visible in conversations. Soft-deleted conversations appear in list when they shouldn't.

**Why it happens:** Developer adds query but forgets `$this->applyDeleteFilter()` or manually checks `deleted_at IS NULL` inconsistently.

**How to avoid:** BaseRepository uses Softdeletable trait. Every query in ConversationRepository and MessageRepository MUST append `$this->applyDeleteFilter()` to WHERE clause. Example: `"WHERE conversation_id = ? " . $this->applyDeleteFilter()`. Pre-commit hook should grep for queries without this.

**Warning signs:** Manual test — soft-delete a user, verify their messages disappear from other users' conversation lists.

### Pitfall 3: Not Auto-Marking Messages on Fetch
**What goes wrong:** Users see unread count never decreases even after opening conversation. Frontend polling sees stale unread counts.

**Why it happens:** Developer implements mark-read endpoints but forgets to auto-mark in getConversationHistory(). Requires two calls (fetch history + mark read) instead of one.

**How to avoid:** ChatService.getConversationHistory() must call `$this->messageRepo->markConversationRead($conversationId, $userId)` BEFORE returning data. Also update conversation unread flag with `$this->conversationRepo->markConversationRead()`. Lock this in code review.

**Warning signs:** Manual test — fetch conversation, verify is_read = TRUE in response and unread_count = 0.

### Pitfall 4: Incorrect Unread Flag Updates When Sending Messages
**What goes wrong:** Sender marks their own message as unread (wrong). Conversation unread flag remains false when it should be true for recipient.

**Why it happens:** Simple logic error — not distinguishing between buyer_id and seller_id when setting flags.

**How to avoid:** In ChatService.sendMessage(), determine recipient with: `$otherUserId = ($senderId === $conversation['buyer_id']) ? $conversation['seller_id'] : $conversation['buyer_id']`. Only update the flag for OTHER user. Use conditional: `$field = ($senderId === $conversation['buyer_id']) ? 'unread_by_seller' : 'unread_by_buyer'`.

**Warning signs:** Manual test — send message as buyer, check conversation.unread_by_seller = TRUE. Then open conversation as seller and check unread_by_seller = FALSE.

### Pitfall 5: Timestamp Precision Issues in Polling
**What goes wrong:** Delta polling returns no new messages or returns duplicates. "since={timestamp}" doesn't match message timestamps due to sub-second precision loss.

**Why it happens:** PHP returns timestamps with millisecond precision from database, JavaScript rounds to seconds, comparison fails.

**How to avoid:** Always use TIMESTAMP type in MySQL (not DATETIME). PHP returns 'YYYY-MM-DD HH:MM:SS' format. Frontend passes back same format. Store timestamps as `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`. Query uses: `WHERE created_at > ?` with exact same format. Test with manual timestamp pass-back.

**Warning signs:** Manual test — fetch messages, note timestamp, wait 1 second, poll with that timestamp, verify new message appears.

### Pitfall 6: Missing Authorization Check in Mark-Read Endpoints
**What goes wrong:** User marks another user's messages as read. User marks a message in a conversation they're not in.

**Why it happens:** Developer implements PATCH /api/messages/{id}/mark-read without verifying user is participant in the conversation.

**How to avoid:** Before marking message read, fetch the message WITH its conversation: `SELECT m.*, c.buyer_id, c.seller_id FROM messages m JOIN conversations c WHERE m.id = ?`. Verify buyer_id or seller_id matches $_SESSION['user_id']. Throw InvalidArgumentException if not.

**Warning signs:** Manual test — mark a message in a conversation you're not in. Should return 403 Forbidden.

## Code Examples

Verified patterns from established project code:

### Example 1: Repository CRUD with Soft Delete

```php
// Source: ReuseIT/src/Repositories/UserRepository.php pattern, adapted for messages
public function create(array $data): int {
    $columns = array_keys($data);
    $placeholders = array_fill(0, count($columns), '?');
    $sql = "INSERT INTO messages (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array_values($data));
    return (int) $this->pdo->lastInsertId();
}

public function find(int $id): ?array {
    $sql = "SELECT * FROM messages WHERE id = ?" . $this->applyDeleteFilter();
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
```

### Example 2: Pagination Pattern

```php
// Source: ReuseIT/src/Repositories/ListingPhotoRepository.php, adapted
public function findByListingId(int $listingId, int $limit = 20, int $offset = 0): array {
    $sql = "SELECT * FROM listing_photos 
            WHERE listing_id = ?" . $this->applyDeleteFilter() . "
            ORDER BY display_order ASC 
            LIMIT ? OFFSET ?";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$listingId, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### Example 3: Service Layer Validation and Business Logic

```php
// Source: ReuseIT/src/Services/ListingService.php pattern, adapted for messages
public function sendMessage(int $conversationId, int $senderId, string $content): array {
    // Validate
    $this->validateMessageContent($content);
    
    // Fetch context
    $conversation = $this->conversationRepo->find($conversationId);
    if (!$conversation) {
        throw new Exception('Conversation not found');
    }
    
    // Persist
    $messageId = $this->messageRepo->create([
        'conversation_id' => $conversationId,
        'sender_id' => $senderId,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    
    return ['id' => $messageId];
}

private function validateMessageContent(string $content): void {
    if (empty(trim($content))) {
        throw new Exception('Message cannot be empty');
    }
    if (strlen($content) > 1000) {
        throw new Exception('Message exceeds maximum length');
    }
}
```

### Example 4: Controller with Response Envelope

```php
// Source: ReuseIT/src/Controllers/UserController.php pattern, adapted for chat
public function getConversations(array $get, array $post, array $files, array $params): string {
    try {
        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            return Response::error('Unauthorized', 401);
        }
        
        $limit = (int)($get['limit'] ?? 20);
        $offset = (int)($get['offset'] ?? 0);
        
        if ($limit < 1 || $limit > 50) {
            return Response::validationErrors([
                ['field' => 'limit', 'message' => 'Limit must be between 1 and 50']
            ], 400);
        }
        
        $conversations = $this->chatService->getUserConversations($userId, $limit, $offset);
        return Response::success($conversations, 200);
    } catch (\Exception $e) {
        return Response::error('Server error', 500);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| WebSockets for MVP chat | Polling-based async messaging (3-5s intervals) | Always (MVP decision) | Simpler deployment, works everywhere, sufficient for MVP. Real-time upgradeable to WebSockets in v1.1. |
| Message-only read tracking | Hybrid (per-conversation + per-message) | Phase 5 locked decision | Balances query efficiency (fast conversation list query via boolean check) with granular read status (per-message tracking for history). |
| Create conversation manually | Auto-create via booking (Phase 6) | Phase 5 locked decision | Eliminates orphan conversations. Conversations now tied to transaction intent, improving moderation and context. |
| No message content validation | Plain text max 1000 chars, reject empty | Phase 5 locked decision | Prevents XSS via HTML. Prevents abuse via unlimited messages. UTF-8 support for international users. |

**Deprecated/outdated:**
- Real-time chat requirement: Out of scope for MVP; polling sufficient (re-evaluate at 1000+ concurrent users)
- File sharing in chat: Deferred to v2; focus on text negotiation for Phase 5

## Open Questions

1. **Unread count response format in specific endpoints**
   - What we know: Locked decision includes "unread count in all conversation/message responses"
   - What's unclear: Exact field name and placement (e.g., `unread_count: 5` at conversation level vs per message or both)
   - Recommendation: Use `unread_count` at conversation level in list endpoint; include `is_read` boolean per message in history. OpenCode discretion to finalize format during planning.

2. **Polling interval value**
   - What we know: Locked decision: `X-Poll-Interval` header in responses
   - What's unclear: Exact millisecond value (suggested 3000ms but not locked)
   - Recommendation: Start with 3000ms (3 seconds) in header. Frontend polls at this interval. Tune during load testing if needed. OpenCode discretion to adjust.

3. **Conversation total message count pagination**
   - What we know: Offset-based pagination with 20 messages per page
   - What's unclear: Should conversation list include total_messages count for UI progress indicators?
   - Recommendation: Include total_message_count in conversation response to enable "Page 2 of 5" UI. Add COUNT query or maintain in conversation record.

4. **Storage: Keep or archive old messages?**
   - What we know: Locked decision: "Keep all messages forever"
   - What's unclear: Performance strategy when conversations reach 10K+ messages
   - Recommendation: For MVP, keep all. Add archival (move to archive table) in v2 if queries slow. Current indexed query strategy sufficient for <5K messages/conversation.

## Validation Architecture

> Based on config.json: `workflow.nyquist_validation` is NOT set (defaults to false). Skipping Validation Architecture section per instructions.

## Sources

### Primary (HIGH confidence)
- **Project codebase Phase 1-4** — BaseRepository pattern, Softdeletable trait, Response envelope, AuthMiddleware patterns verified
- **Database schema** (config/ReuseIT.sql) — conversations and messages tables exist with correct structure (UNIQUE key on (listing_id, buyer_id, seller_id), soft-delete columns present)
- **CONTEXT.md** — All locked decisions from Phase 5 discussion verified and documented
- **ROADMAP.md** — Phase 5 success criteria and requirement mapping verified

### Secondary (MEDIUM confidence)
- **Existing Repository patterns** — UserRepository, ListingRepository, ListingPhotoRepository implementations verify that repository layer + soft-delete + pagination is correct approach for this codebase
- **Existing Service patterns** — AuthService, ListingService, UserService verify service layer handles validation and business logic
- **Existing Controller patterns** — UserController, ListingController, AuthController verify controller response envelope pattern

### Tertiary (Not applicable — no external library research needed for Phase 5)
- Phase 5 uses existing project stack (PHP, PDO, MySQL, custom Response/Repository patterns)
- No new external dependencies required

## Metadata

**Confidence breakdown:**
- Standard stack: **HIGH** — All established patterns from Phase 1-4 code; no new libraries
- Architecture: **HIGH** — Database schema pre-defined; patterns verified in existing controllers/services
- Pitfalls: **HIGH** — Common chat issues (N+1, soft delete, auto-mark) documented with specific prevention strategies
- Open questions: **MEDIUM** — Unread count format and polling interval minor details; can be decided during planning without blocking implementation

**Research date:** 2026-03-30  
**Valid until:** 2026-04-06 (7 days — chat patterns stable, no library changes expected)

---

**Research complete. Ready for /gsd-plan-phase.**

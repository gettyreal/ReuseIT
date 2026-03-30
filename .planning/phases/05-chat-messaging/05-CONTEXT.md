# Phase 5: Chat & Messaging - Context

**Gathered:** 2026-03-30  
**Status:** Ready for planning

<domain>
## Phase Boundary

Buyers and sellers communicate through real-time async messaging to negotiate deals and coordinate pickups without leaving the platform. Conversations are created automatically when bookings are made (Phase 6 responsibility). This phase focuses purely on **messaging infrastructure**: sending, receiving, and retrieving message history with unread tracking and efficient polling support.

**In scope:**
- Message send/receive operations
- Message history retrieval with pagination
- Unread message tracking (per-conversation and per-message)
- Polling endpoints for frontend near-real-time updates
- Auto-mark messages as read when fetched

**Out of scope:**
- Real-time WebSockets (v2 enhancement)
- Typing indicators, message reactions, presence
- Conversation initiation by users (only via bookings in Phase 6)
- Video/voice calls, file sharing

</domain>

<decisions>
## Implementation Decisions

### Message Display & Pagination
- Messages ordered **newest first** — Most recent message appears first in API response
- **20 messages per page** — Balanced payload size; fewer if conversation has <20 total messages
- **Offset-based pagination** — Using `limit` and `offset` query parameters
- **Keep all messages forever** — No archival or history depth limits; soft-delete only via conversation deletion

### Unread Message Tracking
- **Hybrid approach:** Per-conversation boolean flags (`unread_by_seller`, `unread_by_buyer`) + per-message read status (`is_read`, `read_at`)
- **Two mark-read endpoints:**
  - `PATCH /api/conversations/{id}/mark-read` — Mark entire conversation as read (bulk operation)
  - `PATCH /api/messages/{id}/mark-read` — Mark individual message as read (granular)
- **Auto-mark on fetch:** When `GET /api/conversations/{id}/messages` is called, automatically mark all returned messages as read for the requesting user
- **Unread count in response:** Include current unread count in all conversation/message responses (format decided by OpenCode)

### Conversation Auto-Creation Policy
- **No manual conversation initiation:** Users cannot call `POST /api/conversations` to start chats manually
- **Conversations only created via bookings:** Phase 6 (Bookings) creates conversations when bookings are made
- **Reuse conversations for same (listing, buyer, seller):** If same buyer books same listing from same seller again, reuse existing conversation (messages persist for context)
- **Keep current schema:** No modifications to conversations table; UNIQUE constraint on `(listing_id, buyer_id, seller_id)` enforced

### Polling Behavior & Performance
- **Delta endpoint for efficient polling:** `GET /api/conversations/{id}/messages/new?since={timestamp}` returns only messages newer than timestamp
- **Delta response includes:**
  - `messages[]` — New messages since timestamp
  - `unread_count` — Updated unread count for this conversation
  - `conversation_updated_at` — Timestamp for next poll
- **Conversation list polling:** `GET /api/conversations?updated_since={timestamp}` returns only conversations with activity since timestamp
- **Server-suggested polling interval:** `X-Poll-Interval` header (in milliseconds) included in all polling responses; frontend respects hint

### Message Content Constraints
- **Plain text only** — No markdown, HTML, or rich formatting; simple string validation
- **Maximum 1000 characters** — Messages longer than 1000 chars rejected with 400 validation error
- **Reject empty/whitespace-only messages** — Backend validates; must have at least 1 non-whitespace character
- **Accept all UTF-8 characters** — Full Unicode support including emojis, accents, symbols; database charset is `utf8mb4`

### OpenCode's Discretion
- Unread count response format (e.g., `unread_count: 3` vs `has_unread: true` vs both)
- Exact pagination metadata returned with message lists
- Error message wording for validation failures
- Polling interval value in `X-Poll-Interval` header (default: 3000ms suggested)

</decisions>

<specifics>
## Specific Ideas

No specific product references or "I want it like X" requirements. Standard marketplace chat patterns apply.

</specifics>

<deferred>
## Deferred Ideas

- Real-time WebSockets — Defer to v1.1 or v2 (current polling strategy sufficient for MVP)
- Typing indicators — Defer to future phase
- Message reactions/emojis — Defer to future phase
- File/image sharing in chat — Defer to future phase
- Voice/video calls — Out of scope for v1

</deferred>

---

*Phase: 05-chat-messaging*  
*Context gathered: 2026-03-30*

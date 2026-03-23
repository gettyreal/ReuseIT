# Architecture Patterns: P2P Used Electronics Marketplace

**Domain:** Peer-to-peer used electronics marketplace  
**Researched:** March 2026  
**Project:** ReuseIT  

---

## Executive Summary

P2P marketplace systems universally employ **layered (n-tier) architecture** with clear separation between presentation, business logic, and data access. The proposed ReuseIT architecture (Controllers → Services → Repositories) aligns with industry best practices and is **sound for marketplace scale**, scaling from MVP (100-1000 users) to production (10K+ users). However, the architecture requires explicit attention to **transaction boundaries, geolocation indexing, and eventual consistency** for the chat/booking workflow. This document validates the proposed design, identifies critical gaps, and provides a build sequencing that ensures foundational components are solid before dependent features.

---

## Recommended Architecture

### Layered Architecture Diagram

```
┌─────────────────────────────────────────────────────────┐
│  CLIENT LAYER (Browser)                                 │
│  HTML/CSS/JavaScript + Fetch API                        │
│  ├─ Map UI (Google Maps)                                │
│  ├─ Listing Forms                                       │
│  ├─ Chat Interface                                      │
│  └─ Ratings Display                                     │
└──────────────────┬──────────────────────────────────────┘
                   │ HTTP/JSON
┌──────────────────▼──────────────────────────────────────┐
│  API LAYER (Controllers)                                │
│  ├─ Front Controller (public/api.php)                   │
│  ├─ Router (HTTP method + URI → action)                 │
│  └─ Endpoint Handlers (Auth, Listings, Chat, Reviews)   │
│     └─ Input Validation (DTOs, Value Objects)           │
└──────────────────┬──────────────────────────────────────┘
                   │ Dependency Injection
┌──────────────────▼──────────────────────────────────────┐
│  BUSINESS LOGIC LAYER (Services)                        │
│  ├─ AuthService (login, register, session)              │
│  ├─ ListingService (create, search, filter, validate)   │
│  ├─ BookingService (reserve, confirm, status workflow)  │
│  ├─ ChatService (send, fetch, mark-as-read)             │
│  ├─ ReviewService (create, calculate ratings)           │
│  ├─ GeoService (address→coordinates, distance calc)     │
│  └─ Transactional Boundaries (ACID guarantees)          │
└──────────────────┬──────────────────────────────────────┘
                   │ Repository Interface
┌──────────────────▼──────────────────────────────────────┐
│  DATA ACCESS LAYER (Repositories)                       │
│  ├─ UserRepository (CRUD, auth lookups)                 │
│  ├─ ListingRepository (spatial queries, indexing)       │
│  ├─ BookingRepository (status queries, transactions)    │
│  ├─ ChatRepository (message history, counts)            │
│  ├─ ReviewRepository (aggregate ratings)                │
│  └─ PDO + Prepared Statements (SQL injection prevention)│
└──────────────────┬──────────────────────────────────────┘
                   │ SQL
┌──────────────────▼──────────────────────────────────────┐
│  DATABASE (MySQL)                                       │
│  ├─ Normalized 3NF Schema                               │
│  ├─ Spatial Indexes (GIS) on coordinates                │
│  ├─ Foreign Keys + Cascading Deletes                    │
│  ├─ Soft Delete Columns (timestamps)                    │
│  ├─ Transaction Log (audit trail)                       │
│  └─ Connection Pool (persistent connections)            │
└─────────────────────────────────────────────────────────┘
```

### Component Boundaries

| Component | Responsibility | Communicates With | Technology |
|-----------|---|---|---|
| **API Router** | HTTP method/URI → action mapping, request parsing | Controllers | PHP native, regex routing |
| **Auth Controller** | Session validation, login/register endpoints | AuthService, Session | PDO, PHP sessions |
| **Listing Controller** | List CRUD endpoints, image upload, search/filter params | ListingService, GeoService | PDO, Google Maps API |
| **Booking Controller** | Reservation CRUD, status transitions | BookingService, ChatService | PDO transactions |
| **Chat Controller** | Message send/fetch, conversation list | ChatService | PDO, polling (not WebSocket) |
| **Review Controller** | Rating submission, aggregate retrieval | ReviewService | PDO |
| **AuthService** | Password hashing, session lifecycle, role checks | UserRepository | password_hash/verify |
| **ListingService** | Validation (category, price, condition), soft delete, status workflow | ListingRepository, GeoService | Business logic validation |
| **BookingService** | Reservation state machine (pending→confirmed→completed), refunds/cancellations | BookingRepository, ChatService | Transaction boundaries |
| **ChatService** | Message persistence, unread count, notification triggers (future) | ChatRepository | Eventually consistent |
| **ReviewService** | 1-5 star validation, user rating aggregation (avg, count) | ReviewRepository, UserRepository | Denormalization (avg_rating in users) |
| **GeoService** | Address geocoding (Google Maps), Haversine distance, bounding box queries | External API, ListingRepository | Google Maps API calls |
| **UserRepository** | User CRUD, auth lookups (email→id, session validation) | Database | PDO prepared statements |
| **ListingRepository** | Spatial queries (radius search), filtering, soft delete visibility | Database, GeoService | PDO + spatial indexes |
| **BookingRepository** | Transactional write (booking + chat trigger), status updates | Database | PDO transactions, foreign keys |
| **ChatRepository** | Message insert, conversation fetch, count unread | Database | PDO, indexes on (user_id, created_at) |
| **ReviewRepository** | Insert review, recalculate user avg_rating | Database | PDO triggers or service-layer denorm |

---

## Data Flow

### Typical Transaction: User Creates Listing

```
1. CLIENT (Browser)
   ├─ Form validation: title, price, category, location
   ├─ File upload (image): multipart/form-data
   └─ POST /api/listings

2. API LAYER (Router + Controller)
   ├─ Route matching: POST /api/listings → ListingController::create()
   ├─ Session validation: is_logged_in() ✓
   ├─ DTO parsing: title, price, category, address, images
   ├─ Input validation: price > 0, category in allowed list
   └─ Delegate to ListingService::create($dto)

3. BUSINESS LOGIC (Service)
   ├─ Validate domain rules:
   │  ├─ Price within acceptable range
   │  ├─ Category matches marketplace items
   │  └─ User has valid profile (reputation check TBD)
   ├─ Coordinate with GeoService:
   │  ├─ Address → coordinates via Google Maps API
   │  └─ Validate coordinates within acceptable region
   ├─ Prepare ListingEntity with status='active'
   └─ Delegate to ListingRepository::save($listing)

4. DATA LAYER (Repository + Database)
   ├─ PDO transaction BEGIN
   ├─ INSERT listing: id, user_id, title, price, category, 
   │            coordinates (lat, lng), created_at, status
   ├─ INSERT listing_images: listing_id, file_path, display_order
   ├─ INSERT audit_log: action='listing_created', listing_id, user_id, timestamp
   ├─ UPDATE users: set listing_count = listing_count + 1 WHERE id = user_id
   ├─ PDO transaction COMMIT (all-or-nothing)
   └─ Return ListingEntity with id (generated)

5. API LAYER (Response)
   ├─ HTTP 201 Created
   ├─ Response body: { id, title, price, coordinates, status, created_at }
   └─ Location header: /api/listings/{id}

6. CLIENT (Success)
   ├─ Redirect to listing detail page
   └─ Show "Listing published successfully"
```

### Geolocation Data Flow

```
USER LISTS ITEM AT "Via Roma 123, Milan, Italy"
        ↓
   GeoService::geocode()  [Google Maps API]
        ↓
   Returns: { lat: 45.464, lng: 9.190 }
        ↓
   Stored in listings.latitude, listings.longitude
        ↓
   USER SEARCHES: "Show items within 5km"
        ↓
   ListingRepository::findNearby($userLat, $userLng, $radiusKm)
        ↓
   SQL Query:
   SELECT id, title, price, 
          ST_Distance(coordinates, POINT($lat, $lng)) as distance
   FROM listings
   WHERE status='active' AND deleted_at IS NULL
     AND ST_Distance(coordinates, POINT($lat, $lng)) <= $radiusKm
   ORDER BY distance ASC
   LIMIT 50
        ↓
   Results hydrated to ListingEntity[]
        ↓
   Client renders on Google Maps with markers
```

### Chat & Booking Data Flow (Eventual Consistency)

```
BUYER RESERVES ITEM
        ↓
   BookingController::create()
        ↓
   BookingService::createReservation($buyerId, $listingId)
        │
        ├─ BEGIN TRANSACTION
        │
        ├─ BookingRepository::save()  [INSERT booking, status='pending']
        │  └─ Returns booking_id
        │
        ├─ ChatService::initiateConversation()  [Async job ideal, but sync for MVP]
        │  └─ INSERT conversation (buyer_id, seller_id)
        │  └─ INSERT system_message("Buyer reserved your item")
        │
        ├─ COMMIT TRANSACTION
        │
        └─ Return booking_id to client

SELLER SEES NOTIFICATION
   ├─ Polls GET /api/conversations (no WebSocket for MVP)
   ├─ ChatService counts unread messages per conversation
   ├─ Each message fetch updates last_seen_at
   └─ Unread = COUNT(messages) WHERE created_at > last_seen_at

SELLER CONFIRMS BOOKING
   ├─ PATCH /api/bookings/{id}/confirm
   ├─ BookingService::confirm()
   ├─ BEGIN TRANSACTION
   ├─ UPDATE bookings: status='confirmed', confirmed_at=NOW()
   ├─ INSERT chat: "Seller confirmed — meetup location: ..."
   ├─ COMMIT
   └─ Client polls chat, sees confirmation

AFTER TRANSACTION COMPLETE
   ├─ PATCH /api/bookings/{id}/complete
   ├─ ChatService::createReviewPrompt()
   ├─ Client redirects to review form
   └─ ReviewService::create() updates user avg_rating (denormalized)
```

---

## Critical Path: MVP Build Sequencing

Build order reflects **dependencies** and **risk**: foundational components must be solid before dependent features.

### Phase 1: Foundation (No Feature Output Yet)
**Duration:** 2-3 weeks  
**Why First:** Everything depends on these.

1. **Database Schema** (3-4 days)
   - Users table (id, email, password_hash, avatar_path, bio, avg_rating, created_at, deleted_at)
   - Listings table (id, user_id, title, description, price, category, condition, coordinates, status, created_at, updated_at, deleted_at)
   - Listings_images (id, listing_id, file_path, display_order)
   - Bookings (id, buyer_id, listing_id, status, created_at, confirmed_at, completed_at, deleted_at)
   - Conversations (id, buyer_id, seller_id, created_at, deleted_at)
   - Chat_messages (id, conversation_id, sender_id, body, created_at, read_at)
   - Reviews (id, reviewer_id, reviewee_id, listing_id, rating, comment, created_at, deleted_at)
   - Audit_log (id, action, entity_type, entity_id, user_id, changes_json, timestamp)
   - Spatial index on listings(coordinates)
   - Foreign keys with CASCADE DELETE (soft delete in application, not DB)

2. **Repository Layer + PDO Wrapper** (3-4 days)
   - BaseRepository (shared PDO, prepared statements pattern)
   - UserRepository with CRUD + findByEmail
   - ListingRepository with CRUD + findNearby (spatial)
   - BookingRepository with transactional save
   - ChatRepository with message querying
   - ReviewRepository with aggregate queries
   - **Test:** Hand-test each repository with sample data

3. **Value Objects** (2 days)
   - Email (validation: valid format, not in use)
   - Price (validation: > 0, precision to 2 decimals)
   - Coordinates (validation: valid lat/lng bounds)
   - Rating (validation: 1-5 integer)
   - Category (enum: phone, laptop, tablet, etc.)
   - BookingStatus (enum: pending, confirmed, completed, cancelled)
   - MessageBody (validation: 1-5000 chars, no SQL injection vector)

4. **API Router + Front Controller** (2-3 days)
   - public/api.php entry point
   - Simple regex router: POST /api/users → UserController::register
   - HTTP method + URI → action mapping
   - Request parsing (JSON + multipart/form-data)
   - Response wrapper (success/error envelope)
   - 404 handling

5. **Session Management** (1 day)
   - PHP native sessions ($_SESSION)
   - Session handlers: login(), logout(), isLoggedIn(), getUserId()
   - No JWT for MVP (stateful sessions simpler)

### Phase 2: Authentication (Feature: Users Register & Login)
**Duration:** 1 week  
**Depends On:** Phase 1  
**Blocker Removal:** None (foundational work complete)

6. **AuthService**
   - register($email, $password): hash with password_hash(), create User
   - login($email, $password): verify with password_verify()
   - logout(): destroy session
   - getCurrentUser(): from $_SESSION['user_id']

7. **Auth Controllers**
   - POST /api/auth/register: create user
   - POST /api/auth/login: validate + set session
   - POST /api/auth/logout: destroy session
   - GET /api/auth/me: current user data

8. **User Profile**
   - GET /api/users/{id}: public profile (name, avatar, avg_rating, listing_count)
   - PATCH /api/users/{id}: update bio, avatar (file upload)
   - Profile page with ratings history

9. **Frontend: Auth UI**
   - Login form → POST /api/auth/login
   - Register form → POST /api/auth/register
   - Logged-in navigation state

### Phase 3: Listings (Feature: Create & View Listings)
**Duration:** 2 weeks  
**Depends On:** Phase 2  
**Blockers:** Google Maps API key, image storage directory

10. **GeoService**
    - address_to_coordinates($address): call Google Maps Geocoding API
    - distance_between($lat1, $lng1, $lat2, $lng2): Haversine formula
    - bounding_box($centerLat, $centerLng, $radiusKm): calculates bounds for queries
    - **Error Handling:** invalid address → return null, let ListingService handle

11. **ListingService**
    - create($dto): validate category/price, geocode address, save via repo
    - findById($id): get with images
    - findNearby($userLat, $userLng, $radiusKm): delegate to ListingRepository
    - findByCategory($category, $filters): price range, condition
    - softDelete($id): update deleted_at
    - **Validation:** price > 0, category in enum, title 10-200 chars

12. **Listing Controllers**
    - POST /api/listings: create (multipart, image upload)
    - GET /api/listings/{id}: detail with images
    - GET /api/listings: search + filter + pagination
    - PATCH /api/listings/{id}: edit (owner only)
    - DELETE /api/listings/{id}: soft delete (owner only)

13. **Image Upload Handler**
    - Store in public/uploads/listings/{id}/ directory
    - Validate file type (jpeg, png only)
    - Validate file size (max 5MB per image, 5 images max)
    - Generate thumbnails? (defer to Phase 4)

14. **Frontend: Listing UI**
    - Create listing form (category, title, price, condition, address, photos)
    - Listing detail page (images carousel, description, seller card, rating)
    - Listing list with pagination

### Phase 4: Map & Search (Feature: Discover Listings on Map)
**Duration:** 1 week  
**Depends On:** Phase 3  
**Blockers:** None (GeoService already done)

15. **Map UI**
    - Google Maps embed on homepage
    - Fetch nearby listings for current map bounds
    - Render markers with price + title popup
    - Click marker → listing detail

16. **Search & Filter Controllers**
    - GET /api/listings/search with query params:
      - lat, lng, radiusKm (required)
      - category, minPrice, maxPrice, condition (optional)
      - page, limit (pagination)
    - Returns ListingEntity[] with calculated distance

17. **Frontend Search UI**
    - Distance slider (1-50km)
    - Category dropdown
    - Price range slider
    - Condition filter
    - Live map updates as filters change

### Phase 5: Chat & Messaging (Feature: Buyer ↔ Seller Communication)
**Duration:** 2 weeks  
**Depends On:** Phase 3 (listings exist to chat about)  
**Blockers:** None (no WebSocket, polling-based)

18. **ChatService**
    - initiateConversation($buyerId, $listingId): create if not exists, or return existing
    - sendMessage($conversationId, $senderId, $body): insert + update last_seen_at
    - fetchMessages($conversationId, $readerId): get history, mark as read
    - getConversations($userId): list with unread count per conversation

19. **Chat Controllers**
    - POST /api/conversations: initiate (buyer reserves listing)
    - GET /api/conversations: list for current user (with unread counts)
    - GET /api/conversations/{id}/messages: fetch history
    - POST /api/conversations/{id}/messages: send message
    - PATCH /api/conversations/{id}/messages/{id}/read: mark as read (implicit on fetch for MVP)

20. **Message Validation**
    - Body: 1-5000 characters
    - No special characters that could break JSON
    - Timestamps: server-side generation (prevent clock skew)

21. **Frontend Chat UI**
    - Conversation list (seller name, last message preview, unread badge)
    - Chat window (message history, send form)
    - Polling: GET /api/conversations every 3 seconds (crude but sufficient)
    - New message notification (page title blink or toast)

### Phase 6: Bookings (Feature: Reserve Items)
**Duration:** 1.5 weeks  
**Depends On:** Phase 5 (chat initiates with booking)  
**Blockers:** None

22. **BookingService**
    - createReservation($buyerId, $listingId): BEGIN transaction, insert booking, initiate chat, COMMIT
    - confirm($bookingId, $sellerId): check ownership, update status='confirmed', log chat message
    - complete($bookingId): update status='completed', trigger review prompt
    - cancel($bookingId, $userId): check ownership (buyer or seller), refund logic (defer for now)
    - **State Machine:**
      - pending (created) → confirmed (seller accepts) → completed (pickup done)
      - pending/confirmed → cancelled (either party)

23. **Booking Controllers**
    - POST /api/bookings: create (buyer_id implicit from session, listing_id from body)
    - GET /api/bookings/{id}: detail (buyer or seller only)
    - GET /api/bookings: list for current user (filters: status, date)
    - PATCH /api/bookings/{id}/confirm: seller confirms (checks session user)
    - PATCH /api/bookings/{id}/complete: either party marks done
    - PATCH /api/bookings/{id}/cancel: either party cancels

24. **Booking Rules**
    - Only one active (pending/confirmed) booking per listing per user
    - Seller cannot book own listing
    - Only seller can confirm, only buyer can initiate
    - **Transactions:** All booking state changes atomic with chat messages

25. **Frontend Booking UI**
    - Reserve button on listing detail
    - Booking list in user dashboard (status badges, actions by role)
    - Booking detail (seller contact, address agreement, status timeline)

### Phase 7: Reviews & Ratings (Feature: Post-Transaction Reputation)
**Duration:** 1 week  
**Depends On:** Phase 6 (booking completed triggers review)  
**Blockers:** None

26. **ReviewService**
    - create($reviewerUserId, $revieweeUserId, $listingId, $rating, $comment):
      - Validate: rating 1-5, comment 0-500 chars
      - Check booking is completed for this listing
      - Insert review
      - Recalculate avg_rating for reviewee (denormalized in users table)
    - getByReviewee($userId): list reviews received
    - getStats($userId): { avg_rating, total_reviews, breakdown [1-5 stars] }

27. **Review Controllers**
    - POST /api/reviews: create (validates booking completed, validates not already reviewed)
    - GET /api/reviews/user/{id}: fetch reviews for user
    - GET /api/reviews/stats: aggregate stats for a user

28. **Rating Denormalization**
    - users.avg_rating (float)
    - users.total_reviews (int)
    - Updated via trigger (SQL) or service method after insert
    - Visible on user profile + listing detail (seller card)

29. **Frontend Review UI**
    - Review form after booking completes (modal)
    - Stars component (1-5 clickable)
    - Comment textarea
    - User profile: review history + stat cards

### Phase 8: Admin & Reporting (Feature: Content Moderation)
**Duration:** 1 week  
**Depends On:** Phase 3 (listings to moderate)  
**Blockers:** None

30. **Admin Service**
    - reportListing($listingId, $reporterId, $reason, $comment): create report
    - getReports($status='pending'): list for admin review
    - reviewReport($reportId, $decision='approved'|'rejected'): soft-delete listing if approved
    - banUser($userId, $reason): flag user (optional for Phase 1)

31. **Report Controllers**
    - POST /api/listings/{id}/report: create report
    - GET /api/admin/reports: list (admin only, check role)
    - PATCH /api/admin/reports/{id}/approve: delete listing
    - PATCH /api/admin/reports/{id}/reject: close report

32. **Frontend Admin Dashboard**
    - Report queue (listing title, reporter name, reason)
    - Preview listing → approve/reject buttons
    - Moderation history

### Phase 9: Polish & Optimization
**Duration:** 1 week  
**Depends On:** Phase 8 (all features exist)  
**No New Features**

33. **Performance**
    - Add indexes: users(email), listings(user_id, status), bookings(status, created_at)
    - Pagination: default 20 per page, max 100
    - Lazy load images: thumbnail on list, full on detail
    - Cache Google Maps results (address → coordinates) with TTL

34. **Error Handling**
    - Standardized error responses: { error: "message", code: "INVALID_INPUT" }
    - Logging: errors to error_log, requests to access_log
    - User-facing messages: generic "Something went wrong" + support email

35. **Frontend Polish**
    - Loading states (spinners on forms)
    - Validation messages inline
    - 404 page for not-found resources
    - Confirm dialogs for destructive actions

---

## Critical Validation & Transaction Boundaries

### ACID Guarantees for P2P Transactions

**Problem:** Booking creates multiple state changes atomically:
- Insert booking (status='pending')
- Initiate conversation
- Log audit trail
- Update user listing count

If any step fails, entire operation fails. MySQL transactions ensure this.

**Solution:**
```php
// BookingService::createReservation()
try {
    $this->db->beginTransaction();
    
    // 1. Create booking
    $booking = new Booking($buyerId, $listingId, 'pending');
    $this->bookingRepo->save($booking);
    
    // 2. Initiate chat
    $this->chatService->initiateConversation($buyerId, $sellerId);
    
    // 3. Log
    $this->auditService->log('booking_created', $booking->id);
    
    $this->db->commit();
    return $booking;
} catch (Exception $e) {
    $this->db->rollBack();
    throw new BookingFailedException("Failed to create booking");
}
```

**Critical:** All repository operations **inside the transaction** must use same PDO connection.

### Eventual Consistency for Chat

**Problem:** Chat is not mission-critical to lock on. Buyer/seller can tolerate 1-3 second delay in message delivery.

**Solution:** Chat writes are committed before returning to client, but reads are not cached. Client polls every 3 seconds.
```php
// ChatService::sendMessage()
$this->chatRepo->save($message);  // Commits immediately
return [ 'id' => $message->id, 'sent_at' => $message->created_at ];
```

No distributed transactions needed. If message insert fails, user retries.

---

## Scalability Considerations

### At 100 Users (MVP)
| Concern | Approach |
|---------|----------|
| Database | Single MySQL instance, 1-2GB storage |
| Geolocation | Google Maps API (free tier, 25K requests/day) |
| Chat polling | 100 users × 3-sec polls = 33 req/sec (acceptable) |
| Image storage | Filesystem, /public/uploads (10GB sufficient) |
| No caching | Too small to need Redis |

### At 10K Users (Early Production)
| Concern | Approach |
|---------|----------|
| Database | Single MySQL, 10GB storage, need indexes on (user_id, status) |
| Geolocation | Cache geocoding results (address→coords) for 24h in MySQL |
| Chat polling | 10K users × 3-sec = 3,300 req/sec (bottleneck!) → implement HTTP long-polling or WebSocket |
| Image storage | Still filesystem, but implement CDN in front (AWS CloudFront) |
| Session store | Move from PHP files to MySQL table (easier horizontal scale) |
| Query optimization | Monitor slow_log, add indexes on filtering columns |

### At 1M Users (Scale-Out Required)
| Concern | Approach |
|---------|----------|
| **Database** | Shard by user_id (hash mod N regions); separate read replicas |
| **Chat** | Event-driven with message queue (RabbitMQ); denormalize unread count |
| **Geolocation** | Redis for geocoding cache; spatial indexing essential |
| **Images** | Cloud storage (S3) + CDN; filesystem no longer viable |
| **Sessions** | Distributed session store (Redis) + load balancer sticky sessions |
| **API** | Horizontal scaling behind load balancer; stateless design |

**Note:** ReuseIT MVP targets 100-1K users. Architectural choices (single DB, no message queue) are **sound for this scale**. Refactoring to event-driven/sharded would occur at ~50K users.

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Shared Database for Multiple Services
**What goes wrong:** Two services (Listings, Reviews) sharing single MySQL schema → tight coupling, refactoring lock-in.  
**Why it happens:** Simple for MVP, seems efficient.  
**Consequences:** Cannot scale services independently, schema changes require coordination.  
**Prevention:** Keep single DB for MVP (fine at this scale), but organize schema into logical domains (users.* , listings.*, bookings.*) with clear FK boundaries. Document "service owns this table set" in code comments.  
**Detection:** If you find yourself writing cross-service transactions, schema is too coupled.

### Anti-Pattern 2: Chatty API Calls
**What goes wrong:** Creating a booking makes 5 HTTP calls (validate user, check listing, reserve, send message, log audit) → latency, network fragility.  
**Why it happens:** Service decomposition enthusiasm without understanding boundaries.  
**Consequences:** Slow endpoints (>1s response), timeouts if one service is slow.  
**Prevention:** Keep related operations in same transaction (same service layer). Async jobs (email, cleanup) are separate from critical path.  
**Detection:** If an endpoint has more than 2-3 repo/service calls, refactor into single service method.

### Anti-Pattern 3: Synchronous Long-Running Operations
**What goes wrong:** Booking endpoint waits for Google Maps geocoding (~500ms) → client request hangs.  
**Why it happens:** Simplicity (no job queue), blocking on external API.  
**Consequences:** User sees 1-2s delays, perceives system as slow.  
**Prevention:** For MVP, google maps calls are **OK to block** (infrequent). But set timeout (2s) and fail gracefully. For Phase 2+, implement async job queue (PHP `exec()` background process).  
**Detection:** Endpoint response time > 500ms for non-I/O operations.

### Anti-Pattern 4: Missing Transaction Boundaries
**What goes wrong:** Creating booking succeeds, creating chat fails → booking orphaned, user confused.  
**Why it happens:** Neglecting ACID during service design.  
**Consequences:** Data inconsistency, support tickets, loss of trust.  
**Prevention:** Any multi-step operation (booking + chat + audit) wrapped in single transaction. Use `try-catch-rollback` pattern.  
**Detection:** If repo calls aren't in `beginTransaction()...commit()` block, flag it.

### Anti-Pattern 5: Missing Soft Delete Checks
**What goes wrong:** User deletes listing, but deleted listing still appears in search results.  
**Why it happens:** WHERE clause forgot `deleted_at IS NULL`.  
**Consequences:** Data appears to come back to life, GDPR violations (deletion not honored).  
**Prevention:** **All** queries must include `AND deleted_at IS NULL` OR use view/scope layer. Repository base class enforces this.  
**Detection:** Write tests: after softDelete(), query should return empty.

### Anti-Pattern 6: Uncontrolled Session State
**What goes wrong:** Session stores user object + auth token + preferences, grows to 10MB → memory bloat.  
**Why it happens:** Lazily storing objects in $_SESSION without cleanup.  
**Consequences:** Slow logins, memory exhaustion.  
**Prevention:** Store only user_id in $_SESSION. Fetch full user object from repo when needed. Serialize only strings/ints.  
**Detection:** If `serialize($_SESSION)` > 1KB, investigate what's being stored.

---

## Layered Validation & Error Handling

### Input Validation (API Layer)
```php
// ListingController::create()
$data = json_decode($request->body, true);

// 1. Presence check
if (empty($data['title']) || empty($data['price'])) {
    http_response_code(400);
    return json_encode(['error' => 'Missing required fields']);
}

// 2. Type coercion
$listing = new CreateListingDTO(
    title: (string) $data['title'],
    price: (float) $data['price'],
    category: (string) $data['category'],
    address: (string) $data['address']
);

// 3. Delegate to service
try {
    $result = $this->listingService->create($listing);
    return $result;
} catch (ValidationException $e) {
    // Caught at service layer
}
```

### Business Logic Validation (Service Layer)
```php
// ListingService::create()
public function create(CreateListingDTO $dto): ListingEntity {
    // Domain validation
    if ($dto->price <= 0 || $dto->price > 100000) {
        throw new ValidationException('Price out of acceptable range');
    }
    if (!in_array($dto->category, Category::ALLOWED)) {
        throw new ValidationException('Invalid category');
    }
    if (strlen($dto->title) < 10 || strlen($dto->title) > 200) {
        throw new ValidationException('Title must be 10-200 chars');
    }
    
    // Geocode address
    $coords = $this->geoService->address_to_coordinates($dto->address);
    if (!$coords) {
        throw new ValidationException('Address not found. Try a more specific address.');
    }
    
    // Persist
    return $this->listingRepository->save(new ListingEntity(...));
}
```

### Database Validation (Constraints)
```sql
CREATE TABLE listings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL CHECK (price > 0),
    status ENUM('active', 'sold', 'delisted') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_deleted (deleted_at),
    SPATIAL INDEX idx_coordinates (coordinates)
);
```

### Error Response Format
```json
{
  "error": {
    "code": "INVALID_PRICE",
    "message": "Price must be between $0.01 and $100,000",
    "field": "price",
    "timestamp": "2026-03-23T14:30:00Z"
  }
}
```

---

## Component Interdependencies

### Build Order Graph (Dependencies)

```
Database Schema
    ↓
Repository Layer
    ↓ (depends on)
    ├─ AuthService → AuthController
    ├─ ListingService → ListingController → GeoService
    ├─ BookingService → BookingController
    ├─ ChatService → ChatController (depends on BookingService for context)
    ├─ ReviewService → ReviewController
    └─ AdminService → AdminController

API Router (glues it all together)
    ↓
Frontend UI (consumes API endpoints)
```

### **Critical Dependencies** (Block Phase Progression)

| Dependency | Blocks | Phase |
|-----------|--------|-------|
| Database schema + UserRepository | Everything | 1 |
| AuthService + session management | Listing creation (need user context) | 2 |
| ListingService + GeoService | Map feature | 3 |
| BookingService + transaction pattern | Chat feature (chat initiated by booking) | 5 |
| ChatService | Reviews (optional, but UX expects chat availability) | 6 |

### **Soft Dependencies** (Can Defer)

| Feature | Nice to Have | Defer to Phase |
|---------|---|---|
| Image thumbnails | Listing list load faster | 4 |
| Email notifications | Notify on message/booking | Future |
| User suspensions | Moderation completeness | 8 (Admin) |
| Analytics dashboard | Understand marketplace health | Future |

---

## Suggested Build Order (Prioritized by MVP Risk)

### Absolute Must (Week 1-3)
1. **Database + Repositories** (not optional; blocks everything)
2. **API Router + Auth** (users must exist; security foundation)
3. **Listings CRUD** (marketplace without items is pointless)

### Must for Basic Marketplace (Week 4-5)
4. **Bookings** (transactional core; demonstrates architecture)
5. **Chat** (communication required for P2P)

### Essential for Reputation (Week 6-7)
6. **Reviews + Ratings** (trust system; differentiator from classifieds)

### Nice-to-Have MVP (Week 8)
7. **Favorites** (users want to save items)
8. **Admin Reporting** (legal compliance, content moderation)

### Not for MVP (Defer to Phase 2)
- Payment processing (cash-only for v1)
- Email notifications (polling sufficient)
- Push notifications (not web-native)
- Advanced analytics (post-launch)

---

## Sources

**High Confidence (Verified against Domain-Driven Design & Fowler's Microservices):**
- Martin Fowler, *Microservices* (martinfowler.com/articles/microservices.html) — Component boundaries, service orchestration
- Sam Newman, *Building Microservices* (O'Reilly, 2015) — Transaction patterns, integration strategies
- Domain-Driven Design (Evans, 2003) — Bounded contexts, layered architecture patterns

**Medium Confidence (Industry Practice):**
- P2P marketplace case studies (eBay, Airbnb technical blogs) — Geolocation indexing, booking workflows
- OWASP guidelines — PDO prepared statements, input validation layering

**Implementation-Specific (Project):**
- Google Maps API documentation — Geocoding, distance matrix
- MySQL spatial indexing (MariaDB GIS) — Geographic queries
- PHP best practices — Session management, error handling

---

## Quality Gate Checklist

- [x] Components clearly defined with boundaries (repo table above)
- [x] Data flow direction explicit (diagrams + transaction flows)
- [x] Build order implications noted (critical path section + dependency graph)
- [x] Transaction boundaries identified (booking + chat example)
- [x] Scalability path clear (100→10K→1M users progression)
- [x] Anti-patterns catalogued (6 critical ones)
- [x] Validation layering documented (API → Service → DB)
- [x] Soft delete pattern enforced (prevents accidental data exposure)

---

**VALIDATION RESULT:** ✅ Proposed architecture (Controllers → Services → Repositories) is **sound for ReuseIT scale**. Layered separation of concerns allows independent testing, maintains ACID boundaries for bookings, and scales to 10K+ users without refactoring. Critical gaps addressed: explicit transaction boundaries for multi-step operations, soft-delete enforcement across all queries, eventual consistency for chat layer.

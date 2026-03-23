# ReuseIT Development Roadmap

**Created:** 2026-03-23  
**Status:** Active  
**Target Completion:** 9 weeks from start  
**Coverage:** 54/54 v1 requirements mapped

---

## Roadmap Overview

This roadmap breaks down the development of ReuseIT into 8 phases, each with outcome-focused goals (what users or the system can DO), specific success criteria, and mapped v1 requirements. The build sequence respects natural dependencies: foundation layers first, then transactional features, then polish.

**Philosophy:** Each phase builds on the previous phase's architectural patterns. Phases 1-3 establish the technical foundation and listing infrastructure. Phases 4-7 add transactional flows (discovery, communication, booking, reputation). Phase 8 polishes the experience and enables moderation.

---

## Phase 1: Foundation (Weeks 1-2)

### Phase Goal
**Infrastructure is ready for user-facing features.** All request handling, data persistence, and security patterns are established and tested.

### What Gets Built
- **Database Schema:** Normalized 3NF design with soft delete columns (`deleted_at`), timestamps, and spatial indexing
- **Repository Layer:** PDO-based data access with prepared statements and transaction support
- **Value Objects:** Immutable, validated domain types (Email, Price, Coordinates, Rating, Category, BookingStatus)
- **API Router:** Regex-based HTTP routing to controller methods
- **Response Envelope:** Consistent JSON response format (success/error with data/message fields)
- **Session Infrastructure:** PHP session management with CSRF token generation
- **Error Handling:** Centralized error catching and graceful error responses

### Success Criteria
1. **All database tables exist with correct schema** — Run schema migration script; verify tables in MySQL (users, listings, bookings, conversations, messages, reviews, reports with deleted_at, created_at, updated_at columns; spatial index on listings.coordinates)
2. **PDO prepared statement pattern enforced** — Every SQL query uses `?` placeholders or named parameters; grep output shows zero `$variable` interpolation in SQL strings
3. **Response envelope works consistently** — Call any API endpoint; response is `{"status": "success"|"error", "data": {...}, "message": "..."}` with appropriate HTTP status codes
4. **CSRF token protection active** — POST/PATCH/DELETE requests require valid token; requests without token return 403
5. **Soft delete filtering applied** — Write test query; verify `deleted_at IS NULL` is automatically appended to all SELECT queries via BaseRepository

### Mapped Requirements
- **API-01:** All frontend-backend communication uses JSON REST API
- **API-02:** API returns consistent response format (success/error with data/message)
- **API-04:** API validates all inputs before processing
- **API-05:** API handles errors gracefully with descriptive messages

**Unmapped after Phase 1:** 46 requirements (covered by later phases)

### Key Pitfalls to Address
- Prepared statement discipline established now; patterns locked in to prevent SQL injection in later phases
- Soft delete filtering logic centralized in BaseRepository; test with soft-deleted records
- Transaction management setup for later atomic operations (booking+chat)

---

## Phase 2: Authentication & User Profiles (Week 3)

### Phase Goal
**Users can identify themselves to the system.** Registration, login, and profile management enable user context for all subsequent features.

### What Gets Built
- **Auth Service:** Registration (email/password/location), login with session persistence, logout, password hashing (PASSWORD_BCRYPT)
- **Auth Controllers:** POST /api/auth/register, /api/auth/login, /api/auth/logout, GET /api/auth/me
- **User Profile Service:** Profile viewing, editing (name, bio, location), profile avatar display
- **Session Validation:** Regenerate session ID post-login; secure cookie flags (HttpOnly, Secure, SameSite)
- **User Statistics:** Active listings count, completed sales count, average rating (denormalized in users table)
- **Authorization Middleware:** Check authentication for protected endpoints

### Success Criteria
1. **User registration creates account with email/password/location** — Register user with email, password, and location; verify record in users table with hashed password (password_hash)
2. **Session persists across browser refresh** — Log in; refresh page; user remains logged in (session cookie active)
3. **User can edit profile (name, bio, location)** — Update name/bio; GET /api/auth/me returns updated values
4. **Password hashing uses PASSWORD_BCRYPT** — Inspect users.password_hash column; verify all passwords use bcrypt format (`$2y$`)
5. **Session ID regenerated post-login** — Compare session ID before and after login; verify they differ (security against fixation)

### Mapped Requirements
- **AUTH-01:** User can register with email, password, and location (address or coordinates)
- **AUTH-02:** User can log in with email and password
- **AUTH-03:** User session persists across browser refresh
- **AUTH-04:** User can log out from any page
- **USER-01:** User can view their profile (name, avatar, bio, statistics)
- **USER-02:** User can edit their profile (name, bio, location)
- **USER-04:** User can view statistics (active listings count, completed sales, average rating)
- **API-03:** API enforces authentication for protected endpoints

**Unmapped after Phase 2:** 42 requirements (covered by later phases)

### Key Pitfalls to Address
- Password hashing with proper cost factor (cost=12) for security
- Session fixation prevention (regenerate ID on successful login)
- CSRF token handling for profile edit endpoints

---

## Phase 3: Listings & Photo Upload (Weeks 4-5)

### Phase Goal
**Users can publish items for sale.** Listing creation with photos and metadata makes the marketplace functional; geolocation integration enables discovery.

### What Gets Built
- **Listing Service:** CRUD operations, validation (category, price, condition, title, description)
- **Geolocation Service:** Address-to-coordinates conversion via Google Maps Geocoding API; coordinate storage and validation
- **Image Upload Handler:** MIME type validation, magic byte verification, image re-encoding to strip metadata, filesystem storage in public/uploads/
- **Listing Controllers:** POST /api/listings, GET /api/listings/{id}, GET /api/listings (list with pagination), PATCH /api/listings/{id}, DELETE /api/listings/{id} (soft delete)
- **Listing Search Endpoints:** Filter by category, price range, condition (coordinates stored but distance filtering deferred to Phase 4)
- **Avatar Upload:** Allow users to upload avatar in Phase 2 profile edit

### Success Criteria
1. **Listing created with all required fields** — POST /api/listings with category, title, description, price, condition; verify record in listings table
2. **Address converted to coordinates** — Create listing with address; verify listings.lat and listings.lng contain coordinates (e.g., 40.7128 for New York)
3. **Photos uploaded and stored securely** — Upload 2+ photos; verify files in public/uploads/; verify filenames are randomized (not original names); verify code re-encodes images
4. **Listing soft-deleted when cancelled** — DELETE /api/listings/{id}; verify listings.deleted_at is set; GET /api/listings doesn't return deleted listing
5. **User can edit/filter listings by category, price, condition** — Create 3 listings with different categories/prices; filter by category; verify only matching listings returned

### Mapped Requirements
- **LIST-01:** User can create a listing with category, title, description, price, condition
- **LIST-02:** User can upload multiple photos for a listing
- **LIST-03:** User can edit their own listings
- **LIST-04:** User can delete/cancel their own listings
- **LIST-05:** User can view all active listings in a sortable/filterable list
- **LIST-06:** User can view listing details including photos, seller info, price, condition
- **LIST-07:** User can filter listings by category, price range, condition
- **GEO-01:** Listing address is converted to latitude/longitude coordinates
- **USER-03:** User can upload and change their avatar image

**Unmapped after Phase 3:** 34 requirements (covered by later phases)

### Key Pitfalls to Address
- Image upload security (verify MIME + magic bytes; re-encode to strip embedded code; store outside web root; disable PHP execution in upload directory)
- Geolocation precision (store 7+ decimal places for lat/lng; test boundary cases near equator/poles)
- Soft delete filtering applied consistently to listing queries
- Error handling for geocoding API failures (invalid addresses, rate limits)

---

## Phase 4: Map & Search Discovery (Week 6)

### Phase Goal
**Users can discover nearby listings.** Interactive map visualization and distance-based filtering enable users to find items in their area.

### What Gets Built
- **Spatial Query Service:** Haversine distance calculation, bounding box queries with ST_Distance for nearby listings
- **Search/Filter Controllers:** GET /api/listings/search with lat, lng, radius, category, minPrice, maxPrice filters
- **Map API Integration:** Google Maps embed on frontend; clustering markers for large result sets
- **Frontend Map View:** Interactive map with clickable markers; clicking shows listing preview (title, price, distance)
- **Distance Sorting:** Listings returned sorted by distance from user location

### Success Criteria
1. **Interactive map displays listing markers** — Load homepage; verify Google Map with listing markers visible; count of markers matches active listings count
2. **Map filter by distance radius returns nearby listings** — Set location, select 5km radius; verify returned listings have ST_Distance <= 5000m
3. **Clicking marker shows listing preview** — Click marker on map; verify listing title, price, distance displayed in popup/modal
4. **Keyword search filters by title and description** — Search for "iPhone"; verify only listings with "iPhone" in title or description returned
5. **Listings sorted by distance (nearest first)** — Request 10 listings; verify distance increases from first to last result

### Mapped Requirements
- **GEO-02:** User can view an interactive map showing all active listings as markers
- **GEO-03:** User can click map markers to preview listing information
- **GEO-04:** User can filter map view by distance radius from their location
- **GEO-05:** User can see nearby listings sorted by distance
- **LIST-08:** User can search listings by keyword (title, description)

**Unmapped after Phase 4:** 29 requirements (covered by later phases)

### Key Pitfalls to Address
- Spatial index on listings(coordinates) for query performance
- Distance calculation accuracy (Haversine vs ST_Distance trade-offs)
- Map clustering to prevent marker overlap at high zoom levels
- Google Maps API rate limiting and caching strategy

---

## Phase 5: Chat & Messaging (Weeks 7-8)

### Phase Goal
**Buyers and sellers can communicate.** Real-time async messaging enables deal negotiation and meetup coordination without external tools.

### What Gets Built
- **Chat Service:** Initiate conversation, send messages, fetch message history with pagination, count unread messages
- **Conversation Model:** Two-user conversation thread (buyer, seller); soft-deleted flag
- **Message Model:** Message body, sender, timestamp, read status
- **Chat Controllers:** POST /api/conversations, GET /api/conversations (list user's conversations), GET /api/conversations/{id}/messages, POST /api/messages
- **Unread Tracking:** Track per-user read status for each message; mark conversations as read
- **Frontend Chat View:** Conversation list showing unread badge; chat window with message history and input field; 3-5 second polling for new messages

### Success Criteria
1. **User can view list of all conversations** — Log in as buyer with 2+ conversations; GET /api/conversations returns all conversations with other user info
2. **User can send and receive messages** — Send message as buyer; verify message appears in conversation for both users
3. **Unread count tracks correctly** — Send message to user; unread count increments; mark as read; unread count decrements
4. **Message history loads with pagination** — Request first 20 messages; request next 20 with offset; verify correct message sets returned
5. **New messages appear without full page refresh** — Send message; polling detects new message within 3-5 seconds without user refresh

### Mapped Requirements
- **CHAT-01:** User can view all their conversations
- **CHAT-02:** User can open a conversation and view message history
- **CHAT-03:** User can send messages to negotiate pickup details
- **CHAT-04:** Messages show unread count for each conversation
- **CHAT-05:** User can mark messages as read

**Unmapped after Phase 5:** 24 requirements (covered by later phases)

### Key Pitfalls to Address
- N+1 query problem (eager-load sender/recipient user data via JOIN)
- Polling resource exhaustion at scale (implement long-polling or WebSocket in future phase if needed)
- Message ordering (sort by created_at DESC for newest first; reverse on client for display)
- Soft delete filtering on conversations (deleted users shouldn't appear)

---

## Phase 6: Bookings & Transaction Workflow (Weeks 9-10)

### Phase Goal
**Users can reserve items with structured transaction state.** Booking system coordinates intent between buyer and seller; chat integration enables communication within transaction context.

### What Gets Built
- **Booking Service:** Create reservation with atomic transaction (booking + auto-create conversation), state machine (pending → confirmed → completed/cancelled), validation (listing must be active, user not seller)
- **Booking State Management:** Pending (buyer reserved, awaiting seller confirmation), Confirmed (seller agreed, awaiting pickup), Completed (user marked pickup done), Cancelled (either party cancelled)
- **Double-Booking Prevention:** SELECT...FOR UPDATE pessimistic locking; UNIQUE(listing_id, user_id, status NOT IN ('cancelled', 'completed'))
- **Booking Controllers:** POST /api/bookings, GET /api/bookings (list user's bookings), GET /api/bookings/{id}, PATCH /api/bookings/{id}/confirm, PATCH /api/bookings/{id}/complete, PATCH /api/bookings/{id}/cancel
- **Conversation Auto-Creation:** When booking created, automatically create conversation between buyer and seller
- **Frontend Booking View:** Reserve button on listing detail; booking dashboard with separate buyer/seller views; status badges and action buttons

### Success Criteria
1. **User can create booking for a listing** — Click reserve on active listing; booking created with status=pending; conversation auto-created
2. **Booking status workflow progresses correctly** — Pending → Confirmed (seller action) → Completed (user action); verify status in database after each action
3. **Seller cannot double-book listing** — Attempt simultaneous bookings from 2 users; verify only 1 booking in pending/confirmed state (other rejected with 409 Conflict)
4. **Conversation created automatically** — Create booking; verify conversation exists for buyer and seller; users can message without manual init
5. **Buyer/seller see different booking views** — Log in as seller; see "incoming bookings to confirm"; log in as buyer; see "my reservations"

### Mapped Requirements
- **BOOK-01:** User can book/reserve a listing for in-person pickup
- **BOOK-02:** Booking creates a conversation between buyer and seller
- **BOOK-03:** User can view their bookings (separate buyer and seller views)
- **BOOK-04:** Booking status workflow: pending → confirmed → completed / cancelled
- **BOOK-05:** Seller can confirm a pending booking
- **BOOK-06:** User can schedule pickup date/time for a confirmed booking
- **BOOK-07:** User can mark a booking as completed after pickup
- **BOOK-08:** User can cancel a booking (if not completed)
- **CHAT-06:** System automatically creates conversation when booking is made

**Unmapped after Phase 6:** 15 requirements (covered by later phases)

### Key Pitfalls to Address
- Transaction atomicity (booking + conversation creation must be atomic; wrap in BEGIN...COMMIT)
- Race condition prevention (pessimistic locking with SELECT...FOR UPDATE; unique constraint on active bookings)
- Conversation initialization (avoid duplicate conversation creation if user manually initiates after booking auto-creates one)
- Soft delete filtering (prevent deleted users from appearing in booking participants)

---

## Phase 7: Reviews & Reputation System (Week 11)

### Phase Goal
**Trust is quantified through ratings.** Review system enables reputation building after transactions complete.

### What Gets Built
- **Review Service:** Create review (1-5 stars, optional comment), validate booking marked as completed, recalculate user avg_rating (denormalized), fetch reviews for user
- **Review Model:** Reviewer user, reviewed user, booking reference, rating (1-5), comment, created_at
- **Rating Denormalization:** users.avg_rating, users.total_reviews (updated atomically in same transaction as review creation)
- **Review Validation:** Only allow review after booking marked completed; prevent duplicate reviews for same booking; rate limiting (1 review per 24hrs per user)
- **Review Controllers:** POST /api/reviews, GET /api/reviews/user/{id}
- **Frontend Review Flow:** Modal on booking complete; star rating picker; comment field; submit creates review; profile displays avg_rating + total_reviews + review history

### Success Criteria
1. **Review created with rating and comment** — Complete booking; POST /api/reviews with rating=4, comment="Great phone"; verify review in database
2. **User avg_rating calculated correctly** — Create 3 reviews with ratings 5,4,3; verify user.avg_rating = 4.0
3. **Review only allowed after booking completed** — Attempt review on pending booking; receive 400 error (can't review until completed)
4. **User profile shows rating and review count** — GET /api/users/{id}/profile; verify avg_rating and total_reviews fields
5. **Review history visible on profile** — View user profile; see list of all reviews received with reviewer name, rating, comment

### Mapped Requirements
- **REV-01:** User can leave a review (1-5 stars + optional comment) after completed booking
- **REV-02:** Review is only available after pickup marked as completed
- **REV-03:** User can view their own reviews received from others
- **REV-04:** System calculates and displays user's average rating
- **REV-05:** User profile shows number of reviews received

**Unmapped after Phase 7:** 10 requirements (covered by Phase 8)

### Key Pitfalls to Address
- Atomicity (review creation + rating denormalization must be single transaction)
- Soft delete filtering (deleted users shouldn't appear in review queries)
- Review gaming prevention (rate limiting, prevent multiple reviews per booking)
- Calculation accuracy (avg_rating formula and denormalization sync)

---

## Phase 8: Favorites, Admin, & Polish (Week 12)

### Phase Goal
**Marketplace is production-ready.** Favorites enable users to save items for later; admin reporting enables content moderation; polish ensures UX completeness.

### What Gets Built
- **Favorites Service:** Save listing, remove from favorites, list user's favorites
- **Favorites Model:** User, Listing, created_at
- **Report Service:** Report listing (reason: spam, fake, illegal, harmful), report user (same reasons), list reports for admin, approve/reject report
- **Report Model:** Reporter user, reported content (listing or user), reason, status (pending, approved, rejected), created_at
- **Admin Actions:** Hide/unhide listing (sets hidden_at), suspend/ban user (sets banned_at), mark report as processed
- **Admin Controllers:** GET /api/admin/reports (paginated), PATCH /api/admin/reports/{id}/approve, PATCH /api/admin/reports/{id}/reject, PATCH /api/listings/{id}/hide, PATCH /api/users/{id}/ban
- **Report Controllers:** POST /api/listings/{id}/report, POST /api/users/{id}/report
- **Frontend Favorites:** Heart icon on listings; favorites dashboard (sortable, filterable saved listings)
- **Frontend Report:** Report button on listing/user profiles; modal with reason dropdown and optional comment
- **Frontend Admin Panel:** Dashboard showing report queue, recent actions, user/listing stats
- **Error Handling:** Standardized 4xx/5xx error pages; user-friendly validation messages
- **Performance Polish:** Pagination on all list endpoints; lazy-load images; database indexing on hot queries

### Success Criteria
1. **User can save listing to favorites** — Click heart on listing; listing appears in user's favorites dashboard; click again removes from favorites
2. **User can view sorted/filtered favorites** — Save 5 listings; view favorites; filter by category; sort by price; correct subset returned
3. **User can report inappropriate listing** — Click report on listing; select reason (spam); submit; report appears in admin queue
4. **Admin can view and process reports** — Log in as admin; see pending reports; click approve to hide listing; listing no longer visible in search
5. **Admin can ban suspicious user** — Ban user from admin panel; user can't log in; user's listings hidden

### Mapped Requirements
- **FAV-01:** User can save listings to a favorites/wishlist
- **FAV-02:** User can view their saved favorites
- **FAV-03:** User can remove listings from favorites
- **ADMIN-01:** Admin user role exists with special permissions
- **ADMIN-02:** User can report listings as inappropriate
- **ADMIN-03:** User can report other users as suspicious
- **ADMIN-04:** Admin can view reported content
- **ADMIN-05:** Admin can remove/hide reported listings
- **ADMIN-06:** Admin can suspend/ban problematic users

**All v1 requirements covered after Phase 8:** 50/50 ✓

### Key Pitfalls to Address
- Admin role enforcement (check role on every admin endpoint; no data leakage to non-admins)
- Report handling workflow (prevent duplicate reports; track reporter identity for abuse prevention)
- Soft delete filtering (banned users and hidden listings shouldn't appear in queries)
- Performance optimization (index on reports.status, users.banned_at, listings.hidden_at)

---

## Cross-Phase Themes

### Security Throughout All Phases
- **Prepared Statements:** Every SQL query must use `?` or named parameters; Phase 1 establishes pattern, all phases enforce
- **Soft Delete Filtering:** BaseRepository ensures `deleted_at IS NULL` appended to all SELECTs (users, listings, conversations)
- **Session Security:** Regenerate IDs post-login; HttpOnly, Secure, SameSite cookie flags
- **CSRF Protection:** All POST/PATCH/DELETE require valid token; GET is safe (read-only)
- **Authorization:** Middleware validates user ownership of resources (e.g., can only edit own profile, delete own listings)

### Performance Throughout All Phases
- **Database Indexing:** (user_id), (status), (deleted_at), (coordinates with SPATIAL) indexed by Phase 1; verify with EXPLAIN
- **Pagination:** All list endpoints (listings, conversations, reviews) default to 20 items/page with offset
- **Lazy Image Loading:** Frontend images use `loading="lazy"` attribute; thumbnail generation optional for later CDN migration
- **Query Optimization:** Eager-load related data (e.g., listing + seller + photos in one query); avoid N+1 with JOINs

### Data Consistency Throughout All Phases
- **Denormalization:** Carefully managed (users.avg_rating, users.total_reviews, listings.photo_count) updated atomically
- **Cascade Soft Deletes:** User deletion cascades to all their listings, bookings, reviews (all soft-deleted in same transaction)
- **Foreign Key Constraints:** Defined but soft deletes handle logical cascades
- **Audit Trail:** created_at, updated_at, deleted_at columns on all tables for GDPR compliance and debugging

---

## Success Criteria Summary by Phase

| Phase | Outcome | Key Measurable | Success Indicator |
|-------|---------|-----------------|-------------------|
| **1** | Infrastructure ready | All endpoints return consistent JSON; all queries use prepared statements | Schema migration succeeds; grep shows zero SQL injection patterns |
| **2** | Users can identify themselves | Users register/login/persist sessions | POST /api/auth/register succeeds; session survives refresh |
| **3** | Users can list items for sale | Listings created with photos and geolocation | POST /api/listings creates record; address → coordinates converted |
| **4** | Users discover nearby items | Map shows markers; distance filtering works | Map loads with 10+ markers; filter by radius returns correct results |
| **5** | Buyers/sellers communicate | Chat messages sent/received without page refresh | POST message; GET retrieves within 5 seconds; unread count accurate |
| **6** | Transactions reserve items | Bookings progress through states; double-booking prevented | Create booking → confirm → complete workflow succeeds; 2nd simultaneous booking rejected |
| **7** | Trust is quantified | Users accumulate ratings; avg shown on profile | Create review; avg_rating calculated; review visible on profile |
| **8** | Moderation works; polish complete | Reports processed; favorites saved; UX feels complete | Report listing → admin hides it; heart saves to favorites; pagination on all lists |

---

## Dependency Graph

```
Phase 1: Foundation (Database, Router, Sessions, Value Objects)
    ↓ (blocks all others)

Phase 2: Auth (Users, Login, Profiles)
    ↓ (users must exist)

Phase 3: Listings (CRUD, Photos, Geolocation)
    ├→ Phase 4: Discovery (Map, Search, Distance Filtering)
    │   (depends on listings + coordinates existing)
    │
    └→ Phase 5: Chat (Conversations, Messaging)
        (depends on users + listings existing)
        ↓ (chat infrastructure needed)

Phase 6: Bookings (Reservation, State Machine, Auto-create Chat)
    (depends on listings + users + chat)
    ↓ (only rate after completed booking)

Phase 7: Reviews (Ratings, Reputation, Denormalization)
    (depends on completed bookings)
    ↓ (after core transaction loop works)

Phase 8: Favorites, Admin, Polish
    (parallel work possible from week 6 onward)
```

**Critical Path:** 1 → 2 → 3 → 6 → 7 (auth → listings → transactions → ratings form core value)

**Parallel Opportunities:**
- Phases 4 (Map) and 5 (Chat) can start once Phase 3 done (list queries, user context, locations exist)
- Phase 8 (Favorites, Admin, Polish) can run alongside Phase 7 (no blocking dependencies)

---

## Requirement Mapping Summary

| Requirement Set | Count | Phase | Status |
|-----------------|-------|-------|--------|
| **API Infrastructure** | 5 | 1, 2 | 5/5 mapped |
| **Authentication & Users** | 8 | 2, 3 | 8/8 mapped |
| **Listings & Discovery** | 8 | 3, 4 | 8/8 mapped |
| **Geolocation & Map** | 5 | 3, 4 | 5/5 mapped |
| **Bookings & Transactions** | 8 | 6 | 8/8 mapped |
| **Chat & Messaging** | 6 | 5, 6 | 6/6 mapped |
| **Reviews & Reputation** | 5 | 7 | 5/5 mapped |
| **Favorites & Wishlist** | 3 | 8 | 3/3 mapped |
| **Admin & Moderation** | 6 | 8 | 6/6 mapped |
| **TOTAL** | **54** | **1-8** | **54/54 ✓** |

**Coverage: 100%** — All v1 requirements mapped to exactly one phase.

---

## Next Steps

1. **Team Kickoff:** Confirm Phase leads for each phase; schedule Phase 0 (infrastructure setup)
2. **Phase 0 Checklist:**
   - [ ] Git repo created with .gitignore, .env.example, composer.json
   - [ ] MySQL 8.0+ provisioned; test connection from PHP
   - [ ] Google Maps API key secured
   - [ ] Pre-commit hooks configured (prepared statement linting, secret detection)
   - [ ] Team security training (SQL injection, CSRF, session fixation prevention)
3. **Phase 1 Kickoff:** Database schema design + repository pattern implementation
4. **Weekly Standups:** Phase lead reports completion of success criteria; blockers escalated

---

**Roadmap complete. Ready to execute. All 50 v1 requirements mapped. 100% coverage confirmed.**

*Last updated: 2026-03-23*

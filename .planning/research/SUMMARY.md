# ReuseIT Research Summary

**Project:** Peer-to-Peer Used Electronics Marketplace  
**Researched:** 2026-03-23  
**Synthesis Date:** 2026-03-23

---

## Executive Summary

ReuseIT is a **hyperlocal P2P marketplace for used electronics** where users list, discover, and transact on items locally via cash pickup. The platform solves the "trust without escrow" problem through reputation ratings, geolocation discovery, and async communication rather than in-platform payments.

**Technology approach:** Plain PHP (7.4+) with vanilla JavaScript—a deliberate choice to showcase architectural mastery over framework-driven development. The stack is minimal: MySQL 8.0+, Google Maps API, PDO, and select Composer packages (image processing, validation, logging, env config). No frameworks, no build tools, no complexity beyond what the domain requires.

**Core insight from research:** ReuseIT's success depends on **three architectural foundations**: (1) solid transaction boundaries for bookings + chat integration, (2) strict prepared statement discipline to prevent SQL injection, and (3) explicit soft-delete filtering across all queries for GDPR compliance. All other features flow from these. The marketplace pattern is proven; the execution risk is implementation discipline on security and data consistency.

**Roadmap philosophy:** Build foundation phases first (Auth, Listings, Map) to establish architectural patterns, then add transactional features (Bookings, Chat, Reviews) that depend on those patterns being solid. Polish and optimize last.

---

## Key Findings by Research Area

### Technology Stack (HIGH confidence)

| Layer | Technology | Version | Rationale |
|-------|----------|---------|-----------|
| **Runtime** | PHP | 7.4+ (8.4 recommended) | Project requirement; 8.4 provides modern features (attributes, named args) with stable ecosystem |
| **Database** | MySQL | 8.0+ LTS | InnoDB for ACID transactions; spatial indexes for geolocation queries |
| **API** | PDO (native) | Built-in | Mandatory prepared statements via PDO; no external ORM overhead |
| **Maps** | Google Maps API | Latest (v3) | Geocoding (address→coordinates) + Maps JS for visualization |
| **Frontend** | Vanilla HTML/CSS/ES6+ | Native | No frameworks, no build tools; Fetch API for AJAX |
| **Images** | intervention/image | 3.11.7 | Lightweight image processing; GD driver (built-in) sufficient |
| **Validation** | respect/validation | 0.4.x | Framework-agnostic validator; teaches validation patterns |
| **Config** | vlucas/phpdotenv | 5.6.3 | Industry-standard environment config management |
| **Logging** | monolog/monolog | 3.8+ | PSR-3 structured logging for production debugging |

**Key version decisions:** Use PHP 8.4 minimum for new projects (8.5 ecosystem still stabilizing). intervention/image v3.11.7 is production-stable; defer v4 until it reaches full release. All supporting libraries verified as actively maintained through Q1 2026.

**What to NOT use:** No Laravel/Symfony/Slim (project requirement to showcase plain PHP architecture). No webpack/Vite (vanilla JS only). No ORM like Doctrine (PDO teaches better fundamentals). No Guzzle (curl handles Google Maps calls directly).

### Feature Landscape (HIGH confidence)

**Table Stakes (all required for MVP—missing one breaks the platform):**
1. User registration & auth (email/password, sessions)
2. User profiles (avatar, bio, ratings, transaction history)
3. Listing CRUD (title, description, photos, price, condition, category)
4. Search & filtering (price range, distance, condition, category)
5. Geolocation (address → coordinates, map visualization)
6. Chat (async messaging for buyer-seller coordination)
7. Bookings (pending → confirmed → completed workflow)
8. Ratings & reviews (1-5 stars, reputation aggregation)
9. Favorites/wishlist (save items, enable follow-up)
10. Account deletion/GDPR (soft delete with audit trail)
11. Admin reporting (flag inappropriate listings)

**Differentiators (Phase 1 post-MVP—not required for launch but critical for growth):**
- **Verified Seller Badges** (email/phone verification + rating thresholds) → trust signal, competitive advantage
- **Instant Notifications** (browser alerts on price drops, message activity) → engagement driver
- **Smart Recommendations** (ML-based "users who bought X also saw Y") → cross-selling, discovery
- **Bulk Listing Tools** (CSV upload, seller dashboard) → scales sellers, attracts power users
- **Two-Factor Auth** → security signal

**Anti-Features (explicitly DO NOT build):**
- **In-platform payment processing** → PCI compliance burden, fraud liability; use cash-only for v1
- **Shipping/logistics** → out of P2P scope; position as "local pickup" strength
- **Real-time WebSocket chat** → use polling-based async for MVP, upgrade if latency becomes bottleneck
- **Video uploads** → storage overhead; photos + description sufficient for condition assessment
- **Email notifications** → skip for MVP; in-app + chat sufficient
- **Admin approval workflows** → kills seller experience; use reactive flagging instead
- **Bidding/auctions** → complexity; chat-based negotiation is simpler

**Build sequence for MVP:** Auth → Listings → Search/Map → Chat → Bookings → Reviews → Favorites → Admin Panel. This sequence respects feature dependencies: chat depends on listings existing, bookings depend on chat infrastructure, reviews depend on completed bookings.

### Architecture Essentials (HIGH confidence)

**Layered pattern (Controllers → Services → Repositories → Database):**
- **Controllers:** HTTP routing, session validation, DTO parsing, delegate to Services
- **Services:** Business logic validation, domain rules, transaction coordination, call Repositories
- **Repositories:** All database access via PDO prepared statements, return domain entities
- **Value Objects:** Immutable, validated containers (Price, Email, Coordinates, Rating, etc.) for type safety

**Critical transaction boundaries:**
- **Booking + Chat creation** must be atomic: if booking succeeds but chat fails, data is orphaned. Always wrap in `BEGIN TRANSACTION...COMMIT`.
- **Review creation + user rating denormalization** must be atomic: publish review, immediately update user.avg_rating in same transaction.
- **Soft delete cascade**: deleting a user must cascade to their listings, bookings, reviews (all soft-deleted); queries must filter `deleted_at IS NULL` everywhere.

**Geolocation data flow:**
1. User enters address (text) when listing
2. GeoService calls Google Maps Geocoding API → returns lat/lng
3. Listings table stores coordinates as DECIMAL(10,7) with SPATIAL index
4. Search queries use ST_Distance() or Haversine + bounding box pre-filter to find nearby items within radius
5. Client renders on Google Maps with markers; clicking shows listing detail

**Scalability assumptions (sound to 10K users):**
- Single MySQL instance with proper indexing (status, user_id, coordinates)
- PDO connection pooling via PHP-FPM (no external connection pool needed)
- Chat polling every 3-5 seconds (33 req/sec at 100 users; at 10K users, upgrade to long-polling or WebSocket)
- Filesystem image storage (upgrade to S3 CDN at ~100K images)
- No caching layer needed until >5K concurrent users
- No denormalization needed until >10K users

**Build sequencing impact:** Database + repositories first (week 1-2); Router + Auth next (week 3); Listings/Maps (week 4-5); Bookings/Chat (week 6-7); Reviews (week 8); Polish (week 9). This respects architectural dependencies and ensures solid foundation before dependent features.

### Pitfalls & Risk Mitigations (HIGH confidence)

**Top 5 Critical Pitfalls (cause rewrites, data loss, or breaches):**

1. **Prepared Statement Loss Under Time Pressure**
   - Risk: SQL injection vulnerability; full database disclosure
   - Prevention: Enforce prepared statements at Repository layer; code review every SQL query; grep for `\$` in SQL strings; pre-commit hook
   - Phase address: Phase 1 (Auth) — establish pattern before other modules copy bad practice

2. **Soft Delete Filters Forgotten**
   - Risk: GDPR violations (deleted users still visible); deleted listings appear on map; reputation system broken
   - Prevention: Create AbstractRepository with applyDeleteFilter() method; all SELECT queries must use it; test soft delete filtering across system
   - Phase address: Phase 2 (Listings) — enforce before Reviews/Chat phases depend on it

3. **Transaction Atomicity Lost Between Booking + Chat**
   - Risk: Bookings without chat conversations (communication impossible); duplicate attempts; orphaned data
   - Prevention: Wrap booking+chat in single `BEGIN TRANSACTION...COMMIT` block; test by simulating mid-transaction failure
   - Phase address: Phase 3 (Bookings) — architect transaction patterns before Chat integration

4. **Image Upload Validation Bypasses (RCE)**
   - Risk: Attacker uploads PHP script as `.jpg`; achieves remote code execution
   - Prevention: Verify file content (MIME type + magic bytes); re-encode image (strips embedded code); store outside web root; randomize filenames; disable PHP execution in upload directory
   - Phase address: Phase 2 (Listings) — secure upload handler before public launch

5. **Double-Booking Race Condition**
   - Risk: Two users simultaneously book same item; both bookings created; seller confusion
   - Prevention: Use `SELECT...FOR UPDATE` pessimistic locking in transaction; add UNIQUE constraint on (listing_id, user_id, status); test concurrent booking attempts
   - Phase address: Phase 3 (Bookings) — add locking before launch

**Moderate Pitfalls (cause data loss or UX breakage):**
- Geolocation precision loss (store 7+ decimals; use spatial indexes; test boundary cases)
- Session fixation/hijacking (regenerate ID post-login; implement CSRF tokens; use secure cookie flags)
- N+1 query problem in chat (eager-load sender data via JOIN; implement cursor pagination)
- Image storage without cleanup (track paths in DB; implement weekly garbage collection)

**Minor Pitfalls (user friction, technical debt):**
- Wrong password hashing (must use `password_hash(PASSWORD_BCRYPT, cost=12)` only)
- Validation only at Controller layer (move to Value Objects + Service layer)
- Hard-coded secrets (use `.env` file; never commit)

---

## Implications for Roadmap

### Recommended Phase Structure (9 Weeks to MVP)

**Phase 1: Foundation (Weeks 1-2)**
- Database schema (users, listings, bookings, conversations, reviews, audit_log; soft delete columns; spatial index on coordinates)
- Repository layer (BaseRepository with PDO wrapper; all query methods with prepared statements)
- Value Objects (Email, Price, Coordinates, Rating, Category, BookingStatus, MessageBody)
- API Router (regex-based HTTP routing to handlers)
- Session management (PHP sessions; regenerate ID post-login; CSRF token infrastructure)

*Deliverable:* No user-facing feature; infrastructure ready to build on.

*Risk if skipped:* Later phases will have bad patterns baked in (SQL injection, soft delete leaks, missing transactions).

---

**Phase 2: Authentication (Week 3)**
- AuthService (register, login, logout, password hashing with PASSWORD_BCRYPT)
- Auth Controllers (POST /api/auth/register, /api/auth/login, /api/auth/logout, GET /api/auth/me)
- User Profiles (avatar upload, bio, rating display)
- Frontend: Login/register forms, navigation state

*Deliverable:* Users can sign up and log in.

*Risk if skipped:* No user context; can't build listings yet.

---

**Phase 3: Listings (Weeks 4-5)**
- GeoService (address geocoding via Google Maps API; Haversine distance; bounding box queries)
- ListingService (CRUD, validation, soft delete, status workflow)
- Image upload handler (MIME validation + magic bytes; re-encode to strip metadata; filesystem storage in public/uploads/)
- Listing Controllers (POST /api/listings, GET /api/listings/{id}, GET /api/listings search+filter, PATCH edit, DELETE soft delete)
- Frontend: Listing form, listing detail page, listing list with pagination

*Deliverable:* Users can create listings with photos; view others' listings.

*Pitfalls to address:* Image file upload security (re-encode); geolocation precision (store 7+ decimals); soft delete filtering.

*Risk if skipped:* No inventory; can't demo marketplace.

---

**Phase 4: Map & Search (Week 6)**
- ListingRepository spatial queries (findNearby with distance calculation; indexed on coordinates + status)
- Search/filter endpoints (GET /api/listings/search?lat=40.7&lng=-74&radius=5&category=phone&minPrice=100&maxPrice=500)
- Frontend: Google Maps embed on homepage; live filter sliders (distance, price, condition)

*Deliverable:* Users discover listings on map; filter by distance/price/condition.

*Pitfalls to address:* Spatial index performance; coordinate precision at boundary conditions.

*Risk if skipped:* No discovery mechanism; searches feel broken.

---

**Phase 5: Chat (Weeks 7-8)**
- ChatService (initiate conversation; send message; fetch history; count unread)
- Chat Controllers (POST /api/conversations, GET /api/conversations, GET /api/conversations/{id}/messages, POST message)
- Frontend: Conversation list, chat window, 3-second polling for new messages

*Deliverable:* Buyers and sellers can message each other about listings.

*Pitfalls to address:* N+1 query problem (eager-load sender via JOIN); polling resource exhaustion (cache results; implement long-polling in Phase 2).

*Risk if skipped:* No communication channel; coordination impossible.

---

**Phase 6: Bookings (Week 9)**
- BookingService (create reservation with transaction wrapper; state machine: pending → confirmed → completed)
- BookingRepository (transactional save with concurrent booking prevention via SELECT...FOR UPDATE)
- Booking Controllers (POST /api/bookings, GET /api/bookings/{id}, PATCH confirm, PATCH complete, PATCH cancel)
- Frontend: Reserve button on listing; booking list in dashboard with status badges

*Deliverable:* Users can reserve listings; sellers can confirm; track transaction status.

*Pitfalls to address:* Transaction atomicity (booking + chat must be atomic); double-booking race condition (pessimistic locking + unique constraint).

*Risk if skipped:* No transaction workflow; inventory coordination breaks.

---

**Phase 7: Reviews (Week 10)**
- ReviewService (create review; recalculate user avg_rating denormalized; validate booking completed)
- Review Controllers (POST /api/reviews, GET /api/reviews/user/{id})
- Frontend: Review form post-transaction (modal); user profile shows rating + review history

*Deliverable:* Users build reputation; ratings visible on profiles.

*Pitfalls to address:* Soft delete filtering on reputation system; review gaming (rate limiting, cooldown).

*Risk if skipped:* No trust signal; stranger risk too high.

---

**Phase 8: Polish & Admin (Week 11)**
- Favorites feature (save items, wishlist dashboard)
- Admin reporting (POST /api/listings/{id}/report, GET /api/admin/reports, PATCH approve/reject)
- Frontend: Report button on listings; admin dashboard (report queue, moderation history)
- Error handling (standardized error responses, 404 pages, validation messages)
- Performance (indexing, pagination, lazy-load images)

*Deliverable:* Users can save listings; admins can moderate; UX feels complete.

---

### Research Flags & Phase Dependencies

| Phase | Needs Research? | Notes |
|-------|-----------------|-------|
| Phase 1 (Foundation) | **NO** — High confidence | Database schema, PDO patterns, value objects are proven; patterns clear from ARCHITECTURE.md |
| Phase 2 (Auth) | **NO** — High confidence | Session management, password hashing well-documented in PITFALLS.md; standard PHP patterns |
| Phase 3 (Listings) | **MONITOR** — Medium confidence | Image upload security critical; test polyglot files + re-encoding; geolocation precision needs boundary testing |
| Phase 4 (Map/Search) | **NO** — High confidence | Spatial indexing patterns clear; Google Maps API straightforward; test query performance with mock 1000+ listings |
| Phase 5 (Chat) | **MONITOR** — Medium confidence | N+1 problem well-documented but needs perf testing at load; polling vs long-polling trade-off |
| Phase 6 (Bookings) | **NO** — High confidence | Transaction patterns documented in ARCHITECTURE.md; race condition prevention clear (locking + constraints) |
| Phase 7 (Reviews) | **NO** — High confidence | Reputation system pattern proven; denormalization strategy clear |
| Phase 8 (Polish) | **NO** — Standard feature | Admin panel, favorites are straightforward; error handling is boilerplate |

**When to trigger deep `/gsd-research-phase` during planning:**
- Phase 3: Image upload optimization (file size, thumbnail caching strategy)
- Phase 4: Performance benchmarking of spatial queries (if >10K listings expected soon)
- Phase 5: Long-polling vs WebSocket decision (benchmark polling latency with concurrent users)

---

## Confidence Assessment

| Area | Confidence | Evidence | Gaps |
|------|-----------|----------|------|
| **Technology Stack** | **HIGH** | All versions verified Q1 2026; no EOL packages; PHP 7.4-8.4 compatibility confirmed; library versions audited | None significant. Assume 8.4 ecosystem stable enough for v1. |
| **Feature Completeness** | **HIGH** | 11 table stakes confirmed against PROJECT.md; differentiators vs anti-features clearly categorized; MVP scope crisp | Uncertainty on feature priority (e.g., Verified Badges—Phase 1 or Phase 2?). Recommend validation during planning. |
| **Architecture** | **HIGH** | Layered pattern proven at scale; transaction boundaries explicit; scalability path clear (100→10K→1M users); anti-patterns documented | Assumption: single MySQL instance sufficient to 10K users. Needs monitoring/capacity planning; may need sharding earlier. |
| **Security Posture** | **HIGH** | Critical pitfalls identified; prevention strategies concrete; OWASP alignment verified | Assumption: team discipline on prepared statements + code review. If team inexperienced with security, recommend mandatory training Phase 0. |
| **Performance** | **MEDIUM** | Assumed: PDO connection pooling sufficient, no caching needed <10K users, polling acceptable for chat | Unknown: real latency of Google Maps API calls (may need caching); chat polling threshold (when does 3s poll become bottleneck?); image handling at scale (CDN transition point). |
| **Geolocation** | **MEDIUM** | Google Maps API approach sound; Haversine formula correct; spatial indexes strategy clear | Unknown: precision loss edge cases (poles, equator); best spatial index implementation (ST_Distance vs geohash vs bounding box). Recommend Phase 4 load testing. |

**Known Gaps:**
1. **API authentication for v2** — JWT vs OAuth vs API key? Defer to Phase 2 planning.
2. **Payment processing decision** — Research which payment provider (Stripe, PayPal) if v2 moves away from cash-only.
3. **Image CDN strategy** — When to migrate from filesystem to S3/CloudFront? Recommend Phase 4 capacity planning.
4. **Chat real-time upgrade** — At what user count does polling become unacceptable? Need perf testing during Phase 5.
5. **Mobile app decision** — Web-first responsive design vs native iOS/Android? Defer to post-MVP market validation.

---

## Build Sequence Dependencies

```
Phase 1: Foundation
    ↓ (blocks everything else)
Phase 2: Auth
    ↓ (users must exist)
Phase 3: Listings
    ├→ Phase 4: Map/Search (depends on listings existing)
    └→ Phase 5: Chat (depends on listings + users + auth)
        ↓ (depends on chat infrastructure)
Phase 6: Bookings (chat required for buyer-seller negotiation)
    ↓ (only rate after transaction completes)
Phase 7: Reviews
    ↓ (after features exist; not blocking)
Phase 8: Polish/Admin
```

**Critical path:** 1→2→3→6→7 (auth, listings, bookings, reviews form the core transaction loop)

**Parallel work possible:** Phases 4 and 5 can start before 6 if architecture is solid. Phase 8 can run in parallel with later phases.

---

## MVP Success Criteria

Marketplace is **feature-complete MVP** when:
- [ ] Users can register, log in, and maintain profiles with avatar + bio
- [ ] Users can create listings with title, description, photos (5 angles), price, condition, category, address
- [ ] Users can search/filter listings by price, distance (via map), condition, category
- [ ] Users can view listings on interactive Google Map with markers; click to detail
- [ ] Users can message each other asynchronously about listings
- [ ] Users can reserve/book listings; sellers can confirm; status shows pending/confirmed/completed
- [ ] Users can rate each other 1-5 stars after transaction; ratings display on profile
- [ ] Users can save listings to favorites/wishlist
- [ ] Admins can flag and hide inappropriate listings
- [ ] GDPR: users can delete accounts (soft delete with audit trail)
- [ ] All database queries use prepared statements; soft delete filters applied everywhere; transactions atomic where needed

**Launch-blocking pitfalls:**
- [ ] No SQL injection vulnerabilities (all queries parameterized)
- [ ] Deleted users/listings don't leak into public queries
- [ ] Double-booking prevented (SELECT...FOR UPDATE or unique constraint)
- [ ] Image uploads can't execute code (re-encoded, outside web root)

---

## Sources & Confidence Summary

| Research File | Quality | Key Contributions |
|---------------|---------|-------------------|
| **STACK.md** | Excellent | Technology versions verified Q1 2026; rationale for each choice; no deprecated packages; clear "what not to use" section |
| **FEATURES.md** | Excellent | 11 table stakes validated; differentiators vs anti-features clearly separated; feature dependencies mapped; MVP scope defined; complexity estimates per feature |
| **ARCHITECTURE.md** | Excellent | Layered pattern with explicit component boundaries; data flow diagrams; build sequencing with critical path; scalability assumptions; 6 critical anti-patterns with examples |
| **PITFALLS.md** | Excellent | 12 pitfalls (5 critical, 4 moderate, 3 minor) with prevention strategies; phase-specific warnings; marketplace vulnerabilities; scaling considerations |

**Overall Research Confidence: HIGH**

All research files are well-sourced (OWASP, PHP-FIG, official documentation, domain case studies) and provide actionable, concrete recommendations. No major contradictions between files; synthesis is coherent.

---

## Next Steps for Roadmap Planning

1. **Validate MVP scope** with stakeholders (11 features confirmed; confirm post-MVP differentiators)
2. **Estimate team capacity** (9 weeks assumes 2-person team; adjust phases if team size differs)
3. **Secure dependencies** (Google Maps API key ready? Server infrastructure booked? MySQL version confirmed?)
4. **Assign Phase leads** (who owns Foundation? Auth? Listings? Chat?)
5. **Schedule Phase 0 kick-off:**
   - Set up git repo with .env.example, .gitignore, composer.json
   - Database setup (MySQL 8.0+, test connection)
   - Pre-commit hooks (prepared statement linting, secret detection)
   - Team security training (SQL injection prevention, session management, GDPR requirements)

**Roadmap is ready to proceed to detailed Phase planning once stakeholders confirm feature scope and team assignments are finalized.**

---

**Research synthesis complete. All 4 research dimensions integrated; architectural decisions validated; risks mitigated. Ready for requirements definition.**

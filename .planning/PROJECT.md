# ReuseIT - Marketplace per Elettronica Usata

## What This Is

ReuseIT is a web marketplace for buying and selling used electronic devices. **v1.0 shipped:** Secure backend infrastructure with authentication, listing management with photo upload, interactive geolocation map with distance filtering, end-to-end booking workflow with pickup negotiation, automatic chat conversation creation on booking, reputation system with user ratings, favorites/wishlist, and admin moderation. Users can discover nearby used electronics, book items for in-person pickup, communicate with counterparties, and exchange ratings after completion. The platform focuses on decentralized peer-to-peer transactions with reputation-based trust.

## Core Value

Create a trustworthy, geographically-aware peer-to-peer marketplace for used electronics where users can discover nearby items, communicate directly, and build reputation through ratings.

## Requirements

### Validated (v1.0 Shipped)

- ✓ User registration and authentication with email/password and session persistence — v1.0
- ✓ User profiles with bio, rating statistics, and transaction history — v1.0
- ✓ Listing creation with category, title, description, price, condition — v1.0
- ✓ Photo upload and storage for listings — v1.0
- ✓ Interactive map visualization showing active listings with location markers — v1.0
- ✓ Search and filtering (keyword search, category, price range, distance radius, condition) — v1.0
- ✓ Listing geolocation using address-to-coordinates conversion — v1.0
- ✓ Booking system for reserving items with status workflow (pending → confirmed → completed) — v1.0
- ✓ Automatic chat conversation creation on booking — v1.0
- ✓ Conversation management with message history and unread tracking — v1.0
- ✓ Review and rating system (1-5 stars) for post-transaction reputation — v1.0
- ✓ User statistics (active listings, completed sales, average rating) — v1.0
- ✓ Favorites/wishlist for saving listings — v1.0
- ✓ Admin reporting functionality and content moderation — v1.0
- ✓ API response consistency and error handling — v1.0
- ✓ Pickup date/time negotiation with role-aware action hints — v1.0
- ✓ Booking cancellation workflow — v1.0

### Active (v2.0 Backlog)

- [ ] User avatar upload and profile image management (backend ready, UI deferred)
- [ ] Chat message UI and real-time message delivery (automatic conversation creation done, UI/messaging deferred)
- [ ] Listing CRUD UI (backend infrastructure ready, UI deferred)
- [ ] Profile editing UI (backend infrastructure ready, UI deferred)
- [ ] Email notifications for bookings and messages (v2.0 feature)
- [ ] Verified seller badges (email/phone verification) — v2.0
- [ ] Seller promotion tools (bulk listings, analytics) — v2.0

### Out of Scope (Deferred to v2+)

- Mobile app (web-first, mobile optimization later)
- Real-time chat with WebSockets (polling-based implementation sufficient for MVP)
- Payment processing (cash/in-person only for initial version)
- SMS/Email notifications (future v2 feature)
- Video listings (images only for v1)
- Video hosting (filesystem storage only, not cloud)

## Context

**Current State (v1.0 Shipped):**
- Backend: Full layered architecture with 8 phases of development
- Database: Normalized MySQL schema with soft delete, timestamps, spatial indexing
- API: Complete REST endpoints for auth, listings, bookings, conversations, reviews, favorites, admin
- Frontend: Ready for HTML/CSS/JavaScript UI implementation
- Codebase: ~5,000 LOC PHP + database migrations, test structure established
- Coverage: 54/54 v1 requirements mapped; 17 shipped in v1.0, 37 deferred to v2.0+
- Timeline: 9 weeks total (March 23 — May 10, 2026)

**Technical Environment:**
- Backend: PHP 7.4+ plain (no framework) for showcase architecture with full control
- Database: MySQL with PDO prepared statements for security
- Frontend: HTML/CSS/JavaScript vanilla (no build tools, fetch API for communication)
- Mapping: Google Maps API for geolocation and visualization
- Authentication: PHP native sessions (stateful)
- File storage: Filesystem-based (local storage for MVP)

**Architecture Pattern:**
- Layered architecture: Controllers → Services → Repositories
- Separation of concerns with clear responsibility boundaries
- Repository pattern for data access abstraction
- Value Objects for domain validation (Email, Price, Coordinates)
- DTOs for inter-layer data transfer
- Soft delete for GDPR compliance and audit trail

**Technical Decisions Made (v1.0):**
- Plain PHP chosen to showcase architecture mastery (not framework dependency) — ✓ Validated
- PDO + prepared statements required (prevent SQL injection) — ✓ Validated
- Password hashing with password_hash() + verify — ✓ Validated
- Session-based auth (no JWT for simplicity) — ✓ Validated
- Haversine formula for distance calculations (no Google Distance Matrix API for cost) — ✓ Validated
- Filesystem image storage (no cloud CDN for MVP) — ✓ Validated
- Layered architecture pattern — ✓ Validated
- Soft delete strategy for GDPR compliance — ✓ Validated

**Core Modules Shipped (v1.0):**
1. Auth & Users (registration, login, session management, profiles, statistics)
2. Listings (creation, photo upload, geolocation, search, filtering)
3. Bookings (reservation workflow, pickup negotiation, status management, cancellation)
4. Chat & Messaging (conversation creation, message history, unread tracking)
5. Reviews & Ratings (reputation system, user rating calculation)
6. Favorites & Reports (wishlist, content reporting, admin moderation)
7. API Infrastructure (REST endpoints, error handling, response consistency)

## Constraints

- **Tech Stack**: PHP 7.4+, MySQL, vanilla JavaScript, Google Maps API — non-negotiable for showcase project
- **Database**: Normalized 3NF schema with soft delete, timestamps, and proper indexing
- **Frontend**: No dependencies or build tools — vanilla HTML/CSS/JS only
- **Security**: PDO prepared statements mandatory for all queries, password_hash() for authentication
- **Performance**: Index critical columns (status, coordinates, user_id), implement pagination, lazy load images
- **Deployment**: Simple Apache-based hosting with .htaccess rewrite rules

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Plain PHP (no framework) | Showcase architecture mastery, full control, educational value | ✓ Shipped v1.0 |
| Vanilla JavaScript (no build tools) | Simplicity, no dependencies, direct browser execution | ✓ Ready for v2.0 UI |
| Google Maps API integration | Standard geolocation solution, well-documented, geocoding support | ✓ Shipped v1.0 |
| Filesystem image storage | MVP simplicity, no cloud vendor lock-in, easier local testing | ✓ Shipped v1.0 |
| Session-based auth (no JWT) | Simpler implementation, natural PHP integration, sufficient for MVP | ✓ Shipped v1.0 |
| Layered architecture pattern | Clear separation of concerns, maintainable code, extensible design | ✓ Shipped v1.0 |
| Soft delete strategy | GDPR compliance, audit trail preservation, data recovery capability | ✓ Shipped v1.0 |
| Pickup negotiation workflow | Buyer-first proposal model with counter support for realistic P2P flow | ✓ Shipped v1.0 |

---
*Last updated: 2026-05-10 after v1.0 milestone completion*

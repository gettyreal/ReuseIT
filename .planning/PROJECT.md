# ReuseIT - Marketplace per Elettronica Usata

## What This Is

ReuseIT is a web marketplace for buying and selling used electronic devices. **v1.0 shipped:** Secure backend infrastructure with authentication, listing management with photo upload, interactive geolocation map with distance filtering, end-to-end booking workflow with pickup negotiation, automatic chat conversation creation on booking, reputation system with user ratings, favorites/wishlist, and admin moderation. **v2.0 focus:** Complete frontend implementation consuming v1.0 APIs and following Figma design specifications. Users will have intuitive, responsive interfaces to discover nearby used electronics, book items for in-person pickup, communicate with counterparties, and exchange ratings. The platform delivers trustworthy peer-to-peer transactions with reputation-based trust.

## Core Value

Create a trustworthy, geographically-aware peer-to-peer marketplace for used electronics where users can discover nearby items, communicate directly, and build reputation through ratings.

## Current Milestone: v2.0 Frontend

**Goal:** Deliver complete frontend implementation for all user-facing features, consuming v1.0 backend APIs and following Figma design specifications. Build fast with vanilla HTML/CSS/JavaScript, maximizing reusable component library for maintainability.

**Target Features:**
- Complete auth UI (registration, login, password reset)
- User profile management (view, edit, avatar upload)
- Listing browsing, search, filtering, and detailed views
- Interactive map with distance-based discovery
- Listing creation and management UI
- Booking workflow UI with pickup negotiation
- Chat/messaging interface with conversation management
- Review and rating submission UI
- Favorites/wishlist management
- Admin moderation dashboard
- Vanilla CSS component library for reusability

**Constraints:**
- Desktop-only for v2.0 (mobile later)
- No build tools — pure vanilla HTML/CSS/JavaScript
- localStorage for client state management
- Direct fetch() API calls to backend
- Best-effort accessibility (WCAG considerations)
- Ship fast — phased delivery over perfect completeness

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

### Active (v2.0 Frontend Implementation)

- [ ] Frontend architecture design and component planning
- [ ] Design system extraction from Figma (tokens, components, patterns)
- [ ] Authentication UI (login, registration, password reset)
- [ ] User profile UI (view, edit, avatar management)
- [ ] Listing discovery UI (browse, search, filter, map)
- [ ] Listing detail view with photos and seller info
- [ ] Listing creation and management UI
- [ ] Booking workflow UI (booking form, negotiation, status tracking)
- [ ] Chat/messaging UI (conversations, message history, send messages)
- [ ] Review and rating submission UI
- [ ] Favorites/wishlist UI
- [ ] Admin moderation dashboard
- [ ] Error handling and loading states across all pages
- [ ] Form validation and user feedback
- [ ] Vanilla CSS component library

### Out of Scope (Deferred to v2.1+)

- Mobile app (web-first, mobile optimization after v2.0)
- Real-time chat with WebSockets (polling/manual refresh sufficient for v2.0)
- Email notifications (v2.1 feature)
- SMS notifications (future feature)
- Payment processing (cash/in-person only)
- Verified seller badges (v2.1 feature)
- Seller analytics and promotion tools (v2.1 feature)

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

**Technical Decisions (v1.0):**
- Plain PHP chosen to showcase architecture mastery (not framework dependency) — ✓ Shipped v1.0
- PDO + prepared statements required (prevent SQL injection) — ✓ Shipped v1.0
- Password hashing with password_hash() + verify — ✓ Shipped v1.0
- Session-based auth (no JWT for simplicity) — ✓ Shipped v1.0
- Haversine formula for distance calculations (no Google Distance Matrix API for cost) — ✓ Shipped v1.0
- Filesystem image storage (no cloud CDN for MVP) — ✓ Shipped v1.0
- Layered architecture pattern — ✓ Shipped v1.0
- Soft delete strategy for GDPR compliance — ✓ Shipped v1.0

**Technical Decisions (v2.0 Frontend):**
- Vanilla HTML/CSS/JavaScript (no frameworks, no build tools) — Simple, direct browser execution
- localStorage + sessionStorage for client state — No backend session required for UI
- Vanilla CSS component library — Reusable, maintainable, no CSS-in-JS overhead
- Figma design extraction via One CLI/MCP — Fast design-to-code workflow
- Direct fetch() API calls — No abstraction layer, keep it simple
- Desktop-only for v2.0 — Responsive design not prioritized, ship fast

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
*Last updated: 2026-05-10 after v2.0 milestone initialization*

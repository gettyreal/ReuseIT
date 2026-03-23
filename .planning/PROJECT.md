# ReuseIT - Marketplace per Elettronica Usata

## What This Is

ReuseIT is a web marketplace for buying and selling used electronic devices. Users can publish listings for devices they want to sell, discover nearby used electronics on an interactive map, book items for in-person pickup, communicate with buyers/sellers via chat to arrange meetups, and exchange ratings after transaction completion. The platform focuses on decentralized peer-to-peer transactions with reputation-based trust.

## Core Value

Create a trustworthy, geographically-aware peer-to-peer marketplace for used electronics where users can discover nearby items, communicate directly, and build reputation through ratings.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] User registration and authentication with email/password and session persistence
- [ ] User profiles with avatar, bio, rating statistics, and transaction history
- [ ] Listing creation with category, title, description, price, condition, and photo upload
- [ ] Interactive map visualization showing active listings with location markers
- [ ] Search and filtering (category, price range, distance radius, condition)
- [ ] Listing geolocation using address-to-coordinates conversion
- [ ] Booking system for reserving items with status workflow (pending → confirmed → completed)
- [ ] Real-time chat messaging between buyer and seller for meetup coordination
- [ ] Conversation management with unread message tracking
- [ ] Review and rating system (1-5 stars) for post-transaction reputation building
- [ ] Favorites/wishlist for saving listings
- [ ] User statistics (active listings, completed sales, average rating)
- [ ] Admin reporting functionality for content moderation

### Out of Scope

- Mobile app (web-first, mobile optimization later)
- Real-time chat with WebSockets (polling-based implementation sufficient for MVP)
- Payment processing (cash/in-person only for initial version)
- SMS/Email notifications (future v2 feature)
- Video listings (images only for v1)
- Video hosting (filesystem storage only, not cloud)

## Context

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

**Technical Decisions:**
- Plain PHP chosen to showcase architecture mastery (not framework dependency)
- PDO + prepared statements required (prevent SQL injection)
- Password hashing with password_hash() + verify
- Session-based auth (no JWT for simplicity)
- Haversine formula for distance calculations (no Google Distance Matrix API for cost)
- Filesystem image storage (no cloud CDN for MVP)

**Core Modules:**
1. Auth & Users (registration, login, profiles, avatars, statistics)
2. Listings (CRUD, geolocation, filtering, photo upload)
3. Bookings (reservation workflow, status management)
4. Chat & Messaging (conversations, messaging, unread tracking)
5. Reviews & Ratings (reputation system, user rating calculation)
6. Favorites & Reports (wishlist, content moderation)

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
| Plain PHP (no framework) | Showcase architecture mastery, full control, educational value | — Pending |
| Vanilla JavaScript (no build tools) | Simplicity, no dependencies, direct browser execution | — Pending |
| Google Maps API integration | Standard geolocation solution, well-documented, geocoding support | — Pending |
| Filesystem image storage | MVP simplicity, no cloud vendor lock-in, easier local testing | — Pending |
| Session-based auth (no JWT) | Simpler implementation, natural PHP integration, sufficient for MVP | — Pending |
| Layered architecture pattern | Clear separation of concerns, maintainable code, extensible design | — Pending |
| Soft delete strategy | GDPR compliance, audit trail preservation, data recovery capability | — Pending |

---
*Last updated: 2026-03-23 after initialization*

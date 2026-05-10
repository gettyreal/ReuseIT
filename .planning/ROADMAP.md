# ReuseIT Development Roadmap

**Last Updated:** 2026-05-10  
**Current Status:** v1.0 Shipped (Phases 1-8)  
**Next Milestone:** v2.0 UI & Features (planned)

---

## Milestones

- ✅ **v1.0 MVP** — Phases 1-8 (shipped 2026-05-10) — Full backend infrastructure with booking, geolocation, chat integration, and reputation system
- 📋 **v2.0 UI & Features** — (planned) — Frontend implementation and deferred features (avatars, chat UI, admin dashboard)

---

## Phases

<details open>
<summary>📋 v2.0 UI & Features (In Planning)</summary>

To be defined during next milestone planning phase.

</details>

<details>
<summary>✅ v1.0 MVP (Phases 1-8) — SHIPPED 2026-05-10</summary>

### Phase 1: Foundation (Weeks 1-2)
**Status:** ✓ Complete (1/1 plans)

**Goal:** Infrastructure is ready for user-facing features. All request handling, data persistence, and security patterns are established and tested.

**Deliverables:**
- Database Schema with normalized 3NF design, soft delete, timestamps, and spatial indexing
- Repository Layer with PDO prepared statements and transaction support
- Value Objects for domain validation (Email, Price, Coordinates, Rating, Category, BookingStatus)
- API Router with regex-based HTTP routing
- Response Envelope with consistent JSON format
- Session Infrastructure with CSRF token generation
- Error Handling with centralized error catching

**Key Accomplishment:** Secure foundation with layered architecture (Controllers → Services → Repositories) following clean architecture principles.

---

### Phase 2: Authentication & User Profiles (Week 3)
**Status:** ✓ Complete (3/3 plans)

**Goal:** Users can identify themselves to the system. Registration, login, and profile management enable user context for all subsequent features.

**Deliverables:**
- Auth Service (registration, login, logout, session management)
- Auth Controllers for /api/auth endpoints
- User Profile Service (viewing, editing, avatar management)
- Session Validation with secure cookie flags and session ID regeneration
- User Statistics (denormalized in users table)
- Authorization Middleware for protected endpoints

**Key Accomplishment:** Full authentication infrastructure with password hashing, session persistence, and role-aware access control.

---

### Phase 3: Listings & Photo Upload (Weeks 4-5)
**Status:** ✓ Complete (3/3 plans)

**Goal:** Users can publish items for sale. Listing creation with photos and metadata makes the marketplace functional; geolocation integration enables discovery.

**Deliverables:**
- Listing Service with CRUD and validation
- Geolocation Service (address-to-coordinates conversion via Google Maps)
- Image Upload Handler with MIME validation, magic byte verification, metadata stripping
- Listing Controllers for full REST API
- Listing Search Endpoints with category/price/condition filtering

**Key Accomplishment:** Complete listing and photo upload infrastructure with secure image handling and geolocation integration.

---

### Phase 4: Map & Search Discovery (Week 6)
**Status:** ✓ Complete (3/3 plans)

**Goal:** Users can discover nearby listings. Interactive map visualization and distance-based filtering enable users to find items in their area.

**Deliverables:**
- Spatial Query Service with Haversine distance calculations
- Search/Filter Controllers with radius-based queries
- Map API Integration with Google Maps
- Distance-based sorting and bounding box queries

**Key Accomplishment:** Interactive geolocation discovery with 5km radius filtering and distance-sorted results.

---

### Phase 5: Chat & Messaging (Weeks 7-8)
**Status:** ✓ Complete (3/3 plans)

**Goal:** Buyers and sellers can communicate. Chat infrastructure enables negotiation and coordination for pickup.

**Deliverables:**
- Conversation Service and Controllers
- Message Service with unread tracking
- Pagination for message history
- Auto-creation of conversations when bookings are made
- Unread count tracking per conversation

**Key Accomplishment:** Full conversation and messaging infrastructure with automatic booking-triggered chat creation.

---

### Phase 6: Bookings & Transaction Workflow (Weeks 9-10)
**Status:** ✓ Complete (4/4 plans)

**Goal:** Users can reserve items and coordinate pickup. Booking state machine with pickup negotiation workflow enables buyer-seller coordination.

**Deliverables:**
- Booking Service with status workflow (pending → confirmed → completed/cancelled)
- Pickup Window Service with proposal/counter negotiation
- Booking Controllers with role-aware dashboards
- Role-aware next_actions derivation for API payloads
- Open proposal lookup for action gating
- Seller confirmation workflow with urgency metadata

**Key Accomplishment:** End-to-end booking workflow with pickup date/time negotiation, buyer-first proposal model, and seller counter-proposal support.

---

### Phase 7: Reviews & Reputation System (Week 11)
**Status:** ✓ Complete (3/3 plans)

**Goal:** Users build reputation through ratings. Review and rating system enables trust-building after transactions.

**Deliverables:**
- Review Service with 1-5 star ratings and optional comments
- Review Controllers (create, view, list user reviews)
- Reputation Service with average rating calculations
- Review gating (only after booking completion)
- User profile integration with rating display and review counts

**Key Accomplishment:** Complete reputation system with completion gates and user rating aggregation.

---

### Phase 8: Favorites, Admin, & Polish (Week 12)
**Status:** ✓ Complete (5/5 plans)

**Goal:** Users can save favorites and admins can moderate. Wishlist functionality and content moderation enable community safety.

**Deliverables:**
- Favorites Service (add, remove, list user favorites)
- Report Service (user/listing reporting)
- Admin Service (report management, user/listing moderation)
- Report Controllers for community moderation
- Admin Controllers for content management
- Admin user role with special permissions

**Key Accomplishment:** Complete favorites system and admin moderation infrastructure for community safety.

---

</details>

---

## Progress Summary

| Phase | Name | Plans | Status | Completed |
|-------|------|-------|--------|-----------|
| 1 | Foundation | 1/1 | ✓ Complete | 2026-03-31 |
| 2 | Authentication & User Profiles | 3/3 | ✓ Complete | 2026-04-07 |
| 3 | Listings & Photo Upload | 3/3 | ✓ Complete | 2026-04-14 |
| 4 | Map & Search Discovery | 3/3 | ✓ Complete | 2026-04-21 |
| 5 | Chat & Messaging | 3/3 | ✓ Complete | 2026-04-28 |
| 6 | Bookings & Transaction | 4/4 | ✓ Complete | 2026-05-05 |
| 7 | Reviews & Reputation | 3/3 | ✓ Complete | 2026-05-07 |
| 8 | Favorites, Admin, & Polish | 5/5 | ✓ Complete | 2026-05-10 |

**Totals:** 8 phases, 25 plans, 100% complete

---

## v1.0 MVP Scope

**What Shipped:** Complete backend infrastructure enabling peer-to-peer marketplace transactions:
- User authentication and profiles with statistics
- Listing creation and photo upload
- Interactive geolocation map with distance filtering
- Real-time search and category/price filtering
- End-to-end booking workflow with pickup negotiation
- Automatic chat integration on booking
- User reputation system with 1-5 star ratings
- Favorites/wishlist functionality
- Admin content reporting and moderation

**What's in v2.0 Backlog:** Frontend UI implementation and deferred features (user avatars, chat message UI, listing CRUD UI, email notifications, verified seller badges).

---

## Architecture Decisions (v1.0)

- **Plain PHP** (no framework) — showcases architecture mastery and full control
- **Layered architecture** (Controllers → Services → Repositories) — clean separation of concerns
- **PDO prepared statements** — security against SQL injection
- **Soft delete** — GDPR compliance and audit trail
- **Session-based auth** (no JWT) — simpler implementation
- **Haversine distance** (no Google Distance Matrix API) — cost efficiency
- **Filesystem image storage** — no cloud vendor lock-in

---

For detailed phase information, see archived roadmap: `.planning/milestones/v1.0-ROADMAP.md`

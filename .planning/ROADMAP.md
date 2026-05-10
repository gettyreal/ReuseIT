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
<summary>📋 v2.0 Frontend Implementation (Phases 9-16) — IN PLANNING</summary>

**Phases:** 8 (Phases 9–16)  
**Total Requirements:** 45  
**Coverage:** 45/45 mapped ✓  

The v2.0 Frontend Milestone implements a complete user-facing interface for the ReuseIT marketplace. The backend (v1.0) is complete and stable; this work focuses on consuming APIs through vanilla HTML/CSS/JavaScript interfaces, following Figma design specifications. Work is grouped into 8 phases emphasizing user journeys and minimal dependencies, allowing parallel development of discovery and management features.

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

---

## v2.0 Frontend Implementation: Detailed Roadmap

### Phase 9: Foundation & Authentication

**Goal:** Establish frontend entry point with working authentication and reusable component infrastructure.

**Depends on:** Nothing (foundational)

**Requirements:** FE-AUTH-01, FE-AUTH-02, FE-AUTH-03, FE-AUTH-04, FE-UX-05

**Success Criteria** (what must be TRUE when phase completes):
1. User can register with email/password on a functional registration page with client-side validation
2. User can log in with email/password and session persists across browser refresh
3. User can reset forgotten password via email link simulation (frontend form + backend validation flow)
4. User can log out and return to login page with session fully cleared
5. Navigation header/sidebar visible and functional on all pages (root layout component)
6. Vanilla CSS component library initialized with reusable patterns (buttons, forms, cards, notifications)

---

### Phase 10: Profiles & Reputation

**Goal:** Enable users to view and edit their profiles, see reputation metrics, and discover reviews.

**Depends on:** Phase 9 (auth must work)

**Requirements:** FE-PROF-01, FE-PROF-02, FE-PROF-03, FE-PROF-04, FE-REV-02, FE-REV-03

**Success Criteria** (what must be TRUE when phase completes):
1. User can view own profile showing name, bio, location, avatar, and stats (active listings, completed sales, average rating)
2. User can edit profile (name, bio, location) and save changes
3. User can upload and view avatar on profile
4. User can view another user's profile with their transaction statistics and review history
5. User can see reviews/ratings list on profile with ratings breakdown (star distribution)
6. User statistics dashboard shows active listings, completed sales count, and average rating from backend data

---

### Phase 11: Listing Discovery

**Goal:** Provide users ability to browse, search, filter, and view detailed listing information.

**Depends on:** Phase 9 (auth) + Phase 10 (profile display in seller info)

**Requirements:** FE-LIST-01, FE-LIST-02, FE-LIST-03, FE-LIST-04, FE-FAV-01, FE-FAV-02, FE-FAV-03

**Success Criteria** (what must be TRUE when phase completes):
1. User can browse all listings in a grid/list view with toggle between display modes
2. User can search listings by keyword, filter by category, price range, and condition
3. User can view detailed listing page with all photos, full description, seller info, and action buttons
4. User can add/remove listings from favorites (wishlist) with visual indicators
5. User can view Favorites page showing all saved listings with ability to sort/filter
6. Listings display includes seller profile link and average rating for trust-building

---

### Phase 12: Map & Location Discovery

**Goal:** Enable geographic discovery with interactive map, distance filtering, and location-based search.

**Depends on:** Phase 9 (auth) + Phase 11 (listing browsing to compare with map view)

**Requirements:** FE-MAP-01, FE-MAP-02, FE-MAP-03, FE-MAP-04

**Success Criteria** (what must be TRUE when phase completes):
1. Interactive Google Map displays all active listings as markers with listing preview on hover
2. User can filter listings by distance radius (from user's location or search location)
3. User can click marker to see listing preview and drill-down to full detail page
4. User can search for a location address and map recenters to that location automatically
5. Distance in kilometers displays on listings when browsing from map view

---

### Phase 13: Listing Management

**Goal:** Enable users to create, edit, and manage their own listings.

**Depends on:** Phase 9 (auth) + Phase 11 (listing detail view for reference)

**Requirements:** FE-LMGMT-01, FE-LMGMT-02, FE-LMGMT-03, FE-LMGMT-04

**Success Criteria** (what must be TRUE when phase completes):
1. User can create listing with category, title, description, price, condition, and multiple photo uploads
2. User can edit existing listing (update any field including photos)
3. User can delete/cancel their own listing with confirmation dialog
4. User can view "My Listings" page showing all own listings with current status (active, sold, delisted) and quick edit/delete actions
5. Photo upload shows preview before saving with multiple file selection support

---

### Phase 14: Booking Workflow

**Goal:** Implement complete booking lifecycle from creation through completion with negotiation.

**Depends on:** Phase 11 (listing detail where booking starts) + Phase 9 (auth)

**Requirements:** FE-BOOK-01, FE-BOOK-02, FE-BOOK-03, FE-BOOK-04, FE-BOOK-05, FE-BOOK-06

**Success Criteria** (what must be TRUE when phase completes):
1. User (buyer) can create booking for a listing with initial booking form (creates conversation with seller)
2. User can view "My Bookings" page with separate Buyer and Seller views, filtered by role
3. User can view booking detail page showing status (pending/confirmed/completed), item info, and next action hints
4. Buyer can propose pickup date/time and seller receives negotiation request
5. Seller can accept proposal, reject, or submit counter-proposal with different date/time
6. User can confirm booking completion and trigger review prompt (leads to Phase 15 chat)

---

### Phase 15: Chat & Messaging

**Goal:** Enable direct communication between buyers and sellers through conversations and messages.

**Depends on:** Phase 14 (booking creates conversations) + Phase 9 (auth)

**Requirements:** FE-CHAT-01, FE-CHAT-02, FE-CHAT-03, FE-CHAT-04, FE-CHAT-05, FE-REV-01

**Success Criteria** (what must be TRUE when phase completes):
1. User can view Conversations list page showing all active conversations (one per booking) with most recent message preview
2. User can open a conversation to see full message history sorted chronologically
3. User can type and send messages in real-time (manual refresh/polling model, no WebSockets)
4. User sees unread message indicators (badge count, visual highlight) on conversation list and detail
5. Chat interface supports message pagination/loading earlier messages
6. User can submit a review (1-5 stars + comment) after booking completion from chat or booking detail page

---

### Phase 16: Admin & Polish

**Goal:** Provide admin moderation tools and finalize UX/form handling across all pages.

**Depends on:** Phase 9 (auth) + all previous phases (cross-cutting)

**Requirements:** FE-ADMIN-01, FE-ADMIN-02, FE-ADMIN-03, FE-UX-01, FE-UX-02, FE-UX-03, FE-UX-04

**Success Criteria** (what must be TRUE when phase completes):
1. Admin user can access moderation dashboard showing reported listings and users
2. Admin can view reported content and take moderation actions (remove listing, suspend user)
3. All pages display loading spinners and states during API calls (no frozen UI)
4. Form validation (auth, profile, listing, booking, chat) provides real-time feedback and prevents invalid submissions
5. Error messages display consistently across pages (API errors, validation errors, network errors)
6. Desktop layout is responsive and functional at 1024px+ with proper spacing and typography

---

## v2.0 Progress Table

| Phase | Goal | Requirements | Success Criteria | Plans Complete | Status |
|-------|------|--------------|------------------|-----------------|--------|
| 9 | Foundation & Auth | 5 | 6 | 0/TBD | Not started |
| 10 | Profiles & Reputation | 6 | 6 | 0/TBD | Not started |
| 11 | Listing Discovery | 7 | 6 | 0/TBD | Not started |
| 12 | Map & Location | 4 | 5 | 0/TBD | Not started |
| 13 | Listing Management | 4 | 5 | 0/TBD | Not started |
| 14 | Booking Workflow | 6 | 6 | 0/TBD | Not started |
| 15 | Chat & Messaging | 7 | 6 | 0/TBD | Not started |
| 16 | Admin & Polish | 8 | 6 | 0/TBD | Not started |
| **TOTAL** | **8 phases** | **45 requirements** | **46 criteria** | **0/TBD** | **Planning** |

---

## v2.0 Architecture Notes

**Frontend Structure:**
- Single-page application (SPA) with client-side routing via URL hash (#/path)
- HTML template files for each page/view
- Vanilla CSS component library (buttons, forms, cards, modals, notifications)
- localStorage for auth token, user preferences, draft recovery
- localStorage for unread message tracking and favorites state

**Component Library (grows throughout phases):**
- Phase 9: Buttons, forms, headers, navigation, modals
- Phase 10: Profile cards, stats displays, review ratings
- Phase 11: Listing cards, grids, filters, search inputs
- Phase 12: Map controls, markers, location search
- Phase 13: Photo uploader, listing form, status badges
- Phase 14: Booking detail, negotiation UI, timeline
- Phase 15: Chat bubbles, message input, unread indicators
- Phase 16: Admin tables, error alerts, loading states, form validation UI

**API Integration Pattern:**
- Each page has fetch() calls to v1.0 endpoints
- Error handling with user-friendly messages
- Loading states prevent UI freezing
- No abstraction layer (direct fetch, keep it simple)

**State Management:**
- localStorage for persistent auth token and session
- Form state in component memory (not persisted)
- Unread message counts in localStorage
- Favorites in localStorage + synced to backend

**Design Integration:**
- Figma tokens extracted via One CLI for:
  - Color palette (primary, secondary, states)
  - Typography (sizes, weights, line heights)
  - Spacing scale (4px, 8px, 16px, etc.)
  - Border radius, shadows, transitions
- CSS custom properties (variables) for easy theming

---

## v2.0 Dependency Graph

```
Phase 9 (Auth, Nav, Component Library)
    ↓
    ├─→ Phase 10 (Profiles) ──────┐
    │                             ├─→ Phase 11 (Listing Discovery) ──┐
    ├─→ Phase 11 (Listing Browse) ┘                                 ├─→ Phase 14 (Booking)
    │                                                                │
    ├─→ Phase 12 (Map)  ───────────→ Phase 11 (Discovery context)  ├─→ Phase 15 (Chat + Reviews)
    │                                                                │
    └─→ Phase 13 (Listing Create)  ─→ Phase 14 (Booking)           └─→ Phase 16 (Admin + UX)
                                           ↓
                                      Phase 15 (Chat)
```

**Parallelization Opportunities:**
- Phase 10 (Profiles) and Phase 13 (Listing Management) can start in parallel after Phase 9
- Phase 11 (Discovery) and Phase 12 (Map) are largely parallel (shared listing display)
- Phase 15 (Chat) can start after Phase 14 begins (conversations already created)
- Phase 16 (Polish) can overlap with earlier phases as cross-cutting concern

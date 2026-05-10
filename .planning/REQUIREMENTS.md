# Requirements: ReuseIT v2.0 Frontend

**Defined:** 2026-05-10  
**Core Value:** Create a trustworthy, geographically-aware peer-to-peer marketplace for used electronics where users can discover nearby items, communicate directly, and build reputation through ratings.

## v2 Requirements (Frontend Implementation)

### Authentication UI

- [ ] **FE-AUTH-01**: Registration page with email/password form and validation
- [ ] **FE-AUTH-02**: Login page with persistent session handling
- [ ] **FE-AUTH-03**: Password reset flow (email link simulation)
- [ ] **FE-AUTH-04**: Logout functionality with session cleanup

### User Profiles

- [ ] **FE-PROF-01**: Profile view page showing user stats (ratings, sales, listings)
- [ ] **FE-PROF-02**: Profile edit form (name, bio, location)
- [ ] **FE-PROF-03**: Avatar upload and display
- [ ] **FE-PROF-04**: User statistics dashboard (active listings, completed sales, avg rating)

### Listings Discovery

- [ ] **FE-LIST-01**: Listings browse page with grid/list view
- [ ] **FE-LIST-02**: Search functionality (keyword, category, price range, condition filter)
- [ ] **FE-LIST-03**: Listing detail page with photos, seller info, actions
- [ ] **FE-LIST-04**: Favorites/wishlist add/remove from listings

### Map & Discovery

- [ ] **FE-MAP-01**: Interactive Google Maps display with listing markers
- [ ] **FE-MAP-02**: Distance radius filtering on map
- [ ] **FE-MAP-03**: Marker click preview and drill-down to detail
- [ ] **FE-MAP-04**: Location search and map centering

### Listing Management

- [ ] **FE-LMGMT-01**: Create listing form (category, title, description, price, condition, photos)
- [ ] **FE-LMGMT-02**: Edit existing listing
- [ ] **FE-LMGMT-03**: Delete/cancel listing
- [ ] **FE-LMGMT-04**: My listings view with status tracking

### Bookings

- [ ] **FE-BOOK-01**: Booking creation form for a listing
- [ ] **FE-BOOK-02**: My bookings page (separated buyer/seller views)
- [ ] **FE-BOOK-03**: Booking detail with status and next actions
- [ ] **FE-BOOK-04**: Pickup date/time proposal form
- [ ] **FE-BOOK-05**: Pickup counter-proposal UI for seller
- [ ] **FE-BOOK-06**: Booking confirmation and completion workflow

### Chat & Messaging

- [ ] **FE-CHAT-01**: Conversations list page
- [ ] **FE-CHAT-02**: Chat detail page with message history
- [ ] **FE-CHAT-03**: Message input and send
- [ ] **FE-CHAT-04**: Unread message indicators
- [ ] **FE-CHAT-05**: Message pagination/loading

### Reviews & Ratings

- [ ] **FE-REV-01**: Review submission form (1-5 stars + comment)
- [ ] **FE-REV-02**: User reviews list on profile
- [ ] **FE-REV-03**: Review display with ratings breakdown

### Favorites

- [ ] **FE-FAV-01**: Favorites page listing all saved items
- [ ] **FE-FAV-02**: Add/remove from favorites
- [ ] **FE-FAV-03**: Sort/filter favorites

### Admin

- [ ] **FE-ADMIN-01**: Admin moderation dashboard
- [ ] **FE-ADMIN-02**: View reported content
- [ ] **FE-ADMIN-03**: User/listing moderation actions (remove, suspend)

### UI & Experience

- [ ] **FE-UX-01**: Loading states and spinners across pages
- [ ] **FE-UX-02**: Error messages and notifications
- [ ] **FE-UX-03**: Form validation and user feedback
- [ ] **FE-UX-04**: Responsive layout for desktop
- [ ] **FE-UX-05**: Navigation header/sidebar across all pages

## v2.1 Requirements

### Deferred Features

- **FE-NOTIF-01**: Email notifications for bookings and messages
- **FE-NOTIF-02**: In-app notification bell and toast messages
- **FE-SELLER-01**: Verified seller badges (email/phone verification display)
- **FE-SELLER-02**: Seller analytics dashboard (bulk listing tools, pricing suggestions)
- **FE-MOBILE-01**: Mobile responsive design and optimization
- **FE-SOCKET-01**: Real-time chat with WebSockets

## Out of Scope

| Feature | Reason |
|---------|--------|
| Mobile app | Desktop-only for v2.0, mobile optimization planned for v2.1+ |
| Real-time chat with WebSockets | Polling-based message refresh sufficient for v2.0 |
| Email notifications | Server-side infrastructure ready, UI deferred to v2.1 |
| SMS notifications | Not planned for initial version |
| Payment processing | Cash/in-person only, no payment gateway integration |
| Verified seller badges | Infrastructure ready, feature deferred to v2.1 |
| Seller analytics tools | Advanced reporting deferred to v2.1 |
| OAuth/3rd-party login | Email/password sufficient for v2.0 |
| Dark mode | Best-effort accessibility only, no theme switching |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| FE-AUTH-01 | Phase 9 | Pending |
| FE-AUTH-02 | Phase 9 | Pending |
| FE-AUTH-03 | Phase 9 | Pending |
| FE-AUTH-04 | Phase 9 | Pending |
| FE-PROF-01 | Phase 10 | Pending |
| FE-PROF-02 | Phase 10 | Pending |
| FE-PROF-03 | Phase 10 | Pending |
| FE-PROF-04 | Phase 10 | Pending |
| FE-LIST-01 | Phase 11 | Pending |
| FE-LIST-02 | Phase 11 | Pending |
| FE-LIST-03 | Phase 11 | Pending |
| FE-LIST-04 | Phase 11 | Pending |
| FE-MAP-01 | Phase 12 | Pending |
| FE-MAP-02 | Phase 12 | Pending |
| FE-MAP-03 | Phase 12 | Pending |
| FE-MAP-04 | Phase 12 | Pending |
| FE-LMGMT-01 | Phase 13 | Pending |
| FE-LMGMT-02 | Phase 13 | Pending |
| FE-LMGMT-03 | Phase 13 | Pending |
| FE-LMGMT-04 | Phase 13 | Pending |
| FE-BOOK-01 | Phase 14 | Pending |
| FE-BOOK-02 | Phase 14 | Pending |
| FE-BOOK-03 | Phase 14 | Pending |
| FE-BOOK-04 | Phase 14 | Pending |
| FE-BOOK-05 | Phase 14 | Pending |
| FE-BOOK-06 | Phase 14 | Pending |
| FE-CHAT-01 | Phase 15 | Pending |
| FE-CHAT-02 | Phase 15 | Pending |
| FE-CHAT-03 | Phase 15 | Pending |
| FE-CHAT-04 | Phase 15 | Pending |
| FE-CHAT-05 | Phase 15 | Pending |
| FE-REV-01 | Phase 16 | Pending |
| FE-REV-02 | Phase 16 | Pending |
| FE-REV-03 | Phase 16 | Pending |
| FE-FAV-01 | Phase 17 | Pending |
| FE-FAV-02 | Phase 17 | Pending |
| FE-FAV-03 | Phase 17 | Pending |
| FE-ADMIN-01 | Phase 18 | Pending |
| FE-ADMIN-02 | Phase 18 | Pending |
| FE-ADMIN-03 | Phase 18 | Pending |
| FE-UX-01 | Phase 19 | Pending |
| FE-UX-02 | Phase 19 | Pending |
| FE-UX-03 | Phase 19 | Pending |
| FE-UX-04 | Phase 19 | Pending |
| FE-UX-05 | Phase 19 | Pending |

**Coverage:**
- v2 requirements: 45 total
- Mapped to phases: 0 (to be determined by roadmapper)
- Unmapped: 45

---
*Requirements defined: 2026-05-10*
*Last updated: 2026-05-10 after v2.0 requirements definition*

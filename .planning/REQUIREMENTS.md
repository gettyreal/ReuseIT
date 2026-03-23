# Requirements: ReuseIT

**Defined:** 2026-03-23
**Core Value:** Create a trustworthy, geographically-aware peer-to-peer marketplace for used electronics where users can discover nearby items, communicate directly, and build reputation through ratings.

## v1 Requirements

### Authentication & Users

- [ ] **AUTH-01**: User can register with email, password, and location (address or coordinates)
- [ ] **AUTH-02**: User can log in with email and password
- [ ] **AUTH-03**: User session persists across browser refresh
- [ ] **AUTH-04**: User can log out from any page
- [ ] **USER-01**: User can view their profile (name, avatar, bio, statistics)
- [ ] **USER-02**: User can edit their profile (name, bio, location)
- [ ] **USER-03**: User can upload and change their avatar image
- [ ] **USER-04**: User can view statistics (active listings count, completed sales, average rating)

### Listings & Discovery

- [ ] **LIST-01**: User can create a listing with category, title, description, price, condition
- [ ] **LIST-02**: User can upload multiple photos for a listing
- [ ] **LIST-03**: User can edit their own listings
- [ ] **LIST-04**: User can delete/cancel their own listings
- [ ] **LIST-05**: User can view all active listings in a sortable/filterable list
- [ ] **LIST-06**: User can view listing details including photos, seller info, price, condition
- [ ] **LIST-07**: User can filter listings by category, price range, condition
- [ ] **LIST-08**: User can search listings by keyword (title, description)

### Geolocation & Map

- [ ] **GEO-01**: Listing address is converted to latitude/longitude coordinates
- [ ] **GEO-02**: User can view an interactive map showing all active listings as markers
- [ ] **GEO-03**: User can click map markers to preview listing information
- [ ] **GEO-04**: User can filter map view by distance radius from their location
- [ ] **GEO-05**: User can see nearby listings sorted by distance

### Bookings & Transactions

- [ ] **BOOK-01**: User can book/reserve a listing for in-person pickup
- [ ] **BOOK-02**: Booking creates a conversation between buyer and seller
- [ ] **BOOK-03**: User can view their bookings (separate buyer and seller views)
- [ ] **BOOK-04**: Booking status workflow: pending → confirmed → completed / cancelled
- [ ] **BOOK-05**: Seller can confirm a pending booking
- [ ] **BOOK-06**: User can schedule pickup date/time for a confirmed booking
- [ ] **BOOK-07**: User can mark a booking as completed after pickup
- [ ] **BOOK-08**: User can cancel a booking (if not completed)

### Chat & Messaging

- [ ] **CHAT-01**: User can view all their conversations
- [ ] **CHAT-02**: User can open a conversation and view message history
- [ ] **CHAT-03**: User can send messages to negotiate pickup details
- [ ] **CHAT-04**: Messages show unread count for each conversation
- [ ] **CHAT-05**: User can mark messages as read
- [ ] **CHAT-06**: System automatically creates conversation when booking is made

### Reviews & Reputation

- [ ] **REV-01**: User can leave a review (1-5 stars + optional comment) after completed booking
- [ ] **REV-02**: Review is only available after pickup marked as completed
- [ ] **REV-03**: User can view their own reviews received from others
- [ ] **REV-04**: System calculates and displays user's average rating
- [ ] **REV-05**: User profile shows number of reviews received

### Favorites & Wishlist

- [ ] **FAV-01**: User can save listings to a favorites/wishlist
- [ ] **FAV-02**: User can view their saved favorites
- [ ] **FAV-03**: User can remove listings from favorites

### Admin & Moderation

- [ ] **ADMIN-01**: Admin user role exists with special permissions
- [ ] **ADMIN-02**: User can report listings as inappropriate
- [ ] **ADMIN-03**: User can report other users as suspicious
- [ ] **ADMIN-04**: Admin can view reported content
- [ ] **ADMIN-05**: Admin can remove/hide reported listings
- [ ] **ADMIN-06**: Admin can suspend/ban problematic users

### API Infrastructure

- [x] **API-01**: All frontend-backend communication uses JSON REST API
- [x] **API-02**: API returns consistent response format (success/error with data/message)
- [ ] **API-03**: API enforces authentication for protected endpoints
- [x] **API-04**: API validates all inputs before processing
- [x] **API-05**: API handles errors gracefully with descriptive messages

## v2 Requirements

### Notifications (Future)

- **NOTF-01**: User receives email notification for new bookings on their listings
- **NOTF-02**: User receives email notification when seller confirms booking
- **NOTF-03**: User receives in-app notification of new messages
- **NOTF-04**: User can configure notification preferences

### Enhanced Features (Future)

- **ENH-01**: Verified Seller Badge (email + phone verification)
- **ENH-02**: Bulk listing tools for power sellers
- **ENH-03**: Pricing suggestions based on comparable listings
- **ENH-04**: Saved searches/alerts for specific categories
- **ENH-05**: Buyer protection (escrow or dispute resolution)
- **ENH-06**: Mobile app (native or PWA)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Payment Processing | High compliance liability; in-person cash only for MVP. Use Stripe in v2 if needed. |
| Shipping Integration | Breaks P2P model. ReuseIT is hyperlocal, in-person pickup only. |
| Real-time Chat (WebSockets) | Polling-based async chat sufficient for MVP. Upgrade only if load testing justifies. |
| Video Listings | Storage/bandwidth costs too high. Photos only for v1. |
| Mobile App | Web-first. Mobile optimization deferred to v2. |
| Video Calls for Inspection | Out of scope; users meet in person. |
| Automated Dispute Resolution | Manual admin resolution sufficient for MVP. Escrow integration deferred. |
| OAuth Login | Email/password sufficient for v1. OAuth deferred to v2. |
| Advanced Analytics Dashboard | Basic stats only. Advanced metrics defer to v2+. |
| Multi-language Support | English only for MVP. i18n deferred to v2. |
| Dark Mode | Out of scope for MVP. UI polish deferred. |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Success Criterion | Status |
|-------------|-------|-------------------|--------|
| AUTH-01 | Phase 2 | User registration creates account with email/password/location | Mapped |
| AUTH-02 | Phase 2 | Session persists across browser refresh | Mapped |
| AUTH-03 | Phase 2 | User session persists across browser refresh | Mapped |
| AUTH-04 | Phase 2 | User can log out from any page | Mapped |
| USER-01 | Phase 2 | User can view their profile (name, avatar, bio, statistics) | Mapped |
| USER-02 | Phase 2 | User can edit their profile (name, bio, location) | Mapped |
| USER-03 | Phase 3 | Photos uploaded and stored securely | Mapped |
| USER-04 | Phase 2 | User can view statistics (active listings count, completed sales, average rating) | Mapped |
| LIST-01 | Phase 3 | Listing created with all required fields | Mapped |
| LIST-02 | Phase 3 | Photos uploaded and stored securely | Mapped |
| LIST-03 | Phase 3 | User can edit/filter listings by category, price, condition | Mapped |
| LIST-04 | Phase 3 | Listing soft-deleted when cancelled | Mapped |
| LIST-05 | Phase 3 | User can view all active listings in a sortable/filterable list | Mapped |
| LIST-06 | Phase 3 | Listing created with all required fields | Mapped |
| LIST-07 | Phase 4 | Listings returned filtered by category/price/condition | Mapped |
| LIST-08 | Phase 4 | Keyword search filters by title and description | Mapped |
| GEO-01 | Phase 3 | Address converted to coordinates | Mapped |
| GEO-02 | Phase 4 | Interactive map displays listing markers | Mapped |
| GEO-03 | Phase 4 | Clicking marker shows listing preview | Mapped |
| GEO-04 | Phase 4 | Map filter by distance radius returns nearby listings | Mapped |
| GEO-05 | Phase 4 | Listings sorted by distance (nearest first) | Mapped |
| BOOK-01 | Phase 6 | User can create booking for a listing | Mapped |
| BOOK-02 | Phase 6 | Conversation created automatically | Mapped |
| BOOK-03 | Phase 6 | Buyer/seller see different booking views | Mapped |
| BOOK-04 | Phase 6 | Booking status workflow progresses correctly | Mapped |
| BOOK-05 | Phase 6 | Seller cannot double-book listing | Mapped |
| BOOK-06 | Phase 6 | User can schedule pickup date/time for a confirmed booking | Mapped |
| BOOK-07 | Phase 6 | User can mark a booking as completed after pickup | Mapped |
| BOOK-08 | Phase 6 | User can cancel a booking (if not completed) | Mapped |
| CHAT-01 | Phase 5 | User can view list of all conversations | Mapped |
| CHAT-02 | Phase 5 | User can open a conversation and view message history | Mapped |
| CHAT-03 | Phase 5 | User can send and receive messages | Mapped |
| CHAT-04 | Phase 5 | Unread count tracks correctly | Mapped |
| CHAT-05 | Phase 5 | Message history loads with pagination | Mapped |
| CHAT-06 | Phase 6 | Conversation created automatically | Mapped |
| REV-01 | Phase 7 | Review created with rating and comment | Mapped |
| REV-02 | Phase 7 | Review only allowed after booking completed | Mapped |
| REV-03 | Phase 7 | Review history visible on profile | Mapped |
| REV-04 | Phase 7 | User avg_rating calculated correctly | Mapped |
| REV-05 | Phase 7 | User profile shows rating and review count | Mapped |
| FAV-01 | Phase 8 | User can save listing to favorites | Mapped |
| FAV-02 | Phase 8 | User can view sorted/filtered favorites | Mapped |
| FAV-03 | Phase 8 | User can remove listings from favorites | Mapped |
| ADMIN-01 | Phase 8 | Admin can ban suspicious user | Mapped |
| ADMIN-02 | Phase 8 | User can report inappropriate listing | Mapped |
| ADMIN-03 | Phase 8 | User can report other users as suspicious | Mapped |
| ADMIN-04 | Phase 8 | Admin can view and process reports | Mapped |
| ADMIN-05 | Phase 8 | Admin can view and process reports | Mapped |
| ADMIN-06 | Phase 8 | Admin can ban suspicious user | Mapped |
| API-01 | Phase 1 | Response envelope works consistently | Mapped |
| API-02 | Phase 1 | Response envelope works consistently | Mapped |
| API-03 | Phase 2 | Session persists across browser refresh | Mapped |
| API-04 | Phase 1 | All database tables exist with correct schema | Mapped |
| API-05 | Phase 1 | Response envelope works consistently | Mapped |

**Coverage:**
- v1 requirements: 54 total
- Mapped to phases: 54
- Unmapped: 0 ✓
- **Status:** ROADMAP CREATED ✓ (2026-03-23)

---
*Requirements defined: 2026-03-23*
*Last updated: 2026-03-23 after initial definition*

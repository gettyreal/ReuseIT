# Phase 10: Profiles & Reputation - Context

**Gathered:** 2026-05-11
**Status:** Ready for planning

<domain>
## Phase Boundary

Enable users to view and edit their profiles, see reputation metrics, and discover reviews. Users can view their own profile with comprehensive stats and edit capabilities, view other users' profiles with trust-building information, upload avatars, and browse reviews with flexible sorting.

</domain>

<decisions>
## Implementation Decisions

### Profile Context & Permissions
- Own profile shows basic stats (name, bio, location, avatar, active listings, completed sales, average rating)
- Other users' profiles show standard view (name, bio, location, avatar, key stats, reviews)
- Edit button always visible on own profile only
- Different views for own vs other users (own profile shows more detail)

### Avatar Handling
- Simple upload (no cropping required)
- Multiple sizes stored on backend (200px for profile, 48px for lists, thumbnails) for best quality
- Initials-based default fallback when avatar not uploaded (e.g., "JD" for John Doe)

### Profile Editing Flow
- Modal dialog interface for editing (quick edits, clear cancel path)
- All editable fields in modal: name, bio, location, avatar upload
- Client-side + server validation (prevent bad data client-side, handle business rules server-side)
- Display backend errors inline in form fields

### Profile Layout & Information
- Vertical card layout (mobile-friendly, natural scroll flow)
- Visual stats display with icons/colors/trends (scannable, engaging)
- Extended 6 stats per profile: Active Listings, Completed Sales, Average Rating, Member Since, Response Time, Total Reviews Count

### Reviews & Ratings Display
- Rating summary shows average rating + review count (e.g., "4.8 ⭐ from 127 reviews")
- Standard review list format (not card-based)
- Standard review details per review: rating stars, reviewer name, review text, item being reviewed, transaction date
- User-selectable review sort: newest first (default), highest rated first, oldest first

### OpenCode's Discretion
- Profile card visual styling and shadows
- Exact stat card layout and spacing
- Avatar cropping on backend (if implementation chooses to crop server-side)
- Color coding for rating visual indicators
- Exact modal styling and animation

</decisions>

<specifics>
## Specific Ideas

- Own profile should feel like a personal dashboard (more stats visible)
- Other users' profiles should focus on trust signals (reviews, stats, verification)
- Avatar fallback (initials) should match the design system color palette
- Modal editing should be quick and non-disruptive (not a full page load)

</specifics>

<deferred>
## Deferred Ideas

- Face detection avatar cropping — future enhancement
- Profile privacy/visibility controls — future phase
- Profile badges and verification system — may belong in Phase 16 (Admin & Polish)
- Profile follower/following — not in scope for Phase 10

</deferred>

---

*Phase: 10-profiles-reputation*
*Context gathered: 2026-05-11*

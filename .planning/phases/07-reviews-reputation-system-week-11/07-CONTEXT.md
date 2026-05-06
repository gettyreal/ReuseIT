# Phase 7: Reviews & Reputation System (Week 11) - Context

**Gathered:** 2026-05-06
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase delivers a review and reputation system that enables trust-building after transactions complete. Users can leave ratings (1-5 stars) with optional comments on completed bookings, view the reviews they've received from others, and have their average rating and review count displayed on their profile.

Payment processing, escrow, dispute resolution, and advanced reputation features (badges, trust scores, historical trending) remain out of scope for this phase.

</domain>

<decisions>
## Implementation Decisions

### Review submission flow (REV-01, REV-02)
- Reviews can only be submitted after a booking is marked as **completed** (state = 'completed').
- Review submission is available to both **buyer and seller** (either party can initiate).
- A review consists of:
  - **Rating:** 1-5 stars (whole numbers only, no decimals)
  - **Comment:** Optional, max 500 characters, plain text only (no markdown/HTML)
  - **Booking reference:** Link to the completed booking to prevent duplicate reviews

### Rating presentation (REV-04)
- User's **average rating** is calculated as: `SUM(ratings) / COUNT(reviews)` rounded to 1 decimal place.
- Average rating is displayed as **star visualization** (not numeric score) on user profile.
- A **distribution breakdown** is shown: count of 5-star, 4-star, 3-star, 2-star, 1-star reviews.
- **Interactive 5-star picker** is used in the review submission modal (click to select, visual feedback).

### Review history and visibility (REV-03, REV-05)
- Users can view all reviews they have **received** from others on their profile.
- Reviews are displayed in **newest-first** order.
- Review history is **paginated** (OpenCode discretion: page size, default 10 per page).
- Each review shows:
  - **Reviewer name + avatar** (small thumbnail)
  - **Star rating** (visual stars, no numeric display)
  - **Comment text** (truncated to 100 chars, with "expand" button for full text)
  - **Date posted** (relative or absolute, OpenCode discretion)

### Denormalization and data consistency (REV-04, REV-05)
- User profile denormalization: `users.avg_rating` and `users.total_reviews` columns.
- These are updated **atomically** in the same transaction as review creation.
- Review deletion/editing: **Reviews are immutable** after posting (no edit/delete; must be admin-handled if disputes arise).
- Soft delete filtering: Deleted users' reviews remain in DB but are hidden from queries via soft-delete trait.

### Rate limiting and spam prevention
- **One review per 24 hours per user** (max 1 review posted per user per day).
- Duplicate prevention: **One review per booking** (prevent multiple reviews for same booking).
- Review content: Plain text only, no scripting or markdown evaluation server-side.

### OpenCode's Discretion
- Exact pagination size (suggest 10 per page; no pagination < 5 items).
- Badge thresholds (e.g., "Trusted Seller" at 50+ reviews, 4.5+ rating).
- Milestone badge display rules (always visible on profile or only when earned).
- Review submission modal styling and star interaction animations.
- Exact rate-limiting enforcement (per IP, per user, or both).
- Calculation rounding for average rating (nearest 0.1, 0.5, or exact decimal).

</decisions>

<specifics>
## Specific Ideas

- Keep review submission modal simple: star picker + textarea + submit button.
- Profile rating section should be visually prominent (above fold if possible).
- Show review count prominently ("4.8★ from 23 reviews") to encourage reading.
- Star visualization should match the picker style for consistency.
- Truncated comments with expand should lazy-load full text via AJAX if desired.

</specifics>

<deferred>
## Deferred Ideas

- Email notifications for new reviews (Phase 8+ or v2).
- Review flagging / moderation workflows (Phase 8 admin tools).
- Seller response capability (future enhancement).
- Review verification badges ("Verified Purchase").
- Analytics dashboard for reputation trends.

</deferred>

---

*Phase: 07-reviews-reputation-system-week-11*
*Context gathered: 2026-05-06*

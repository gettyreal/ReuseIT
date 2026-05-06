# Phase 7: Reviews & Reputation System (Week 11) - Planning Complete

**Planning Date:** 2026-05-06
**Status:** ✓ READY FOR EXECUTION
**Total Plans:** 3 in 3 sequential waves
**Total Tasks:** 9 (3 per plan)

---

## Executive Summary

Phase 7 delivers a trust-building review and reputation system enabling users to rate each other (1-5 stars) after completed bookings and view accumulated ratings on their profiles. The system includes immutable reviews with optional comments, automatic denormalization of average rating and review count, rate limiting (1 review per 24 hours), and a complete UI flow from modal submission to profile history display.

**Critical Path:** Wave 1 (schema) → Wave 2 (service logic) → Wave 3 (HTTP + frontend)
**Parallel Opportunities:** All three waves must execute sequentially (strict dependencies)
**Autonomous Execution:** Yes (all plans are fully autonomous within their wave)

---

## Wave Structure & Dependency Graph

```
Wave 1: Schema Foundation
├─ 07-01-PLAN.md
│  ├─ Task 1: Reviews migration + user denormalization
│  ├─ Task 2: ReviewRepository CRUD + queries
│  └─ Task 3: Rating value object (1-5 validation)
│
└─ Outputs: reviews table, user.avg_rating, user.total_reviews,
            ReviewRepository, Rating value object
            
           ↓ (blocks Wave 2)
           
Wave 2: Service & Validation Logic
├─ 07-02-PLAN.md (depends_on: 07-01)
│  ├─ Task 1: ReviewService with atomicity + denormalization
│  ├─ Task 2: RateLimitService (1 per 24 hrs) + ReviewRateLimitRepository
│  └─ Task 3: BookingRepository.getBookingStatus() helper
│
└─ Outputs: ReviewService, RateLimitService, ReviewRateLimitRepository,
            atomic review+rating updates
            
           ↓ (blocks Wave 3)
           
Wave 3: HTTP API & Frontend UI
└─ 07-03-PLAN.md (depends_on: 07-02)
   ├─ Task 1: ReviewController (POST /api/reviews, GET /api/reviews/user/:id, GET /api/users/:id/stats)
   ├─ Task 2: UserController.getProfile() enhancement + Router registration
   └─ Task 3: Frontend components (modal, history, profile rating display, styling, interaction)
   
   Outputs: 3 HTTP endpoints, 3 frontend components (modal, history, rating),
            complete review submission + display workflow
```

---

## Requirement Coverage

| Req | Title | Plan | Task | Wave |
|-----|-------|------|------|------|
| **REV-01** | User can leave review (1-5 stars + optional comment) | 07-03 | Task 1, 3 | 3 |
| **REV-02** | Review only available after pickup marked completed | 07-02 | Task 1 | 2 |
| **REV-03** | User can view reviews received from others | 07-03 | Task 1, 3 | 3 |
| **REV-04** | System calculates and displays avg rating | 07-02 | Task 1 | 2 |
| **REV-05** | User profile shows number of reviews received | 07-02, 07-03 | Task 1, 2 | 2-3 |

**Coverage:** 5/5 requirements (100%)

---

## Plan Details

### Plan 07-01: Schema & Model Foundation (Wave 1) — AUTONOMOUS ✓

**Objective:** Create persistent foundation for reviews with schema migration, user denormalization, and value object validation.

**Dependencies:** None (Wave 1 starter)

**Files Modified:**
- `config/migrations/20260506_phase07_reviews.sql` — Create reviews table + alter users table
- `src/Repositories/ReviewRepository.php` — CRUD + user statistics queries
- `src/ValueObjects/Rating.php` — 1-5 star validation

**Tasks:**
1. **Task 1:** Phase 7 reviews migration
   - `reviews` table: id, reviewer_user_id, reviewed_user_id, booking_id (UNIQUE), rating (CHECK 1-5), comment (VARCHAR 500), soft-delete
   - Indexes: (reviewed_user_id, created_at), (booking_id), (reviewer_user_id)
   - User denormalization: `avg_rating` DECIMAL(3,2), `total_reviews` INT
   - **Verify:** UNIQUE(booking_id) constraint, 500-char comment limit, deleted_at present

2. **Task 2:** ReviewRepository
   - Methods: create(), find(), findByBookingId(), findByUserId() (newest-first), countByUserId(), exists()
   - Soft-delete filtering on all SELECT queries
   - findByUserId includes reviewer metadata (name, avatar)
   - **Verify:** Soft-delete filtering, prepared statements, newest-first ordering

3. **Task 3:** Rating value object
   - Constructor validation: 1-5 inclusive
   - Methods: getValue(), getAsStars() (★★★★☆ visualization), equals(), __toString()
   - Immutable after construction
   - **Verify:** Rating(4)->getAsStars() = "★★★★☆", Rating(0) throws InvalidArgumentException

**Success Criteria:**
- ✓ reviews table with UNIQUE(booking_id) and soft-delete
- ✓ User denormalization columns (avg_rating, total_reviews)
- ✓ ReviewRepository supports read/write with soft-delete filtering
- ✓ Rating value object enforces 1-5 range

---

### Plan 07-02: Service Logic & Validation (Wave 2) — AUTONOMOUS ✓

**Objective:** Implement ReviewService with validation (booking completed, no duplicate, rate limiting) and atomic denormalization (avg_rating, total_reviews).

**Dependencies:** 07-01 (schema + repositories)

**Files Modified:**
- `src/Services/ReviewService.php` — Core review logic, atomicity, denormalization
- `src/Services/RateLimitService.php` — 1-per-24-hour rate limiting
- `src/Repositories/ReviewRateLimitRepository.php` — Rate limit persistence
- `src/Repositories/BookingRepository.php` (updated) — Add getBookingStatus() helper

**Tasks:**
1. **Task 1:** ReviewService
   - `submitReview(bookingId, reviewerId, rating, comment): array`
     - Validate: booking status = 'completed'
     - Validate: reviewer is booking participant
     - Validate: no existing review for booking
     - Validate: rate limit 1 per 24 hrs
     - Validate: rating 1-5, comment ≤500 chars, plain text
     - **ATOMIC:** Begin transaction → insert review → recalculate avg_rating → recalculate total_reviews → update users denormalization → record rate limit → commit
     - Return review record + updated user stats
   - `getUserReviews(userId, limit, offset): array` — Paginated, newest-first, soft-delete filtered
   - `getUserStats(userId): array` — Return {avg_rating, total_reviews, distribution: {5-1: counts}}
   - **Verify:** Transaction boundary, atomic denormalization, validation gates

2. **Task 2:** RateLimitService + ReviewRateLimitRepository
   - ReviewRateLimitRepository: create(), findLatestByUserId(), deleteOlderThan()
   - Table: review_rate_limits (user_id, submitted_at)
   - RateLimitService: isReviewLimited(userId), recordReviewSubmission(userId), cleanup()
   - Rate limit window: 24 hours (locked decision)
   - **Verify:** NOW() - 24 HOURS < latest submission → blocked

3. **Task 3:** BookingRepository helper
   - Add `getBookingStatus(bookingId): ?string` — Return 'pending'|'confirmed'|'completed'|'cancelled'|null
   - Used by ReviewService for validation
   - **Verify:** Returns correct status string

**Success Criteria:**
- ✓ submitReview enforces all validation rules
- ✓ Denormalization (avg_rating, total_reviews) atomic with review creation
- ✓ Rate limiting 1 per 24 hours per user
- ✓ Prepared statements + proper exception handling

---

### Plan 07-03: HTTP API & Frontend (Wave 3) — AUTONOMOUS ✓

**Objective:** Expose review service via REST endpoints and implement frontend UI for modal submission and profile display.

**Dependencies:** 07-02 (ReviewService + RateLimitService)

**Files Modified:**
- `src/Controllers/ReviewController.php` — HTTP endpoints
- `src/Controllers/UserController.php` (updated) — Profile enhancement
- `src/Router.php` (updated) — Route registration
- `public/js/reviews.js` — Modal interaction, form submission, interaction
- `public/css/reviews.css` — Styling (stars, modal, rating display)
- `views/components/review-modal.html` — Submission form template
- `views/components/review-history.html` — History list template
- `views/components/profile-rating.html` — Profile rating display template

**Tasks:**
1. **Task 1:** ReviewController
   - `POST /api/reviews` — submitReview()
     - Body: {booking_id, rating (1-5), comment (optional, ≤500 chars)}
     - Returns: 201 Created with review record + reviewer metadata
     - Errors: 400 (invalid), 403 (unauthorized), 429 (rate limit)
     - **Verify:** Authentication enforced, exceptions mapped to HTTP status
   
   - `GET /api/reviews/user/:id` — getReviewsByUser()
     - Query: limit, offset (pagination)
     - Returns: 200 array of reviews with reviewer info, comment preview (100 chars)
     - Public endpoint (no auth required)
     - **Verify:** Newest-first, comment truncation at 100 chars
   
   - `GET /api/users/:id/stats` — getUserStats()
     - Returns: 200 {avg_rating, total_reviews, distribution: {5-1: counts}}
     - Public endpoint
     - **Verify:** Returns null for avg_rating if no reviews, 0 for total_reviews

2. **Task 2:** UserController + Router
   - Enhance `UserController.getProfile()` to include:
     - avg_rating, total_reviews, rating_distribution
   - Router registration:
     - POST /api/reviews → ReviewController.submitReview() [protected]
     - GET /api/reviews/user/:id → ReviewController.getReviewsByUser() [public]
     - GET /api/users/:id/stats → ReviewController.getUserStats() [public]
   - Inject ReviewService, ReviewRepository into Router for ReviewController
   - **Verify:** Profile includes rating fields, all routes registered

3. **Task 3:** Frontend Components
   - `views/components/review-modal.html`
     - Star picker (5 clickable ☆→★), textarea (500 char counter), submit/cancel buttons
     - Error message placeholder
     - Hidden by default, shown on booking completion
   
   - `public/js/reviews.js`
     - `bindStarPicker(modalId)` — Click listeners, visual fill, return selected rating
     - `submitReview(bookingId, rating, comment)` — POST to /api/reviews, show toast/error
     - `loadReviewHistory(userId, page, limit)` — GET paginated reviews
     - `renderReviewHistory(reviews)` — Insert into DOM
     - `toggleCommentExpand(reviewId)` — Show/hide full comment
     - `initializeReviewModal()` — Setup on page load
   
   - `public/css/reviews.css`
     - `.star-picker` — Interactive 5-star with hover animation
     - `.review-modal` — Modal dialog (centered, overlay, rounded)
     - `.rating-display` — Profile star visualization (★★★★☆ 4.5)
     - `.review-item` — Review list item (avatar, name, stars, comment, date)
     - `.comment-truncated` / `.comment-full` — Comment text handling
     - `.rating-distribution` — Bar chart / list of star counts
   
   - `views/components/review-history.html`
     - Paginated review list (default 10 per page)
     - Each: reviewer avatar + name, stars, truncated comment, date, expand button
     - "No reviews yet" message if empty
   
   - `views/components/profile-rating.html`
     - Rating summary: "4.8★ from 23 reviews"
     - Distribution breakdown (5 stars: X, 4 stars: Y, etc.)
     - Link to full history section
   
   - **Verify:** Modal appears on booking completion, star picker works, comment truncate/expand, pagination, consistent styling

**Success Criteria:**
- ✓ ReviewController.submitReview accepts rating + comment, validates, returns proper HTTP codes
- ✓ UserController.getProfile includes rating stats
- ✓ Router registers all 3 endpoints with correct auth
- ✓ Review modal interactive, form submits, handles errors (400/429)
- ✓ User profile displays rating + review history with pagination
- ✓ Comment truncation, comment expansion, pagination controls working
- ✓ Consistent styling and frontend patterns

---

## Observable Truths & Verification

### Wave 1 (Schema)
- [ ] `reviews` table exists with UNIQUE(booking_id) and deleted_at column
- [ ] `users` table has avg_rating and total_reviews columns
- [ ] ReviewRepository.create() inserts reviews; findByBookingId() prevents duplicates
- [ ] Rating(4).getAsStars() returns "★★★★☆"

### Wave 2 (Service Logic)
- [ ] ReviewService.submitReview() enforces: completed booking, no duplicate, rate limit 1/24hrs, valid rating, ≤500 char comment
- [ ] Denormalization: users.avg_rating = SUM(rating)/COUNT(*), users.total_reviews = COUNT(*), updated in same transaction
- [ ] RateLimitService.isReviewLimited() returns true if submission within 24 hours, false otherwise
- [ ] BookingRepository.getBookingStatus() returns status string or null

### Wave 3 (HTTP + Frontend)
- [ ] POST /api/reviews returns 201 on success, 400 on validation, 403 on auth, 429 on rate limit
- [ ] GET /api/reviews/user/:id returns paginated reviews newest-first
- [ ] GET /api/users/:id/stats returns {avg_rating, total_reviews, distribution}
- [ ] UserController.getProfile includes rating fields
- [ ] Review modal appears, star picker selects rating, form submits to /api/reviews
- [ ] User profile displays rating ("4.8★ from 23 reviews") + paginated review history
- [ ] Comment truncated at 100 chars with expand button
- [ ] No rate limit errors (429) when user submits >1 review in 24 hours

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| Atomic denormalization race condition | Low | High | Use transaction + locks, test concurrent reviews |
| Soft-delete leaking deleted user reviews | Medium | Medium | Test filter with deleted users, grep for unfiltered queries |
| Rate limit bypass (frontend-only validation) | Medium | Low | Enforce server-side in ReviewService |
| Duplicate reviews after transaction retry | Low | Medium | UNIQUE(booking_id) constraint at DB level |
| Comment injection (HTML/script) | Low | Medium | Store plain text only, escape on display |

---

## Handoff Checklist

Before marking Wave complete:
- [ ] All syntax checks pass (php -l, rg patterns)
- [ ] All files created in correct locations
- [ ] No SQL injection patterns (grep for `$var` in SQL)
- [ ] Soft-delete filtering present in all SELECT queries
- [ ] Transactions properly bounded (begin/commit/rollback)
- [ ] All endpoints return correct HTTP status codes
- [ ] Authentication enforced on protected endpoints
- [ ] Frontend components load and interact with API
- [ ] Error handling produces user-friendly messages

---

## Timeline & Parallel Execution

**Wave 1 Duration:** ~15 minutes (3 tasks × 5 min each)
- Schema migration: 5 min
- ReviewRepository: 5 min
- Rating value object: 5 min

**Wave 2 Duration:** ~20 minutes (3 tasks × 7 min each)
- ReviewService + atomicity: 8 min
- RateLimitService: 6 min
- BookingRepository helper: 2 min

**Wave 3 Duration:** ~25 minutes (3 tasks × 8 min each)
- ReviewController: 8 min
- UserController + Router: 5 min
- Frontend components: 12 min

**Total Phase 7 Duration:** ~60 minutes (1 hour)

**No Parallel Work:** All waves must execute sequentially (strict dependencies)

---

## Decision Locks (Phase 7 Context)

✓ **Comments:** Optional, 500 char max, plain text only, immutable after posting
✓ **Ratings:** Interactive 5-star picker, whole stars only, display as stars (no numbers), show distribution breakdown
✓ **History:** Paginated view, reviewer name + avatar, truncated comments (100 chars) with expand, newest-first sort
✓ **Rate Limit:** 1 review per 24 hours per user
✓ **Denormalization:** Atomic with review creation, updates users.avg_rating and users.total_reviews
✓ **Validation:** Only after booking marked completed, no duplicate reviews per booking

**OpenCode's Discretion Areas:**
- Pagination size (recommend 10 per page, min 5)
- Badge thresholds (e.g., "Trusted Seller" rules)
- Comment preview character count (recommend 100)
- Average rating rounding (1 decimal recommended)
- Modal styling and star animation details
- Rate limit enforcement details (per IP, per user, or both)

---

## Next Steps

1. **Execute Wave 1:** Run 07-01-PLAN.md tasks in sequence
   - Verify migration applies without errors
   - Confirm ReviewRepository compiles and queries use soft-delete filtering
   - Test Rating value object validation

2. **Execute Wave 2:** Run 07-02-PLAN.md tasks (depends on Wave 1 completion)
   - Verify ReviewService atomic transaction boundaries
   - Test rate limit enforcement (24-hour window)
   - Confirm denormalization updates are correct

3. **Execute Wave 3:** Run 07-03-PLAN.md tasks (depends on Wave 2 completion)
   - Verify HTTP endpoints return correct status codes
   - Test frontend modal interaction
   - Verify profile rating display and review history pagination

4. **Verification:** Run Phase 7 verification checklist before marking complete
   - All success criteria met
   - All requirements (REV-01 through REV-05) covered
   - No defects or deviations from plan

5. **Handoff to Phase 8:** After Phase 7 complete, Phase 8 (Favorites, Admin, Polish) unblocked

---

## Summary Statistics

| Metric | Value |
|--------|-------|
| Total Plans | 3 |
| Total Tasks | 9 |
| Total Files Modified | 9 |
| Lines of Code (estimated) | 1500-2000 |
| Database Tables Modified | 2 (reviews, users) |
| HTTP Endpoints | 3 |
| Frontend Components | 3 |
| Requirements Covered | 5/5 (100%) |
| Estimated Duration | 1 hour |
| Dependency Type | Sequential (no parallelization) |
| Autonomous | Yes (all waves autonomous within phase) |

---

**Planning Status:** ✓ COMPLETE (2026-05-06)
**Ready for Execution:** YES
**Next Phase:** Phase 8 (Favorites, Admin, Polish) — Ready when Phase 7 complete

*Phase 7: Reviews & Reputation System (Week 11) - Planning Complete*

# Plan 07-01 Completion Summary

**Status:** ✓ COMPLETE (2026-05-06)

**Duration:** ~5 minutes

## Objective

Build the persistent data layer for reviews: database schema with user denormalization, ReviewRepository for data access, and Rating value object for validation.

## What Was Built

### 1. Reviews Migration (Task 1)
**File:** `config/migrations/20260506_phase07_reviews.sql`

Created an idempotent SQL migration that:
- Creates `reviews` table with:
  - Core columns: id, reviewer_user_id, reviewed_user_id, booking_id, rating, comment
  - Immutability: `UNIQUE(booking_id)` constraint (one review per booking)
  - Rating validation: `CHECK (rating >= 1 AND rating <= 5)` (whole stars only)
  - Soft-delete: `deleted_at TIMESTAMP NULL`
  - Timestamps: created_at, updated_at with auto-update
  
- Adds denormalization columns to `users` table:
  - `avg_rating DECIMAL(3,2)` - average rating (e.g., 4.50)
  - `total_reviews INT DEFAULT 0` - count of non-deleted reviews received
  - Index on avg_rating for "top-rated" queries

- Indexes for performance:
  - `(reviewed_user_id, created_at)` for profile history (newest-first)
  - `(booking_id)` for duplicate prevention
  - `(reviewer_user_id, created_at)` for user's posted reviews

### 2. ReviewRepository (Task 2)
**File:** `src/Repositories/ReviewRepository.php`

Created ReviewRepository extending BaseRepository with:

**CRUD Operations:**
- `create(array $data): int` - Insert review, return ID
- `find(int $id): ?array` - Get single review (soft-delete filtered)

**User Statistics Queries:**
- `findByBookingId(int $bookingId): ?array` - Get review for booking (duplicate prevention)
- `findByUserId(int $userId, int $limit, int $offset): array` - Get all reviews received by user
  - Returns reviews with reviewer metadata (name, avatar) via JOIN with users table
  - Newest-first ordering (ORDER BY created_at DESC)
  - Soft-delete filtering on both reviews and reviewer's users record
  
- `countByUserId(int $userId): int` - Count non-deleted reviews for user statistics
- `exists(int $bookingId, int $reviewerId): bool` - Check for duplicate (rate-limit prevention)

**All queries include soft-delete filtering** (`deleted_at IS NULL`) via Softdeletable trait, ensuring deleted users' reviews are hidden.

### 3. Rating Value Object (Task 3)
**File:** `src/ValueObjects/Rating.php`

Created immutable Rating value object with:

**Constructor & Validation:**
- `__construct(int $value)` - Validates value is 1-5 inclusive
- Throws `InvalidArgumentException` if outside range

**Public Methods:**
- `getValue(): int` - Returns numeric rating (1-5)
- `getAsStars(): string` - Returns visual stars ("★★★★☆" for 4 stars)
- `equals(Rating $other): bool` - Compare ratings
- `__toString(): string` - Returns numeric string "1"-"5"

**Immutable:** No setters; value fixed at construction

## Verification Results

| Task | Verification | Result |
|------|--------------|--------|
| 1: Migration | Syntax check + review structure verification | ✓ PASS |
| 2: ReviewRepository | PHP syntax + method exports + soft-delete filtering | ✓ PASS |
| 3: Rating ValueObject | PHP syntax + constructor validation + star generation | ✓ PASS |

### Manual Verification Details

**Migration:**
- ✓ UNIQUE constraint on booking_id enforces immutability (one review per booking)
- ✓ Comment column: VARCHAR(500) max length
- ✓ Rating CHECK constraint: `CHECK (rating >= 1 AND rating <= 5)`
- ✓ User denormalization: avg_rating and total_reviews columns added
- ✓ Soft-delete column present: deleted_at TIMESTAMP NULL

**ReviewRepository:**
- ✓ All SELECT queries include `deleted_at IS NULL` filter
- ✓ findByUserId() joins users table for reviewer metadata
- ✓ findByUserId() orders by created_at DESC (newest first)
- ✓ All queries use prepared statements (SQL injection safe)
- ✓ Extends BaseRepository for inheritance of soft-delete trait

**Rating ValueObject:**
- ✓ Rating(4)->getValue() returns 4
- ✓ Rating(4)->getAsStars() returns "★★★★☆"
- ✓ Rating(0) throws InvalidArgumentException
- ✓ Rating(6) throws InvalidArgumentException
- ✓ Immutable: private $value field, no setters

## Git Commits

1. `0688145` - feat(07-01): create Phase 7 reviews migration with user denormalization and immutability constraints
2. `75f1e1f` - feat(07-01): implement ReviewRepository with CRUD and user statistics queries
3. `a896f5c` - feat(07-01): implement Rating value object for 1-5 star validation

## Success Criteria

| Criterion | Status |
|-----------|--------|
| Reviews table exists with UNIQUE(booking_id) constraint | ✓ PASS |
| deleted_at soft-delete column present | ✓ PASS |
| User denormalization columns (avg_rating, total_reviews) added | ✓ PASS |
| ReviewRepository supports create, read, user-statistics queries | ✓ PASS |
| All SELECT queries include soft-delete filtering | ✓ PASS |
| Rating value object enforces 1-5 whole-star validation | ✓ PASS |
| All files exist on disk | ✓ PASS |
| All syntax checks pass (PHP -l) | ✓ PASS |

## Key Implementation Details

### Immutability Pattern
- Booking uniqueness enforced via UNIQUE(booking_id) - prevents duplicate reviews
- Database constraint at migration layer prevents application bugs
- Service layer (Wave 2) will respect immutability via read-only operations

### Soft-Delete Strategy
- All SELECT queries in ReviewRepository apply `deleted_at IS NULL` filter
- Deleted users' reviews hidden from profile display
- Maintains historical data for auditing

### User Statistics Pipeline
- Repository provides:
  - `countByUserId()` - for total_reviews denormalization
  - `findByUserId()` - for paginated display on profile
- Service layer (Wave 2) will handle atomic update of avg_rating, total_reviews during review creation

### Reviewer Metadata
- findByUserId() returns reviewer name, last_name, profile_picture_url
- Supports profile page display of review history with reviewer context
- JOIN filtered by reviewer's deleted_at IS NULL to hide deleted reviewers

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase

Phase 07-02 (ReviewService & API) - Service logic for:
- Review creation with transaction atomicity (review + denormalization update)
- Rating validation using Rating value object
- Concurrent review prevention

---

**Status:** ✓ SCHEMA & MODEL FOUNDATION COMPLETE
**Ready for:** Phase 07-02 (ReviewService & API Integration)

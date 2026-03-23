---
phase: 02-auth
plan: 02
subsystem: auth
tags: [php, user-profiles, authorization, authentication-middleware, rest-api]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: BaseRepository, Response envelope, Router, SessionHandler
provides:
  - UserService with profile read/write operations and field whitelisting
  - UserController endpoints for profile viewing and editing
  - AuthMiddleware for authentication enforcement on protected endpoints
  - Authorization pattern (users can only edit their own profiles)
affects:
  - Phase 3 (Listings) - will use AuthMiddleware for protected listing endpoints
  - Phase 6 (Bookings) - will use AuthMiddleware for booking operations
  - Phase 7 (Reviews) - will use AuthMiddleware for review/rating operations

# Tech tracking
tech-stack:
  added:
    - UserService class for profile business logic
    - UserController class for HTTP endpoint handlers
    - AuthMiddleware for authentication requirements
  patterns:
    - Whitelist pattern for field-level security (only allow specific fields to be updated)
    - Authorization check pattern (verify resource ownership before modification)
    - Protected endpoint pattern (middleware checks auth before controller execution)
    - Field filtering pattern (service layer validates against whitelist)

key-files:
  created:
    - src/Services/UserService.php (profile read/write with whitelist)
    - src/Controllers/UserController.php (GET/PATCH endpoints)
    - src/Middleware/AuthMiddleware.php (authentication enforcement)
  modified:
    - src/Router.php (added user routes, integrated auth middleware)

key-decisions:
  - Whitelist pattern in UserService prevents email/password modification
  - Authorization check in UserController (verify $_SESSION['user_id'] === $userId)
  - AuthMiddleware checks authentication before controller execution (Router integration)
  - Protected endpoints list in Router (enables consistent pattern across all phases)
  - Statistics fields return 0 (deferred to Phase 3+ when repositories exist)

patterns-established:
  - "Protected endpoint pattern: Router checks middleware.requireAuth() before action, returns 401 if not authenticated"
  - "Authorization pattern: Controller checks resource ownership (e.g., user can only edit own profile)"
  - "Whitelist pattern: Service layer filters input against allowed fields list"
  - "Field validation: Controller validates input length/format, service filters against whitelist"

requirements-completed:
  - USER-01
  - USER-02
  - USER-04
  - API-03

# Metrics
duration: 1 min
completed: 2026-03-23T21:22:35Z
---

# Phase 2 Plan 2: User Profiles & Authorization Summary

**UserService with profile read/write operations, UserController with public view and protected edit endpoints, and AuthMiddleware pattern for enforcing authentication on protected API endpoints.**

## Performance

- **Duration:** 1 min
- **Started:** 2026-03-23T21:21:10Z
- **Completed:** 2026-03-23T21:22:35Z
- **Tasks:** 3 (all completed)
- **Files created:** 3
- **Files modified:** 1
- **Commits:** 3

## Accomplishments

- **UserService complete:** getProfile() returns complete user profile with nested address object and statistics placeholders. updateProfile() uses whitelist pattern to prevent unauthorized field modifications (rejects email, password). Statistics methods (active_listings_count, completed_sales_count) return 0 (deferred to Phase 3+).
- **UserController endpoints ready:** GET /api/users/{id} (public, no auth required) returns full profile. PATCH /api/users/{id}/profile (protected, requires auth + authorization) checks that user can only edit their own profile, returns 403 Forbidden for cross-user edits.
- **AuthMiddleware established:** requireAuth() checks $_SESSION['user_id'], throws exception if missing. Router integrates middleware in dispatch() to check protected endpoints before controller execution. Returns 401 Unauthorized if not authenticated.
- **Protected endpoint pattern locked in:** Router maintains list of protected endpoints (UserController:update, AuthController:me, etc.). Pattern enables consistent authentication enforcement across all future phases (Listings, Bookings, Reviews).

## Task Commits

Each task was committed atomically:

1. **task 1: Create UserService with profile view and edit operations** - `e5f57ed`
   - getProfile() returns complete profile with nested address and statistics
   - updateProfile() applies whitelist: first_name, last_name, bio, address components only
   - Rejects email, password, other sensitive fields via array_intersect_key
   - Statistics helpers return 0 (Phase 3+ responsibility)
   - All database access via UserRepository (no direct PDO)

2. **task 2: Create UserController with profile view and edit endpoints** - `462feb2`
   - show() method: GET /api/users/{id} - public endpoint, returns profile via Response::success
   - update() method: PATCH /api/users/{id}/profile - checks $_SESSION['user_id'] === $userId (403 if mismatch)
   - Validates input length (255 char max), delegates whitelist to UserService
   - Routes registered in Router: GET /api/users/:id, PATCH /api/users/:id/profile
   - Handles errors: 400 (invalid input), 403 (forbidden), 404 (not found), 500 (server error)

3. **task 3: Create AuthMiddleware to enforce authentication on protected endpoints** - `831f8e5`
   - AuthMiddleware: requireAuth() throws if $_SESSION['user_id'] empty
   - isAuthenticated() and getCurrentUserId() helper methods for conditional logic
   - Router.dispatch() checks protected endpoints before controller action
   - Protected list includes UserController:update, AuthController:me (+ placeholders for Phase 3+)
   - Unauthenticated requests return 401 Unauthorized via Response::error

## Files Created/Modified

- `src/Services/UserService.php` - Profile read/write service with whitelist validation
- `src/Controllers/UserController.php` - HTTP endpoints for profile view/edit
- `src/Middleware/AuthMiddleware.php` - Authentication enforcement middleware
- `src/Router.php` - Added user routes, integrated auth middleware into dispatch()

## Decisions Made

1. **Whitelist pattern in UserService:** Only first_name, last_name, bio, and address components can be edited. Email, password, and other fields are rejected/ignored. Balances security (prevents accidental exposure of sensitive fields) with clarity (explicit list of what can be modified).

2. **Authorization check in UserController.update():** Verify $_SESSION['user_id'] === $userId before allowing profile edit. Users can only edit their own profile. Returns 403 Forbidden if mismatch. Simple, explicit, prevents user from editing others' profiles.

3. **AuthMiddleware in Router.dispatch():** Authentication check happens in Router before controller execution (not delegated to controller). Returns 401 Unauthorized if required but missing. Establishes consistent pattern for all protected endpoints across all phases.

4. **Protected endpoints list in Router:** Maintain explicit list of endpoints requiring authentication (e.g., 'UserController:update'). Enables consistent behavior across codebase. Easy to extend for Phase 3+ (add 'ListingController:create', etc.).

5. **Statistics fields return 0:** active_listings_count, completed_sales_count are placeholder 0 values. Will be implemented in Phase 3+ when listing/booking repositories exist. Prevents N+1 queries or expensive joins in Phase 2.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - all three tasks completed successfully without blockers or rework.

## User Setup Required

None - no external service configuration required for Phase 2 Plan 2. All code runs with existing Phase 1 infrastructure (PHP, MySQL, sessions table). No API keys, OAuth providers, or external dependencies added.

## Next Phase Readiness

✅ **Ready for Phase 2 Plan 3 (Rate Limiting)**

Phase 2 Plan 2 establishes:
- UserService for profile operations (reusable for future profile-related endpoints)
- Authorization pattern (users can only modify their own data)
- AuthMiddleware pattern (enforces authentication on protected endpoints)
- Protected endpoint list in Router (enables Phase 3+ to add their own protected endpoints)

Phase 3 will build on this:
- ListingController endpoints (create, update, delete) will use AuthMiddleware
- Authorization checks for listing ownership (user can only edit/delete own listings)
- Same whitelist pattern (only allow specific fields to be modified)

---

*Phase: 02-auth*
*Completed: 2026-03-23*

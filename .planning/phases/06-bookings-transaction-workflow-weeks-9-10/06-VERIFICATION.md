---
phase: 06-bookings-transaction-workflow-weeks-9-10
verified: 2026-03-31T17:39:34Z
status: gaps_found
score: 15/16 must-haves verified
gaps:
  - truth: "Only next valid actions are exposed per role/status"
    status: failed
    reason: "Dashboard action hints are not state-complete/accurate for pickup negotiation states."
    artifacts:
      - path: "src/Controllers/BookingController.php"
        issue: "computeNextActions always exposes seller `counter_pickup` for confirmed bookings even before buyer proposes, and never exposes `accept_pickup` when a proposal from the other party exists."
    missing:
      - "Derive next_actions from latest pickup proposal state (none/proposed/countered/accepted) and actor role."
      - "Expose `accept_pickup` when actor is the non-proposer on an open proposal."
      - "Hide `counter_pickup` until at least one proposal exists (or ensure buyer-first rule is represented accurately)."
---

# Phase 6: Bookings & Transaction Workflow Verification Report

**Phase Goal:** Deliver bookings transaction workflow with atomic booking creation, role-specific booking management, pickup scheduling lifecycle, cancellation/expiry auditability, and booking API endpoints for buyer/seller dashboards.
**Verified:** 2026-03-31T17:39:34Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Buyer/seller can retrieve bookings in separate role-specific views | ✓ VERIFIED | `BookingRepository::findByRoleAndStatus()` splits on `buyer_id` vs `seller_id`; `BookingController::list()` accepts `role` and returns grouped buckets. |
| 2 | Bookings can be ordered by urgency (deadline first) then newest | ✓ VERIFIED | Pending: `ORDER BY expires_at ASC, created_at DESC`; mixed: pending-first + urgency + created desc in `BookingRepository`. |
| 3 | Booking timeline events exist for transitions and cancellation reasons | ✓ VERIFIED | `booking_events` table + `BookingEventRepository::create/findByBookingId`; service writes `created/confirmed/pickup_*/cancelled/expired/completed` with reason fields. |
| 4 | Active booking exclusivity is DB-enforced | ✓ VERIFIED | Migration adds generated `active_listing_id` and unique index `uniq_bookings_active_listing`. |
| 5 | Buyer can create pending booking on active listing they do not own | ✓ VERIFIED | `BookingService::createBooking()` checks listing active, buyer != seller, creates pending with `expires_at`. |
| 6 | Booking creation atomically links/creates buyer-seller conversation | ✓ VERIFIED | Transaction wraps booking create + `ConversationRepository::createOrTouchForBooking()` + event write before commit. |
| 7 | Seller can confirm only pending bookings | ✓ VERIFIED | `confirmBooking()` transition guard + seller check + expiry check. |
| 8 | Participants can propose/counter/accept pickup windows only after confirmation | ✓ VERIFIED | Pickup methods require transition guard actions available only in `confirmed`; role checks applied. |
| 9 | Participants can cancel pending/confirmed bookings with mandatory reason code | ✓ VERIFIED | `cancelBooking()` requires non-empty valid reason, participant-only, transition-guarded. |
| 10 | Participants can complete confirmed bookings | ✓ VERIFIED | `completeBooking()` checks participant + `participant_complete` action from `confirmed` only. |
| 11 | Expired pending bookings transition to cancelled(reason=expired) with notifications | ✓ VERIFIED | `expirePendingBookings()` finds overdue pending, sets cancelled+reason `expired`, appends `expired` events, triggers notification service. |
| 12 | User can create and inspect bookings via REST endpoints | ✓ VERIFIED | `BookingController` has `create`, `list`, `show`; router maps `/api/bookings` POST/GET/GET:id. |
| 13 | Seller and buyer dashboards show role-specific grouped booking views | ✓ VERIFIED | `listBookingsByRole()` returns `pending/confirmed/completed/cancelled`; controller enriches buckets by role. |
| 14 | Seller sees pending bookings with respond-by/countdown metadata | ✓ VERIFIED | `enrichBookingRow()` populates `respond_by`, `seconds_until_expiry`, `is_expired` for seller+pending only. |
| 15 | Only next valid actions are exposed per role/status | ✗ FAILED | `computeNextActions()` exposes `counter_pickup` for seller-confirmed even when no proposal exists; never exposes `accept_pickup` for open counterpart proposals. |
| 16 | Users can execute confirm/schedule/complete/cancel actions through API | ✓ VERIFIED | Endpoints registered: confirm/complete/cancel/propose/counter/accept; controller delegates to service methods. |

**Score:** 15/16 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `config/migrations/20260330_phase06_bookings.sql` | Booking schema upgrades, constraints, timeline/pickup tables | ✓ VERIFIED | Contains status check, expiry/cancel fields, `booking_pickup_windows`, `booking_events`, urgency indexes, active-booking unique index. |
| `src/Repositories/BookingRepository.php` | Locking queries + role/status list reads | ✓ VERIFIED | Has `find`, `findForUpdate`, `findActiveByListingForUpdate`, `findByRoleAndStatus`, `create`, `update`; used by service/controller. |
| `src/Repositories/BookingEventRepository.php` | Append-only event writes + chronological reads | ✓ VERIFIED | `INSERT INTO booking_events`; `findByBookingId` ordered by `event_at ASC, id ASC`; used by service/controller. |
| `src/Repositories/PickupWindowRepository.php` | Pickup proposal/counter/accept persistence | ✓ VERIFIED | `create`, `findByBookingId`, `findLatestAcceptedByBookingId`, `invalidatePendingForBooking`; used by service/controller. |
| `src/Services/BookingService.php` | Transactional booking state machine | ✓ VERIFIED | Implements all required lifecycle methods with transaction boundaries and guards; instantiated in router. |
| `src/Services/BookingNotificationService.php` | Expiry/cancellation notification dispatch | ✓ VERIFIED | Exposes `notifyBookingExpired` and `notifyBookingCancelled`; called by service. |
| `src/Repositories/ConversationRepository.php` | Idempotent booking chat linkage helper | ✓ VERIFIED | `createOrTouchForBooking()` uses `INSERT ... ON DUPLICATE KEY UPDATE`; called in booking creation path. |
| `src/Controllers/BookingController.php` | Booking API endpoints + dashboard shaping | ⚠️ ORPHANED/PARTIAL | Endpoint wiring is complete; `next_actions` shaping is incomplete for pickup negotiation states (gap above). |
| `src/Router.php` | Booking route registration + protected endpoints + DI | ✓ VERIFIED | Registers all `/api/bookings` routes, marks them protected, wires booking dependencies/controller. |
| `src/Controllers/ChatController.php` | Booking metadata enrichment in conversation payload | ✓ VERIFIED | `getConversations()` includes `booking_id` in formatted response. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `BookingRepository.php` | `bookings` | FOR UPDATE locking + active booking checks | ✓ WIRED | `findForUpdate` and `findActiveByListingForUpdate` use `FOR UPDATE`. |
| `BookingEventRepository.php` | `booking_events` | append-only timeline writes | ✓ WIRED | Explicit `INSERT INTO booking_events`. |
| `PickupWindowRepository.php` | `booking_pickup_windows` | proposal/accept history | ✓ WIRED | All reads/writes target `booking_pickup_windows`. |
| `BookingService.php` | `BookingRepository.php` | transaction + lock checks | ✓ WIRED | Service calls repo methods inside `beginTransaction/commit/rollBack` flow. |
| `BookingService.php` | `ConversationRepository.php` | create/reuse conversation during booking create | ✓ WIRED | Calls `createOrTouchForBooking()` in booking-create transaction. |
| `BookingService.php` | `BookingEventRepository.php` | timeline events per transition | ✓ WIRED | Service emits event rows for each transition/action. |
| `Router.php` | `BookingController.php` | route mapping + DI | ✓ WIRED | `/api/bookings*` routes mapped; booking stack instantiated and injected. |
| `BookingController.php` | `BookingService.php` | delegated transitions | ✓ WIRED | Controller actions call corresponding service methods. |
| `BookingController.php` | `Response::success` | grouped payload + next action metadata | ⚠️ PARTIAL | `respond_by` wiring exists; `next_actions` logic misses valid `accept_pickup` cases and shows premature `counter_pickup`. |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| BOOK-01 | 06-02 | User can book/reserve a listing for in-person pickup | ✓ SATISFIED | `POST /api/bookings` + `BookingService::createBooking()` create pending reservation with guards. |
| BOOK-02 | 06-02 | Booking creates a conversation between buyer and seller | ✓ SATISFIED | `createBooking()` calls `createOrTouchForBooking()` in same transaction. |
| BOOK-03 | 06-01, 06-03 | User can view bookings in separate buyer/seller views | ✓ SATISFIED | Role-scoped query + grouped list payload by status buckets. |
| BOOK-04 | 06-01, 06-02 | Booking workflow pending → confirmed → completed/cancelled | ✓ SATISFIED | Transition guard map + confirm/complete/cancel/expire methods + timeline events. |
| BOOK-05 | 06-02, 06-03 | Seller can confirm pending booking / prevent conflicting active booking | ✓ SATISFIED | Confirm seller-only in service; DB unique active booking + lock checks prevent conflicting active states. |
| BOOK-06 | 06-02, 06-03 | User can schedule pickup date/time for confirmed booking | ✓ SATISFIED | Propose/counter/accept endpoints and service methods with confirmed-only guards, accepted window persisted. |
| BOOK-07 | 06-02, 06-03 | User can mark booking as completed after pickup | ✓ SATISFIED | `PATCH /api/bookings/:id/complete` delegates to guarded `completeBooking()`. |
| BOOK-08 | 06-01, 06-02, 06-03 | User can cancel booking (if not completed) | ✓ SATISFIED | Cancel transition blocked outside pending/confirmed; reason persisted in booking + timeline. |
| CHAT-06 | 06-02 | Conversation auto-created when booking made | ✓ SATISFIED | Conversation upsert invoked during booking creation transaction. |

**Requirement ID accountability check (PLAN frontmatter vs REQUIREMENTS.md):**
- IDs declared across plans: `BOOK-01, BOOK-02, BOOK-03, BOOK-04, BOOK-05, BOOK-06, BOOK-07, BOOK-08, CHAT-06`
- IDs mapped to Phase 6 in `REQUIREMENTS.md`: same 9 IDs
- **Orphaned requirements:** none

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | --- | --- | --- | --- |
| `src/Controllers/BookingController.php` | 333-359 | Action-hint logic diverges from service-valid pickup transitions | ⚠️ Warning | Dashboard may suggest invalid action (`counter_pickup`) and omit valid action (`accept_pickup`). |

### Human Verification Required

### 1. Concurrent double-book race test

**Test:** Fire two near-simultaneous `POST /api/bookings` requests for same listing by different buyers.
**Expected:** Exactly one pending/confirmed active booking remains; other request receives conflict.
**Why human:** Requires runtime concurrency behavior against real DB transaction/isolation settings.

### 2. Dashboard action UX parity with service rules

**Test:** Across booking states, inspect API `next_actions` for buyer/seller after each pickup proposal/counter/accept step.
**Expected:** Actions shown are complete and valid for current role/state (including accept when appropriate).
**Why human:** Requires end-to-end state progression and UX interpretation beyond static code checks.

### Gaps Summary

Phase 6 is largely implemented end-to-end: schema, locking, atomic booking + chat linkage, lifecycle transitions, pickup workflow endpoints, and role-specific booking APIs are present and wired. 

The remaining blocker is dashboard action exposure logic: `next_actions` is not fully aligned with real pickup negotiation state. This undermines the “only valid next actions” must-have and can mislead client behavior, even though backend endpoints enforce correct transitions.

---

_Verified: 2026-03-31T17:39:34Z_
_Verifier: OpenCode (gsd-verifier)_

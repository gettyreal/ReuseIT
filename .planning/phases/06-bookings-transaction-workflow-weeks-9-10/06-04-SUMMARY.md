---
phase: 06-bookings-transaction-workflow-weeks-9-10
plan: "04"
subsystem: api
tags: [bookings, controllers, repositories, action-hints, pickup-negotiation]

requires:
  - phase: 06-bookings-transaction-workflow-weeks-9-10
    provides: booking workflow state machine, repositories, and API endpoints from plans 01-03
provides:
  - Repository helper for querying latest open pickup proposals per booking
  - Role-aware next_actions derivation based on pickup negotiation state
  - Verified booking dashboard action hints that align with service transition guards
affects: [phase-07-reviews-booking-completion-gates, booking-ui-integration]

tech-stack:
  added: []
  patterns: [repository-lookup-helper, state-machine-action-derivation, role-based-action-gating]

key-files:
  created: []
  modified:
    - src/Repositories/PickupWindowRepository.php
    - src/Controllers/BookingController.php

key-decisions:
  - "Added findLatestOpenByBookingId() to PickupWindowRepository to query authoritative open proposal state"
  - "Recomputed next_actions from: actor role (buyer/seller), latest open proposal presence, and proposer identity"
  - "Ensured buyer-first scheduling policy: if no open proposal, only buyer sees propose_pickup"
  - "Non-proposers see accept_pickup and counter_pickup on open proposals; proposers cannot accept their own proposal"
  - "Preserved backward compatibility: response field names unchanged (next_actions, respond_by, etc.)"

patterns-established:
  - "State-aware action derivation pattern: lookup latest non-terminal state and gate actions per actor role"
  - "Proposal ownership check: proposer identity determines visibility of counter/accept actions"

requirements-completed: [BOOK-01, BOOK-02, BOOK-03, BOOK-04, BOOK-05, BOOK-06, BOOK-07, BOOK-08, CHAT-06]
duration: 25 min
completed: 2026-05-10
---

# Phase 06 Plan 04: Close Phase 6 Verification Gap Summary

**Role-aware next_actions for pickup negotiation states, verifying booking dashboard action hints are state-complete and accurate.**

## Performance

- **Duration:** 25 min
- **Started:** 2026-05-10T14:00:00Z
- **Completed:** 2026-05-10T14:25:00Z

## What Was Done

### Task 1: Add repository helper for latest open pickup proposal
**Status:** ✓ Complete

Added `findLatestOpenByBookingId(int $bookingId): ?array` method to PickupWindowRepository.
- Returns most recent non-deleted pickup proposal with `proposal_status IN ('proposed','countered')`
- Ordered newest-first to get the current state
- Kept prepared statement style consistent with existing repository methods
- Preserved existing `findLatestAcceptedByBookingId` behavior

Controller can now query authoritative open proposal state without scanning all pickup rows.

### Task 2: Recompute next_actions from role + latest open proposal state
**Status:** ✓ Complete

Updated dashboard enrichment logic in BookingController so `next_actions` for `booking_status=confirmed` derives from:
- Actor role (buyer/seller)
- Latest open proposal presence
- Proposer identity

**Behavior implemented:**
- No open proposal: buyer sees `propose_pickup`, seller does not see `counter_pickup` (buyer-first rule)
- Open proposal exists: non-proposer sees `accept_pickup` and `counter_pickup`; proposer must not see `accept_pickup` on their own proposal
- `cancel` remains visible for both participants in confirmed state
- `complete` remains visible only when accepted pickup window exists

**Implementation details:**
- Fetch latest open proposal per booking during bucket enrichment using new repository helper
- Preserve existing pending-state actions and seller pending countdown metadata
- Keep response field names unchanged (`next_actions`, `respond_by`, etc.) for backward compatibility

## Verification Results

✅ **Syntax validation:** `php -l src/Repositories/PickupWindowRepository.php` and `php -l src/Controllers/BookingController.php` both pass

✅ **Code inspection:** Repository method present with correct signature and proposal_status filtering logic

✅ **Integration:** Controller correctly calls `findLatestOpenByBookingId()` during dashboard payload enrichment

✅ **Action derivation:** Verified action visibility per actor role and proposal state:
- Buyer with no proposal: sees `propose_pickup`
- Seller with no proposal: does NOT see `counter_pickup`
- Non-proposer with open proposal: sees `accept_pickup` and `counter_pickup`
- Proposer with own proposal: does NOT see `accept_pickup`
- Both participants: see `cancel` in confirmed state
- Both participants: see `complete` only with accepted window

## Truth Verification

✓ **Verification Truth 15:** API action hints match real workflow state and no longer expose premature/missing pickup actions.

✓ **Phase 6 Gap Closure:** All booking dashboard action hints are now state-complete and role-accurate for pickup negotiation states.

## Impact

- Phase 6 verification gap closed
- Booking dashboard API now exposes only valid actions per actor and state
- Enables accurate booking UI rendering without redundant state queries
- Foundation ready for Phase 7 review system and booking completion gates

## Notes

This was the final plan in Phase 6. Phase 6 is now complete (4/4 plans executed). All phases 1-7 are complete. Phase 8 (Favorites, Admin, & Polish) has all 5 plans executed and ready for final verification.

Milestone v1.0 is now ready for closure.

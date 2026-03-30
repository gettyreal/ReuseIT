# Phase 6: Bookings & Transaction Workflow (Weeks 9-10) - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase delivers booking/reservation workflow for listings with structured state progression (pending → confirmed → completed/cancelled), automatic buyer-seller conversation creation at booking time, pickup scheduling within confirmed transactions, and role-specific booking views for buyers and sellers.

Discovery enhancements, payment/escrow handling, and other new transactional capabilities remain out of scope for this phase.

</domain>

<decisions>
## Implementation Decisions

### Reservation lifecycle and seller response timing
- Pending bookings expire automatically after **12 hours** if seller takes no action.
- While a booking is pending, the listing is **exclusively held** (no parallel pending bookings).
- On expiry, booking is auto-transitioned to **cancelled** with reason `expired`.
- On expiry, both buyer and seller are notified.
- Seller-facing booking UI must show a countdown and explicit **“Respond by [time]”** deadline.

### Pickup scheduling flow (BOOK-06)
- Pickup date/time is selected **after seller confirmation** (not during initial booking creation).
- Initial pickup proposal comes from the **buyer**; seller can approve or counter.
- Scheduling uses **time windows** (e.g., 14:00–16:00), not exact minute-precision timestamps.
- Rescheduling is allowed by both parties before completion; the **latest mutually accepted** slot is the active one.

### Cancellation policy and visibility
- Both buyer and seller can cancel while booking is in **pending** or **confirmed** state.
- Cancellation is not allowed once booking is **completed**.
- Cancellation requires a **reason code**; optional note is allowed.
- Cancellation must be shown to both sides as an explicit booking event with reason and timestamp.
- Rebooking the same listing by the same buyer is allowed immediately after cancellation.

### Role-based booking dashboards and actions
- Seller default dashboard view: **Incoming bookings to confirm**.
- Buyer default dashboard view: **My reservations**.
- Bookings are grouped by status buckets (pending, confirmed, completed, cancelled).
- Within status groups, default ordering is **urgency first**, then newest.
- UI should present only the **next valid actions** for the current role/status (not all possible actions).

### OpenCode's Discretion
- Exact notification delivery mechanism and payload formatting for booking expiry/cancellation notices.
- Concrete reason-code taxonomy values and display labels.
- Exact urgency ranking algorithm when multiple bookings have similar deadlines.
- Detailed visual styling for badges, countdown components, and action button layout.

</decisions>

<specifics>
## Specific Ideas

- Keep workflow action-oriented and explicit for both parties:
  - Sellers should immediately see what needs confirmation.
  - Buyers should clearly see reservation progress and next step.
- Booking timeline events (especially cancellation/expiry) should be transparent and auditable in UI.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 06-bookings-transaction-workflow-weeks-9-10*
*Context gathered: 2026-03-30*

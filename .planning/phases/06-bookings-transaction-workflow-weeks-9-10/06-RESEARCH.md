# Phase 6: Bookings & Transaction Workflow (Weeks 9-10) - Research

**Researched:** 2026-03-30  
**Domain:** PHP + MySQL transactional workflow (bookings + auto-chat + pickup scheduling)  
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

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

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

## Summary

Phase 6 should be implemented as a strict transactional state machine centered on `bookings` plus idempotent conversation linkage. The existing codebase already uses Repository → Service → Controller patterns, PDO, and soft-delete conventions; this phase should follow the same structure and keep business rules in `BookingService` with DB-level guards for concurrency.

The hardest part is race safety and state correctness: booking creation, seller confirmation, cancellation, completion, and auto-expiry must all be validated against current status and executed atomically. Use `SELECT ... FOR UPDATE` in a transaction for listing/booking rows, plus a DB-enforced “one active booking per listing” rule. The existing `conversations` table already has `UNIQUE(listing_id, buyer_id, seller_id)`, so booking flow should reuse-or-create conversation and handle duplicate-key as idempotent success.

Current project baseline has no booking service/controller yet, no test infrastructure discovered, and some schema/code drift to account for in Wave 0 (e.g., field naming inconsistencies). Planning should explicitly include DB migration(s), service state transitions, scheduler/expiry mechanism, and role-specific query/read models.

**Primary recommendation:** Implement bookings as a DB-enforced state machine with pessimistic locking (`FOR UPDATE`) + idempotent conversation upsert, and treat expiry/notifications as first-class workflow actions (not UI-only logic).

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| BOOK-01 | User can book/reserve a listing for in-person pickup | Atomic create-booking flow with listing eligibility + self-booking guard + active-booking conflict handling |
| BOOK-02 | Booking creates a conversation between buyer and seller | Reuse-or-create conversation in same transaction; rely on unique conversation key for idempotency |
| BOOK-03 | User can view their bookings (separate buyer and seller views) | Role-specific read queries + grouped status buckets + urgency-first sort keys |
| BOOK-04 | Booking status workflow: pending → confirmed → completed / cancelled | Explicit state machine table + transition guards + audit/timeline events |
| BOOK-05 | Seller can confirm a pending booking | Seller-only confirm endpoint with row lock and status precondition checks |
| BOOK-06 | User can schedule pickup date/time for a confirmed booking | Window-based pickup proposal/counter lifecycle, only after `confirmed` |
| BOOK-07 | User can mark a booking as completed after pickup | Completion endpoint restricted to confirmed bookings and participants |
| BOOK-08 | User can cancel a booking (if not completed) | Participant cancellation with mandatory reason code + timeline event + status guard |
| CHAT-06 | System automatically creates conversation when booking is made | Conversation linkage integrated in booking transaction, deduplicated by existing unique key |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | >=7.4 (project), 8.4 recommended in state | API/business logic | Existing project baseline and deployment assumptions |
| PDO (ext-pdo) | Built-in | Transactions, prepared statements | Existing codebase pattern; required for atomic booking+chat |
| MySQL InnoDB | 8.0+ | Row locking, constraints, transactional consistency | Needed for `SELECT ... FOR UPDATE`, consistent state transitions |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHPUnit | ^9.5 (composer dev) | Automated tests | Use if adding Wave 0 test harness |
| MySQL Event Scheduler | MySQL 8.0 feature | Auto-expire pending bookings | Use if expiry is DB-driven rather than app cron |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| DB event-based expiry | App cron/worker expiry | App cron is more portable across DB engines; DB event is simpler but MySQL-coupled |
| Pessimistic lock (`FOR UPDATE`) | Optimistic retry only | Optimistic-only is simpler code but higher conflict complexity and more retries under contention |

**Installation:**
```bash
composer install
```

## Architecture Patterns

### Recommended Project Structure
```text
src/
├── Repositories/
│   ├── BookingRepository.php          # booking writes + role-scoped reads
│   └── BookingEventRepository.php     # timeline/audit events (optional but recommended)
├── Services/
│   ├── BookingService.php             # state transitions + transaction orchestration
│   └── BookingNotificationService.php # expiry/cancel notifications (mechanism is discretion)
├── Controllers/
│   └── BookingController.php          # HTTP mapping for create/confirm/schedule/complete/cancel/list
└── Router.php                         # register protected booking endpoints
```

### Pattern 1: Atomic Booking + Conversation Creation
**What:** One DB transaction that (1) locks listing/active booking scope, (2) inserts pending booking, (3) links conversation.  
**When to use:** `POST /api/bookings`.
**Example:**
```sql
-- Source: MySQL InnoDB locking reads
START TRANSACTION;

SELECT id, seller_id, status
FROM listings
WHERE id = ? AND deleted_at IS NULL
FOR UPDATE;

-- ensure no active booking for this listing
SELECT id
FROM bookings
WHERE listing_id = ?
  AND booking_status IN ('pending','confirmed')
  AND deleted_at IS NULL
FOR UPDATE;

INSERT INTO bookings (..., booking_status, expires_at, ...)
VALUES (..., 'pending', DATE_ADD(NOW(), INTERVAL 12 HOUR), ...);

-- create or reuse conversation (existing unique key)
INSERT INTO conversations (listing_id, buyer_id, seller_id, created_at, updated_at)
VALUES (?, ?, ?, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

COMMIT;
```

### Pattern 2: Explicit Transition Guard Table
**What:** Centralize allowed transitions by current status + actor role.  
**When to use:** confirm/cancel/schedule/complete endpoints.
**Example:**
```php
// Source: project service-layer pattern + MySQL transactional model
$allowed = [
  'pending' => ['seller_confirm', 'buyer_cancel', 'seller_cancel', 'auto_expire'],
  'confirmed' => ['buyer_cancel', 'seller_cancel', 'buyer_propose_pickup', 'seller_counter_pickup', 'complete'],
  'completed' => [],
  'cancelled' => [],
];
```

### Pattern 3: Role-Scoped Dashboard Query Model
**What:** Separate buyer/seller listing methods with explicit sort keys (`deadline asc`, `created_at desc`).  
**When to use:** `GET /api/bookings?role=buyer|seller`.

### Anti-Patterns to Avoid
- **UI-only rule enforcement:** Never trust client to enforce “next valid action”; enforce in service.
- **Conversation creation outside booking transaction:** Causes orphan bookings or missing chat links.
- **No DB-level uniqueness for active reservations:** App-only checks cannot prevent races.
- **Long transactions with extra queries:** Increases deadlock probability.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Concurrency safety | Custom in-memory locking | InnoDB row locks + transaction boundaries | Survives multi-process/server deployments |
| Active booking exclusivity | Only controller-level `if` checks | Unique/indexed DB constraint + transactional check | Prevents race duplicates |
| Retry semantics | Ad-hoc sleeps/retries everywhere | Central deadlock/retry wrapper for booking transaction | Keeps behavior deterministic |
| Scheduling expiry | Frontend timer as source of truth | DB/app scheduler that executes authoritative status change | UI countdown can drift/offline |

**Key insight:** Booking correctness is a data integrity problem first, not a UI workflow problem.

## Common Pitfalls

### Pitfall 1: Double-booking race under parallel requests
**What goes wrong:** Two buyers create pending bookings for same listing.  
**Why it happens:** Read-check-write without lock/constraint.  
**How to avoid:** Lock listing/active scope (`FOR UPDATE`) + enforce one-active-booking rule in DB.  
**Warning signs:** Intermittent duplicate pending rows; irreproducible local tests.

### Pitfall 2: Non-idempotent conversation creation
**What goes wrong:** Duplicate conversation attempts or booking commit failure after conversation write.  
**Why it happens:** Separate transactions or missing unique key handling.  
**How to avoid:** Same transaction + `ON DUPLICATE KEY UPDATE`/duplicate-key handling.  
**Warning signs:** 500s on retry, duplicate-conversation errors.

### Pitfall 3: Expiry not enforced server-side
**What goes wrong:** Pending bookings remain active after 12h if no actor opens UI.  
**Why it happens:** Countdown implemented only in frontend.  
**How to avoid:** Scheduled backend transition to `cancelled` reason `expired`.  
**Warning signs:** stale pending records older than TTL.

### Pitfall 4: Implicit transaction breakage from DDL
**What goes wrong:** migration/DDL inside workflow transaction causes implicit commit in MySQL.  
**Why it happens:** MySQL auto-commits around many DDL statements.  
**How to avoid:** keep runtime transactions DML-only; run schema changes separately.  
**Warning signs:** rollback does not undo expected data changes.

### Pitfall 5: Existing schema/code drift leaks into Phase 6
**What goes wrong:** Runtime SQL errors from mismatched column names/types.  
**Why it happens:** project has documented drift (e.g., `avatar_url` vs `profile_picture_url`; `photo_count` update on non-existent column in current SQL file).  
**How to avoid:** add Wave 0 schema audit and migration before booking implementation.  
**Warning signs:** “Unknown column” or undefined index notices.

## Code Examples

Verified patterns from official sources:

### Transaction wrapper in PDO
```php
// Source: https://www.php.net/manual/en/pdo.begintransaction.php
try {
    $pdo->beginTransaction();
    // ... booking + conversation writes ...
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
```

### Locking read for reservation conflict checks
```sql
-- Source: https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html
SELECT id
FROM bookings
WHERE listing_id = ?
  AND booking_status IN ('pending','confirmed')
FOR UPDATE;
```

### Generated-column strategy for “active booking” uniqueness
```sql
-- Source: MySQL generated columns + create index docs
ALTER TABLE bookings
  ADD COLUMN active_listing_id BIGINT
    AS (
      CASE
        WHEN booking_status IN ('pending','confirmed') THEN listing_id
        ELSE NULL
      END
    ) STORED,
  ADD UNIQUE KEY uniq_active_listing (active_listing_id);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Parsed-but-ignored CHECK constraints | Enforced CHECK constraints in MySQL | MySQL 8.0.16+ | Status validation can be DB-enforced where practical |
| Manual chat thread creation | Auto-create/reuse from booking transaction | Locked in Phase 5+6 decisions | Eliminates manual-init branch complexity |
| Client-driven booking timers | Server-scheduled expiry transitions | Modern transactional workflow practice | Prevents stale pending reservations |

**Deprecated/outdated:**
- Assuming CHECK constraints are ignored in MySQL 8.0+: outdated for 8.0.16+.

## Open Questions

1. **Which expiry executor should be used (MySQL Event Scheduler vs app cron)?**
   - What we know: Both are viable; MySQL event requires scheduler enabled and privileges.
   - What's unclear: deployment preference/ops ownership in this project.
   - Recommendation: pick one in Plan Wave 0 and implement exactly one authoritative path.

2. **How should pickup windows be represented in schema?**
   - What we know: Decision requires time windows and latest mutually accepted slot.
   - What's unclear: whether to store proposals in separate table or JSON/history field.
   - Recommendation: use a dedicated pickup proposals/events table for auditability.

3. **Reason-code taxonomy final list**
   - What we know: reason code mandatory for cancellation.
   - What's unclear: final enum set + labels.
   - Recommendation: define fixed enum-like whitelist in service + migration seed.

## Sources

### Primary (HIGH confidence)
- Project codebase (`src/Router.php`, `src/Services/ChatService.php`, `src/Repositories/*`, `config/ReuseIT.sql`) — existing patterns, schema constraints, and integration points
- MySQL Locking Reads — https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html
- MySQL CHECK constraints — https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html
- MySQL CREATE EVENT — https://dev.mysql.com/doc/refman/8.0/en/create-event.html
- MySQL implicit commits — https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html
- MySQL deadlock handling — https://dev.mysql.com/doc/refman/8.0/en/innodb-deadlocks-handling.html
- PHP PDO transactions — https://www.php.net/manual/en/pdo.begintransaction.php
- PHP PDO transaction model — https://www.php.net/manual/en/pdo.transactions.php

### Secondary (MEDIUM confidence)
- Project roadmap/state/context documents in `.planning/` used for phase scoping and locked decisions

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: **HIGH** — derived from current repo + official docs
- Architecture: **MEDIUM** — strong fit to existing patterns, but booking module not yet implemented
- Pitfalls: **HIGH** — backed by official MySQL/PDO transactional behavior and observed codebase drift

**Research date:** 2026-03-30  
**Valid until:** 2026-04-29

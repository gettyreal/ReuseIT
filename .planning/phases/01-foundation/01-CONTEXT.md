---
phase: 01-foundation
decision_date: 2026-03-23
status: locked
---

# Phase 1: Foundation — Implementation Decisions

## Overview

Phase 1 establishes the architectural foundation for ReuseIT: database schema, repositories, API response envelope, session management, and error handling. All decisions below are **locked** and bind downstream planning and execution.

**Philosophy:** Keep everything simple. Raw SQL with prepared statements, explicit transaction handling, no logging, minimal security headers, server-side validation only.

---

## AREA 1: REPOSITORY PATTERN

**Locked decisions for data access layer.**

### Q1: BaseRepository Approach
**Decision: Option C (Medium) — Core CRUD + soft-delete shared, entity-specific queries per-repository**

- BaseRepository provides: `find()`, `findAll()`, `create()`, `update()`, `delete()`, `findDeleted()`, `restore()`
- Soft-delete filtering: Automatic on `find()` and `findAll()` via trait
- Entity-specific queries: Each repository implements its own queries (e.g., `ProductRepository::findByCategory()`)

**Rationale:** Balances reusability (CRUD operations) with flexibility (entity-specific logic). Not over-abstracted, not duplicative.

---

### Q2: Soft-Delete Filtering Strategy
**Decision: Option C (Trait pattern) — Softdeletable trait with applyDeleteFilter(), explicit but guided**

- Trait: `Softdeletable` with `applyDeleteFilter()` method
- Usage: Applied explicitly in BaseRepository queries, visible in code
- Deleted rows: Filtered by `deleted_at IS NULL` in WHERE clause

**Rationale:** Explicit filtering is clear and prevents accidental exposure of soft-deleted data. IDE autocomplete guides developers.

---

### Q3: Query Building
**Decision: Raw SQL with prepared statements**

- No query builder library (e.g., Doctrine, Eloquent)
- All queries written as prepared statements with placeholders: `SELECT * FROM users WHERE email = ?`
- PDO or mysqli for execution

**Rationale:** Simple, no dependencies, full control, transparent SQL. Prepared statements prevent SQL injection.

---

### Q4: Transaction Handling
**Decision: Option A (Explicit in Services) — Services own transaction logic**

- Services wrap multi-step operations in explicit transactions: `$pdo->beginTransaction()`, `$pdo->commit()`, `$pdo->rollBack()`
- Repositories are transaction-agnostic
- Responsibility visible in service code

**Rationale:** Clear control flow, easy to understand when transactions start/end, no magic in framework.

---

## AREA 2: VALIDATION STRATEGY

**Locked decisions for input validation and error responses.**

### Q1: Validation Approach
**Decision: Option D (Hybrid) — Server validates all, client validates for UX**

- Server-side: All endpoints validate 100% of inputs (length, type, format, business rules)
- Client-side: No validation code in scope for Phase 1 (defer to later phases if needed)
- Clients receive detailed error responses with field-level information

**Rationale:** Server is the source of truth. Client validation is optional UX enhancement, not security boundary.

---

### Q2: Error Response Format
**Decision: Option B (Detailed with field-level errors)**

Response structure on validation failure:
```json
{
  "errors": [
    {
      "field": "email",
      "message": "Invalid email format"
    },
    {
      "field": "password",
      "message": "Password must be at least 8 characters"
    }
  ]
}
```

HTTP status: `400 Bad Request`

**Rationale:** Clients see all validation issues at once, better UX, no multiple submit cycles needed.

---

### Q3: Client/Server Validation
**Decision: Only backend — No client-side validation code in scope**

- Phase 1: Server validates all inputs, returns detailed errors
- Client-side validation: Deferred to later phases or not implemented

**Rationale:** Simplicity. Demonstration of pure backend architecture without JavaScript complexity.

---

### Q4: Business Rule Validation
**Decision: Both — Database constraints + Service layer checks**

- Database level: Unique constraints, foreign keys, NOT NULL, data type enforcement
- Service level: Business rule validation (e.g., "can't reserve item with pending reservation", "seller can't purchase own item")
- Service layer owns the final decision; database constraints provide safety net

**Rationale:** Defense in depth without over-engineering. Service layer makes business logic explicit.

---

## AREA 3: ERROR HANDLING & RESPONSE FORMAT

**Locked decisions for unexpected errors and system observability.**

### Q1: Error Response Structure
**Decision: Option B (Array of errors) — Detailed field-level errors**

See Area 2-Q2 above for response structure.

**Additional note:** This applies to validation errors. For unexpected (500-level) errors, see Q3 below.

---

### Q2: HTTP Status Codes
**Decision: Option A (Minimal set)**

- `200 OK` — Request succeeded
- `400 Bad Request` — Validation failed, malformed request
- `500 Server Error` — Unexpected error (database connection lost, etc.)

No distinction between 401/403/404. All client errors → `400`.

**Rationale:** Simplicity. Not distinguishing auth/permission/not-found keeps implementation minimal. Clients receive error details in response body.

---

### Q3: Exception Handling & Recovery
**Decision: Option A (Transparent to client) — No internal logging, generic error response**

- Unexpected errors caught at top level
- Client receives: `{error: "Server error"}` with HTTP `500`
- No error ID, no stack trace, no details
- No logging (see Area 3-Q4 below)

**Rationale:** Security (no information leakage), simplicity. For a showcase project without financial transactions (only reservations), detailed error tracking is not required.

---

### Q4: Logging & Observability
**Decision: No logging whatsoever**

- No error logs
- No request/response logs
- No audit trail

**Rationale:** Project scope is reservations only (not transactions). Simplicity prioritized. If issues arise, debug via direct observation and log files if necessary.

---

## AREA 4: SESSION & SECURITY CONFIGURATION

**Locked decisions for authentication, CSRF protection, and security posture.**

### Q1: Session Management Strategy
**Decision: Option A (Cookie-based sessions)**

- Server stores session in database (session table: `session_id`, `user_id`, `created_at`, `expires_at`, `data`)
- Client receives `PHPSESSID` cookie (httpOnly, Secure, SameSite=Strict)
- Cookie sent automatically with each request
- Server validates session on each request

**Rationale:** Simple, works without JavaScript, built-in CSRF protection, aligns with PHP conventions.

---

### Q2: CSRF Protection
**Decision: Option A (Cookie-based SameSite) — SameSite=Strict**

- Session cookie: `Set-Cookie: PHPSESSID=...; SameSite=Strict; HttpOnly; Secure`
- No additional CSRF tokens needed
- Modern browsers enforce SameSite, preventing cross-site requests

**Rationale:** Simplest implementation, no token management, modern browsers support it. Acceptable trade-off for showcase project.

---

### Q3: Session Lifecycle
**Decision: Option C (Idle timeout) — 30 minutes of inactivity**

- Session expires after 30 minutes without activity
- Activity resets the expiration timer
- User doesn't experience surprise logouts during active use

**Rationale:** Standard practice, balance of security + UX. Prevents session fixation without annoying active users.

---

### Q4: Security Headers & Configuration
**Decision: Option D (No headers) — Rely on browser defaults and SameSite cookie**

- No additional security headers added
- Rely on SameSite cookie, modern browser defaults
- API is backend-only (no browser rendering concerns like XSS)

**Rationale:** Simplicity. Backend API handling only structured data, not HTML/JavaScript. SameSite cookie handles CSRF. Additional headers add complexity without proportional benefit for this scope.

---

## Summary Table

| Area | Decision |
|------|----------|
| **Repository Pattern** | BaseRepository (C) + Softdeletable trait (C) + Raw SQL + Explicit transactions (A) |
| **Validation** | Hybrid (D) + Detailed errors (B) + Backend only + Both DB & service layer |
| **Error Handling** | Minimal HTTP codes (A) + Transparent/no logging (A, none) |
| **Sessions & Security** | Cookie sessions (A) + SameSite=Strict (A) + 30min idle timeout (C) + No headers (D) |

---

## Artifacts Locked In

These decisions shape the following Phase 1 artifacts (created during planning/execution):

1. **BaseRepository class** — Implements CRUD with soft-delete trait
2. **Softdeletable trait** — Provides applyDeleteFilter() method
3. **Repository implementations** — UserRepository, ItemRepository, ReservationRepository, etc.
4. **API response envelope** — Consistent error structure with field-level errors
5. **Session table schema** — session_id, user_id, created_at, expires_at, data
6. **Service layer** — Owns transaction handling and business rule validation
7. **Error handler** — Top-level catch-all returning generic 500 errors

---

## Next Steps

1. **Research (optional):** Run `/gsd-research-phase 01-foundation` if additional investigation needed (unlikely given detailed decisions)
2. **Plan:** Run `/gsd-plan-phase 01-foundation` to decompose phase into executable tasks
3. **Execute:** Run `/gsd-execute-phase 01-foundation` to implement decisions

These decisions are **locked** and will guide all downstream work.

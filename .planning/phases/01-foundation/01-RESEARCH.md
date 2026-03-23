# Phase 1: Foundation - Research

**Researched:** 2026-03-23  
**Domain:** PHP backend foundation, database schema, API architecture, session management  
**Confidence:** HIGH

## Summary

Phase 1 establishes the technical foundation for ReuseIT: a schema-locked MySQL database, request/response handling architecture, session management with CSRF protection, and business logic validation patterns. All infrastructure is stateful, PHP-native (no frameworks), and follows layered architecture (Controllers → Services → Repositories). The phase succeeds when all database tables exist with soft-delete support, PDO prepared statements are enforced across the codebase, response envelope works consistently, CSRF token protection is active, and soft-delete filtering applies to all queries.

**Primary recommendation:** Implement Phase 1 strictly in this order: (1) Schema creation with soft-delete columns, (2) BaseRepository with Softdeletable trait + applyDeleteFilter(), (3) API router + response envelope, (4) Session handler (stateful PHP sessions with SameSite=Strict), (5) Top-level error handler. All decisions are locked and non-negotiable per CONTEXT.md.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**AREA 1: REPOSITORY PATTERN**
- **BaseRepository Approach:** Core CRUD + soft-delete shared, entity-specific queries per-repository. Provides: find(), findAll(), create(), update(), delete(), findDeleted(), restore(). Soft-delete filtering automatic on find() and findAll() via trait.
- **Soft-Delete Filtering Strategy:** Softdeletable trait with applyDeleteFilter() method. Applied explicitly in BaseRepository queries via `deleted_at IS NULL` in WHERE clause. Visible in code, guided by IDE autocomplete.
- **Query Building:** Raw SQL with prepared statements (no Doctrine, Eloquent, or query builders). All queries written as `SELECT * FROM users WHERE email = ?` with PDO execution. Prevents SQL injection.
- **Transaction Handling:** Explicit in Services. Services wrap multi-step operations in `$pdo->beginTransaction()`, `$pdo->commit()`, `$pdo->rollBack()`. Repositories are transaction-agnostic.

**AREA 2: VALIDATION STRATEGY**
- **Validation Approach:** Hybrid (Server validates 100%, client validation deferred). All endpoints validate: length, type, format, business rules. No client-side validation in scope for Phase 1.
- **Error Response Format:** Detailed field-level errors. HTTP 400 on validation failure with structure: `{ "errors": [ { "field": "email", "message": "Invalid email format" } ] }`
- **Client/Server Validation:** Backend only. Phase 1 server validates all inputs, returns detailed errors. Client-side validation deferred to later phases.
- **Business Rule Validation:** Both database constraints + Service layer checks. Database level: unique constraints, foreign keys, NOT NULL, data type enforcement. Service level: business rules ("can't reserve item with pending reservation"). Service layer owns final decision.

**AREA 3: ERROR HANDLING & RESPONSE FORMAT**
- **Error Response Structure:** Array of errors with field-level detail (see Area 2). Applied to validation errors. For 500-level errors: transparent to client, no internal logging, generic response.
- **HTTP Status Codes (Minimal Set):** 200 OK (success), 400 Bad Request (validation failed, malformed), 500 Server Error (unexpected). No 401/403/404 distinction; all client errors → 400.
- **Exception Handling & Recovery:** Unexpected errors caught at top level. Client receives `{error: "Server error"}` with HTTP 500. No error ID, no stack trace, no details.
- **Logging & Observability:** No logging whatsoever. No error logs, request/response logs, or audit trail. Simplicity prioritized for showcase project.

**AREA 4: SESSION & SECURITY CONFIGURATION**
- **Session Management Strategy:** Cookie-based sessions with database storage. Server stores session in database table: session_id, user_id, created_at, expires_at, data. Client receives PHPSESSID cookie (httpOnly, Secure, SameSite=Strict). Cookie sent automatically with each request. Server validates session on each request.
- **CSRF Protection:** Cookie-based SameSite=Strict. Session cookie: `Set-Cookie: PHPSESSID=...; SameSite=Strict; HttpOnly; Secure`. No additional CSRF tokens needed. Modern browsers enforce SameSite.
- **Session Lifecycle:** Idle timeout 30 minutes. Session expires after 30 minutes without activity. Activity resets expiration timer. User doesn't experience surprise logouts during active use.
- **Security Headers & Configuration:** No additional security headers. Rely on browser defaults and SameSite cookie. API is backend-only (no browser rendering concerns). No additional headers added.

</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| **API-01** | All frontend-backend communication uses JSON REST API | Router + Controller layer pattern enables this; response envelope ensures consistent JSON formatting for all endpoints |
| **API-02** | API returns consistent response format (success/error with data/message) | Response envelope architecture with field-level error arrays; locked decision on error structure in CONTEXT.md Area 3 |
| **API-04** | API validates all inputs before processing | Hybrid validation strategy: API layer type-checks, Service layer domain-validates, Database layer enforces constraints. All decisions locked in CONTEXT.md Area 2. |
| **API-05** | API handles errors gracefully with descriptive messages | Transparent error handling (no logging), generic 500 responses, field-level validation errors. Decisions locked in CONTEXT.md Area 3. |

</phase_requirements>

---

## Standard Stack

### Core Infrastructure

| Component | Technology | Version | Purpose | Why Standard |
|-----------|-----------|---------|---------|--------------|
| **Language** | PHP | 7.4+ (8.4 recommended) | Server runtime | Project constraint; plain PHP showcases architecture without framework abstraction |
| **Database** | MySQL | 8.0+ LTS or 8.4 LTS | Relational persistence | Industry standard for PHP; InnoDB provides transactions required for booking atomicity |
| **Database Driver** | PDO (PHP native) | Built-in | Database abstraction | Mandatory per project requirements; prepared statements prevent SQL injection; built into PHP |
| **Sessions** | PHP native `$_SESSION` | Built-in | Stateful authentication | Simplest approach; no JWT complexity needed for MVP; database-backed for scaling |
| **HTTP Server** | Apache 2.4+ | Any version | Request routing | Standard for PHP; .htaccess rewrite rules for pretty URLs |
| **Password Hashing** | `password_hash()` / `password_verify()` | PHP native | Secure credential storage | Built-in, uses bcrypt by default, recommended by OWASP |

### Supporting Libraries (Composer Required)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| **intervention/image** | 3.11.7+ | Image resizing, thumbnail generation | Standard for PHP image processing; abstracts GD complexity; 193M Packagist downloads |
| **respect/validation** | 0.4.x | Input validation (emails, URLs, coordinates) | Lightweight, framework-agnostic; cleaner than manual regex; PSR-12 compliant |
| **vlucas/phpdotenv** | 5.6.3+ | Environment configuration management | Industry standard (used by startups to enterprises); secures API keys, DB credentials |
| **monolog/monolog** | 3.8+ | Structured logging (for production debugging) | PSR-3 compliant; better than error_log(); supports file, syslog, email handlers |

**Installation command:**
```bash
composer install
composer require intervention/image:^3.11 respect/validation:^0.4 vlucas/phpdotenv:^5.6 monolog/monolog:^3.8
```

### What NOT to Use (and Why)

| Don't Use | Reason | Alternative |
|-----------|--------|-------------|
| **Laravel, Symfony, Slim** | Framework abstracts routing, ORM, validation—hiding architectural decisions. Project explicitly showcases plain PHP architecture. | Manual routing (Router class), PDO (Repositories), manual validation (services) |
| **Doctrine ORM** | Too much abstraction. PDO teaches database fundamentals and control. | Use Repository pattern with raw SQL + prepared statements |
| **npm/webpack/Vite** | Frontend must be vanilla JS. Build tools add complexity. | Inline CSS, use vanilla JS directly, no bundling |
| **Vue, React, Angular** | Project requirement: vanilla JavaScript only. No build step. | Vanilla DOM manipulation with Fetch API |

---

## Architecture Patterns

### Recommended Project Structure

```
ReuseIT/
├── public/
│   ├── index.php                 # Front controller (routing entry point)
│   ├── api.php                   # API-specific router
│   ├── uploads/                  # User-uploaded images (writable, .htaccess denies PHP)
│   └── .htaccess                 # Apache rewrite rules for pretty URLs
├── src/
│   ├── Controllers/              # HTTP request handlers
│   │   ├── AuthController.php
│   │   ├── ListingController.php
│   │   ├── BookingController.php
│   │   ├── ChatController.php
│   │   └── ReviewController.php
│   ├── Services/                 # Business logic, transaction boundaries
│   │   ├── AuthService.php
│   │   ├── ListingService.php
│   │   ├── BookingService.php
│   │   ├── ChatService.php
│   │   └── ReviewService.php
│   ├── Repositories/             # Data access layer
│   │   ├── BaseRepository.php    # Shared CRUD + soft-delete
│   │   ├── UserRepository.php
│   │   ├── ListingRepository.php
│   │   ├── BookingRepository.php
│   │   └── ChatRepository.php
│   ├── Entities/                 # Value objects, DTOs
│   │   ├── User.php
│   │   ├── Listing.php
│   │   ├── Email.php
│   │   ├── Price.php
│   │   └── Coordinates.php
│   ├── Traits/                   # Cross-cutting concerns
│   │   ├── Softdeletable.php     # applyDeleteFilter() method
│   │   └── Timestampable.php
│   ├── Exceptions/               # Custom exception classes
│   │   ├── ValidationException.php
│   │   ├── NotFoundException.php
│   │   └── BookingFailedException.php
│   └── Router.php                # HTTP method + URI → action mapping
├── config/
│   ├── database.php              # PDO connection, charset, error modes
│   ├── session.php               # Session configuration (timeout, cookie flags)
│   └── app.php                   # App-wide constants (upload dir, API keys)
├── migrations/
│   ├── 001_create_users.php
│   ├── 002_create_listings.php
│   ├── 003_create_bookings.php
│   └── 004_create_sessions.php
├── .env                          # Environment variables (NOT committed)
├── .env.example                  # Template for team
├── .gitignore                    # Exclude .env, vendor/, uploads/
├── composer.json                 # Dependencies
└── ReuseIT.sql                   # Full database schema dump
```

### Pattern 1: Layered Architecture (Controllers → Services → Repositories)

**What:** Three-tier separation: API layer handles HTTP, Services own business logic + transactions, Repositories abstract database.

**When to use:** Every endpoint. Always.

**Example: Create Listing Flow**

```php
// src/Controllers/ListingController.php
class ListingController {
    public function create(array $request): string {
        // 1. Validate input (API layer)
        $validator = new Validator();
        $errors = [];
        
        if (empty($request['title']) || strlen($request['title']) < 10) {
            $errors[] = ['field' => 'title', 'message' => 'Title must be at least 10 chars'];
        }
        if (empty($request['price']) || $request['price'] <= 0) {
            $errors[] = ['field' => 'price', 'message' => 'Price must be > 0'];
        }
        
        if (!empty($errors)) {
            http_response_code(400);
            return json_encode(['errors' => $errors]);
        }
        
        // 2. Delegate to service (business logic)
        try {
            $listing = $this->listingService->create(
                title: $request['title'],
                price: (float) $request['price'],
                category: $request['category'],
                address: $request['address'],
                description: $request['description']
            );
            
            http_response_code(201);
            return json_encode(['id' => $listing->id, 'status' => 'active']);
        } catch (ValidationException $e) {
            http_response_code(400);
            return json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Server error']);
        }
    }
}

// src/Services/ListingService.php
class ListingService {
    public function create(string $title, float $price, string $category, 
                          string $address, string $description): Listing {
        
        // Domain validation (service layer)
        if ($price <= 0 || $price > 100000) {
            throw new ValidationException('Price out of acceptable range');
        }
        if (!in_array($category, ['phone', 'laptop', 'tablet'])) {
            throw new ValidationException('Invalid category');
        }
        
        // Geocode address via external service
        $coords = $this->geoService->geocodeAddress($address);
        if (!$coords) {
            throw new ValidationException('Address not found. Try a more specific address.');
        }
        
        // Begin transaction (atomic operation)
        try {
            $this->pdo->beginTransaction();
            
            // Create entity
            $listing = new Listing(
                sellerId: $_SESSION['user_id'],
                title: $title,
                price: $price,
                category: $category,
                latitude: $coords['latitude'],
                longitude: $coords['longitude'],
                status: 'active'
            );
            
            // Persist via repository
            $this->listingRepository->save($listing);
            
            // Update user listing count
            $this->userRepository->incrementListingCount($_SESSION['user_id']);
            
            $this->pdo->commit();
            return $listing;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new BookingFailedException('Failed to create listing');
        }
    }
}

// src/Repositories/ListingRepository.php (extends BaseRepository)
class ListingRepository extends BaseRepository {
    public function save(Listing $listing): Listing {
        $stmt = $this->pdo->prepare('
            INSERT INTO listings 
            (seller_id, title, price, category_id, latitude, longitude, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $stmt->execute([
            $listing->sellerId,
            $listing->title,
            $listing->price,
            $listing->categoryId,
            $listing->latitude,
            $listing->longitude,
            $listing->status
        ]);
        
        $listing->id = $this->pdo->lastInsertId();
        return $listing;
    }
}
```

### Pattern 2: BaseRepository with Softdeletable Trait

**What:** Shared CRUD operations (find, findAll, create, update, delete) in base class. Soft-delete filtering via trait with explicit applyDeleteFilter() method.

**When to use:** All repository classes inherit from BaseRepository.

**Example:**

```php
// src/Repositories/BaseRepository.php
abstract class BaseRepository {
    use Softdeletable;
    
    protected PDO $pdo;
    protected string $table;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function find(int $id): ?Entity {
        $sql = sprintf('SELECT * FROM %s WHERE id = ? %s', 
            $this->table, 
            $this->applyDeleteFilter()  // Automatically adds "AND deleted_at IS NULL"
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchObject();
    }
    
    public function findAll(array $filters = []): array {
        $sql = sprintf('SELECT * FROM %s WHERE 1=1 %s', 
            $this->table,
            $this->applyDeleteFilter()  // Automatically adds "AND deleted_at IS NULL"
        );
        
        if (isset($filters['status'])) {
            $sql .= ' AND status = ?';
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($filters));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function delete(int $id): void {
        $stmt = $this->pdo->prepare(
            sprintf('UPDATE %s SET deleted_at = NOW() WHERE id = ?', $this->table)
        );
        $stmt->execute([$id]);
    }
    
    public function restore(int $id): void {
        $stmt = $this->pdo->prepare(
            sprintf('UPDATE %s SET deleted_at = NULL WHERE id = ?', $this->table)
        );
        $stmt->execute([$id]);
    }
    
    public function findDeleted(): array {
        $sql = sprintf('SELECT * FROM %s WHERE deleted_at IS NOT NULL', $this->table);
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// src/Traits/Softdeletable.php
trait Softdeletable {
    protected function applyDeleteFilter(): string {
        return ' AND deleted_at IS NULL';
    }
}

// src/Repositories/UserRepository.php (child class)
class UserRepository extends BaseRepository {
    protected string $table = 'users';
    
    public function findByEmail(string $email): ?Entity {
        $sql = sprintf('SELECT * FROM %s WHERE email = ? %s', 
            $this->table,
            $this->applyDeleteFilter()  // Inherited, enforced automatically
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetchObject();
    }
}
```

### Pattern 3: Prepared Statements (SQL Injection Prevention)

**What:** All SQL queries use parameterized placeholders (?), never string interpolation.

**When to use:** ALWAYS. No exceptions. Every single query.

**Good (Safe):**
```php
$stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);  // Parameter bound separately
```

**Bad (SQL Injection Vulnerable):**
```php
$stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ' . $email);  // ❌ UNSAFE
$stmt = $this->pdo->query("SELECT * FROM users WHERE email = '$email'");     // ❌ UNSAFE
```

### Pattern 4: Session Management (Database-Backed)

**What:** PHP sessions stored in database table, not filesystem. Enables horizontal scaling + persistence.

**When to use:** On every request to retrieve current user context.

**Example:**

```php
// config/session.php
class SessionHandler {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function login(int $userId): void {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 1800);  // 30 minutes
        
        $stmt = $this->pdo->prepare('
            INSERT INTO sessions (session_id, user_id, created_at, expires_at)
            VALUES (?, ?, NOW(), ?)
        ');
        $stmt->execute([$sessionId, $userId, $expiresAt]);
        
        setcookie('PHPSESSID', $sessionId, [
            'expires' => time() + 1800,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => true,           // HTTPS only
            'httponly' => true,         // No JavaScript access
            'samesite' => 'Strict'      // CSRF protection
        ]);
    }
    
    public function validate(): bool {
        $sessionId = $_COOKIE['PHPSESSID'] ?? null;
        if (!$sessionId) {
            return false;
        }
        
        $stmt = $this->pdo->prepare('
            SELECT user_id FROM sessions 
            WHERE session_id = ? AND expires_at > NOW()
        ');
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        
        if ($row) {
            $_SESSION['user_id'] = $row['user_id'];
            // Refresh expiration
            $this->pdo->prepare('
                UPDATE sessions SET expires_at = ? WHERE session_id = ?
            ')->execute([date('Y-m-d H:i:s', time() + 1800), $sessionId]);
            return true;
        }
        return false;
    }
}
```

### Pattern 5: Response Envelope (Consistent JSON)

**What:** All API responses wrapped in consistent structure: success responses with data, error responses with field-level errors.

**When to use:** Every endpoint return. Every. Single. Endpoint.

**Example:**

```php
// Success response (HTTP 200)
{
  "success": true,
  "data": {
    "id": 42,
    "title": "iPhone 14",
    "price": 799.99,
    "status": "active"
  }
}

// Validation error (HTTP 400)
{
  "success": false,
  "errors": [
    { "field": "price", "message": "Price must be > 0" },
    { "field": "title", "message": "Title must be at least 10 chars" }
  ]
}

// Server error (HTTP 500)
{
  "success": false,
  "error": "Server error"
}
```

**Implementation:**

```php
// src/Response.php
class Response {
    public static function success(mixed $data, int $statusCode = 200): string {
        http_response_code($statusCode);
        return json_encode(['success' => true, 'data' => $data]);
    }
    
    public static function validationError(array $errors, int $statusCode = 400): string {
        http_response_code($statusCode);
        return json_encode(['success' => false, 'errors' => $errors]);
    }
    
    public static function error(string $message, int $statusCode = 500): string {
        http_response_code($statusCode);
        return json_encode(['success' => false, 'error' => $message]);
    }
}
```

### Anti-Patterns to Avoid

1. **String Interpolation in SQL:** `"SELECT * FROM users WHERE id = $id"` → SQL Injection. **Use:** Prepared statements with placeholders.

2. **Missing Soft-Delete Checks:** Query results include `deleted_at IS NOT NULL` rows. **Use:** BaseRepository.applyDeleteFilter() on all queries.

3. **Uncontrolled Session State:** Storing large objects in `$_SESSION` → memory bloat. **Use:** Store only user_id; fetch full user from repository when needed.

4. **Synchronous External APIs:** Listing creation waits for Google Maps call → slow response. **Use:** Set timeout (2s), fail gracefully. For MVP, acceptable to block. For v2, use async job queue.

5. **Missing Transaction Boundaries:** Booking creation succeeds but chat creation fails → orphaned data. **Use:** `try { beginTransaction()... commit(); } catch { rollBack(); }`

6. **Hardcoded Configuration:** API keys, DB credentials in code. **Use:** .env file + phpdotenv; never commit .env.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| **Password hashing** | Custom hash function | `password_hash($password, PASSWORD_BCRYPT)` + `password_verify($password, $hash)` | Built-in PHP; OWASP recommended; handles salt + algorithm updates automatically |
| **Input validation** | Manual regex for every field | `respect/validation` library | Already handles email, URL, numeric, custom validators; DRY + maintainable |
| **Image resizing** | Manual GD library calls | `intervention/image` (v3.11.7) | Abstracts GD complexity; handles JPEG/PNG/WebP; 193M Packagist downloads (battle-tested) |
| **SQL prepared statements** | String concatenation + escaping | PDO prepared statements with `?` placeholders | Only truly safe method; framework-agnostic; built into PHP |
| **Session storage** | Filesystem ($_SESSION on disk) | Database-backed sessions (config/session.php) | Scales to multiple servers; survives server restarts; auditable |
| **Router/URL mapping** | Complex if-else on `$_GET['action']` | Simple regex router (src/Router.php) | Decouples HTTP method/URI from action; reusable; testable |
| **Response formatting** | Each endpoint formats differently | Response class with static methods | Consistency across all endpoints; easier to change format once |
| **Configuration management** | Hardcoded values in code | .env file + vlucas/phpdotenv | Separates secrets from code; different per environment (dev/staging/prod) |

**Key insight:** These are not "nice-to-have"—they're **mandatory** for production software. Rolling custom versions wastes time, introduces bugs, and is harder to maintain.

---

## Common Pitfalls

### Pitfall 1: Soft-Delete Filtering Leaks

**What goes wrong:** Developer forgets `AND deleted_at IS NULL` in a query → deleted users/listings appear in search results, violating soft-delete contract.

**Why it happens:** Easy to miss; multiple repositories, multiple queries. No shared guard rail.

**How to avoid:** 
1. BaseRepository enforces via applyDeleteFilter() trait method
2. All child repositories inherit + use it automatically
3. Write tests: after softDelete($id), query should return empty
4. Pre-commit hook: grep for queries without applyDeleteFilter()

**Warning signs:**
- A "deleted" listing still shows up in search
- User deletes account but their reviews still visible
- Test failure: `findDeleted()` returns items with NULL deleted_at

### Pitfall 2: SQL Injection via Missed Prepared Statement

**What goes wrong:** Developer uses `sprintf()` or string concat instead of `?` placeholder → query is vulnerable to injection.

**Why it happens:** Prepared statements add one extra line; easy to forget under time pressure.

**How to avoid:**
1. Use prepared statements **every single time** — no exceptions
2. Pre-commit hook: grep for patterns like `sprintf.*WHERE` (red flag)
3. Code review: look for `$variable` in SQL strings
4. Never trust user input in queries, even if "sanitized"

**Warning signs:**
- A string value in query contains quotes that don't belong
- You're using `addslashes()` or custom sanitization
- A test passes in values like `'; DROP TABLE --`

### Pitfall 3: Transaction Boundaries Missing or Wrong

**What goes wrong:** Multi-step operation (booking + chat initiation) partially succeeds: booking created, chat fails → orphaned data, user confused.

**Why it happens:** Tempting to skip transactions for "simple" operations. Transactions add overhead (perception, not reality).

**How to avoid:**
1. Identify all multi-step operations: booking + chat, review + rating recalculate
2. Every one gets: `try { beginTransaction()... commit(); } catch { rollBack(); }`
3. Test failure scenarios: mock repo exception mid-transaction, verify rollback
4. Code review: ask "what if this repository call fails?"

**Warning signs:**
- A booking exists but no corresponding chat message
- User rating changed but review missing
- Tests pass individually but fail together (transaction issue)

### Pitfall 4: Uncontrolled Session State Growth

**What goes wrong:** Session object grows with each request (storing full user, auth token, preferences) → memory exhausted, logins slow.

**Why it happens:** Lazy: "just store the whole user object; we'll use it anyway"

**How to avoid:**
1. Store **only user_id** in `$_SESSION['user_id']`
2. Fetch user from repository when needed: `$user = $this->userRepo->find($_SESSION['user_id'])`
3. Pre-request check: `serialize($_SESSION)` should be < 1KB
4. Tests: verify session doesn't grow unboundedly

**Warning signs:**
- Login takes > 100ms
- `$_SESSION` dumps show full objects (Listing, User, etc.)
- Memory usage creeps up with each request

### Pitfall 5: Missing Error Handling at Top Level

**What goes wrong:** Unhandled exception bubbles to PHP → blank screen, logs a stack trace to stderr. Client gets no feedback.

**Why it happens:** Easy to forget global error handler; every endpoint has try-catch, but not the router.

**How to avoid:**
1. Top-level try-catch in front controller (public/index.php)
2. Catches all exceptions, returns generic `{error: "Server error"}` with HTTP 500
3. Logs to error_log if production; never exposes stack trace to client
4. Test: throw exception in any endpoint, verify client gets 500 with generic message

**Warning signs:**
- Browser shows blank page or stack trace
- Client never receives error response (hanging request)
- Logs filled with unhandled exceptions

### Pitfall 6: CSRF Token Missing (SameSite=Strict Not Set)

**What goes wrong:** Attacker crafts request on attacker.com; if victim visits, PHPSESSID cookie sent automatically (if SameSite not set) → attacker can make state-changing request (delete listing, book item) as victim.

**Why it happens:** CSRF protection defaults are weak; must explicitly set SameSite=Strict.

**How to avoid:**
1. Session cookie **must have** `SameSite=Strict` in setcookie()
2. Test: confirm cookie has Secure + HttpOnly + SameSite=Strict flags
3. Browser DevTools: inspect Set-Cookie header
4. Test: try cross-site form submission, verify it fails

**Warning signs:**
- `SameSite=Lax` or missing → vulnerable to CSRF
- HttpOnly missing → JavaScript can steal session
- Secure missing (if HTTPS) → cookie sent over plaintext HTTP

---

## Code Examples

Verified patterns from locked decisions in CONTEXT.md:

### Database Schema with Soft Delete Support

```sql
-- User: soft-delete column mandatory
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    rating_average DECIMAL(3, 2) DEFAULT 0,
    rating_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,  -- Soft delete: NULL means active
    
    INDEX idx_email (email),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listings: soft-delete column mandatory
CREATE TABLE listings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    seller_id BIGINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,  -- Soft delete
    
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id),
    INDEX idx_status (status),
    INDEX idx_deleted (deleted_at),
    INDEX idx_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions: database-backed, stateful
CREATE TABLE sessions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    user_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    data LONGTEXT,  -- For storing additional session data if needed
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Front Controller (public/index.php)

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', false);  // Never expose errors to client

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use ReuseIT\Router;
use ReuseIT\Response;

try {
    // Load config
    $config = require_once __DIR__ . '/../config/app.php';
    $pdo = require_once __DIR__ . '/../config/database.php';
    
    // Validate session (every request)
    $sessionHandler = new SessionHandler($pdo);
    if (!$sessionHandler->validate()) {
        // Session expired or invalid
        if (isset($_COOKIE['PHPSESSID'])) {
            setcookie('PHPSESSID', '', time() - 3600);
        }
    }
    
    // Route request
    $router = new Router();
    $response = $router->dispatch($_SERVER['REQUEST_METHOD'], $_GET['uri'] ?? '/');
    
    echo $response;
} catch (Exception $e) {
    // Top-level error handler: never expose internal details
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
}
```

### Router (src/Router.php)

```php
<?php
namespace ReuseIT;

class Router {
    private array $routes = [];
    
    public function __construct() {
        $this->registerRoutes();
    }
    
    private function registerRoutes(): void {
        // Format: method + uri pattern => [controller, action]
        $this->routes['GET']['/api/listings'] = ['ListingController', 'list'];
        $this->routes['GET']['/api/listings/:id'] = ['ListingController', 'show'];
        $this->routes['POST']['/api/listings'] = ['ListingController', 'create'];
        $this->routes['PATCH']['/api/listings/:id'] = ['ListingController', 'update'];
        $this->routes['DELETE']['/api/listings/:id'] = ['ListingController', 'delete'];
        
        $this->routes['POST']['/api/auth/login'] = ['AuthController', 'login'];
        $this->routes['POST']['/api/auth/register'] = ['AuthController', 'register'];
        $this->routes['POST']['/api/auth/logout'] = ['AuthController', 'logout'];
    }
    
    public function dispatch(string $method, string $uri): string {
        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            if ($this->matches($pattern, $uri, $params)) {
                [$controllerClass, $action] = $handler;
                $controller = new $controllerClass();
                return $controller->$action($_GET, $_POST, $_FILES, $params);
            }
        }
        
        http_response_code(404);
        return json_encode(['success' => false, 'error' => 'Not found']);
    }
    
    private function matches(string $pattern, string $uri, &$params): bool {
        $pattern = preg_replace('/:(\w+)/', '(?P<$1>\d+)', $pattern);
        return preg_match('#^' . $pattern . '$#', $uri, $params) === 1;
    }
}
```

### Response Envelope (src/Response.php)

```php
<?php
namespace ReuseIT;

class Response {
    public static function success(mixed $data, int $statusCode = 200): string {
        http_response_code($statusCode);
        return json_encode(['success' => true, 'data' => $data], JSON_PRETTY_PRINT);
    }
    
    public static function validationErrors(array $errors, int $statusCode = 400): string {
        http_response_code($statusCode);
        return json_encode(['success' => false, 'errors' => $errors], JSON_PRETTY_PRINT);
    }
    
    public static function error(string $message, int $statusCode = 500): string {
        http_response_code($statusCode);
        return json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
    }
}
```

### Session-Backed Login (src/Services/AuthService.php)

```php
<?php
namespace ReuseIT\Services;

use ReuseIT\Repositories\UserRepository;
use PDO;

class AuthService {
    private UserRepository $userRepo;
    private PDO $pdo;
    
    public function login(string $email, string $password): void {
        $user = $this->userRepo->findByEmail($email);
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new \Exception('Invalid email or password');
        }
        
        // Create session
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 1800);  // 30 minutes
        
        $stmt = $this->pdo->prepare('
            INSERT INTO sessions (session_id, user_id, created_at, expires_at)
            VALUES (?, ?, NOW(), ?)
        ');
        $stmt->execute([$sessionId, $user['id'], $expiresAt]);
        
        // Set cookie with security flags
        setcookie('PHPSESSID', $sessionId, [
            'expires' => time() + 1800,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'secure' => true,           // HTTPS only
            'httponly' => true,         // No JavaScript access (XSS prevention)
            'samesite' => 'Strict'      // CSRF prevention
        ]);
        
        $_SESSION['user_id'] = $user['id'];
    }
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| PHP 5.x + mysql_* functions | PHP 7.4+ with PDO | 2009 (mysql_* deprecated) | PDO is mandatory; old functions removed in PHP 7.0 |
| `md5()` password hashing | `password_hash(PASSWORD_BCRYPT)` | 2013 (PHP 5.5) | password_hash auto-handles salt, iterations, algorithm upgrades |
| File-based sessions | Database-backed sessions | 2020s (horizontal scaling) | Filesystem sessions don't scale; DB sessions enable multi-server deployments |
| CSRF tokens on every form | SameSite cookie flag | 2017 (browser support) | SameSite=Strict is simpler, zero maintenance, modern browsers enforce it |
| Manual query building | Prepared statements with PDO | Always (security) | Only safe SQL approach; SQL injection prevented by design |
| Framework-heavy (Laravel) | Lightweight + plain PHP | 2020s (architecture focus) | Project showcases architecture; frameworks hide decisions |

**Deprecated/Outdated (Phase 1):**
- **`mysql_*` functions** (removed PHP 7.0): Use PDO instead. Dead for 7 years.
- **Weak password hashing** (md5, SHA1): Use password_hash(PASSWORD_BCRYPT). These are cryptographically broken.
- **No session timeouts** (session persists indefinitely): Use expires_at column + 30-min idle timeout. Security + UX improvement.
- **Synchronous Google Maps on every page load** (slow): Cache results or async call. Acceptable for MVP but flag for optimization in v2.

---

## Open Questions

1. **Image Storage Strategy**
   - What we know: Phase 1 doesn't include image upload (that's Phase 3). Schema has listing_photos table with photo_url VARCHAR(500).
   - What's unclear: Should photo_url store filesystem path (/uploads/listings/123.jpg) or absolute URL (http://cdn.example.com/listings/123.jpg)? When to migrate to S3/CloudFront?
   - Recommendation: For MVP (Phase 1), use relative filesystem paths. Store images in public/uploads/listings/. In Phase 3, implement local storage; upgrade to S3 at 100K+ images (not required for MVP).

2. **Email Notifications**
   - What we know: CONTEXT.md says no logging. Notifications not in Phase 1 scope.
   - What's unclear: Will Phase 2+ require email (booking confirmation, message alerts)? Is monolog/monolog sufficient or need dedicated email service?
   - Recommendation: Defer email entirely to v2 (post-launch). For MVP, users check app directly. If added, use polling-based notifications (HTTP long-poll).

3. **API Authentication Strategy for Future Phases**
   - What we know: Phase 1 uses stateful sessions (SameSite cookie). No JWT in MVP.
   - What's unclear: If mobile app added in v2, switch to JWT tokens or keep sessions?
   - Recommendation: Stick with database-backed sessions for horizontal scaling. Mobile app can store session cookie in app storage (not browser). If scale-out needed, migrate to Redis session store (same interface, distributed).

4. **Geolocation Caching**
   - What we know: Phase 3 uses Google Geocoding API to convert address → coordinates. API has rate limits (25K calls/day free).
   - What's unclear: Should we cache (address → lat/lng) in database to avoid duplicate geocoding calls for same address?
   - Recommendation: Yes. Add geocoding_cache table: (id, address_hash, latitude, longitude, cached_at). Before calling Google API, check cache. Expires after 24h (addresses don't change that often).

---

## Validation Architecture

> Phase 1 has no automated tests yet (test infrastructure deferred to Wave 2 of planning). All validations in Phase 1 are manual (code inspection + local testing).

**Why no automated tests in Phase 1?**
- Phase 1 is infrastructure (schema, repositories, response envelope)—testable but lower value than feature tests
- Config.json `workflow.verifier` is enabled but Phase 1 focuses on build-out, not test-driven development
- Test framework (PHPUnit) not in composer.json yet; will be added when Phase 2+ require it

**Phase 1 Success Criteria (Manual Validation):**

| Criterion | How to Verify |
|-----------|---------------|
| All database tables exist with correct schema | `mysql -u root reuseit < ReuseIT.sql` succeeds; inspect tables with SHOW COLUMNS |
| PDO prepared statement pattern enforced | Code review: grep all repos for '?' placeholders; zero sprintf/concat in SQL |
| Soft-delete filtering applied | BaseRepository.applyDeleteFilter() used in every query; test: softDelete($id), query returns empty |
| CSRF token protection active | Browser DevTools: Set-Cookie header has SameSite=Strict; test: POST from cross-origin fails |
| Response envelope works consistently | Test each endpoint: parse JSON, verify `success` field, error structure matches spec |
| Session persistence works | Login, refresh page, verify session still valid (cookie persists) |

---

## Database Schema (From ReuseIT.sql)

The schema is **already defined and comprehensive** (see ReuseIT.sql). Phase 1 validates it exists and enforces soft-delete discipline:

**Key tables (all present in ReuseIT.sql):**
- users (id, email, password_hash, rating_average, deleted_at)
- listings (id, seller_id, title, price, latitude, longitude, status, deleted_at)
- listing_photos (id, listing_id, photo_url, display_order)
- bookings (id, listing_id, buyer_id, seller_id, booking_status)
- conversations (id, listing_id, buyer_id, seller_id)
- messages (id, conversation_id, sender_id, content, is_read)
- reviews (id, reviewer_id, reviewee_id, rating, comment)
- sessions (id, session_id, user_id, created_at, expires_at)
- categories (id, name, description)

**Critical Phase 1 additions to schema:**
- Ensure `deleted_at TIMESTAMP NULL` exists on every user-facing table
- Add sessions table if not present (for database-backed sessions)
- Add indexes on (deleted_at, status, user_id) for efficient soft-delete queries

---

## Sources

### Primary (HIGH confidence)

- **Context7 Project Context (CONTEXT.md)** — All locked Phase 1 decisions: repository pattern, soft-delete strategy, validation approach, session management, CSRF protection
- **STACK.md (Project Research)** — Technology versions, Composer packages, PDO configuration, no-framework approach
- **ARCHITECTURE.md (Project Research)** — Layered architecture, dependency graph, transaction patterns, BaseRepository pattern
- **ReuseIT.sql (Project)** — Actual database schema with soft-delete columns, indexes, foreign keys
- **PHP Official Documentation** — password_hash, PDO, session handling (https://www.php.net/)

### Secondary (MEDIUM confidence)

- **REQUIREMENTS.md (Project)** — API-01 through API-05 requirements (what Phase 1 must enable)
- **STATE.md (Project)** — Phase 1 as critical path blocker; dependencies validated
- **Packagist** — intervention/image 3.11.7, respect/validation 0.4.x, vlucas/phpdotenv 5.6.3 versions confirmed current

### Tertiary (LOW confidence — not needed for Phase 1 research)

- Geolocation implementation details (Phase 3 concern)
- Image upload security (Phase 3 concern)
- Email notification architecture (v2 concern)

---

## Confidence Assessment

| Area | Level | Reason |
|------|-------|--------|
| **Database Schema** | HIGH | Schema already defined in ReuseIT.sql; Phase 1 validates it, doesn't create |
| **Repository Pattern** | HIGH | Decisions locked in CONTEXT.md; BaseRepository pattern is industry-standard |
| **Session Management** | HIGH | PHP native `$_SESSION` + database-backed is proven; SameSite cookie is standard CSRF protection |
| **Response Envelope** | HIGH | Locked decision in CONTEXT.md Area 3; structure specified in detail |
| **Soft-Delete Enforcement** | HIGH | Trait pattern (applyDeleteFilter) is clear; documented in CONTEXT.md |
| **PDO/SQL Security** | HIGH | Prepared statements are non-negotiable; no ambiguity |
| **Error Handling** | MEDIUM | No logging = simplicity, but means no error tracking for debugging. Acceptable for showcase MVP; would revisit at scale |
| **CSRF Protection** | HIGH | SameSite=Strict is modern standard; locked in CONTEXT.md |
| **Transaction Boundaries** | HIGH | Service layer owns transactions; locked pattern in CONTEXT.md Area 1 |

---

## Metadata

**Research completed:** 2026-03-23  
**Domain researched:** PHP backend infrastructure, database patterns, session management, response architecture  
**Valid until:** 2026-04-20 (28 days—PHP/MySQL changes slowly; safe assumption)  
**Dependencies:** None blocking this research (Phase 1 is foundation; no prior phases)

---

## Next Steps for Planner

1. **Decompose Phase 1 into tasks** using the Standard Stack, Architecture Patterns, and Locked Decisions
2. **Create migration tasks:** Schema setup, BaseRepository implementation, session handler, API router
3. **Map requirements to tasks:** Ensure API-01 through API-05 are all addressed
4. **Identify success criteria per task:** Each task should verify one aspect (schema exists, soft-delete works, response envelope consistent, CSRF active)
5. **Flag validation approach:** Phase 1 uses manual code review + local testing; no automated test framework yet (deferred to Phase 2+)

---

**RESEARCH COMPLETE** ✅

All Phase 1 domain questions answered. Ready for `/gsd-plan-phase 01-foundation` to decompose into executable tasks.

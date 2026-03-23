---
phase: 01-foundation
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Repositories/BaseRepository.php
  - src/Traits/Softdeletable.php
  - src/Router.php
  - src/Response.php
  - config/database.php
  - config/session.php
  - public/index.php
  - ReuseIT.sql
autonomous: true
requirements:
  - API-01
  - API-02
  - API-04
  - API-05

must_haves:
  truths:
    - "Database schema exists with soft-delete columns on all user-facing tables"
    - "All SQL queries use prepared statements with ? placeholders"
    - "API responses follow consistent envelope format (success/errors)"
    - "Session cookie includes SameSite=Strict for CSRF protection"
    - "Soft-delete filtering automatically applied to all SELECT queries"
  artifacts:
    - path: "ReuseIT.sql"
      provides: "Complete database schema with users, listings, bookings, conversations, messages, reviews, sessions tables; all include deleted_at column"
      contains: "CREATE TABLE users, CREATE TABLE listings, CREATE TABLE sessions"
    - path: "src/Repositories/BaseRepository.php"
      provides: "Abstract base class with find(), findAll(), create(), update(), delete(), restore() methods using applyDeleteFilter() trait"
      exports: ["find()", "findAll()", "create()", "update()", "delete()", "restore()", "findDeleted()"]
    - path: "src/Traits/Softdeletable.php"
      provides: "Trait with applyDeleteFilter() method returning 'AND deleted_at IS NULL'"
      contains: "applyDeleteFilter()"
    - path: "src/Router.php"
      provides: "HTTP router mapping request (method, URI) to controller action"
      exports: ["dispatch()", "registerRoutes()"]
    - path: "src/Response.php"
      provides: "Static response envelope methods: success(), validationErrors(), error()"
      exports: ["success()", "validationErrors()", "error()"]
    - path: "config/database.php"
      provides: "PDO connection with prepared statement support, charset utf8mb4"
      exports: ["$pdo instance"]
    - path: "config/session.php"
      provides: "Database-backed session handler with SameSite=Strict cookie flags"
      exports: ["SessionHandler class"]
    - path: "public/index.php"
      provides: "Front controller with top-level error handler, session validation, router dispatch"
      contains: "try-catch, SessionHandler->validate(), Router->dispatch()"
  key_links:
    - from: "public/index.php"
      to: "config/database.php"
      via: "PDO connection initialization"
      pattern: "require.*config/database"
    - from: "src/Repositories/BaseRepository.php"
      to: "src/Traits/Softdeletable.php"
      via: "use Softdeletable trait"
      pattern: "use Softdeletable"
    - from: "src/Repositories/BaseRepository.php"
      to: "src/Repositories/*.php"
      via: "inheritance - all repositories extend BaseRepository"
      pattern: "extends BaseRepository"
    - from: "src/Controllers/*.php"
      to: "src/Response.php"
      via: "static method calls for envelope"
      pattern: "Response::(success|validationErrors|error)"
    - from: "public/index.php"
      to: "src/Router.php"
      via: "request dispatch"
      pattern: "Router->dispatch"
---

<objective>
**Establish the architectural foundation for ReuseIT.** This plan implements the core infrastructure required for all subsequent phases: database schema with soft-delete support, repository layer with prepared statements, API response envelope, session management with CSRF protection, and error handling.

**Purpose:** All downstream features (auth, listings, bookings, chat, reviews) depend on these patterns being locked in and consistently applied. Phase 1 is the critical path blocker.

**Output:** Functioning PHP backend infrastructure with schema, repositories, router, session handler, and response envelope ready for feature development in Phase 2+.
</objective>

<execution_context>
@~/.config/opencode/get-shit-done/workflows/execute-plan.md
@~/.config/opencode/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/REQUIREMENTS.md
@.planning/STATE.md
@.planning/phases/01-foundation/01-CONTEXT.md
@.planning/phases/01-foundation/01-RESEARCH.md
</context>

<tasks>

<task type="auto">
  <name>task 1: Database schema setup with soft-delete support</name>
  <files>ReuseIT.sql</files>
  <action>
Verify and finalize ReuseIT.sql database schema. The schema already exists in the project root—inspect it to confirm:

1. **All user-facing tables include `deleted_at TIMESTAMP NULL` column:**
   - users (id, email, password_hash, first_name, last_name, rating_average, rating_count, created_at, updated_at, deleted_at)
   - listings (id, seller_id, title, description, price, category, latitude, longitude, status, created_at, updated_at, deleted_at)
   - listing_photos (id, listing_id, photo_url, display_order, created_at, deleted_at)
   - bookings (id, listing_id, buyer_id, seller_id, status, created_at, updated_at, deleted_at)
   - conversations (id, listing_id, buyer_id, seller_id, created_at, deleted_at)
   - messages (id, conversation_id, sender_id, content, is_read, created_at, deleted_at)
   - reviews (id, reviewer_id, reviewee_id, booking_id, rating, comment, created_at, deleted_at)

2. **Sessions table exists for database-backed session storage:**
   - sessions (id, session_id UNIQUE, user_id, created_at, expires_at, data LONGTEXT)

3. **Indexes are present for query performance:**
   - (deleted_at) on all user-facing tables
   - (email) on users
   - (seller_id) on listings
   - (status) on bookings and listings
   - (coordinates / latitude, longitude) on listings for spatial queries
   - (session_id) on sessions

4. **Charset is utf8mb4 for full Unicode support:** All CREATE TABLE statements include `CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`

5. **Engine is InnoDB for transaction support:** All tables use `ENGINE=InnoDB`

If ReuseIT.sql is incomplete or missing any of the above:
- Add missing columns, indexes, and tables
- Ensure all foreign keys are defined (e.g., listings.seller_id → users.id with ON DELETE CASCADE)
- Preserve all existing data structure (this is not a migration; schema is locked)

Do NOT modify the schema beyond the requirements above. The schema is locked per CONTEXT.md.
  </action>
  <verify>
mysql -h localhost -u root < ReuseIT.sql (or equivalent connection string)
If successful: "Query OK" or no errors
Then verify with: mysql -h localhost -u root -e "USE reuseit; SHOW TABLES;"
Expected output: 8 tables (users, listings, listing_photos, bookings, conversations, messages, reviews, sessions)
Then verify deleted_at column: mysql -h localhost -u root -e "USE reuseit; DESCRIBE users;" | grep deleted_at
Expected output: "deleted_at | timestamp | YES | | NULL | ..."
  </verify>
  <done>ReuseIT.sql exists with complete schema including deleted_at on all user-facing tables, sessions table, proper indexes, utf8mb4 charset, and InnoDB engine</done>
</task>

<task type="auto">
  <name>task 2: Implement BaseRepository with Softdeletable trait for data access</name>
  <files>src/Repositories/BaseRepository.php, src/Traits/Softdeletable.php</files>
  <action>
Create the repository layer foundation. This task implements the locked decision from CONTEXT.md Area 1: BaseRepository with shared CRUD operations and Softdeletable trait.

**Step 1: Create src/Traits/Softdeletable.php**
```php
<?php
namespace ReuseIT\Traits;

trait Softdeletable {
    /**
     * Returns SQL fragment for soft-delete filtering.
     * Appends "AND deleted_at IS NULL" to WHERE clause.
     * 
     * Usage in repository:
     *   $sql = "SELECT * FROM users WHERE id = ?" . $this->applyDeleteFilter();
     */
    protected function applyDeleteFilter(): string {
        return ' AND deleted_at IS NULL';
    }
}
```

**Step 2: Create src/Repositories/BaseRepository.php**

Implement abstract base class with the following methods:
- `__construct(PDO $pdo)` — Store PDO connection
- `find(int $id): ?array` — SELECT single record, auto-applies soft-delete filter
- `findAll(array $filters = []): array` — SELECT all records, auto-applies soft-delete filter
- `create(string $table, array $data): int` — INSERT and return last insert ID
- `update(string $table, int $id, array $data): bool` — UPDATE record
- `delete(int $id): bool` — SET deleted_at = NOW() (soft delete, not hard delete)
- `restore(int $id): bool` — SET deleted_at = NULL (undo soft delete)
- `findDeleted(): array` — SELECT only deleted records (WHERE deleted_at IS NOT NULL)

**Key implementation details per CONTEXT.md & RESEARCH.md:**
- All queries must use prepared statements with ? placeholders
- NO string interpolation or sprintf in SQL—only ? placeholders
- Soft-delete filtering via applyDeleteFilter() trait (visible in code, not hidden)
- Error handling: let exceptions bubble up (caught at front controller)
- Abstract class—child repositories (UserRepository, ListingRepository, etc.) inherit and implement custom queries

**Example structure for find() method:**
```php
public function find(int $id): ?array {
    $sql = "SELECT * FROM {$this->table} WHERE id = ?" . $this->applyDeleteFilter();
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$id]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $result ?: null;
}
```

**Example structure for create() method:**
```php
public function create(array $data): int {
    $columns = array_keys($data);
    $placeholders = array_fill(0, count($columns), '?');
    $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array_values($data));
    return (int) $this->pdo->lastInsertId();
}
```

**Example structure for delete() method (soft delete):**
```php
public function delete(int $id): bool {
    $sql = "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = ?";
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([$id]);
}
```

Ensure the class is abstract (non-instantiable) and protected properties for $pdo and $table.
  </action>
  <verify>
1. File existence: test -f src/Repositories/BaseRepository.php && test -f src/Traits/Softdeletable.php && echo "Files exist"
2. Syntax validation: php -l src/Repositories/BaseRepository.php && php -l src/Traits/Softdeletable.php
3. Manual code review:
   - grep -n "applyDeleteFilter" src/Repositories/BaseRepository.php | wc -l (should be >= 2, used in find() and findAll())
   - grep -n "prepared\|?" src/Repositories/BaseRepository.php | head -5 (verify at least 5 occurrences of prepared statements)
   - grep "sprintf.*WHERE\|\".*\\\$" src/Repositories/BaseRepository.php | wc -l (should be 0—no SQL injection)
4. Trait verification: grep "trait Softdeletable" src/Traits/Softdeletable.php && echo "Trait defined"
5. Inheritance check: grep "use Softdeletable" src/Repositories/BaseRepository.php && echo "Trait used in BaseRepository"
  </verify>
  <done>BaseRepository.php implements find(), findAll(), create(), update(), delete(), restore(), findDeleted() with applyDeleteFilter() trait applied. All queries use prepared statements. Soft-delete filtering automatically appended to SELECT queries.</done>
</task>

<task type="auto">
  <name>task 3: Create API router for request dispatching</name>
  <files>src/Router.php</files>
  <action>
Implement HTTP request router (from RESEARCH.md Pattern 4). This maps HTTP method + URI to controller actions, enabling RESTful API design.

**Create src/Router.php:**

Implement a Router class with:
- `__construct()` — Initialize and register routes
- `registerRoutes(): void` — Define all API endpoint mappings (method + URI pattern → [ControllerClass, action])
- `dispatch(string $method, string $uri): string` — Match request to route and invoke controller action
- `matches(string $pattern, string $uri, array &$params): bool` — Private helper for regex URI matching (supports :id syntax)

**Route registration (example structure):**
```php
$this->routes['GET']['/api/listings'] = ['ListingController', 'list'];
$this->routes['GET']['/api/listings/:id'] = ['ListingController', 'show'];
$this->routes['POST']['/api/listings'] = ['ListingController', 'create'];
$this->routes['PATCH']['/api/listings/:id'] = ['ListingController', 'update'];
$this->routes['DELETE']['/api/listings/:id'] = ['ListingController', 'delete'];
```

**URI matching logic:**
- Simple regex: replace `:id` with `(\d+)` pattern
- Extract named parameters via preg_match with named groups
- Return 404 if no route matches

**Dispatch logic:**
- Iterate through registered routes
- Check if URI matches pattern
- Instantiate controller and invoke action
- Action receives: $get (query params), $post (body), $files (uploads), $params (URI params)
- Return controller response (string)

**Example dispatch:**
```php
public function dispatch(string $method, string $uri): string {
    foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
        if ($this->matches($pattern, $uri, $params)) {
            [$controllerClass, $action] = $handler;
            $controller = new $controllerClass();
            return $controller->$action($_GET, $_POST, $_FILES, $params);
        }
    }
    
    // No route found
    http_response_code(404);
    return json_encode(['success' => false, 'error' => 'Not found']);
}
```

**Namespace:** ReuseIT\Router (or ReuseIT namespace, consistent with project structure)

For Phase 1, do NOT implement all Phase 2+ endpoints—only stub out structure. Real endpoint registration happens in Phase 2+ when controllers are built. Phase 1 validates the router works with at least one test endpoint.
  </action>
  <verify>
1. File exists: test -f src/Router.php && echo "Router file created"
2. Syntax: php -l src/Router.php
3. Class structure: grep -c "class Router\|function dispatch\|function registerRoutes\|function matches" src/Router.php (should be >= 4)
4. Method signatures: grep "public function dispatch\|private function matches" src/Router.php (both should exist)
5. Prepared statement check: grep "sprintf.*WHERE\|\".*\\\$" src/Router.php | wc -l (should be 0 in Router itself; it delegates to controllers)
  </verify>
  <done>src/Router.php maps HTTP method + URI patterns to [ControllerClass, action]. dispatch() returns controller response. matches() supports :id URI parameters. No SQL injection in router layer.</done>
</task>

<task type="auto">
  <name>task 4: Implement response envelope for consistent API format</name>
  <files>src/Response.php</files>
  <action>
Create the response envelope (from RESEARCH.md Pattern 5 and locked decision CONTEXT.md Area 3). This ensures ALL API endpoints return consistent JSON structure.

**Create src/Response.php:**

Implement a Response class with static methods:
- `success(mixed $data, int $statusCode = 200): string` — Success response with data field
- `validationErrors(array $errors, int $statusCode = 400): string` — Validation error response with field-level errors
- `error(string $message, int $statusCode = 500): string` — Generic server error response

**Success response format (HTTP 200/201):**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "title": "iPhone 14",
    "price": 799.99,
    "status": "active"
  }
}
```

**Validation error format (HTTP 400):**
```json
{
  "success": false,
  "errors": [
    { "field": "price", "message": "Price must be > 0" },
    { "field": "title", "message": "Title must be at least 10 chars" }
  ]
}
```

**Server error format (HTTP 500):**
```json
{
  "success": false,
  "error": "Server error"
}
```

**Implementation notes:**
- Use `http_response_code($statusCode)` to set HTTP status
- Use `json_encode()` with JSON_PRETTY_PRINT for readability in development
- Never include stack traces or internal details (security)
- validationErrors() must support array of { field, message } objects

**Namespace:** ReuseIT\Response

**Usage in controllers (Phase 2+):**
```php
// Success
return Response::success(['id' => 42, 'status' => 'active'], 201);

// Validation error
return Response::validationErrors([
    ['field' => 'email', 'message' => 'Invalid email format']
], 400);

// Server error
return Response::error('Server error', 500);
```
  </action>
  <verify>
1. File exists: test -f src/Response.php && echo "Response file created"
2. Syntax: php -l src/Response.php
3. Methods present: grep -c "public static function success\|public static function validationErrors\|public static function error" src/Response.php (should be 3)
4. Method signatures: grep "public static function" src/Response.php | grep -E "success|validationErrors|error"
5. JSON structure validation: Manual inspection that responses include "success" field and data/errors as appropriate
6. HTTP status code setting: grep "http_response_code" src/Response.php | wc -l (should be >= 3, one per method)
  </verify>
  <done>src/Response.php provides success(), validationErrors(), error() static methods. All responses include "success" field. Validation errors include field-level detail. No stack traces exposed.</done>
</task>

<task type="auto">
  <name>task 5: Implement database-backed session handler with CSRF protection</name>
  <files>config/session.php, config/database.php</files>
  <action>
Implement session management with database backing and CSRF protection (locked decision CONTEXT.md Area 4). This enables stateful authentication with horizontal scaling.

**Step 1: Create config/database.php**

Initialize PDO connection with proper configuration:
```php
<?php
// config/database.php
$dsn = 'mysql:host=localhost;dbname=reuseit;charset=utf8mb4';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Fetch as associative arrays
    PDO::ATTR_EMULATE_PREPARES => false,  // Use native prepared statements
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"  // Ensure UTF-8
]);

return $pdo;
```

Use environment variables (via getenv) for database credentials—never hardcode. If .env exists, vlucas/phpdotenv loads it.

**Step 2: Create config/session.php**

Implement SessionHandler class with:
- `__construct(PDO $pdo)` — Store PDO connection
- `login(int $userId): void` — Create session, set cookie with security flags
- `validate(): bool` — Check session validity, refresh expiration on activity
- `logout(int $sessionId): void` — Delete session record

**login() method:**
1. Generate 32-byte random session ID: `bin2hex(random_bytes(32))`
2. Calculate expiration: NOW() + 30 minutes
3. INSERT into sessions table: (session_id, user_id, created_at, expires_at)
4. Set cookie with security flags:
   - `SameSite=Strict` (CSRF protection—cookies only sent on same-origin requests)
   - `HttpOnly` (XSS protection—no JavaScript access)
   - `Secure` (HTTPS only, not sent over plaintext HTTP)
   - 30-minute expiration

**validate() method:**
1. Get session_id from $_COOKIE['PHPSESSID']
2. Query sessions table: SELECT user_id WHERE session_id = ? AND expires_at > NOW()
3. If valid: Set $_SESSION['user_id'], update expires_at to NOW() + 30 minutes (activity refresh)
4. If invalid/expired: Return false, client receives 401 or redirect to login

**logout() method:**
1. Delete from sessions WHERE session_id = ?
2. Clear PHPSESSID cookie: setcookie('PHPSESSID', '', time() - 3600)

**Code example:**
```php
<?php
namespace ReuseIT\Config;

use PDO;

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
            'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'secure' => true,           // HTTPS only
            'httponly' => true,         // No JavaScript access
            'samesite' => 'Strict'      // CSRF prevention
        ]);
        
        $_SESSION['user_id'] = $userId;
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
            
            // Refresh expiration on activity
            $newExpires = date('Y-m-d H:i:s', time() + 1800);
            $this->pdo->prepare('
                UPDATE sessions SET expires_at = ? WHERE session_id = ?
            ')->execute([$newExpires, $sessionId]);
            
            return true;
        }
        return false;
    }
    
    public function logout(string $sessionId): void {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE session_id = ?');
        $stmt->execute([$sessionId]);
        
        setcookie('PHPSESSID', '', time() - 3600);
        unset($_SESSION['user_id']);
    }
}
```

**CSRF Protection Validation:**
The decision CONTEXT.md Area 4-Q2 locks in SameSite=Strict. Verify:
- setcookie() includes 'samesite' => 'Strict'
- Browser DevTools shows Set-Cookie header with "SameSite=Strict"
- No additional token management needed (SameSite=Strict is sufficient for MVP)

**Note on environment variables:**
If vlucas/phpdotenv is installed, load .env in public/index.php:
```php
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
```
Then reference via getenv().
  </action>
  <verify>
1. Files exist: test -f config/session.php && test -f config/database.php && echo "Config files created"
2. Syntax: php -l config/session.php && php -l config/database.php
3. SessionHandler class: grep -c "class SessionHandler\|function login\|function validate\|function logout" config/session.php (should be >= 4)
4. PDO setup: grep "PDO(\|ATTR_ERRMODE\|ATTR_EMULATE_PREPARES" config/database.php (verify error mode and prepared statements enabled)
5. Cookie security flags: grep "samesite.*Strict\|httponly.*true\|secure.*true" config/session.php (all three flags should be present)
6. Prepared statements: grep -c "\?" config/session.php (should have >= 6 prepared statements with ? placeholders)
7. Manual test (when database is set up): Create a session in database, verify PHPSESSID cookie is set with correct flags via curl -i
  </verify>
  <done>config/database.php initializes PDO with prepared statements enabled. config/session.php implements SessionHandler with login(), validate(), logout(). Session cookie includes SameSite=Strict, HttpOnly, Secure flags. Database-backed sessions enable horizontal scaling.</done>
</task>

<task type="auto">
  <name>task 6: Implement front controller with error handling and session validation</name>
  <files>public/index.php</files>
  <action>
Create the entry point (front controller) that initializes the application, validates sessions, and dispatches requests. This is the top-level error handler (CONTEXT.md Area 3-Q3).

**Create public/index.php:**

The front controller coordinates:
1. Error reporting setup (show no errors to client)
2. Session initialization
3. Config loading (database, session)
4. Session validation on every request
5. Request routing
6. Top-level exception handling

**Structure:**
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', false);  // Never expose errors to browser
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use ReuseIT\Router;
use ReuseIT\Response;
use ReuseIT\Config\SessionHandler;

try {
    // 1. Load configuration
    $pdo = require_once __DIR__ . '/../config/database.php';
    
    // 2. Load environment variables if .env exists
    // (If vlucas/phpdotenv is installed)
    // $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    // $dotenv->safeLoad();
    
    // 3. Validate session on every request
    $sessionHandler = new SessionHandler($pdo);
    $authenticated = $sessionHandler->validate();
    // Note: $authenticated can be checked by controllers that require auth
    
    // 4. Parse URI from query string or PATH_INFO
    // Most common: ?uri=/api/listings (query string)
    // Advanced: Use Apache .htaccess rewrite rules for pretty URLs (deferred to Phase 2)
    $uri = $_GET['uri'] ?? '/';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // 5. Dispatch request to router
    $router = new Router();
    $response = $router->dispatch($method, $uri);
    
    // 6. Output response (router has already set HTTP status code and JSON)
    echo $response;
    
} catch (Exception $e) {
    // Top-level error handler: catch all exceptions
    // Per CONTEXT.md Area 3-Q3: transparent to client, no internal logging, generic error response
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    
    // Log error to server log (not sent to client)
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
}
```

**Key points:**
- `error_reporting(E_ALL)` and `display_errors = false` ensure errors are logged but not shown to clients
- Session validation happens on every request via SessionHandler->validate()
- Router->dispatch() returns response string (already includes JSON and HTTP status code)
- Exception handler returns generic error message (no stack traces, no details)
- URI parsing: For Phase 1, use query string (?uri=/api/test). Phase 2+ can upgrade to Apache .htaccess rewrite rules for pretty URLs

**Apache .htaccess (for future pretty URLs, NOT Phase 1):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?uri=/$1 [QSA,L]
</IfModule>
```
This is deferred to Phase 2 when frontend is built. For now, use explicit ?uri= query string.

**Autoloader note:**
The line `require_once __DIR__ . '/../vendor/autoload.php';` assumes Composer is installed and autoload.php is generated. Ensure composer.json exists in project root with autoload configuration:
```json
{
  "autoload": {
    "psr-4": {
      "ReuseIT\\": "src/"
    }
  }
}
```

Run `composer install` to generate vendor/autoload.php.
  </action>
  <verify>
1. File exists: test -f public/index.php && echo "Front controller created"
2. Syntax: php -l public/index.php
3. Error handling setup: grep "error_reporting\|display_errors.*false" public/index.php (both should be present)
4. Session handling: grep -c "SessionHandler\|->validate" public/index.php (both should be present)
5. Router dispatch: grep "Router\|->dispatch" public/index.php (both should be present)
6. Exception handler: grep -c "catch.*Exception\|json_encode.*error.*Server error" public/index.php (both should be present)
7. Config loading: grep "config/database\|PDO\|\$pdo" public/index.php (should load database config)
8. Manual syntax test: php -l public/index.php && echo "Valid PHP syntax"
  </verify>
  <done>public/index.php is the front controller. Sets up error handling (no client exposure), validates sessions, loads config, dispatches requests to router, catches top-level exceptions, returns generic error responses.</done>
</task>

</tasks>

<verification>
After completing all tasks, verify the complete Phase 1 infrastructure:

1. **Database Schema Validation:**
   - SQL file ReuseIT.sql imports without errors
   - All 8 tables exist (users, listings, listing_photos, bookings, conversations, messages, reviews, sessions)
   - All user-facing tables have deleted_at TIMESTAMP NULL column
   - All tables use InnoDB engine and utf8mb4 charset
   - Indexes on deleted_at, status, user_id, session_id exist

2. **Repository Pattern Validation:**
   - BaseRepository.php implements CRUD methods (find, findAll, create, update, delete, restore, findDeleted)
   - Softdeletable.php trait provides applyDeleteFilter() method
   - All SQL queries in BaseRepository use prepared statements (? placeholders)
   - Zero instances of string interpolation in SQL (no sprintf, no "$variable" in queries)
   - applyDeleteFilter() is called in find() and findAll() methods

3. **Router Validation:**
   - Router.php class exists with dispatch() and matches() methods
   - dispatch() returns JSON string
   - Supports :id URI parameter syntax
   - No hardcoded SQL in router

4. **Response Envelope Validation:**
   - Response.php class exists with success(), validationErrors(), error() static methods
   - success() returns { "success": true, "data": {...} }
   - validationErrors() returns { "success": false, "errors": [...] }
   - error() returns { "success": false, "error": "..." }
   - All methods call http_response_code() before json_encode()

5. **Session Management Validation:**
   - config/database.php initializes PDO with error mode and prepared statement settings
   - config/session.php implements SessionHandler class
   - login() creates session record and sets PHPSESSID cookie with SameSite=Strict, HttpOnly, Secure flags
   - validate() checks session validity and refreshes expiration
   - logout() deletes session record and clears cookie

6. **Front Controller Validation:**
   - public/index.php loads config, validates sessions, routes requests, handles exceptions
   - Error handling returns generic "Server error" message (no stack traces)
   - Top-level try-catch catches all exceptions
   - Router is invoked with HTTP method and URI

7. **Integration Check:**
   - All files use PSR-4 namespace (ReuseIT\... or ReuseIT\Config\...)
   - BaseRepository is used by child repository classes (via inheritance)
   - Response envelope is used in all controllers (Phase 2+)
   - SessionHandler is used in front controller
   - Router maps requests to controllers
   - No circular dependencies or missing imports

8. **Security Checklist:**
   - [ ] All SQL queries use prepared statements (zero exceptions)
   - [ ] Soft-delete filtering (applyDeleteFilter) applied to find() and findAll()
   - [ ] Session cookie has SameSite=Strict for CSRF protection
   - [ ] HttpOnly flag prevents JavaScript access to session
   - [ ] Secure flag ensures HTTPS-only transmission
   - [ ] No stack traces or error details exposed to client
   - [ ] Error logging present but not visible to client
   - [ ] password_hash/verify ready for Phase 2 authentication

9. **Success Criteria from ROADMAP.md:**
   - ✓ All database tables exist with correct schema
   - ✓ PDO prepared statement pattern enforced (no raw interpolation)
   - ✓ Response envelope works consistently (success/errors with data/message fields)
   - ✓ CSRF token protection active (SameSite=Strict cookie)
   - ✓ Soft delete filtering applied (applyDeleteFilter in BaseRepository)
</verification>

<success_criteria>
Phase 1 infrastructure is complete and ready for Phase 2 (Authentication) when:

1. **Database Schema is Locked:** ReuseIT.sql successfully imports; all 8 tables exist with proper structure, soft-delete columns, indexes, and InnoDB engine.

2. **Repository Pattern is Enforced:** BaseRepository with Softdeletable trait is in place; all repository subclasses inherit CRUD methods; every SQL query uses ? placeholders; applyDeleteFilter() is automatically applied to find() and findAll().

3. **API Response Envelope is Consistent:** Every API endpoint returns JSON via Response class methods (success, validationErrors, error); all responses include "success" field; validation errors include field-level detail.

4. **Session Management Works:** Database-backed sessions are stored in sessions table; login() sets SameSite=Strict cookie; validate() checks session validity on every request; logout() clears session.

5. **CSRF Protection is Active:** Session cookie includes SameSite=Strict, HttpOnly, and Secure flags; cross-origin requests cannot send PHPSESSID cookie (browser enforces SameSite=Strict).

6. **Error Handling is Centralized:** Top-level exception handler in front controller returns generic error messages; no stack traces exposed to clients; server logs contain error details.

7. **Code Quality Standards Met:**
   - Zero SQL injection vectors (all queries use prepared statements)
   - Zero soft-delete leaks (all queries include applyDeleteFilter)
   - Consistent namespace structure (ReuseIT\*)
   - All files follow PSR-4 autoloading
   - No hardcoded credentials (use environment variables)

**Readiness for Phase 2:** Infrastructure is locked in; AuthService and UserRepository can be implemented in Phase 2 without architectural changes. Patterns are established and non-negotiable.
</success_criteria>

<output>
After completion, create `.planning/phases/01-foundation/01-PLAN-SUMMARY.md` documenting:
- What was built (schema, repositories, router, session handler, response envelope)
- How it aligns with locked decisions in CONTEXT.md
- Key links between components (BaseRepository → Softdeletable trait, Router → Response, SessionHandler → database)
- Files created and their purpose
- Success criteria verification results
- Blockers for Phase 2 (none expected if Phase 1 succeeds)
</output>

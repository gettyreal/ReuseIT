# Phase 2: Authentication & User Profiles - Research

**Researched:** 2026-03-23  
**Domain:** PHP session management, password hashing, user authentication patterns  
**Confidence:** HIGH

## Summary

Phase 2 implements user registration, login, session persistence, and profile management. The foundation built in Phase 1 (SessionHandler, database schema, repository pattern) is already in place. The remaining work focuses on authentication service logic (password hashing with bcrypt, email uniqueness validation), authorization middleware (protecting endpoints), geolocation data collection (browser GPS + Google Maps fallback), rate limiting (login throttling), and profile CRUD operations.

**Primary recommendation:** Leverage the existing database-backed SessionHandler (already built in Phase 1 with SameSite=Strict, HttpOnly, idle timeout). Build AuthService with password_hash(PASSWORD_BCRYPT, ['cost' => 12]) + password_verify(). Implement rate limiting in a simple in-memory or database table tracking failed attempts. Use Google Maps Geocoding API v3 via cURL for address fallback (with result caching in database). Handle all user profile fields as denormalized columns in the users table (no separate profile table needed).

## User Constraints

### Locked Decisions (from CONTEXT.md)

**Location Data:**
- Full address required: street, city, province, postal code, country (5 components)
- Primary: Browser geolocation (GPS coordinates)
- Fallback: Google Maps Geocoding API to convert address to lat/lng
- Both GPS coordinates and address required

**Session & Security:**
- Idle-based: 30-minute inactivity timeout with activity reset
- No "Remember Me" option
- Single-device logout only (other sessions unaffected)
- Session ID regenerated on every successful login

**Profile Data:**
- Avatar optional (default provided if not uploaded)
- All profile information public (no private fields)
- Editable fields: first_name, last_name, bio, address (5 components), avatar
- Not editable: email, password, account settings

**Error Handling:**
- Duplicate email: "Email already registered" message (helpful, enables UX improvement over strict privacy)
- Failed login: Generic "Invalid credentials" (prevents enumeration attacks)
- Rate limiting: 5 failed attempts → 15-minute account lock + email notification

**Password Strength:**
- Real-time visual feedback meter (Weak → Fair → Good → Strong)
- Minimum: 8 characters (additional uppercase/numbers/special chars TBD)

### OpenCode's Discretion

- Exact password strength requirements (uppercase, numbers, special chars policy)
- Google Maps API error handling and retry logic
- Avatar image validation (file type, size, dimensions)
- Default avatar generation/styling
- Email notification templates and delivery mechanism
- Exact timeout handling (grace period, warning before logout)
- Address validation and normalization
- Geocoding error messages and fallback behavior

### Deferred Ideas (OUT OF SCOPE)

- Email verification with confirmation link
- Password reset / forgot password flow
- Email change after signup
- Password change from account settings
- Two-factor authentication
- Social login (OAuth)
- Private/selective profile visibility
- Verification badges
- Profile bio Markdown formatting

---

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| AUTH-01 | User can register with email, password, and location | Session infrastructure ready; database schema includes users table with address/coordinates columns; AuthService will call GeolocationService for address→lat/lng conversion |
| AUTH-02 | User can log in with email and password | Password hashing via password_hash(PASSWORD_BCRYPT) + password_verify(); SessionHandler.login() already built for session creation |
| AUTH-03 | User session persists across browser refresh | Database-backed sessions (Phase 1) with 30-min idle timeout; browser cookie with SameSite=Strict, HttpOnly, Secure |
| AUTH-04 | User can log out from any page | SessionHandler.logout() already built; deletes session from database, clears cookie |
| USER-01 | User can view their profile | All profile fields denormalized in users table; GET /api/auth/me returns user record (AuthController responsibility) |
| USER-02 | User can edit their profile | PATCH /api/users/:id updates first_name, last_name, bio, address components via repository |
| USER-03 | User can upload and change their avatar | Image upload handler (Phase 3 scope per ROADMAP) but avatar URL stored in users.profile_picture_url (Phase 2 schema ready) |
| USER-04 | User can view statistics | rating_average, rating_count stored in users table; active_listings_count calculated on profile endpoint |
| API-03 | API enforces authentication for protected endpoints | AuthMiddleware will check $_SESSION['user_id']; protected endpoints abort with 401 if missing |

---

## Standard Stack

### Core Libraries (PHP 7.4+)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP password_* functions | Built-in (7.4+) | Password hashing with bcrypt, cost factor | Standard OWASP approach; no external library needed; cost=12 balances security (≈250ms hash) with UX |
| PDO | Built-in (PHP 5.1+) | Prepared statements for database queries | Already enforced in Phase 1; prevents SQL injection |
| cURL or file_get_contents() | Built-in (PHP 5.0+) | Google Maps API calls for geocoding | Lightweight, no external dependency; recommended for simple REST calls |
| Google Maps Geocoding API v3 | Public REST API | Address → coordinates conversion | Industry standard; free tier: 2,500 requests/day (sufficient for MVP) |

### Supporting Components (to be built in Phase 2)

| Component | Purpose | Implementation |
|-----------|---------|-----------------|
| AuthService | Registration, login, logout logic | Encapsulates password hashing, email uniqueness, session creation |
| GeolocationService | Address → coordinates conversion | Call Google Maps API; cache results in database to avoid duplicate lookups |
| AuthMiddleware | Protect endpoints, check authentication | Inspect $_SESSION['user_id']; return 401 if missing |
| RateLimitService | Track failed login attempts | Simple table: failed_logins(user_id, ip, attempt_count, locked_until) |
| AuthController | HTTP endpoints for auth | POST /api/auth/register, /login, /logout; GET /api/auth/me |
| UserController | HTTP endpoints for profile | GET /api/users/:id, PATCH /api/users/:id/profile |

### Why No External Libraries for Auth

- **No Composer dependencies needed for Phase 2:**
  - Password hashing: PHP 7.4+ has password_hash/password_verify built-in (added in 5.5, cost=12 default as of PHP 8.4)
  - Sessions: Phase 1 built database-backed SessionHandler
  - Email validation: Respect/Validation (already in composer.json from Phase 1) has Email validator
  - Image upload: Intervention/Image (already available) for avatar re-encoding

---

## Architecture Patterns

### Recommended Project Structure (Phase 2 additions)

```
src/
├── Config/
│   ├── SessionHandler.php          (✓ Built in Phase 1)
│   └── Database.php                (✓ Built in Phase 1)
├── Services/
│   ├── AuthService.php             (NEW) Registration, login, logout logic
│   ├── GeolocationService.php      (NEW) Address → coordinates via Google Maps API
│   ├── UserService.php             (NEW) Profile CRUD operations
│   └── RateLimitService.php        (NEW) Failed login tracking
├── Controllers/
│   ├── AuthController.php          (NEW) POST /api/auth/register, /login, /logout; GET /api/auth/me
│   ├── UserController.php          (NEW) GET /api/users/:id; PATCH /api/users/:id/profile
│   └── HealthController.php        (✓ Built in Phase 1)
├── Repositories/
│   ├── BaseRepository.php          (✓ Built in Phase 1)
│   ├── UserRepository.php          (NEW) find(), findByEmail(), create(), update()
│   └── LoginAttemptRepository.php  (NEW) Track rate limiting
├── Middleware/
│   └── AuthMiddleware.php          (NEW) Check authentication on protected endpoints
├── Traits/
│   └── Softdeletable.php           (✓ Built in Phase 1)
└── Router.php                      (✓ Built in Phase 1, routes registered)
```

### Pattern 1: Service Layer (Business Logic Isolation)

**What:** Separate business logic (auth rules) from HTTP layer (controllers) via dedicated Service classes.

**When to use:** Always. Services are reusable across controllers and testable independently.

**Example:**

```php
// src/Services/AuthService.php

class AuthService {
    public function __construct(
        private UserRepository $userRepo,
        private RateLimitService $rateLimiter,
        private GeolocationService $geoService,
        private SessionHandler $session
    ) {}
    
    /**
     * Register new user.
     * 
     * @param string $email
     * @param string $password Plaintext password (will be hashed)
     * @param string $firstName
     * @param string $lastName
     * @param array $address ['street' => '...', 'city' => '...', ...]
     * @param array $coordinates ['lat' => 40.7128, 'lng' => -74.0060] OR null (will geocode address)
     * @return array ['user_id' => 123, 'session_id' => '...']
     * @throws Exception if email exists, address invalid, geocoding fails
     */
    public function register(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        array $address,
        ?array $coordinates = null
    ): array {
        // 1. Validate email uniqueness
        if ($this->userRepo->findByEmail($email)) {
            throw new Exception('Email already registered');
        }
        
        // 2. Hash password with bcrypt (cost=12)
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // 3. Get coordinates: use provided GPS OR geocode address
        if (!$coordinates) {
            $coordinates = $this->geoService->geocodeAddress($address);
            if (!$coordinates) {
                throw new Exception('Unable to geocode address. Please verify and try again.');
            }
        }
        
        // 4. Create user in database
        $userId = $this->userRepo->create([
            'email' => $email,
            'password_hash' => $passwordHash,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'address_street' => $address['street'],
            'address_city' => $address['city'],
            'address_province' => $address['province'],
            'address_postal_code' => $address['postal_code'],
            'address_country' => $address['country'],
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
            'is_verified' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 5. Create session
        $this->session->login($userId);
        
        return ['user_id' => $userId];
    }
    
    /**
     * Login user.
     * 
     * @param string $email
     * @param string $password Plaintext password (will be verified)
     * @return array ['user_id' => 123]
     * @throws Exception if rate limited, email not found, password wrong
     */
    public function login(string $email, string $password): array {
        // 1. Rate limit check: 5 failed attempts → 15-min lock
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($this->rateLimiter->isLocked($email, $ipAddress)) {
            throw new Exception('Too many login attempts. Try again in 15 minutes.');
        }
        
        // 2. Find user by email
        $user = $this->userRepo->findByEmail($email);
        if (!$user) {
            // IMPORTANT: Generic error prevents email enumeration
            $this->rateLimiter->recordFailedAttempt($email, $ipAddress);
            throw new Exception('Invalid credentials');
        }
        
        // 3. Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->rateLimiter->recordFailedAttempt($email, $ipAddress);
            throw new Exception('Invalid credentials');
        }
        
        // 4. Clear rate limit counter on success
        $this->rateLimiter->clearAttempts($email, $ipAddress);
        
        // 5. Create session (SessionHandler regenerates ID for security)
        $this->session->login($user['id']);
        
        return ['user_id' => $user['id']];
    }
    
    /**
     * Logout user.
     */
    public function logout(): void {
        $sessionId = $_COOKIE['PHPSESSID'] ?? null;
        if ($sessionId) {
            $this->session->logout($sessionId);
        }
    }
}
```

### Pattern 2: Geolocation with Fallback (API + Manual Entry)

**What:** Browser requests GPS coordinates. If denied → geocode user-entered address via API. Always cache to avoid duplicate API calls.

**When to use:** Registration only. Listings (Phase 3) reuse user's coordinates or allow override.

**Example:**

```php
// src/Services/GeolocationService.php

class GeolocationService {
    private const GOOGLE_GEOCODING_URL = 'https://maps.googleapis.com/maps/api/geocode/json';
    
    public function __construct(private PDO $pdo) {}
    
    /**
     * Convert address to latitude/longitude.
     * 
     * Strategy:
     * 1. Check cache in database
     * 2. Call Google Maps API
     * 3. Cache result
     * 4. Return coordinates OR null if API fails
     */
    public function geocodeAddress(array $address): ?array {
        // Normalize address for caching
        $addressString = $this->normalizeAddress($address);
        $cacheKey = md5($addressString);
        
        // 1. Check cache
        $cached = $this->pdo->prepare(
            'SELECT latitude, longitude FROM geocoding_cache WHERE address_hash = ?'
        )->execute([$cacheKey])->fetch();
        
        if ($cached) {
            return ['lat' => (float)$cached['latitude'], 'lng' => (float)$cached['longitude']];
        }
        
        // 2. Call Google Maps API
        try {
            $url = self::GOOGLE_GEOCODING_URL . '?' . http_build_query([
                'address' => $addressString,
                'key' => $_ENV['GOOGLE_MAPS_API_KEY'] ?? ''
            ]);
            
            $response = json_decode(file_get_contents($url), true);
            
            if ($response['status'] !== 'OK' || empty($response['results'])) {
                return null;
            }
            
            $result = $response['results'][0];
            $lat = $result['geometry']['location']['lat'];
            $lng = $result['geometry']['location']['lng'];
            
            // 3. Cache result to avoid duplicate API calls
            $this->pdo->prepare('
                INSERT INTO geocoding_cache (address_hash, address_string, latitude, longitude, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE created_at = NOW()
            ')->execute([$cacheKey, $addressString, $lat, $lng]);
            
            return ['lat' => $lat, 'lng' => $lng];
        } catch (\Throwable $e) {
            // API failure → return null; caller will show friendly error
            return null;
        }
    }
    
    private function normalizeAddress(array $address): string {
        return "{$address['street']}, {$address['city']}, {$address['province']}, {$address['postal_code']}, {$address['country']}";
    }
}
```

### Pattern 3: Rate Limiting (Database Table)

**What:** Track login attempts per email + IP. Lock account for 15 minutes after 5 failures.

**When to use:** All login endpoints. Can expand to other sensitive actions (password reset, etc.).

**Example:**

```php
// src/Services/RateLimitService.php

class RateLimitService {
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;
    
    public function __construct(private PDO $pdo) {}
    
    /**
     * Check if email + IP is currently locked out.
     */
    public function isLocked(string $email, string $ipAddress): bool {
        $stmt = $this->pdo->prepare('
            SELECT locked_until FROM login_attempts
            WHERE email = ? AND ip_address = ? AND locked_until > NOW()
            LIMIT 1
        ');
        $stmt->execute([$email, $ipAddress]);
        return (bool)$stmt->fetch();
    }
    
    /**
     * Record a failed login attempt.
     * After 5 attempts, lock account for 15 minutes.
     */
    public function recordFailedAttempt(string $email, string $ipAddress): void {
        $stmt = $this->pdo->prepare('
            UPDATE login_attempts 
            SET attempt_count = attempt_count + 1,
                locked_until = IF(
                    attempt_count + 1 >= ?,
                    DATE_ADD(NOW(), INTERVAL ? MINUTE),
                    locked_until
                ),
                updated_at = NOW()
            WHERE email = ? AND ip_address = ?
        ');
        $stmt->execute([self::MAX_ATTEMPTS, self::LOCKOUT_MINUTES, $email, $ipAddress]);
        
        // If no row updated, insert new
        if ($stmt->rowCount() === 0) {
            $this->pdo->prepare('
                INSERT INTO login_attempts (email, ip_address, attempt_count, created_at, updated_at)
                VALUES (?, ?, 1, NOW(), NOW())
            ')->execute([$email, $ipAddress]);
        }
    }
    
    /**
     * Clear failed attempts after successful login.
     */
    public function clearAttempts(string $email, string $ipAddress): void {
        $this->pdo->prepare('
            DELETE FROM login_attempts WHERE email = ? AND ip_address = ?
        ')->execute([$email, $ipAddress]);
    }
}
```

### Pattern 4: Authorization Middleware (Protect Endpoints)

**What:** Check $_SESSION['user_id'] before allowing access to protected endpoints.

**When to use:** All user-specific endpoints (profile edit, bookings, messages, etc.).

**How to Integrate:** In Router.dispatch(), before calling controller action:

```php
// In Router.php dispatch() method:

public function dispatch(string $method, string $uri): string {
    // ... route matching code ...
    
    if ($this->matches($pattern, $uri, $params)) {
        $controllerClass = $handler[0];
        $action = $handler[1];
        
        // NEW: Check if endpoint requires authentication
        if ($this->isProtectedEndpoint($controllerClass, $action)) {
            $middleware = new AuthMiddleware();
            $middleware->requireAuth(); // Throws 401 if not authenticated
        }
        
        // ... instantiate and call controller ...
    }
}

private function isProtectedEndpoint(string $controller, string $action): bool {
    // All endpoints except register, login, health are protected
    $publicEndpoints = [
        'AuthController:register',
        'AuthController:login',
        'HealthController:check'
    ];
    return !in_array("{$controller}:{$action}", $publicEndpoints);
}
```

### Pattern 5: Profile View/Edit (UserService)

**What:** Encapsulate profile read/write logic. Denormalize all user fields in users table (no JOIN needed).

**Example:**

```php
// src/Services/UserService.php

class UserService {
    public function __construct(private UserRepository $repo) {}
    
    /**
     * Get user profile (public view).
     */
    public function getProfile(int $userId): array {
        $user = $this->repo->find($userId);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'bio' => $user['bio'],
            'avatar_url' => $user['profile_picture_url'],
            'address' => [
                'street' => $user['address_street'],
                'city' => $user['address_city'],
                'province' => $user['address_province'],
                'postal_code' => $user['address_postal_code'],
                'country' => $user['address_country']
            ],
            'statistics' => [
                'active_listings_count' => $this->getActiveListingsCount($userId),
                'completed_sales_count' => $this->getCompletedSalesCount($userId),
                'average_rating' => $user['rating_average'],
                'total_reviews' => $user['rating_count']
            ]
        ];
    }
    
    /**
     * Update user profile (authenticated user only).
     */
    public function updateProfile(int $userId, array $updates): bool {
        // Only allow these fields to be edited
        $allowedFields = ['first_name', 'last_name', 'bio', 'address_street', 'address_city', 'address_province', 'address_postal_code', 'address_country'];
        $filtered = array_intersect_key($updates, array_flip($allowedFields));
        
        if (empty($filtered)) {
            return true; // No changes
        }
        
        return $this->repo->update($userId, $filtered);
    }
    
    private function getActiveListingsCount(int $userId): int {
        // Query count of active listings where seller_id = $userId and deleted_at IS NULL
        // Defer to Phase 3 when ListingRepository exists
        return 0;
    }
    
    private function getCompletedSalesCount(int $userId): int {
        // Query count of completed bookings where seller_id = $userId and booking_status = 'completed'
        // Defer to Phase 6 when BookingRepository exists
        return 0;
    }
}
```

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Password hashing | Custom hash algorithm or raw MD5/SHA | `password_hash(PASSWORD_BCRYPT, ['cost' => 12])` | Bcrypt automatically salts, adjusts cost for future hardware changes; MD5/SHA vulnerable to GPU brute-force |
| Session management | Custom session ID generation or $_SESSION only | Database-backed sessions (already built Phase 1) | Enables distributed deployments, session inspection, audit trails; PHPSESSID cookie alone doesn't survive server restart |
| Rate limiting | Naive in-memory counter | Database table with email + IP + timestamp | Memory resets on server restart; database persists across requests and deployments |
| Google Maps API calls | Direct REST calls without caching | GeolocationService with database cache | Avoiding duplicate API calls saves quota (2.5K/day free tier) and improves latency |
| Email validation | Regex pattern | Respect\Validation Email validator or filter_var() | Regex misses edge cases (RFC 5321 compliance); library handles known pitfalls |
| Password verification | strcmp() or == operator | password_verify() | Timing attack vulnerable (attacker can infer password length from response time); password_verify() uses constant-time comparison |

**Key insight:** Auth logic is deceptively complex—session fixation, timing attacks, rate limiting, geolocation fallbacks. Leverage existing libraries and patterns; don't optimize prematurely.

---

## Common Pitfalls

### Pitfall 1: Session Fixation via Predictable Session ID

**What goes wrong:** Attacker forces a known session ID onto victim; victim logs in; attacker uses pre-known ID to hijack session.

**Why it happens:** Session ID not regenerated on login, or ID is predictable (timestamps, sequential numbers).

**How to avoid:**
- SessionHandler.login() regenerates ID with `bin2hex(random_bytes(32))` (256-bit entropy ✓)
- Phase 1 already implemented; Phase 2 must call session.login() after password verification
- **Code check:** Grep for `session_regenerate_id()` in login flow

**Warning signs:**
- Session ID looks short or numeric
- Same session ID visible across multiple login attempts
- Session ID changes on each request (over-regeneration, wastes CPU)

### Pitfall 2: Timing Attack on Password Verification

**What goes wrong:** Attacker measures response time; short response = wrong password early in check; long response = password matched more characters before failing.

**Why it happens:** Manual string comparison (`if ($inputPassword === $hash)`) takes longer the more characters match before diverging.

**How to avoid:**
- Use `password_verify($input, $hash)` which compares hashes in constant time
- Never use `==` or `strcmp()` for password comparison
- **Code check:** Grep for `password_verify` in login controller; no manual hash comparison

**Warning signs:**
- Response time varies significantly between "wrong email" and "wrong password"
- Custom password verification logic anywhere in codebase

### Pitfall 3: Generic Error Messages Not Generic Enough

**What goes wrong:** "Invalid credentials" message is correct, but system logs or error details reveal whether email exists (via different error codes or admin panel).

**Why it happens:** Frontend developers see "Invalid credentials" and assume privacy is fine; backend still logs/tracks user existence.

**How to avoid:**
- Return same error code (e.g., 400) for both "email not found" and "password wrong"
- Log failures without exposing the reason to user
- Rate limit by email + IP (to prevent enumeration via response time variation)
- **Code check:** Verify AuthController returns same HTTP status for both failures

**Warning signs:**
- Different error messages for "email not found" vs "password wrong"
- Admin panel showing "failed login count" per email (leaks user existence)
- Logs revealing which accounts are targeted

### Pitfall 4: Forgetting to Clear Failed Login Counter on Success

**What goes wrong:** User enters password wrong 4 times, then logs in correctly on 5th attempt; account is immediately locked because counter wasn't reset.

**Why it happens:** Rate limiting logic updates on failure but forgets to reset on success.

**How to avoid:**
- Call `rateLimiter.clearAttempts($email, $ip)` after successful password_verify()
- Unit test: simulate 4 failures + 1 success → verify no lockout

**Warning signs:**
- Legitimate users complaining of false lockouts after mix of failed + successful logins
- Login counter never decreases in admin panel

### Pitfall 5: Race Condition in Geolocation Caching

**What goes wrong:** Two simultaneous requests both geocode "123 Main St"; both hit Google API; both try to insert cache row → one fails with duplicate key error.

**Why it happens:** No lock between "check cache" and "insert cache" steps.

**How to avoid:**
- Use `INSERT ... ON DUPLICATE KEY UPDATE` (atomically inserts or updates)
- Already shown in Pattern 2 example above
- **Code check:** Verify geocoding_cache table has unique index on address_hash

**Warning signs:**
- Sporadic errors during high signup traffic
- Google Maps API quotas exhausted despite low unique addresses

### Pitfall 6: Password Hashing Cost Too Low (speed) or Too High (DoS)

**What goes wrong:** Cost=10 → too fast, vulnerable to GPU brute-force. Cost=15 → hashing takes 2+ seconds; 100 concurrent logins = 200s total response time = DoS.

**Why it happens:** No benchmarking for the target server's performance.

**How to avoid:**
- Use cost=12 as baseline (256ms on typical server; recommended since PHP 8.4)
- Run benchmark on production-like hardware: increase cost until hash takes 250-350ms
- **Code check:** RateLimitService should have const BCRYPT_COST = 12 (configurable if needed)
- Test: Login should respond in <500ms even under load

**Warning signs:**
- Login consistently slow (>1s)
- CPU at 100% during login surge
- Users reporting "spinning wheel" on login page

### Pitfall 7: No Validation on Address Components

**What goes wrong:** User enters gibberish address "!@#$% &*()"; geocoding API returns null or invalid coordinates; system crashes or stores nonsense.

**Why it happens:** Assuming Google API will reject invalid addresses; forgetting to validate before submission.

**How to avoid:**
- Frontend: Form UI with address field validators (zipcode format, city lookup, etc.)
- Backend: Require all 5 address components non-empty; validate postal code format per country
- GeolocationService returns null if geocoding fails; caller shows friendly error
- **Code check:** Verify PostalCodeValidator exists (or manual regex per country) before inserting

**Warning signs:**
- Users with invalid addresses in database
- Geocoding API errors in logs but no user-facing message

### Pitfall 8: Session Storage Disabled (PHP ini setting)

**What goes wrong:** sessions.save_path directory doesn't exist or isn't writable; $_SESSION vars silently lost; users can't stay logged in.

**Why it happens:** PHP ini not configured; file permissions wrong; /tmp cleaned by server

**How to avoid:**
- Phase 1 uses database-backed sessions (SessionHandler class) → no filesystem dependency
- Verify in Phase 2: SessionHandler queries sessions table on every request
- Test: Make request; verify rows in sessions table; refresh page; verify same session_id in cookie
- **Code check:** Confirm api.php calls SessionHandler.validate() before each request

**Warning signs:**
- Session cookie set but user logged out after page refresh
- No entries in sessions table
- PHP error logs showing "open_basedir restriction" or "Permission denied" for session handler

### Pitfall 9: Forgetting Authorization Checks on Profile Edit

**What goes wrong:** PATCH /api/users/42 allows user with ID 1 to edit user 42's profile.

**Why it happens:** Controller checks authentication ($_SESSION['user_id'] exists) but not authorization (user_id matches resource owner).

**How to avoid:**
- In UserController.update(), verify: if ($_SESSION['user_id'] !== $userId) throw 403 Forbidden
- **Code check:** Every controller action that modifies data checks ownership
- Test: Login as user 1; try PATCH /api/users/2; expect 403

**Warning signs:**
- Users reporting their profile changed without action
- Multiple users sharing a phone number / address (sign of unauthorized edits)

---

## Code Examples

### Example 1: Registration Endpoint (AuthController)

```php
// src/Controllers/AuthController.php

class AuthController {
    public function __construct(
        private AuthService $authService,
        private Response $response
    ) {}
    
    /**
     * POST /api/auth/register
     * 
     * Request body:
     * {
     *   "email": "user@example.com",
     *   "password": "SecurePass123",
     *   "first_name": "John",
     *   "last_name": "Doe",
     *   "address": {
     *     "street": "123 Main St",
     *     "city": "New York",
     *     "province": "NY",
     *     "postal_code": "10001",
     *     "country": "USA"
     *   },
     *   "coordinates": {
     *     "lat": 40.7128,
     *     "lng": -74.0060
     *   }
     * }
     * 
     * Response (201):
     * {
     *   "status": "success",
     *   "data": {
     *     "user_id": 1,
     *     "email": "user@example.com",
     *     "first_name": "John"
     *   },
     *   "message": "Registration successful"
     * }
     */
    public function register(array $get, array $post, array $files, array $params): string {
        try {
            // Parse request body
            $input = json_decode(file_get_contents('php://input'), true) ?? $post;
            
            // Validate required fields
            $required = ['email', 'password', 'first_name', 'last_name', 'address'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    return $this->response->validationError([
                        'field' => $field,
                        'message' => "{$field} is required"
                    ]);
                }
            }
            
            // Register user
            $result = $this->authService->register(
                email: $input['email'],
                password: $input['password'],
                firstName: $input['first_name'],
                lastName: $input['last_name'],
                address: $input['address'],
                coordinates: $input['coordinates'] ?? null
            );
            
            http_response_code(201);
            return $this->response->success($result, 'Registration successful');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'already registered')) {
                http_response_code(400);
                return $this->response->error($e->getMessage());
            }
            
            http_response_code(400);
            return $this->response->error($e->getMessage());
        }
    }
    
    /**
     * POST /api/auth/login
     */
    public function login(array $get, array $post, array $files, array $params): string {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? $post;
            
            if (empty($input['email']) || empty($input['password'])) {
                return $this->response->validationError([
                    'field' => 'email|password',
                    'message' => 'Email and password required'
                ]);
            }
            
            $result = $this->authService->login($input['email'], $input['password']);
            
            return $this->response->success($result, 'Login successful');
        } catch (Exception $e) {
            http_response_code(401);
            return $this->response->error('Invalid credentials');
        }
    }
    
    /**
     * POST /api/auth/logout
     */
    public function logout(array $get, array $post, array $files, array $params): string {
        $this->authService->logout();
        return $this->response->success([], 'Logout successful');
    }
    
    /**
     * GET /api/auth/me
     * 
     * Returns current user's profile (requires auth).
     */
    public function getMe(array $get, array $post, array $files, array $params): string {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                http_response_code(401);
                return $this->response->error('Not authenticated');
            }
            
            $userService = new UserService($this->userRepo);
            $profile = $userService->getProfile($userId);
            
            return $this->response->success($profile);
        } catch (Exception $e) {
            http_response_code(500);
            return $this->response->error($e->getMessage());
        }
    }
}
```

### Example 2: UserRepository (Find by Email)

```php
// src/Repositories/UserRepository.php

class UserRepository extends BaseRepository {
    public function __construct(PDO $pdo) {
        parent::__construct($pdo, 'users');
    }
    
    /**
     * Find user by email (excluding soft-deleted).
     */
    public function findByEmail(string $email): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE email = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
```

### Example 3: Database Tables (Schema)

```sql
-- Already in Phase 1 schema:

CREATE TABLE sessions (
  session_id VARCHAR(64) PRIMARY KEY,
  user_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  bio TEXT,
  profile_picture_url VARCHAR(500),
  address_street VARCHAR(255),
  address_city VARCHAR(100),
  address_province VARCHAR(100),
  address_postal_code VARCHAR(10),
  address_country VARCHAR(100),
  latitude DECIMAL(10, 8),
  longitude DECIMAL(11, 8),
  rating_average DECIMAL(3, 2) DEFAULT 0,
  rating_count INT DEFAULT 0,
  is_verified BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  INDEX idx_email (email),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NEW tables for Phase 2:

CREATE TABLE login_attempts (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempt_count INT DEFAULT 1,
  locked_until TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email_ip (email, ip_address),
  INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE geocoding_cache (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  address_hash VARCHAR(64) NOT NULL UNIQUE,
  address_string TEXT NOT NULL,
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_address_hash (address_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `md5()` password hashing | `password_hash(PASSWORD_BCRYPT)` with cost=12 | PHP 5.5+ (2013) | Bcrypt is GPU/ASIC-resistant; MD5 can be brute-forced in hours |
| `session_start()` with file storage | Database-backed sessions | 2010s | Enables distributed deployments; no lost sessions on server restart; auditable |
| `strcmp()` password comparison | `password_verify()` constant-time | PHP 5.6+ (2014) | Eliminates timing attack vulnerability |
| Email verification at signup mandatory | Optional email verification (Phase 2 deferred) | 2020s trend | Reduces friction; spam prevention via reputation system instead |
| "Remember me" 30-day tokens | Single 30-minute idle session | Mobile-first design (2020s) | Simpler; users expect re-login; reduces token theft risk |
| `rand()` for session IDs | `random_bytes()` CSPRNG | PHP 7.0+ (2015) | Cryptographically secure; `rand()` is predictable |

**Deprecated/Outdated:**
- `mcrypt_*` functions: Removed in PHP 7.1 (use OpenSSL instead)
- `md5(password)` hashing: Vulnerable to GPU brute-force (use bcrypt)
- File-based sessions: Lost on restart (use database)

---

## Open Questions

1. **Password strength requirement specifics**
   - **What we know:** Minimum 8 characters; real-time meter (Weak/Fair/Good/Strong)
   - **What's unclear:** Do we require uppercase + numbers + special chars? (marked OpenCode's Discretion)
   - **Recommendation:** Start with 8 chars minimum; real-time meter shows Good if 8+ chars + mixed case + number. Tighten rules based on user feedback.

2. **Google Maps API Rate Limiting**
   - **What we know:** Free tier = 2,500 requests/day; caching will help
   - **What's unclear:** What if user hits limit mid-signup? (marked OpenCode's Discretion)
   - **Recommendation:** Implement exponential backoff + cached results; if API fails, show friendly error "Unable to verify address; try again later"

3. **Avatar Upload Security**
   - **What we know:** User optional; default provided if skipped
   - **What's unclear:** File size limit? Dimensions? Formats? (marked OpenCode's Discretion)
   - **Recommendation:** Suggest 2MB max, 1024x1024px min (for thumbnail), JPG/PNG only. Phase 3 covers image upload details.

4. **Email Notifications for Rate Limiting**
   - **What we know:** 5 failed logins → 15-min lock + email notification
   - **What's unclear:** Email delivery infrastructure (SMTP, service)? Email template content? (marked OpenCode's Discretion)
   - **Recommendation:** Defer email infrastructure to Phase X; for Phase 2, log the intent "send email to user@example.com" without actually sending. Phase 3 can integrate SendGrid/postmark.

5. **Session Timeout Grace Period**
   - **What we know:** 30-minute idle timeout with activity reset
   - **What's unclear:** Show warning before logout? Allow "5 more minutes"? (marked OpenCode's Discretion)
   - **Recommendation:** Start simple: silently expire session after 30 min. If users complain, add 2-minute warning + "extend session" button in Phase 2.1.

---

## Validation Architecture

**Note:** Configuration `workflow.nyquist_validation` is not set in `.planning/config.json`, so this section is deferred. Tests will be designed and built as part of Phase 2 execution, not Wave 0.

---

## Sources

### Primary (HIGH confidence)

- **PHP password_hash/password_verify documentation:** https://www.php.net/manual/en/function.password-hash.php
  - Topics: Bcrypt cost=12 default (PHP 8.4), password verification constant-time comparison, automatic salt generation
  
- **OWASP Session Management Cheat Sheet:** https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html
  - Topics: Session ID entropy (≥64 bits), regeneration on login, HttpOnly/Secure/SameSite cookie flags, idle timeout best practices, rate limiting
  
- **OWASP Authentication Cheat Sheet:** https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
  - Topics: Password hashing (bcrypt, Argon2), rate limiting (5 failed attempts), error messages (generic), account lockout (15 minutes)

- **Google Maps Geocoding API v3 Documentation:** https://developers.google.com/maps/documentation/geocoding/overview
  - Topics: Address to coordinates conversion, API quotas (2,500 requests/day free tier), response format
  
- **Phase 1 Research (STATE.md):** Confirms SessionHandler already built with:
  - Database-backed sessions (sessions table with session_id, user_id, expires_at)
  - 30-minute idle timeout with activity refresh
  - Secure cookie flags (HttpOnly, Secure, SameSite=Strict)
  - Session ID regeneration post-login

### Secondary (MEDIUM confidence)

- **Respect/Validation Library (already in Phase 1 composer):** Email validation via Respect\Validation\Validator::email()

- **Intervention/Image (already available):** Avatar re-encoding for security (discussed in Phase 3, but relevant for planning)

- **PHP PDO Prepared Statements:** Already confirmed in Phase 1 BaseRepository (eliminates SQL injection)

### Tertiary (LOW confidence - implementation choices)

- Rate limiting database schema (login_attempts table): Proposed based on OWASP guidance; exact schema details TBD
- Geocoding cache strategy: Proposed caching in database; exact TTL and eviction strategy TBD
- RateLimitService implementation: Standard pattern; exact lockout messages and email content marked OpenCode's Discretion

---

## Metadata

**Confidence breakdown:**

| Area | Level | Reason |
|------|-------|--------|
| Standard Stack (PHP password_* + bcrypt) | HIGH | PHP official documentation + OWASP endorsement; no external libraries needed |
| Session Management (database-backed) | HIGH | Phase 1 already built SessionHandler; confirmed in codebase (src/Config/SessionHandler.php) |
| Rate Limiting (5 attempts → 15-min lock) | HIGH | OWASP cheat sheet + clear requirements in CONTEXT.md |
| Geolocation (Google Maps API + fallback) | MEDIUM | Google Maps API documentation confirmed; caching strategy proposed but not verified against production load |
| Authorization Middleware | HIGH | Standard pattern; Phase 1 Router architecture supports middleware injection |
| Password Strength Real-Time Meter | MEDIUM | UI/UX pattern known; backend validation rules (uppercase/numbers/special chars) marked OpenCode's Discretion |
| Error Handling (generic messages) | HIGH | OWASP authentication cheat sheet + CONTEXT.md explicit |

**Research date:** 2026-03-23  
**Valid until:** 2026-04-23 (30 days; PHP auth patterns stable, no major updates expected)

---

**Phase 2 research complete. All authentication, session, and profile patterns documented. Ready for planning.**

*Last updated: 2026-03-23*

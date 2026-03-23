# Domain Pitfalls: P2P Used Electronics Marketplace

**Domain:** Peer-to-peer marketplace (used electronics)  
**Researched:** 2026-03-23  
**Project Context:** Plain PHP (no framework), security-first, layered architecture with Services/Repos

---

## Critical Pitfalls

Critical pitfalls cause system rewrites, data corruption, or complete security breaches.

### Pitfall 1: Prepared Statement Discipline Loss Under Time Pressure

**What goes wrong:** 
Developers bypass PDO prepared statements for "simple" queries, use string concatenation during features added late in sprints, or forget to parameterize edge cases (ORDER BY, LIMIT, status IN lists). A single missed prepared statement becomes an SQL injection vulnerability.

**Why it happens:**
- Plain PHP has no framework ORM enforcing query safety
- String concatenation is faster to write initially than parameterization
- Time pressure at launch tempts shortcuts on "obviously safe" queries
- Dynamic status filters (e.g., booking status IN (?)) require array handling not immediately obvious

**Consequences:**
- Full database disclosure (user passwords, payment data, listing details)
- Data corruption (mass updates/deletes)
- Attacker account creation with admin privileges
- Regulatory violations (GDPR, data protection laws)

**Prevention:**
- Enforce prepared statements at the Repository layer: ALL queries must use `?` placeholders, zero exceptions
- Code review checklist: Every SQL query in diff must show `execute([...params...])` pattern
- Implement a simple SQL lint script: `grep -r "SELECT.*\$" app/repositories/` catches obvious concatenation
- For dynamic IN clauses: create helper: `$placeholders = implode(',', array_fill(0, count($statuses), '?')); $stmt->execute(array_merge($statuses))`
- Add pre-commit hook: fail if raw SQL strings contain `\$` variable interpolation
- Unit test every repository method with malicious input (e.g., `admin' OR '1'='1`)

**Detection:** 
- SQL queries in logs/errors showing variable values (e.g., `...WHERE user_id = 42...` instead of parameter markers)
- Login bypasses with simple SQL injection attempts
- Unexpected data leaks or modifications

**Phase to address:** Phase 1 (Auth) — establish prepared statement patterns before other modules copy bad patterns

---

### Pitfall 2: Soft Delete Filters Forgotten in Critical Queries

**What goes wrong:**
Soft-deleted records (flagged with `deleted_at` timestamp) leak into queries that don't filter them out. A user who deletes their account still appears in listings, chat history, or user searches. Deleted listings appear on the map. Deleted bookings affect transaction counts.

**Why it happens:**
- Plain PHP requires manual filtering — no ORM default scope
- Easy to forget when adding a new query
- Services layer passes repositories that include deleted records
- Tests don't validate soft delete filtering across the system

**Consequences:**
- GDPR violations (deleted users still visible)
- Confusing UX (users see deleted accounts/listings)
- Incorrect reporting (deleted bookings still counted)
- Security risk (deleted admin accounts still accessible if auth cache not invalidated)

**Prevention:**
- Create a base `AbstractRepository` with a protected method: `protected function applyDeleteFilter($query) { return $query . ' AND deleted_at IS NULL'; }`
- Every SELECT query in Repository must call `applyDeleteFilter()` before execute
- Service layer validates: "all records returned must have `deleted_at IS NULL`"
- Implement repository-level test: soft-delete a record, run all SELECT queries, assert record doesn't appear
- Add database constraint: any foreign key to `users` table must use `WHERE deleted_at IS NULL` join condition
- Pre-commit hook: grep for SELECT statements missing `AND deleted_at IS NULL` (with exceptions list for audit queries)

**Detection:**
- Users or listings still visible after deletion
- Abandoned accounts appearing in chat histories
- Admin delete action not removing records from public queries
- Database reports showing "more users than registered"

**Phase to address:** Phase 2 (Listings) — establish soft delete pattern before Reviews/Chat phases that depend on filtering

---

### Pitfall 3: Transaction Atomicity Lost Between Booking and Chat Creation

**What goes wrong:**
When creating a booking, the system creates a booking record AND a chat conversation in two separate SQL transactions. If the second insert fails, the booking exists but users have no way to communicate. Users must retry booking creation, creating duplicates. Bookings exist in abandoned states.

**Why it happens:**
- Plain PHP requires manual transaction management (no ORM automatic wrapping)
- Two separate Repository calls look independent but are logically atomic
- Developers assume "if first succeeds, second will" without error handling
- Testing doesn't simulate mid-transaction failure (DB connection lost, table lock, etc.)

**Consequences:**
- Bookings without chat conversations (communication impossible)
- Duplicate booking attempts
- Stuck transactions blocking other users
- Data inconsistency (booking table out of sync with conversations table)

**Prevention:**
- Implement a `Transaction` class/service that wraps PDO transactions:
  ```php
  public function bookingWithChat($userId, $listingId) {
    $this->db->beginTransaction();
    try {
      $bookingId = $this->bookingRepo->create($userId, $listingId);
      $conversationId = $this->chatRepo->createConversation($userId, $listingId);
      $this->bookingRepo->linkConversation($bookingId, $conversationId);
      $this->db->commit();
      return $bookingId;
    } catch (Exception $e) {
      $this->db->rollBack();
      throw $e;
    }
  }
  ```
- Unit test: simulate exception on second insert, verify rollback via assertion on DB state
- Service layer rule: any operation affecting >1 table must use explicit transaction wrapper
- Monitor: log all transaction rollbacks with reason
- Timeout enforcement: set transaction timeout to prevent locks from hanging forever

**Detection:**
- Bookings in DB without corresponding chat conversations
- Users reporting "booking created but can't message"
- Orphaned chat records (conversations with no booking)

**Phase to address:** Phase 3 (Bookings) — architect transaction patterns before Chat integration

---

### Pitfall 4: Image Upload Validation and Malware Through File Type Bypasses

**What goes wrong:**
An attacker uploads a PHP script disguised as `.jpg` (e.g., `shell.php.jpg` or `shell.jpg.php`), bypasses simple extension whitelist, and achieves RCE. Or a ZIP bomb exhausts disk space. Or an image file containing PHP code in metadata gets executed when the web server misconfigures handlers.

**Why it happens:**
- Simple `getimagesize()` or extension check is insufficient
- File type detection can be spoofed (rename `.exe` to `.jpg`)
- Web server misconfigurations (Apache double extension handling)
- Uploaded files stored in web-accessible directory with execute permissions
- Metadata/comments in image files can contain executable code

**Consequences:**
- Remote code execution on the web server
- Site defacement / malware distribution
- Denial of service (disk space exhaustion)
- User privacy violation (metadata extraction)

**Prevention:**
- Never use extension whitelist alone — always verify file content:
  1. Check MIME type via `mime_content_type()` or `finfo_file()`
  2. Validate file signature (magic bytes): PHP code should start with specific binary patterns for PNG/JPEG
  3. Use getimagesize() to confirm dimensions (validates image structure)
  4. Re-encode the image: use `imagecreatefromjpeg()` → `imagejpeg()` to strip metadata/embedded code
- Store files OUTSIDE web root or in non-executable directory:
  ```apache
  <Directory /var/uploads>
    php_flag engine off
    <FilesMatch "\.php$">
      Deny from all
    </FilesMatch>
  </Directory>
  ```
- Implement file size limits: max 5MB per image, total user quota 50MB
- Randomize filenames: store as `sha256(userid_timestamp_random).jpg`, not user-provided names
- Scan uploaded files before serving (use ClamAV or similar, or upload to separate unexecutable domain)
- Test: attempt to upload polyglot files (valid image + executable code), verify code doesn't execute

**Detection:**
- Web server errors parsing image files
- PHP execution within upload directories
- Unusual file types in upload folder
- Metadata extraction tools finding suspicious content in images

**Phase to address:** Phase 2 (Listings) — secure image upload before public launch

---

### Pitfall 5: Race Condition: Double-Booking or Inventory Clash

**What goes wrong:**
Two users simultaneously book the same item. Both transactions see the item as available, both create bookings. The listing status changes to booked, but now two bookings reference it. Seller is confused (who do I sell to?), one buyer is disappointed.

**Why it happens:**
- Check-then-set pattern: query availability, then insert booking as two separate DB calls
- No unique constraint preventing duplicate bookings
- Concurrency isn't tested locally (single-threaded development)
- SELECT doesn't lock the row, so concurrent SELECTs both return "available"

**Consequences:**
- Duplicate bookings for single item
- Unclear transaction ownership
- Disappointed users
- Seller-buyer coordination issues

**Prevention:**
- Use database-level unique constraint: `UNIQUE(listing_id, user_id, status)` prevents duplicate active bookings
- Use pessimistic locking (SELECT ... FOR UPDATE) to lock the listing row during check-then-set:
  ```php
  $this->db->beginTransaction();
  $listing = $this->db->prepare("SELECT id FROM listings WHERE id = ? AND status = 'available' FOR UPDATE")
    ->execute([$listingId])->fetch();
  if (!$listing) {
    $this->db->rollBack();
    throw new ListingNotAvailableException();
  }
  // Now insert booking (safe, listing is locked)
  $bookingId = $this->bookingRepo->create($userId, $listingId);
  $this->db->commit();
  ```
- Unit test with concurrent inserts: spawn 2 parallel requests to book same item, verify only 1 succeeds
- Monitor: alert on multiple active bookings per listing (should be 0 or 1)

**Detection:**
- Multiple bookings with `status = 'pending'` for same listing
- User reports: "booking succeeded but another user got the item"
- Constraint violations in error logs (duplicate key errors)

**Phase to address:** Phase 3 (Bookings) — add locking/constraints before launch

---

## Moderate Pitfalls

Moderate pitfalls cause data loss, UX breakage, or security gaps that require rework.

### Pitfall 6: Geolocation Coordinate Precision and Query Edge Cases

**What goes wrong:**
Haversine formula distance calculations are imprecise due to:
- Floating-point rounding errors accumulating in distance calculation
- Latitude/longitude precision loss during storage (storing 6 decimals = 0.1m precision needed but only 1m stored)
- Equator vs pole distance metrics (1 degree longitude ≠ 1 degree latitude)
- Query results missing nearby listings due to rounding in distance formula
- Listing at exact search radius boundary sometimes included, sometimes not
- Queries timeout with large result sets (distance calculation on 100K records)

**Why it happens:**
- Developers assume Haversine formula is always accurate
- Google Maps API provides coordinates to 8+ decimal places, but database stores fewer
- No testing with edge cases (items at radius boundary, poles, equator)
- Unindexed coordinate columns cause full-table distance scans

**Consequences:**
- Users can't find nearby items that are actually within search radius
- Inconsistent search results (same query returns different counts)
- Slow queries (timeout on large marketplace)
- Incorrect distance displays ("5.0 km" when actually 5.2 km)

**Prevention:**
- Store coordinates to 7+ decimal places (0.01m precision):
  ```sql
  CREATE TABLE listings (
    latitude DECIMAL(10, 7),
    longitude DECIMAL(10, 7),
    SPATIAL INDEX(geom)  -- or geohash index
  )
  ```
- Use spatial indexes or geohash:
  - Option A: MySQL SPATIAL index for `ST_Distance()` queries (requires MySQL 5.7.6+)
  - Option B: Geohash: convert (lat, lon) to geohash string, index that, then verify distance with Haversine
  - Option C: Bounding box pre-filter before Haversine: `WHERE latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?`
- Always add buffer to radius: search for `distance <= radius + 0.001` (1m buffer) to catch boundary cases
- Unit test: 
  - Items at exactly radius distance
  - Items 1m inside radius
  - Items 1m outside radius
  - Poles and equator queries
  - Verify result consistency across multiple runs
- Cache radius-based queries: for popular radii (1km, 5km, 10km), pre-calculate and cache results

**Detection:**
- Users report "I can see the item on the map but not in search results"
- Distance calculations show inconsistent precision
- Slow queries after 1000+ listings added
- Queries returning incomplete results

**Phase to address:** Phase 2 (Listings) — implement spatial indexing before map goes live

---

### Pitfall 7: Session Management: Fixation and Hijacking in Plain PHP

**What goes wrong:**
- Session IDs not regenerated after login: attacker pre-fixes a session ID, tricks user to login with it, then hijacks the session
- Session tokens stored in URL/query string: leaked in referrer headers, browser history, server logs
- Session data not properly invalidated on logout: session cookie persists or can be reused
- No CSRF protection on state-changing operations: attacker tricks logged-in user to change settings via malicious link
- Session timeout too long: stolen session remains valid for hours

**Why it happens:**
- Plain PHP `session_start()` doesn't regenerate IDs by default
- Developers assume PHP sessions are automatically secure
- Tests don't include session hijacking scenarios
- No token rotation on sensitive operations

**Consequences:**
- Attacker gains access to user accounts
- Ratings/reviews submitted from attacker's identity
- Listing fraudulently created on stolen account
- Chat messages sent from victim's account
- Account data modified without user knowledge

**Prevention:**
- Regenerate session ID after login:
  ```php
  // In AuthService
  session_regenerate_id(true);  // true = delete old session file
  $_SESSION['user_id'] = $user->id;
  $_SESSION['login_time'] = time();
  ```
- Use secure session cookie flags:
  ```php
  ini_set('session.cookie_secure', '1');      // HTTPS only
  ini_set('session.cookie_httponly', '1');    // No JS access
  ini_set('session.cookie_samesite', 'Strict');
  ini_set('session.gc_maxlifetime', '900');   // 15 min timeout
  ```
- Implement CSRF tokens on all state-changing forms:
  ```php
  // Controller
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  // Form
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
  // Validation
  if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) { throw new InvalidTokenException(); }
  // Invalidate after use (optional but stronger)
  ```
- Clear sessions on logout: `session_destroy()` + unset all `$_SESSION` variables
- Re-authenticate for sensitive operations (changing email, password, payment method): verify password again
- Test: attacker pre-fixes session ID, logs in as user, verify token changed

**Detection:**
- Multiple session IDs for same user in session storage
- Session hijacking attempts in access logs (same session from different IPs)
- CSRF token mismatches
- Session timeout bypasses

**Phase to address:** Phase 1 (Auth) — implement before any user data is exposed

---

### Pitfall 8: N+1 Query Problem in Chat Message Fetching

**What goes wrong:**
Chat page loads conversation, then polls every 2-5 seconds for new messages. Each poll:
1. Fetches all messages for conversation (1 query)
2. Loops through messages to fetch sender details (N queries)
If conversation has 100 messages, each poll does 101 queries. With 1000 active users polling, that's 100K queries/second.

**Why it happens:**
- Repository returns message objects without eager-loading sender data
- Service loops through messages and calls `userRepo->findById()` for each
- No caching of user profile data
- No batch loading of related records
- Tests use small message counts, masking the issue

**Consequences:**
- Slow message loading (timeout on conversations with many messages)
- Database resource exhaustion
- Chat UI hangs waiting for response
- Server crashes under load
- Users see "connection error" repeatedly

**Prevention:**
- Eager-load sender data in single query:
  ```php
  // Instead of:
  $messages = $this->db->query("SELECT * FROM messages WHERE conversation_id = ?");
  foreach ($messages as $msg) {
    $msg['sender'] = $this->userRepo->findById($msg['user_id']);  // N queries
  }
  
  // Use JOIN:
  $messages = $this->db->prepare(
    "SELECT m.*, u.id, u.name, u.avatar FROM messages m
     JOIN users u ON m.user_id = u.id
     WHERE m.conversation_id = ? ORDER BY m.created_at DESC LIMIT 50"
  )->execute([$conversationId])->fetchAll();
  ```
- Implement cursor-based pagination: fetch last 50 messages, then only new messages after a timestamp
  ```php
  // Poll only new messages since last fetch
  $messages = $this->db->prepare(
    "SELECT m.*, u.id, u.name FROM messages m
     JOIN users u ON m.user_id = u.id
     WHERE m.conversation_id = ? AND m.created_at > ? ORDER BY m.created_at ASC"
  )->execute([$conversationId, $lastFetchTime]);
  ```
- Cache sender profile data (name, avatar, rating): 1 hour TTL, invalidate on profile update
- Reduce polling frequency: 5 seconds minimum, or implement long-polling with timeout
- Monitor query counts: add logging to track queries per request, alert if >10 queries

**Detection:**
- Chat page load taking >2 seconds
- Database query count spike during peak hours
- User reports of chat "feeling slow"
- Slow query logs showing message fetches

**Phase to address:** Phase 4 (Chat) — optimize queries before launch

---

### Pitfall 9: Image Storage and Retrieval Without Cleanup

**What goes wrong:**
Users upload listing images, edit listings (old images remain on disk), delete listings (images never deleted). After 1 year, disk fills with orphaned images. No way to know which images are referenced, which are abandoned. No garbage collection strategy.

**Why it happens:**
- No cascade delete on listing deletion
- No cleanup process for orphaned files
- Image filenames not tracked in DB for cleanup
- Developers assume "old images don't matter"

**Consequences:**
- Disk space exhaustion
- Server outages (no disk space)
- Slow backups/file operations
- Lost storage budget

**Prevention:**
- Track all image paths in database:
  ```sql
  CREATE TABLE listing_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    listing_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
  );
  ```
- Implement soft delete on images: add `deleted_at` column, set on listing edit/delete
- Create periodic cleanup service (weekly cron job):
  ```php
  // Find orphaned images (no reference in DB, older than 7 days)
  $allFiles = glob('/uploads/listings/*');
  foreach ($allFiles as $file) {
    $inDb = $this->db->query("SELECT id FROM listing_images WHERE file_path = ?", [$file])->fetch();
    if (!$inDb && (time() - filemtime($file) > 7*24*3600)) {
      unlink($file);  // Delete orphaned file
    }
  }
  ```
- Unit test: upload image, delete listing, verify file deleted + database cleaned
- Monitor: alert if upload directory size > 1GB

**Detection:**
- Disk space warnings
- Orphaned files in upload directory
- Database and filesystem out of sync

**Phase to address:** Phase 2 (Listings) — implement cleanup during image feature

---

## Minor Pitfalls

Minor pitfalls cause user friction or technical debt.

### Pitfall 10: Password Hashing Regression (Wrong Algorithm)

**What goes wrong:**
Developer uses `md5()` or `sha1()` for passwords because "it's fast" or "looks secure". These are cryptographic hash functions, not password hashing algorithms. They're fast for attackers to brute-force with modern GPUs. Or uses `password_hash()` without checking the returned cost parameter.

**Why it happens:**
- `password_hash()` seems slower in benchmarks
- Developers unfamiliar with password security best practices
- Legacy code used weak algorithms, new features copied pattern
- No code review catching weak hashes

**Consequences:**
- Passwords cracked via brute-force attacks (GPU rainbow tables)
- User account compromise
- Privacy violations

**Prevention:**
- Mandate `password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])` only
- Code review: grep for md5/sha1 on password columns, fail the review if found
- Add constants to auth service:
  ```php
  const PASSWORD_ALGORITHM = PASSWORD_BCRYPT;
  const PASSWORD_COST = 12;  // Slow enough to deter brute-force
  ```
- Verify on login:
  ```php
  if (!password_verify($submittedPassword, $user->password_hash)) {
    throw new InvalidCredentialsException();
  }
  if (password_needs_rehash($user->password_hash, self::PASSWORD_ALGORITHM, ['cost' => self::PASSWORD_COST])) {
    // Rehash and update on next login
    $user->password_hash = password_hash($submittedPassword, self::PASSWORD_ALGORITHM, ['cost' => self::PASSWORD_COST]);
  }
  ```
- Unit test: verify password hash resistant to common attacks

**Detection:**
- Passwords stored as md5/sha1 hashes
- Short password hashes (32-40 chars instead of 60+ for bcrypt)

**Phase to address:** Phase 1 (Auth)

---

### Pitfall 11: Data Validation Only at Controller Level

**What goes wrong:**
Controllers validate input (max length, format, range). But Services assume data is valid. If Service is called from different controller or external API, validation is skipped. Invalid data corrupts database (negative prices, 500-character titles in title field).

**Why it happens:**
- Developers validate at request boundary, assume internal calls are safe
- Layered architecture not enforced: Service should validate, not rely on Controller
- Refactoring moves validation responsibility without updating all call sites

**Consequences:**
- Invalid data in database (negative prices show as astronomical values, corrupted strings)
- Broken queries or business logic (e.g., price sorting breaks with invalid values)
- Data cleaning required (manual SQL cleanup)

**Prevention:**
- Implement Value Objects for validation:
  ```php
  class Price {
    public function __construct(float $amount) {
      if ($amount < 0 || $amount > 999999) {
        throw new InvalidPriceException("Price must be 0-999999");
      }
      $this->amount = round($amount, 2);  // Always 2 decimals
    }
  }
  
  // In Service:
  $price = new Price($data['price']);  // Validates here
  $this->repo->updateListing($listingId, ['price' => $price->amount]);
  ```
- Services layer validates before Repository:
  ```php
  public function updateListing($listingId, $data) {
    $validated = [
      'title' => $this->validateTitle($data['title']),
      'price' => new Price($data['price']),
      'condition' => $this->validateCondition($data['condition']),
    ];
    return $this->repo->update($listingId, $validated);
  }
  ```
- Unit test: call Service directly with invalid data, verify exception thrown

**Detection:**
- Invalid data appearing in database
- Incorrect calculations/sorting
- User reports of corrupted content

**Phase to address:** Phase 2 (Listings) — establish validation patterns in Services

---

### Pitfall 12: Hard-Coded Secrets and Configuration

**What goes wrong:**
API keys, database passwords, Google Maps API keys are hardcoded in source files. Code is committed to version control. If repository is public, secrets are exposed. If employee leaves, secrets aren't rotated.

**Why it happens:**
- Quick to hardcode during development
- Developers forget to move to config before commit
- No `.env` file or secrets management setup

**Consequences:**
- API key abuse (attacker uses key to run up bills on Google Maps)
- Database compromise
- Account takeover if secrets are service account credentials

**Prevention:**
- Create `.env.example` file documenting required variables, commit it (not actual secrets):
  ```
  DB_HOST=localhost
  DB_USER=root
  DB_PASSWORD=***CHANGE_ME***
  GOOGLE_MAPS_API_KEY=***CHANGE_ME***
  ```
- Load from environment:
  ```php
  $dbPassword = getenv('DB_PASSWORD');
  if (!$dbPassword) {
    throw new ConfigException("DB_PASSWORD not set in environment");
  }
  ```
- Pre-commit hook: reject commits containing common secret patterns (e.g., `password = ` followed by non-placeholder)
- Never add `.env` to version control; use `.gitignore`
- Document in README: "Copy `.env.example` to `.env` and fill in secrets"

**Detection:**
- Secrets visible in git log: `git log -p | grep -i password`
- Code review: look for hardcoded API keys/passwords

**Phase to address:** Phase 0 (Setup) — establish before any development

---

## Phase-Specific Warnings

| Phase | Topic | Likely Pitfall | Mitigation |
|-------|-------|----------------|-----------|
| Phase 1: Auth | Session Management | Session fixation, ID not regenerated | Regenerate session ID post-login, implement CSRF tokens, set secure cookie flags |
| Phase 1: Auth | Password Storage | MD5/SHA1 usage instead of password_hash() | Mandate PASSWORD_BCRYPT with cost=12 |
| Phase 2: Listings | File Uploads | Image validation bypassed, RCE via polyglot files | Re-encode images, store outside web root, randomize filenames, no execute permissions |
| Phase 2: Listings | Geolocation | Coordinate precision loss, slow queries on distance calc | Use spatial indexes, add buffer to radius, test boundary cases |
| Phase 2: Listings | Data Validation | Validation only in Controller, not Service | Implement Value Objects, validate in Service layer |
| Phase 3: Bookings | Transactions | Booking without chat, double-booking | Wrap booking+chat in transaction, use SELECT...FOR UPDATE for locking |
| Phase 3: Bookings | Concurrency | Race conditions on inventory | Add unique constraint, pessimistic locking, test concurrent inserts |
| Phase 4: Chat | Query Performance | N+1 queries on message fetch | Eager-load sender data via JOIN, implement cursor pagination |
| Phase 4: Chat | Session/Polling | Resource exhaustion from constant polling | Cache results, implement long-polling, reduce poll frequency |
| Phase 5: Reviews | Soft Deletes | Deleted users/listings still visible | Apply soft delete filter in all SELECT queries, create base Repository class |
| Phase 6: Scaling | Denormalization | Caching inconsistency, stale data | Implement cache invalidation strategy, document cache keys, set TTL |
| Ongoing | Security | Secrets in code | Use .env files, never commit secrets, pre-commit hooks |
| Ongoing | Monitoring | Pitfalls not detected until production | Add logging for transactions, failed queries, N+1 detection |

---

## Marketplace-Specific Vulnerabilities

### Data Integrity Under Concurrency

**Pitfall:** Two users attempt transactions simultaneously (booking + review calculation), leaving database in inconsistent state.

**Prevention:**
- All multi-step operations (booking, review calculation) must be wrapped in `BEGIN TRANSACTION...COMMIT`
- Test with Apache Bench or similar to simulate concurrent requests
- Use database constraints (UNIQUE, FOREIGN KEY) to catch inconsistencies

### Reputation System Gaming

**Pitfall:** Users create fake accounts to boost their own reputation or sabotage competitors with negative reviews.

**Prevention:**
- Rate limiting: 1 review per user per transaction
- Cooldown: users can't review same seller within 30 days
- Require completed booking to review (verify seller marked transaction complete)
- Flag anomalies: alert if user receives 10 5-star reviews in 1 hour

### Geographic Attack Surface

**Pitfall:** Listings show exact coordinates. Users discover seller's home address even if they don't complete transaction.

**Prevention:**
- Round coordinates to nearest 100m for map display: `ROUND(latitude, 3)` instead of full precision
- Only show exact coordinates to buyer after booking confirmation
- Implement "meeting point" feature: seller specifies pickup location (separate from home)

---

## Scaling Pitfalls (Prepare Early)

### Denormalization Without Invalidation Strategy

**Pitfall:** Cache user rating count in `users.average_rating`. Doesn't update when new review posted. Caching strategy not documented.

**Prevention:**
- Document all cached/denormalized fields: where written, when invalidated
- Implement cache invalidation explicitly: on review creation, update user.average_rating and set cache TTL
- Add monitoring: compare denormalized value vs calculated value quarterly
- Strategy: "prefer correctness over performance" early; optimize only after measuring

### Insufficient Indexing for New Query Patterns

**Pitfall:** After launch, add filtering by `listing.condition` + `distance`. No index on (condition, latitude, longitude). Queries timeout.

**Prevention:**
- Design indexes upfront for all known query patterns in Phase 2
- `CREATE INDEX idx_listings_filter ON listings(condition, status, latitude, longitude)`
- Measure query performance: `EXPLAIN SELECT...` for all Repository queries, target <100ms
- Post-launch: monitor slow query log, add indexes reactively

---

## Sources & Verification

| Source | Confidence | Content |
|--------|-----------|---------|
| OWASP SQL Injection Guide | HIGH | Prepared statements, parameterization best practices |
| OWASP Unrestricted File Upload | HIGH | Image validation, polyglot attacks, file type detection |
| OWASP Session Fixation | HIGH | Session ID regeneration, CSRF protection |
| PHP Password Hashing Guide | HIGH | password_hash() best practices, cost parameter |
| Plain PHP Architecture Patterns | MEDIUM | Layered architecture, Repository pattern validation |
| P2P Marketplace Case Studies | MEDIUM | Transaction consistency, concurrency issues from similar projects |
| MySQL/InnoDB Documentation | HIGH | Transaction isolation levels, locking mechanisms |

---

## Next Steps

- **Phase 1 (Auth):** Implement prepared statements, session security, password hashing
- **Phase 2 (Listings):** Add soft delete patterns, image validation, geolocation indexing, Value Objects for validation
- **Phase 3 (Bookings):** Implement transaction wrapping, pessimistic locking, concurrency tests
- **Phase 4 (Chat):** Optimize query patterns, implement pagination, cache user profiles
- **Phase 5 (Reviews):** Validate soft delete filtering on reputation system
- **Ongoing:** Monitor for security regressions, scale with index/cache strategy

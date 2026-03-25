# Phase 3: Listings & Photo Upload - Research

**Date:** 2026-03-25  
**Purpose:** Understand implementation patterns, libraries, and best practices for building listings with photo upload and geolocation integration in the ReuseIT marketplace.

---

## 1. Tech Stack & Libraries

### Current Stack (from composer.json & codebase)
- **Backend:** PHP 7.4+ (can use PHP 8.0+ features with caution)
- **Database:** MySQL 8.0+ with InnoDB
- **API Style:** REST with JSON response envelope
- **Architecture:** Layered (Controllers → Services → Repositories)
- **Existing Libraries:**
  - `vlucas/phpdotenv` — Environment configuration (.env)
  - No image processing library yet (need to add)

### Required New Libraries for Phase 3

#### Image Processing & Validation
**Library: `intervention/image`** (or `imagick` PHP extension)
- **Why:** Re-encode images to strip EXIF/metadata, validate dimensions
- **Install:** `composer require intervention/image`
- **Usage pattern:**
  ```php
  $image = Image::make($uploadedFilePath);
  $image->resize(null, 2000, function ($constraint) { // Max height 2000px
      $constraint->aspectRatio();
  });
  $image->save($destinationPath); // Saves as JPEG (strips metadata)
  ```
- **Alternative:** PHP's built-in `GD` or `imagick` extension (no extra composer dependency)
  - GD is usually available; use `imagecreatefromjpeg()`, `imagesavejpeg()`
  - **Recommendation:** Use `intervention/image` for cleaner code

#### File Upload Validation
**Library: `respect/validation`** (already useful for listing fields)
- **Why:** Declarative file validation (MIME type, size, dimensions)
- **Install:** `composer require respect/validation`
- **Usage pattern:**
  ```php
  $validator = v::file()->size('1B', '5MB')->mimeType('image/jpeg', 'image/png');
  $validator->assert($file);
  ```

#### Optional: Image Optimization
**Library: `imagemin-php`** or **cwebp CLI tool**
- **For MVP:** Not required; re-encode with `intervention/image` is sufficient
- **For v1.1:** Consider for bandwidth optimization (convert JPEG/PNG to WebP)

---

## 2. Implementation Patterns (from Existing Codebase)

### Repository Pattern
**File:** `src/Repositories/BaseRepository.php`

All phase 3 repositories inherit from `BaseRepository`:
```php
abstract class BaseRepository {
    protected PDO $pdo;
    protected string $table;
    public function find(int $id): ?array
    public function findAll(array $filters = []): array
    public function create(array $data): int
    public function update(int $id, array $data): bool
    // ... soft delete methods
}
```

**For Phase 3, create:**
- `ListingRepository` — extends BaseRepository
- `ListingPhotoRepository` — extends BaseRepository

**Key Pattern:**
- Use prepared statements (all queries parameterized)
- Soft delete filtering via `applyDeleteFilter()` trait
- Return associative arrays (no ORM)

### Service Layer Pattern
**File:** `src/Services/AuthService.php`, `GeolocationService.php`

Services contain business logic and coordinate repositories:
```php
class ListingService {
    private PDO $pdo;
    private ListingRepository $listingRepo;
    private ListingPhotoRepository $photoRepo;
    private GeolocationService $geolocation;
    
    public function createListing(array $data, int $userId): int {
        // 1. Validate fields
        // 2. Geocode address
        // 3. Create listing via repository
        // 4. Return listing ID
    }
    
    public function uploadPhotos(int $listingId, array $files): array {
        // 1. Validate photo count
        // 2. Process each image
        // 3. Save to filesystem
        // 4. Record in database
    }
}
```

**For Phase 3, create:**
- `ListingService` — listing CRUD, geocoding, validation
- `PhotoUploadService` — photo handling, EXIF stripping, file management

### Controller Pattern
**File:** `src/Controllers/AuthController.php`

Controllers handle HTTP parsing and delegate to services:
```php
class ListingController {
    private ListingService $listingService;
    
    public function __construct(ListingService $listingService) { }
    
    public function create($get, $post, $files) {
        // 1. Parse POST body
        // 2. Call service.createListing()
        // 3. Return Response with success/error
    }
}
```

**Response Envelope Pattern (established in Phase 1):**
```php
// Success
Response::success(['listing_id' => 123], 201);
// Output: {"status": "success", "data": {...}, "code": 201}

// Validation Error
Response::validationErrors(['title' => 'Required'], 422);
// Output: {"status": "validationErrors", "errors": {...}, "code": 422}

// Error
Response::error('Address geocoding failed', 400);
// Output: {"status": "error", "message": "...", "code": 400}
```

### Middleware Pattern
**File:** `src/Middleware/AuthMiddleware.php`

Protect endpoints:
```php
// In Router:
protected $protectedEndpoints = [
    'POST /api/listings' => ['AuthMiddleware'],
    'POST /api/listings/:id/photos' => ['AuthMiddleware'],
];
```

**For Phase 3:**
- Create listing → Protected (must be logged in)
- Upload photos → Protected (must own listing)
- View listing details → Public

---

## 3. Geolocation & Geocoding Integration

### Google Maps Geocoding API Setup

**Existing:** `GeolocationService.php` already implements geocoding with caching

```php
$geolocationService->geocodeAddress([
    'street' => '123 Main St',
    'city' => 'Toronto',
    'province' => 'ON',
    'postal_code' => 'M5A 1A1',
    'country' => 'Canada'
]) // Returns: ['lat' => 43.6532, 'lng' => -79.3832]
```

**Key Features Already Implemented:**
- Normalizes address to cache key (MD5 hash)
- Caches results in `geocoding_cache` table
- Returns `null` on API failure (graceful degradation)
- Uses `file_get_contents()` for API calls (no cURL required)

### Handling Ambiguous Addresses

**From CONTEXT.md:** "Suggest alternatives if address invalid/ambiguous"

**Implementation approach:**
- Extend `GeolocationService` with `geocodeAddressWithCandidates()` method
- Return multiple candidates from Google API's `results[]` array
- Frontend lets user pick the correct one or retry with corrected address

**Pattern:**
```php
public function geocodeAddressWithCandidates(array $address): array {
    $normalizedAddress = $this->normalizeAddress($address);
    // Call Google API
    $response = json_decode(file_get_contents($url), true);
    
    // Extract all candidates from $response['results']
    return array_map(fn($result) => [
        'address' => $result['formatted_address'],
        'lat' => $result['geometry']['location']['lat'],
        'lng' => $result['geometry']['location']['lng']
    ], $response['results']);
}
```

### Coordinate Storage & Precision

**From schema:** `DECIMAL(10, 8)` for latitude, `DECIMAL(11, 8)` for longitude

**Precision:**
- 8 decimal places = ~1.1mm accuracy (more than sufficient for local marketplace)
- Storage: ~6-7 bytes per coordinate

**For distance queries (Phase 4):**
- Spatial indexes: `INDEX idx_coordinates (latitude, longitude)`
- Haversine formula for distance calculations (or MySQL's native ST_Distance in 8.1+)

---

## 4. File Upload & Photo Storage

### Photo Upload Flow (from CONTEXT.md)

1. **User creates listing first** (get `listing_id`)
2. **Then uploads photos** in separate request (max 10 per listing)
3. **Multipart form** with multiple files in single POST request

### Directory Structure & Filename Strategy

**Storage path:** `public/uploads/users/{user_id}/listings/{listing_id}/`

**Filename format:** `{timestamp}_{random}.{ext}`

**Example:**
```
public/uploads/users/42/listings/789/
├── 1726918234_a1b2c3.jpg    (timestamp: 1726918234, random: a1b2c3)
├── 1726918235_d4e5f6.jpg
└── 1726918236_g7h8i9.jpg
```

**Advantages:**
- **Debuggability:** Timestamp helps identify image age
- **Security:** Random suffix prevents enumeration attacks (attackers can't guess filenames)
- **Organization:** Grouped by user → listing for clarity
- **Deletion:** Easy to bulk-delete by directory when listing is removed

### Image Re-encoding (EXIF Stripping)

**Requirement:** Strip EXIF/metadata before storage (security)

**Why:** EXIF data can contain:
- GPS coordinates (privacy leak)
- Device identifiers (fingerprinting)
- Potentially executable code (XXE attacks on certain formats)

**Implementation:**
```php
$image = Image::make($uploadedFile['tmp_name']);
$image->resize(null, 2000, function ($constraint) {
    $constraint->aspectRatio();
    $constraint->upsize(); // Don't enlarge
});
$image->orientate(); // Fix rotation from EXIF
$image->save($destinationPath); // Strips all EXIF/metadata automatically
```

**Alternative (using GD):**
```php
$image = imagecreatefromjpeg($uploadedFile['tmp_name']);
imagejpeg($image, $destinationPath, 90); // Re-encode (no EXIF)
```

### File Upload Validation Checklist

- ✓ **MIME type:** `image/jpeg`, `image/png`, `image/webp`
- ✓ **File size:** Max 5MB per file (adjust as needed)
- ✓ **Dimensions:** Min 640×480px, max 8000×8000px
- ✓ **Total photos:** Max 10 per listing (409 Conflict if exceeded)
- ✓ **File extension:** Whitelist `.jpg`, `.jpeg`, `.png`, `.webp`
- ✓ **Magic bytes:** Validate actual file header (not just extension)

**Magic byte validation:**
```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);
if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
    throw new InvalidArgumentException('Invalid file type');
}
```

### Photo URL & Display Order

**Schema fields:**
```sql
CREATE TABLE listing_photos (
    id BIGINT PRIMARY KEY,
    listing_id BIGINT NOT NULL,
    photo_url VARCHAR(500) NOT NULL,      -- Relative path or URL
    display_order INT NOT NULL,           -- 1, 2, 3, ... (reorderable)
    created_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

**URL generation (in repository):**
```php
// Store relative path in DB
$relativePath = "uploads/users/{$userId}/listings/{$listingId}/{$filename}";

// When retrieving, prefix base URL
$photoUrl = "https://reuseit.example.com/" . $relativePath;
```

**Primary/Cover photo:** First photo in `display_order` (frontend can reorder before submission)

### Storage Location (Filesystem Security)

**Current approach:** Store in `public/uploads/`

**Security considerations:**
- ✓ Inside web root (serves static files directly via HTTP)
- ✓ Outside source code (`public/` isolated from `src/`)
- ⚠️ PHP execution risk: disable PHP execution in upload directory

**.htaccess (Apache):**
```apache
<FilesMatch "\.php$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
```

**nginx (if used later):**
```nginx
location ~ /uploads/.*\.php$ {
    deny all;
}
```

**For v1.1 (when scaling):**
- Consider S3/CloudFront for static file hosting
- Move images outside web root; serve via CDN
- Implement cache headers (1 year for immutable filenames)

---

## 5. Listing Creation Form (Multi-Step Wizard)

### Form Structure (from CONTEXT.md)

**Required fields:** title, category, price, address, condition
**Optional fields:** brand, model, year, accessories

### Step 1: Basic Information

```json
{
  "title": "iPhone 12 Pro",
  "description": "Perfect condition...",
  "category_id": 1,
  "price": 650.00,
  "condition": "Excellent",
  "brand": "Apple",
  "model": "iPhone 12 Pro",
  "year": 2021,
  "accessories": ["charger", "box"]
}
```

**Validation:**
- Title: 10-255 chars, required
- Description: 20-5000 chars, required
- Category: Existing category ID, required
- Price: 0.01-999999.99, required
- Condition: enum ['Excellent', 'Good', 'Fair', 'Poor'], required
- Brand, Model, Year: Optional (string, 0-100 chars)
- Accessories: Optional (JSON array, max 20 items)

### Step 2: Location

```json
{
  "address": {
    "street": "123 Main St",
    "city": "Toronto",
    "province": "ON",
    "postal_code": "M5A 1A1",
    "country": "Canada"
  },
  "manual_coordinates": null  // Optional if user picked from map later
}
```

**Geocoding flow:**
1. User submits address
2. Backend calls `geocodeAddressWithCandidates()`
3. If 1 result: auto-select, return success
4. If multiple results: return candidates for user to pick
5. If 0 results: return 400 with "Address not found, please try again"

**Response format:**
```json
{
  "status": "success",
  "data": {
    "listing_id": 123,
    "latitude": 43.6532,
    "longitude": -79.3832,
    "candidates": null  // or array if ambiguous
  }
}
```

### Step 3: Photos

```json
{
  "photos": [
    /* multipart files */
  ]
}
```

**Handling:**
- Max 10 files
- Each file validated (MIME, size, dimensions)
- Process sequentially to avoid server overload
- Return 409 Conflict if >10 files submitted

### Step 4: Review (Optional)

- Display all entered data
- Allow user to edit fields (flow back to step 1/2)
- Final submit triggers listing creation + photo upload

### API Endpoint Design

**Option A (My recommendation): Separate endpoints**
```
POST /api/listings → Create listing, returns listing_id
POST /api/listings/:id/photos → Upload photos to existing listing
```

**Option B (All-in-one):**
```
POST /api/listings → Create listing with photos in one request
```

**Reasoning for Option A:**
- Aligns with CONTEXT.md ("Photos uploaded after listing created")
- Simplifies photo retry logic (user can re-upload if first attempt fails)
- Easier to implement transaction handling
- Better for large uploads (chunked uploads in future)

---

## 6. Database Schema Review

### Listings Table
```sql
CREATE TABLE listings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    seller_id BIGINT NOT NULL,                      -- FK to users
    category_id INT NOT NULL,                       -- FK to categories
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    brand VARCHAR(100),
    model VARCHAR(100),
    year INT,
    condition VARCHAR(50),                          -- 'Excellent', 'Good', etc.
    accessories JSON,                               -- Array of strings
    latitude DECIMAL(10, 8) NOT NULL,               -- HIGH PRECISION (1.1mm)
    longitude DECIMAL(11, 8) NOT NULL,
    location_address VARCHAR(500),                  -- Display string
    status VARCHAR(20) DEFAULT 'active',            -- 'active', 'booked', 'completed', 'cancelled'
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,                      -- Soft delete
    
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_seller_id (seller_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_category_id (category_id),
    INDEX idx_coordinates (latitude, longitude),    -- For spatial queries
    INDEX idx_seller_status (seller_id, status),    -- Seller's active listings
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB;
```

**Note:** Schema already exists and is correct ✓

### Listing Photos Table
```sql
CREATE TABLE listing_photos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    listing_id BIGINT NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    display_order INT NOT NULL,                    -- 1, 2, 3, ...
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,                     -- Soft delete (inherits from listing)
    
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    INDEX idx_listing_id (listing_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB;
```

**Note:** Schema already exists and is correct ✓

### Geocoding Cache Table
```sql
CREATE TABLE geocoding_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address_hash VARCHAR(32) UNIQUE NOT NULL,      -- MD5 hash
    address_string VARCHAR(500) NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_address_hash (address_hash)
) ENGINE=InnoDB;
```

**Note:** Schema already exists and is correct ✓

---

## 7. Security Considerations

### Image Upload Security

| Risk | Mitigation |
|------|-----------|
| **RCE via PHP upload** | Re-encode images (strips PHP); disable PHP execution in upload dir via `.htaccess` |
| **XXE / Billion Laughs attacks** | Use `intervention/image` or GD (safe libraries); avoid raw XML parsing |
| **Malicious EXIF data** | Strip metadata during re-encoding (`intervention/image` does this automatically) |
| **User privacy (GPS in EXIF)** | Re-encode strips all EXIF data including GPS |
| **Directory traversal** | Use random filenames; don't accept user-supplied filenames |
| **File type spoofing** | Validate magic bytes (`finfo_file`), not just extension |
| **Zip bomb / decompression attacks** | Limit file size (5MB per file); limit upload per request (10 files max) |

### Address/Geolocation Security

| Risk | Mitigation |
|------|-----------|
| **Google API rate limiting** | Cache results; implement rate limiting per user/IP if needed |
| **API key exposure** | Store `GOOGLE_MAPS_API_KEY` in `.env`, never in source code; restrict API key to IP range |
| **User privacy (location tracking)** | Store address string for display; don't expose coordinates in public API unless needed |
| **Fake listings spam** | Rate limit listing creation (e.g., max 5 per day per user); implement trust scores (Phase 8) |

### Input Validation

**All fields must be validated before storage:**

```php
// Title validation
v::stringType()->length(10, 255)->validate($title);

// Price validation
v::numericVal()->min(0.01)->max(999999.99)->validate($price);

// Category validation
v::intVal()->validate($categoryId);
$category = $categoryRepo->find($categoryId);
if (!$category) throw new Exception('Invalid category');

// Address validation
v::stringType()->length(5, 255)->validate($address['street']);
v::stringType()->length(2, 100)->validate($address['city']);
// ... validate each address component
```

### SQL Injection Prevention

**Rule: All user input must use prepared statements**

✓ **Correct:**
```php
$stmt = $pdo->prepare("INSERT INTO listings (seller_id, title, price) VALUES (?, ?, ?)");
$stmt->execute([$userId, $title, $price]);
```

❌ **Incorrect:**
```php
$sql = "INSERT INTO listings VALUES ($userId, '$title', $price)"; // VULNERABLE
```

### Authorization Checks

**For listing operations:**
- Create: User must be authenticated
- Edit: User must own listing (`listing.seller_id == current_user.id`)
- Delete: User must own listing
- Upload photos: User must own listing

**Implementation pattern:**
```php
public function uploadPhotos(int $listingId, int $userId, array $files): array {
    $listing = $this->listingRepo->find($listingId);
    if (!$listing) throw new Exception('Listing not found', 404);
    if ($listing['seller_id'] != $userId) throw new Exception('Forbidden', 403);
    
    // Proceed with upload
}
```

---

## 8. Error Handling & Edge Cases

### Photo Upload Errors

| Scenario | Status Code | Response |
|----------|-------------|----------|
| Max 10 photos exceeded | 409 | `{"status": "error", "message": "Maximum 10 photos per listing"}` |
| Invalid MIME type | 422 | `{"status": "validationErrors", "errors": {"photos": "Invalid file type"}}` |
| File too large (>5MB) | 422 | `{"status": "validationErrors", "errors": {"photos": "File too large (max 5MB)"}}` |
| Dimensions too small | 422 | `{"status": "validationErrors", "errors": {"photos": "Image too small (min 640×480)"}}` |
| Listing not found | 404 | `{"status": "error", "message": "Listing not found"}` |
| Unauthorized (not owner) | 403 | `{"status": "error", "message": "Forbidden"}` |

### Listing Creation Errors

| Scenario | Status Code | Response |
|----------|-------------|----------|
| Missing required field | 422 | `{"status": "validationErrors", "errors": {"title": "Required"}}` |
| Address not found | 400 | `{"status": "error", "message": "Address not found. Please try again or use exact address."}` |
| Address ambiguous (multiple matches) | 200 | `{"status": "success", "data": {"candidates": [...]}}` — user picks one |
| Duplicate listing (rate limit exceeded) | 429 | `{"status": "error", "message": "Too many listings created. Please try again later."}` |
| Category not found | 422 | `{"status": "validationErrors", "errors": {"category_id": "Invalid category"}}` |

### File System Errors

| Scenario | Handling |
|----------|----------|
| Directory doesn't exist | Create via `mkdir($uploadDir, 0755, true)` during first upload |
| Write permission denied | Log error; return 500 with generic message |
| Disk full | Return 507 Insufficient Storage (rare, but handle gracefully) |
| Image re-encoding fails | Log error; return 500 |

---

## 9. Performance Considerations

### Photo Upload Optimization

**Current approach for MVP:**
- Process images sequentially (one at a time)
- Re-encode inline during request
- Store to local filesystem

**Potential bottlenecks:**
- Large image re-encoding (can take 2-5 seconds per image for 10MP+ photos)
- **Solution:** Offload to background queue (Phase 5+)

**For MVP, acceptable limitations:**
- Max 10 photos per listing (total ~30-50MB before re-encoding)
- Timeout: Set PHP `max_execution_time = 300` (5 minutes) for upload request
- Request size limit: Set `post_max_size = 100M` in php.ini

### Database Queries

**Listing retrieval with photos (N+1 problem):**

❌ **Naive approach:**
```php
$listing = $listingRepo->find($listingId);  // 1 query
$photos = $photoRepo->findAll(['listing_id' => $listingId]);  // N queries (one per photo)
```

✓ **Optimized:**
```php
$sql = "
    SELECT l.*, 
           GROUP_CONCAT(p.photo_url ORDER BY p.display_order) AS photo_urls,
           COUNT(p.id) AS photo_count
    FROM listings l
    LEFT JOIN listing_photos p ON l.id = p.listing_id
    WHERE l.id = ? AND l.deleted_at IS NULL
    GROUP BY l.id
";
```

### Geolocation API Caching

**Cache hit rate expectations:**
- Popular areas (downtown Toronto): ~80% cache hit rate
- Sparse areas: ~20-30% cache hit rate
- Average: ~60% cache hit rate

**API quota management:**
- Google Maps Geocoding API: $0.005 per request
- Budget: ~100 requests/day = ~$1.50/day (sufficient for MVP)
- If exceeding, implement per-user rate limiting

---

## 10. Common Pitfalls & How to Avoid Them

### Pitfall 1: Lost Timezone Information
**Problem:** Coordinates stored in DECIMAL but no timezone info for address
**Solution:** Store normalized address string separately (`location_address` field)
**Benefit:** User sees "123 Main St, Toronto, ON M5A 1A1" not just lat/lng

### Pitfall 2: No Soft Delete for Photos
**Problem:** When listing soft-deleted, photos orphaned
**Solution:** Ensure `listing_photos.deleted_at` is filtered in queries
**Implementation:** Use `Softdeletable` trait in `ListingPhotoRepository`

### Pitfall 3: Race Condition During Photo Upload
**Problem:** User uploads photo but listing gets deleted concurrently
**Solution:** Check listing exists + user owns it before saving photo
**Pattern:** Transaction or lock (e.g., `SELECT...FOR UPDATE`)

### Pitfall 4: EXIF Data Leaks
**Problem:** GPS coordinates in EXIF reveal user's exact home location
**Solution:** Always re-encode images (strip metadata)
**Verify:** Use `exiftool` to inspect saved images for any metadata

### Pitfall 5: Unbounded File Growth
**Problem:** Each user uploads 10 photos × 5MB = 50MB per listing; multiply by 1000 listings = 50GB
**Solution:** Implement storage quota per user or migrate to S3 early
**For MVP:** Monitor disk usage; set up alerts at 80% capacity

### Pitfall 6: Geocoding API Failures Crash Requests
**Problem:** User enters invalid address → Google API returns error → request fails
**Solution:** Return list of candidates OR specific error message
**Never:** Crash with generic 500 error

### Pitfall 7: Display Order Gaps
**Problem:** Delete photo 2 of 5 → display_order becomes [1, 3, 4, 5] (gap)
**Solution:** After deletion, renumber: `UPDATE listing_photos SET display_order = new_order`
**Alternative:** Let gaps exist; sort by `display_order` regardless

### Pitfall 8: No Address Normalization
**Problem:** User enters "Toronto" vs. "Toronto, ON, Canada" → different API results
**Solution:** Normalize address format before geocoding (already done in `GeolocationService`)
**Check:** `normalizeAddress()` method ensures consistent format

### Pitfall 9: Filename Collisions
**Problem:** Two users upload same filename → one overwrites the other
**Solution:** Use timestamp + random suffix (`1726918234_a1b2c3.jpg`)
**Never:** Use user-supplied filename

### Pitfall 10: No Validation of Photo Count
**Problem:** User submits 15 files → server processes all instead of rejecting
**Solution:** Validate count BEFORE processing: check `count($_FILES['photos']) <= 10`
**Return:** 409 Conflict if exceeded

---

## 11. Testing Strategy (Not Implementation)

### Unit Tests to Write Later
- `ListingService::createListing()` — valid/invalid inputs
- `ListingService::uploadPhotos()` — max photos, file validation
- `GeolocationService::geocodeAddress()` — caching, API failures
- `PhotoUploadService::reencodeImage()` — EXIF stripping, dimensions

### Integration Tests to Write Later
- Full flow: Create listing → Upload photos → Verify in DB
- Geocoding cache: First call hits API, second call hits cache
- Authorization: Verify only owner can upload photos

### Manual Testing Checklist
- ✓ Upload 10 photos (should succeed)
- ✓ Upload 11 photos (should return 409)
- ✓ Upload non-image file (should return 422)
- ✓ Upload image >5MB (should return 422)
- ✓ Upload low-res image <640×480 (should return 422)
- ✓ Delete photo 2 of 5 (verify order renumbered)
- ✓ Geocode valid address (should succeed)
- ✓ Geocode invalid address (should return candidates or error)
- ✓ Non-owner tries to upload photos (should return 403)
- ✓ EXIF check: Use exiftool to verify metadata stripped

---

## 12. Dependency Injection & Router Integration

### Add to Router.php

```php
// In Router constructor, add:
$this->listingService = new ListingService($pdo, new GeolocationService($pdo));
$this->listingController = new ListingController($this->listingService);

// Add routes:
$this->protectedEndpoints = [
    'POST /api/listings' => ['AuthMiddleware'],
    'POST /api/listings/:id/photos' => ['AuthMiddleware'],
    'PATCH /api/listings/:id' => ['AuthMiddleware'],
    'DELETE /api/listings/:id' => ['AuthMiddleware'],
];

// In route handler:
case 'POST /api/listings':
    return $this->listingController->create($get, $post, $files);
case 'POST /api/listings/:id/photos':
    return $this->listingController->uploadPhotos($get, $post, $files);
```

---

## 13. Environment Configuration

### .env Variables

```env
# Existing (from Phase 2)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=reuseit
DB_USER=root
DB_PASSWORD=

GOOGLE_MAPS_API_KEY=AIzaSy... (your API key)

# New for Phase 3
UPLOAD_DIR=public/uploads
MAX_UPLOAD_SIZE_MB=5
MAX_PHOTOS_PER_LISTING=10
IMAGE_MAX_DIMENSION=8000
IMAGE_MIN_DIMENSION=640
JPEG_QUALITY=90
```

### PHP Configuration (php.ini)

```ini
; Increase for file uploads
post_max_size = 100M
upload_max_filesize = 50M
max_execution_time = 300

; Increase for image processing
memory_limit = 512M
```

---

## 14. Implementation Order (Recommended)

1. **Setup** — Add `intervention/image` to composer.json
2. **Repositories** — Create `ListingRepository`, `ListingPhotoRepository`
3. **Services** — Create `ListingService`, `PhotoUploadService`
4. **Controllers** — Create `ListingController` with endpoints
5. **Validation** — Implement field + file validation
6. **Integration** — Wire up in Router, test locally
7. **Testing** — Manual test all happy paths + error cases

---

## Summary: What You Need to Know to Plan Phase 3 Well

### Technology Decisions
- ✓ Use `intervention/image` for safe image re-encoding
- ✓ Separate endpoints: Create listing → Upload photos (not all-in-one)
- ✓ Store files locally in `public/uploads/` (migrate to S3 in v1.1)
- ✓ Leverage existing `GeolocationService` for address → coordinates

### Architecture Decisions
- ✓ Extend `BaseRepository` for `ListingRepository` and `ListingPhotoRepository`
- ✓ Create `ListingService` for business logic (validation, geocoding, DB)
- ✓ Create `PhotoUploadService` for file handling (EXIF stripping, storage)
- ✓ Use existing response envelope (success/validationErrors/error)
- ✓ Protect listing creation/editing with `AuthMiddleware` + authorization check

### Security Decisions
- ✓ Strip EXIF/metadata from all uploaded images
- ✓ Validate MIME type via magic bytes (not extension)
- ✓ Use random filenames to prevent enumeration
- ✓ Disable PHP execution in upload directory
- ✓ Validate all inputs (title, price, category, address)

### Database/Performance Decisions
- ✓ Schema already correct (listings, listing_photos, geocoding_cache tables exist)
- ✓ Use geocoding cache to minimize API calls (~60% hit rate expected)
- ✓ Join photos in single query to prevent N+1 problem
- ✓ Store coordinates as DECIMAL(10,8) for 1.1mm precision

### Error Handling Decisions
- ✓ Return 409 Conflict if max 10 photos exceeded
- ✓ Return 422 Validation Error for invalid photos/fields
- ✓ Return candidates list if address ambiguous (user picks correct one)
- ✓ Return 403 Forbidden if user doesn't own listing

### MVP Scope (What's In)
- ✓ Create listing with required fields (title, category, price, address, condition)
- ✓ Multi-step form (not single page)
- ✓ Geocoding with address normalization + caching
- ✓ Upload max 10 photos per listing
- ✓ EXIF stripping + file validation
- ✓ Public view listings, protected create/edit/delete

### Out of Scope (Deferred to Phase 4+)
- ✗ Distance-based search (Phase 4)
- ✗ Map picker for location (Phase 4)
- ✗ Listing editing workflow (Phase 3 can do basic edit, advanced features later)
- ✗ Background job for image processing (Phase 5+)
- ✗ CDN/S3 storage (v1.1 enhancement)

---

*Research completed: 2026-03-25*  
*Ready for Phase 3 Planning Document*

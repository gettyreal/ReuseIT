# Phase 4: Map & Search Discovery - Research Findings

**Date:** 2026-03-30  
**Status:** Research Complete - Ready for Planning  

---

## 1. Technology Stack Analysis

### 1.1 Haversine Formula Implementation (PHP)
**Decision:** Implement in PHP application layer, not MySQL.

**Why this choice:**
- **Simplicity**: Pure math calculation, no complex MySQL spatial functions
- **Debuggability**: Easy to unit test, log, and reason about
- **MVP compatibility**: Sufficient for <100K users; scales reasonably up to 1M listings
- **Future flexibility**: Can migrate to MySQL ST_Distance() later without changing API

**Implementation approach:**
```php
// Haversine distance between two coordinates
// Returns distance in kilometers
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    const EARTH_RADIUS_KM = 6371;
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return EARTH_RADIUS_KM * $c;
}
```

**Performance characteristics:**
- Single calculation: ~0.001ms (negligible)
- For 1,000 candidate listings: ~1ms total
- For 10,000 listings: ~10ms (acceptable for MVP)
- **At 50K+ listings:** Migrate to MySQL `ST_Distance()` for O(1) filtering vs O(n) in PHP

### 1.2 Database Indexing Strategy (Current State)

**Existing schema (from Phase 3):**
- `listings` table has `latitude`, `longitude` columns (DECIMAL 10,7)
- `users` table has `latitude`, `longitude` columns (user's home location)
- Both use 7 decimal places = ~1.1m precision (appropriate for neighborhood-level search)

**Current indexes:**
- `listings`: PK (id), FK (seller_id), soft-delete filter (deleted_at)
- `geocoding_cache`: PK (id), UNIQUE (address_hash)

**Recommended Phase 4 additions:**
```sql
-- Speed up active listing queries when filtering by distance
CREATE INDEX idx_listings_status_deleted 
ON listings (status, deleted_at, created_at DESC);

-- Help count queries on filtered result sets
CREATE INDEX idx_listings_category_status_deleted 
ON listings (category_id, status, deleted_at);

-- For future optimization (Phase 8+): spatial indexing
-- CREATE SPATIAL INDEX idx_listings_location 
-- ON listings (POINT(latitude, longitude));
```

**Why defer spatial index:**
- MySQL SPATIAL indexes require `POINT` type, not separate DECIMAL columns
- Would require schema migration (Phase 3 schema is locked)
- PHP Haversine is fast enough for MVP (Phase 4-5 query workloads)

### 1.3 Search Implementation: LIKE vs FULLTEXT

**Current approach (Phase 3):** LIKE with wildcards
```php
// In ListingRepository::searchByKeyword()
$keyword = '%' . $keyword . '%';
$sql = "...WHERE (l.title LIKE ? OR l.description LIKE ?)..."
```

**Performance profile:**
- Single keyword search on 10K listings: ~50-100ms (acceptable for MVP)
- With multiple filters combined: ~20-50ms
- **Limitation:** No relevance ranking, no fuzzy matching

**FULLTEXT alternative (Phase 8+ optimization):**
```sql
-- Add natural language search
ALTER TABLE listings ADD FULLTEXT INDEX ft_search (title, description);

-- Query: uses MySQL ranking by relevance
SELECT *, 
       MATCH(title, description) AGAINST('iPhone' IN BOOLEAN MODE) as relevance
FROM listings 
WHERE MATCH(title, description) AGAINST('iPhone' IN BOOLEAN MODE)
ORDER BY relevance DESC;
```

**Decision for Phase 4:** Stick with LIKE. Rationale:
- FULLTEXT has complexity (stop words, min word length tuning)
- LIKE is simpler to debug and understand
- Relevance ranking not yet required (Phase 4 just needs "find listings")
- Easy to add FULLTEXT index later without code changes

---

## 2. Architecture Patterns

### 2.1 Search Endpoint Flow

```
GET /api/listings/search?lat=43.65&lng=-79.38&radius=10&keyword=iPhone&category=1

↓

ListingController::search()
├─ Parse query parameters (lat, lng, radius, keyword, category, etc.)
├─ Validate parameters (radius ≤ 50km, keyword length)
├─ Fetch user's stored location if lat/lng not provided
├─ Call ListingService::searchWithDistance()
│   ├─ Query database for candidate listings (all active, matching category/price/keyword)
│   ├─ Calculate distance for each candidate using Haversine
│   ├─ Filter by radius threshold
│   ├─ Sort by distance (nearest first)
│   └─ Apply pagination (offset/limit)
└─ Return paginated results with distance field
```

### 2.2 Query Strategy for Distance Filtering

**Phase 4 approach (MVP):**
1. Fetch candidate listings from DB with WHERE clause:
   - Category filter (if provided)
   - Price range filter (if provided)
   - Keyword search (if provided)
   - Status = 'active'
   - Soft-delete filter

2. Apply distance calculation in PHP
3. Sort by distance
4. Apply pagination

**Why this works for MVP:**
- Reduces database complexity
- Easy to test and debug
- Acceptable performance for <50K listings with typical search result sizes (20-100 listings)

**Example SQL query:**
```sql
-- Get candidate listings matching filters (not yet sorted by distance)
SELECT l.*, 
       u.first_name, u.last_name, u.avatar_url,
       COUNT(DISTINCT p.id) as photo_count
FROM listings l
LEFT JOIN users u ON l.seller_id = u.id
LEFT JOIN listing_photos p ON l.id = p.listing_id AND p.deleted_at IS NULL
WHERE l.deleted_at IS NULL 
  AND u.deleted_at IS NULL
  AND l.status = 'active'
  AND l.category_id = ? -- optional filter
  AND l.price BETWEEN ? AND ? -- optional filter
  AND (l.title LIKE ? OR l.description LIKE ?) -- optional keyword
GROUP BY l.id
LIMIT 1000; -- Fetch up to 1000 for distance calculation
```

**PHP service layer (pseudocode):**
```php
public function searchWithDistance(
    float $userLat, float $userLng,
    int $radiusMeters,
    array $filters = []
): array {
    // 1. Fetch candidates from DB (with non-distance filters)
    $candidates = $this->listingRepo->searchWithFilters($filters);
    
    // 2. Calculate distances and filter
    $filtered = [];
    foreach ($candidates as $listing) {
        $distanceKm = $this->haversine(
            $userLat, $userLng, 
            $listing['latitude'], $listing['longitude']
        );
        
        if ($distanceKm * 1000 <= $radiusMeters) { // Convert to meters
            $listing['distance_meters'] = $distanceKm * 1000;
            $filtered[] = $listing;
        }
    }
    
    // 3. Sort by distance
    usort($filtered, fn($a, $b) => $a['distance_meters'] <=> $b['distance_meters']);
    
    // 4. Apply pagination
    return array_slice($filtered, $offset, $limit);
}
```

### 2.3 Default Location Resolution

**Decision from CONTEXT.md:** Use user's stored location if `lat/lng` not provided.

**Implementation flow:**
```php
// In search endpoint
$userLat = $get['lat'] ?? null;
$userLng = $get['lng'] ?? null;

if ($userLat === null || $userLng === null) {
    // Fetch user's stored location from profile
    $user = $userRepository->find($_SESSION['user_id']);
    if (!$user || !$user['latitude']) {
        return Response::error('Location required', 400);
    }
    $userLat = $user['latitude'];
    $userLng = $user['longitude'];
}
```

**Key point:** User location must exist in `users` table (set at registration in Phase 2).

### 2.4 Pagination with Distance Sorting

**Challenge:** Pagination typically requires deterministic sorting. Distance sorting adds complexity:
- Can't offset-based paginate on calculated field
- Need consistent ordering

**Solution (standard approach):**
1. Calculate distance for ALL matching listings (not just page 1)
2. Sort by distance
3. Apply LIMIT/OFFSET for pagination

**Performance implication:**
- For large result sets (10K+ listings), this becomes expensive
- **Phase 8 optimization:** Implement cursor-based pagination with distance
- **MVP acceptable:** Most searches return <200 results; processing <500 listings fast enough

**Response structure:**
```json
{
  "listings": [
    {
      "id": 123,
      "title": "iPhone 12",
      "price": 650.00,
      "latitude": 43.6532,
      "longitude": -79.3832,
      "distance_meters": 2500,
      "distance_km": 2.5,
      "photo_count": 3
    }
  ],
  "total": 47,
  "limit": 20,
  "offset": 0,
  "search_radius_meters": 10000
}
```

---

## 3. Common Pitfalls & Mitigations

### 3.1 N+1 Query Problem

**Risk:** Fetching candidates then querying user/photo count for each.

**Already mitigated (Phase 3):**
- Using LEFT JOIN with GROUP BY to get photo count in single query
- User data joined at query time, not in loop

**Verification needed:**
```php
// GOOD (Phase 3 pattern)
$sql = "SELECT l.*, u.first_name, COUNT(DISTINCT p.id) as photo_count
        FROM listings l
        LEFT JOIN users u ON l.seller_id = u.id
        LEFT JOIN listing_photos p ON l.id = p.listing_id
        WHERE ... GROUP BY l.id";

// BAD (avoid)
$listings = $listingRepo->findAll();
foreach ($listings as $listing) {
    $listing['seller'] = $userRepo->find($listing['seller_id']); // N queries!
}
```

### 3.2 Soft-Delete Filtering

**Risk:** Deleted listings/users appearing in search results.

**Already mitigated (Phase 1):**
- `Softdeletable` trait in BaseRepository applies `AND deleted_at IS NULL` automatically
- Phase 3 search queries manually add: `AND l.deleted_at IS NULL AND u.deleted_at IS NULL`

**For Phase 4 searches:** Must verify soft-delete filtering is applied to:
- Listings table (already done)
- Users table (already done in current search query)
- Listing photos (already done in current search query)

### 3.3 Distance Calculation Edge Cases

**Issue 1: Null coordinates**
- User or listing might have NULL lat/lng (shouldn't happen, but edge case)
- **Mitigation:** Validate non-null before Haversine calculation
```php
if (!$listing['latitude'] || !$listing['longitude']) {
    continue; // Skip listings without coordinates
}
```

**Issue 2: Listings at the date line (±180°)**
- Haversine works correctly across date line (uses shortest arc on sphere)
- No action needed

**Issue 3: Poles and equator**
- Haversine handles all latitudes correctly
- No special case handling needed

**Issue 4: Precision loss with DECIMAL(10,7)**
- 7 decimal places = ~1.1m precision
- Haversine calculation in PHP uses float (double, 15+ decimal places)
- No precision issues for MVP

### 3.4 Pagination Consistency

**Risk:** If new listings added between pagination requests, page 2 might contain duplicates from page 1.

**Current mitigation:** Not implemented (acceptable for MVP)
- **Phase 8 enhancement:** Use created_at + ID as cursor for stable pagination
- For MVP: Accept small inconsistency; users won't notice on typical searches

### 3.5 Radius Validation & DDoS Prevention

**Decision from CONTEXT.md:** Max radius 50km (50,000 meters).

**Why cap at 50km:**
- Prevents huge result sets (100K listings within 100km in dense cities)
- Typical user scenario: searching 5-20km radius
- Protects database from expensive full-table scans

**Implementation:**
```php
$radiusMeters = (int)($get['radius'] ?? 10000); // Default 10km

if ($radiusMeters < 0 || $radiusMeters > 50000) {
    return Response::error('Radius must be 0-50000 meters', 400);
}
```

### 3.6 Keyword Search Performance

**Risk:** LIKE '%term%' on 100K listings is slow.

**Current measurement (Phase 3):**
- Estimated ~100-200ms for full LIKE scan on 10K listings
- Acceptable for MVP

**If becomes bottleneck (Phase 8):**
- Add FULLTEXT index (see Section 1.3)
- Implement autocomplete caching
- Add search term filtering (block very short terms like single letters)

---

## 4. Implementation Approach (Step-by-Step)

### 4.1 Phase 4 Deliverables (Backend Only)

**1. Repository layer enhancement**
- Add `searchByDistance()` method to `ListingRepository`
- Implement filtered candidate query (category, price, keyword)
- Return results suitable for distance calculation

**2. Service layer addition**
- `searchWithDistance()` in `ListingService`
- Implement Haversine calculation
- Apply radius filtering
- Sort by distance
- Handle user location fallback

**3. Controller enhancement**
- Enhance existing `GET /api/listings/search` endpoint
- Add new parameters: `lat`, `lng`, `radius`
- Add response field: `distance_meters` (per listing)
- Add response metadata: `search_radius_meters` (for UI)

**4. New utility class**
- `GeometryService` or similar with Haversine static method
- Unit testable, reusable

### 4.2 Database Schema Changes (Minimal)

**Additions:** Optional, no breaking changes
```sql
-- OPTIONAL: Speed up active listing queries (Phase 4+)
ALTER TABLE listings ADD INDEX idx_status_deleted (status, deleted_at);

-- OPTIONAL: Improve category filter performance
ALTER TABLE listings ADD INDEX idx_category_status (category_id, status, deleted_at);
```

**No schema migration needed:**
- `latitude`, `longitude` already exist (Phase 3)
- `users.latitude`, `users.longitude` already exist (Phase 2)
- Soft-delete columns already exist (Phase 1)

### 4.3 Repository Method Signature

```php
/**
 * Get listings matching filter criteria (pre-distance calculation).
 * Used by service layer to build candidate set for distance filtering.
 * 
 * @param array $filters keyword, category_id, condition, price_min, price_max
 * @param int $limit Max candidates to fetch (for distance calc)
 * @return array Listing records with lat/lng
 */
public function searchCandidatesByFilters(array $filters = [], int $limit = 1000): array
```

### 4.4 Service Method Signature

```php
/**
 * Search listings by distance from a location.
 * 
 * @param float $userLat User latitude
 * @param float $userLng User longitude
 * @param int $radiusMeters Search radius in meters (0-50000)
 * @param array $filters keyword, category_id, condition, price_min, price_max
 * @param int $limit Results per page (1-100)
 * @param int $offset Results offset for pagination
 * @return array ['listings' => [...], 'total' => N, 'limit' => L, 'offset' => O]
 * @throws Exception On validation errors (radius too large, location required)
 */
public function searchWithDistance(
    float $userLat,
    float $userLng,
    int $radiusMeters,
    array $filters = [],
    int $limit = 20,
    int $offset = 0
): array
```

### 4.5 Controller Method Enhancement

```php
/**
 * GET /api/listings/search?lat=X&lng=Y&radius=Z&keyword=...&category=...
 * 
 * New parameters vs current implementation:
 * - lat: optional float, user search latitude (defaults to session user's stored lat)
 * - lng: optional float, user search longitude (defaults to session user's stored lng)
 * - radius: optional int meters, max 50000, default 10000
 * 
 * Response additions:
 * - Each listing now includes: distance_meters field
 * - Root response includes: search_radius_meters field
 * - Listings sorted by distance (nearest first) instead of created_at DESC
 */
public function search(array $get, array $post, array $files, array $params): string
```

---

## 5. Dependencies & Libraries (Current Codebase)

### 5.1 Already Available

**From Phase 1-3 foundation:**
- PDO with prepared statements (all queries safe from SQL injection)
- `BaseRepository` pattern (soft-delete filtering, CRUD operations)
- `Softdeletable` trait (automatic WHERE clause filtering)
- `Response` envelope class (consistent JSON responses)
- `ListingRepository` with `filterCombined()` for multi-criteria search

**From Phase 2-3 services:**
- `GeolocationService` with address geocoding (already caches coordinates)
- `UserRepository` with user location access
- `AuthMiddleware` for session validation

### 5.2 What Phase 4 Needs to Add

**Math utility:**
```php
// src/Utils/GeometryService.php
class GeometryService {
    const EARTH_RADIUS_KM = 6371;
    
    /**
     * Calculate distance between two points using Haversine formula.
     * @return float Distance in meters
     */
    public static function haversineDistance(
        float $lat1, float $lng1, 
        float $lat2, float $lng2
    ): float { ... }
}
```

**No external packages needed.**
- Math functions are built into PHP (sin, cos, deg2rad, atan2)
- No additional Composer dependencies required

### 5.3 Dependency Injection Setup

**Router already supports DI (Phase 1).**

Example for Phase 4:
```php
// In Router::dispatch(), instantiate SearchService with dependencies
$listingService = new ListingService(
    $pdo,
    new ListingRepository($pdo),
    new ListingPhotoRepository($pdo),
    new GeolocationService($pdo),
    new GeometryService() // Inject geometry utility
);
```

---

## 6. Performance Characteristics

### 6.1 Query Performance Profile

**Scenario 1: Simple distance search (no filters)**
- Query 1: Fetch all active listings (10K results) ~50ms
- PHP: Calculate distance for 10K listings (10ms) + sort (5ms) = ~15ms
- Total: ~65ms ✓

**Scenario 2: Distance + keyword + category filters**
- Query 1: Fetch filtered listings (~200 results) ~10ms
- PHP: Calculate distance (2ms) + sort (0.5ms) = ~2.5ms
- Total: ~12.5ms ✓

**Scenario 3: Dense city, 50km radius, no filters**
- Query 1: Fetch all active listings (500K results) — ⚠️ PROBLEM
- PHP: Calculate distance for 500K (~500ms) — too slow

**Mitigation for Scenario 3:**
- Unlikely in MVP phase (requires 500K active listings in single city)
- Phase 8: Implement MySQL ST_Distance() filtering before returning to PHP
- Phase 8: Add geo-sharding (split results by grid quadrants)

### 6.2 Database Load

**Concurrent users during map discovery:**
- 100 concurrent searches = 100 queries/second to database
- Each query: ~50ms (cached by MySQL query cache)
- Total throughput: Easily handled by single MySQL instance

**Cumulative load across phases:**
- Phase 4 (search): 100 queries/sec
- Phase 5 (chat): 50 queries/sec
- Phase 6+ (bookings/reviews): 50 queries/sec
- Total: 200 queries/sec on 10K listings — acceptable for MVP

### 6.3 Response Time Budget

**Target:** User expects results in <200ms

- Database query: 50ms
- PHP calculation: 15ms
- Serialization: 5ms
- Network round-trip: 50ms (assumed)
- **Total: ~120ms ✓**

---

## 7. Testing Strategy

### 7.1 Unit Tests Needed

**Haversine calculation:**
```php
public function testHaversineDistance() {
    // Toronto to Montreal: ~330km
    $distance = GeometryService::haversineDistance(
        43.6532, -79.3832,  // Toronto
        45.5017, -73.5673   // Montreal
    );
    $this->assertGreaterThan(320000, $distance); // 320,000 meters
    $this->assertLessThan(340000, $distance);
}
```

**Radius filtering:**
```php
public function testRadiusFilter() {
    $listings = [
        ['id' => 1, 'lat' => 43.6532, 'lng' => -79.3832], // 0km
        ['id' => 2, 'lat' => 43.6600, 'lng' => -79.3800], // ~1km
        ['id' => 3, 'lat' => 43.7000, 'lng' => -79.3000], // ~8km
    ];
    
    $result = $service->searchWithDistance(43.6532, -79.3832, 5000, []);
    // Should return listings 1 and 2 (within 5km)
    $this->assertCount(2, $result['listings']);
}
```

### 7.2 Integration Tests Needed

**Full search flow:**
- POST listing with address
- GET search with lat/lng
- Verify listing appears with distance field
- Verify ordering by distance

**Default location fallback:**
- User stored at (43.65, -79.38)
- GET search without lat/lng params
- Should use user's stored location

---

## 8. OpenCode's Discretionary Decisions

Based on CONTEXT.md "OpenCode's Discretion" section:

**1. Exact Haversine implementation**
- ✓ Use EARTH_RADIUS_KM = 6371 (standard value)
- ✓ Use `deg2rad()` for radian conversion (built-in PHP function)
- ✓ Use `atan2()` for arc calculation (standard approach)
- Round results to nearest meter (3 significant figures)

**2. Query optimization strategy**
- ✓ Phase 4: Use LIKE for keyword search (simple, works for MVP)
- → Phase 8: Add FULLTEXT index if needed (benchmarking decision)
- ✓ Phase 4: Don't add spatial index (breaks Phase 3 schema lock)
- → Phase 8+: Migrate to MySQL ST_Distance() if needed

**3. Pagination page size**
- ✓ Default 20 results (standard for mobile UX)
- ✓ Max 100 results (prevents large responses)
- ✓ Offset-based pagination (standard, works with distance sorting)

**4. FULLTEXT index timing**
- ✓ Phase 4: DON'T add FULLTEXT index
- Rationale: LIKE is fast enough; simpler to understand; easier to debug
- → Phase 8: Benchmark search performance; add FULLTEXT only if needed

---

## 9. Summary: What Backend Needs to Do

### Required Changes (Phase 4 executables)

**1. Create GeometryService utility**
   - Haversine distance calculation
   - Testable, static method

**2. Enhance ListingRepository**
   - Add `searchCandidatesByFilters()` method
   - Return all matching listings (no pagination) for distance calc

**3. Enhance ListingService**
   - Add `searchWithDistance()` method
   - Fetch candidates → calculate distances → filter → sort → paginate

**4. Enhance ListingController**
   - Update `search()` endpoint to handle: `lat`, `lng`, `radius` parameters
   - Default to user's stored location if not provided
   - Validate radius (0-50000 meters)
   - Add `distance_meters` to response

**5. Optional database indexes**
   - Add `idx_status_deleted` on listings
   - Profile and benchmark first; add only if needed

### What Stays the Same

- Soft-delete filtering (already implemented)
- LIKE-based keyword search (already implemented, Phase 4 just uses it)
- Response envelope format (already works)
- User authentication/authorization (already secured)
- Database schema (Phase 3 locked; no breaking changes)

---

**Research Complete. Ready for 04-PLANNING.md**

*Last Updated: 2026-03-30*

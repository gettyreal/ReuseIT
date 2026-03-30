# Phase 4: Map & Search Discovery - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Backend API endpoints for discovering nearby listings through spatial queries and keyword search. Users can filter by location radius, category, price range, and search by keyword. Results are sorted by distance from the user's location. Frontend map UI is out of scope — this phase delivers the data layer only.

</domain>

<decisions>
## Implementation Decisions

### Distance Calculation Strategy
- **Approach:** Haversine formula implemented in PHP
- **Why:** Simpler code, easier to debug and test, sufficient for MVP-scale discovery
- **Calculation:** Distance calculated in PHP after fetching candidate listings from database
- **Performance consideration:** Acceptable for initial phases; can optimize to MySQL ST_Distance in future if needed

### Search Endpoint Parameters (GET /api/listings/search)
- **Optional parameters with intelligent defaults:**
  - `lat`, `lng` — Optional; if not provided, defaults to user's stored location from their profile
  - `radius` — Optional; defaults to 10 km (10,000 meters) if not specified
  - `category` — Optional filter by category
  - `minPrice`, `maxPrice` — Optional price range filters
  - `keyword` — Optional keyword search
- **Maximum search radius:** 50 km (50,000 meters) — API returns 400 error if user requests larger radius
- **Rationale:** User-friendly defaults reduce frontend friction; user location fallback enables one-click search; 50 km cap prevents runaway queries

### Keyword Search Behavior
- **Search fields:** Title and description only (not category names or seller username)
- **Matching strategy:** Partial/substring matching — "iPhone" returns "iPhone 12", "iPhone 13 Pro", etc.
- **Case sensitivity:** Case-insensitive — standard for user-friendly search
- **SQL implementation:** Use LIKE operator with wildcards; consider FULLTEXT index on title + description for performance if needed in future

### Filter & Response Behavior
- **Filters are additive:** All specified filters apply (AND logic)
  - Example: `?keyword=iPhone&category=electronics&minPrice=100&maxPrice=500` returns listings matching ALL criteria
- **Sort order:** Results sorted by distance (nearest first) from the user's search location
- **Pagination:** All search responses paginated (20 results per page default, offset-based)
- **Empty results:** Return HTTP 200 with empty array if no matches; no error

### OpenCode's Discretion
- Exact Haversine implementation details (Earth radius constant, rounding precision)
- Query optimization strategy (index placement, caching approach if needed)
- Pagination page size (current default 20; can be adjusted based on performance testing)
- FULLTEXT index implementation timing (can defer to Phase 4 completion or Phase 8 polish if performance acceptable)

</decisions>

<specifics>
## Specific Ideas

- Ensure distance is always calculated from the user's actual position (either provided or their profile location)
- Search should feel snappy — users expect results within a couple hundred milliseconds
- Consider that users might search from different locations than where they live (traveling, commuting) — lat/lng parameters allow flexibility

</specifics>

<deferred>
## Deferred Ideas

- Frontend map UI with markers and clustering — Phase 4 delivers endpoints only; map visualization handled separately
- Real-time map updates — WebSocket implementation deferred to future optimization phase
- Search result caching/memoization — Can be added if performance testing shows need in Phase 8
- Fuzzy/typo-tolerant search — Out of scope; standard LIKE matching sufficient for v1
- Geographic boundary enforcement (e.g., "don't search across state lines") — Future feature if needed

</deferred>

---

*Phase: 04-map-search-discovery-week-6*
*Context gathered: 2026-03-30*

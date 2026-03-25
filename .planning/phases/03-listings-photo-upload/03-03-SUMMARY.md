---
phase: 03-listings-photo-upload
plan: 03
subsystem: listings
tags: [search, filtering, geolocation, geocoding, caching, candidates, keyword, pagination]

requires:
  - phase: 02-auth-user-profiles
    provides: "AuthService, UserRepository, GeolocationService, AuthMiddleware"
  - phase: 03-listings-photo-upload
    provides: "ListingRepository CRUD, ListingService, ListingController, photo uploads"

provides:
  - "Advanced geolocation with candidate selection for ambiguous addresses"
  - "Multi-criteria search and filtering (keyword, category, condition, price)"
  - "Pagination support with limit/offset throughout"
  - "Filter options endpoint for UI dropdown population"
  - "Foundation for Phase 4 distance-based discovery"

affects:
  - Phase 4 (Discovery/Map - depends on search infrastructure)
  - Phase 5 (Chat - needs listing lookups)

tech-stack:
  added:
    - "GeolocationService.geocodeAddressWithCandidates() for address disambiguation"
    - "ListingRepository filter methods (searchByKeyword, filterBy*, filterCombined)"
    - "ListingService search/filter wrappers with validation"
    - "GET /api/listings/search endpoint with multi-param filtering"
    - "GET /api/listings/filter-options endpoint for UI"
  patterns:
    - "Candidate selection pattern for ambiguous geocoding results"
    - "Combined filter pattern supporting multiple simultaneous filters"
    - "Pagination pattern with limit/offset throughout"
    - "Query optimization via JOINs preventing N+1 queries"

key-files:
  created: []
  modified:
    - src/Services/GeolocationService.php
    - src/Services/ListingService.php
    - src/Repositories/ListingRepository.php
    - src/Controllers/ListingController.php
    - src/Router.php

key-decisions:
  - "Ambiguous addresses return candidates for user selection; unambiguous auto-select"
  - "Only first (highest confidence) result cached to minimize storage"
  - "All search queries use prepared statements + soft-delete filtering"
  - "Filter methods use JOINs to include seller info and photo count (N+1 prevention)"
  - "Search endpoints are PUBLIC (no authentication required)"
  - "Pagination limit capped at 100, defaults to 20 for performance"

requirements-completed: [GEO-01, LIST-07]

duration: 3 min
completed: 2026-03-25
---

# Phase 3 Plan 03: Listing Search & Geolocation Candidate Selection Summary

**Advanced search and filtering with address candidate selection for improved geocoding accuracy and user experience**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-25T21:35:55Z
- **Completed:** 2026-03-25T21:38:34Z
- **Tasks:** 3
- **Files modified:** 5
- **Commits:** 3

## Accomplishments

- **GeolocationService enhancement** with `geocodeAddressWithCandidates()` for handling ambiguous addresses
  - Returns multiple candidates when address is unclear
  - Auto-selects when single unambiguous match found
  - Caches first result to minimize API quota usage
  
- **ListingService integration** of candidate selection into listing creation flow
  - Returns candidates list (HTTP 200) for ambiguous addresses
  - Auto-creates listing (HTTP 201) for unambiguous addresses
  - Improved user experience with address confirmation

- **ListingRepository filter methods** for flexible search and filtering
  - `searchByKeyword()` - searches title and description with pagination
  - `filterByCategory()` - filters by category
  - `filterByCondition()` - filters by condition enum (Excellent, Good, Fair, Poor)
  - `filterByPrice()` - filters by price range
  - `filterCombined()` - supports multiple simultaneous filters
  - All methods use JOINs to prevent N+1 queries

- **ListingService search/filter methods** wrapping repository with validation
  - `searchListings()` - validates filters and returns paginated results
  - `getListingDetails()` - full listing with photos
  - `getFilterOptions()` - categories, conditions, price range for UI

- **Search endpoints** enabling advanced discovery
  - GET /api/listings/search - query params: keyword, category_id, condition, price_min, price_max, limit, offset
  - GET /api/listings/filter-options - returns filter configuration for UI dropdowns/sliders
  - Both public endpoints (no authentication required)

- **Pagination pattern** applied throughout search infrastructure
  - Limit capped at 100 for performance
  - Offset for cursor-based browsing
  - Total count returned for progress indicators

## Task Commits

1. **Task 1: Enhance GeolocationService with candidate selection** - `a313a3e` (feat)
   - Added `geocodeAddressWithCandidates()` for address disambiguation
   - Added `getCachedAddressCandidates()` to check cache before API calls
   - Added `callGoogleMapsAPISingleResults()` returning full Google results array
   - Added `cacheAddressCandidate()` to cache first (highest confidence) result
   - Updated `ListingService.createListing()` to handle candidates:
     - If 1 candidate: auto-select and create listing
     - If multiple: return list for user to pick
     - If 0: return 400 error

2. **Task 2: Add listing search and filter methods** - `8cb7f03` (feat)
   - Added 5 filter methods to ListingRepository with soft-delete filtering
   - Added 3 helper methods to ListingService for search, details, options
   - All queries use prepared statements and JOINs for N+1 prevention
   - Validation layer for price ranges and filter combinations

3. **Task 3: Create search endpoints** - `5577b1b` (feat)
   - Added GET /api/listings/search endpoint with multi-parameter filtering
   - Added GET /api/listings/filter-options endpoint for UI configuration
   - Updated Router with new routes (placed before :id to avoid conflicts)
   - Both endpoints public (no authentication required)

## Files Created/Modified

- `src/Services/GeolocationService.php` - Added 4 new methods (157 lines added)
- `src/Services/ListingService.php` - Added 8 new methods, enhanced createListing (195 lines added)
- `src/Repositories/ListingRepository.php` - Added 5 new methods (221 lines added)
- `src/Controllers/ListingController.php` - Added search and filterOptions methods (69 lines added)
- `src/Router.php` - Added search and filter-options routes (2 lines added)

## Decisions Made

- **Ambiguous address handling:** Multiple candidates returned for user selection; unambiguous auto-selected
- **Caching strategy:** Only first result cached (avoid storage bloat; highest confidence match)
- **Search scope:** Keyword search on title and description for comprehensive discoverability
- **N+1 prevention:** All queries use LEFT JOIN for seller info and photo count
- **Pagination:** Limit capped at 100 for performance; defaults to 20
- **Authorization:** Search endpoints public (no auth required); creation/editing protected
- **Filter combination:** AND logic applied across filters (not OR)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - all verifications passed.

## Geocoding Flow Diagram

```
User submits address (string)
  ↓
GeolocationService.geocodeAddressWithCandidates(address)
  ├─ Check cache (MD5 hash lookup)
  │  └─ If found: return cached result [1 candidate]
  │
  └─ Call Google Maps API with address
     ├─ If 0 results: return null
     ├─ If 1 result: cache it, return [1 candidate]
     └─ If 2+ results: return all as candidates (2-5 typically)

ListingService.createListing(data, userId)
  ├─ Get candidates from geocoding
  ├─ If 1 candidate: auto-select → create listing with coordinates
  └─ If 2+ candidates: return candidates list (HTTP 200) for user selection

User re-submits with confirmed address
  └─ Same flow: geocode → 1 result → auto-select → create
```

## API Contract

### GET /api/listings/search

Search and filter listings with multiple criteria.

**Query Parameters:**
- `keyword` (optional): string - searches title and description
- `category_id` (optional): int - filter by category
- `condition` (optional): string - enum [Excellent, Good, Fair, Poor]
- `price_min` (optional): float - minimum price
- `price_max` (optional): float - maximum price
- `limit` (default 20, max 100): int - results per page
- `offset` (default 0): int - results to skip

**Response:**
```json
{
  "status": "success",
  "data": {
    "listings": [
      {
        "id": 123,
        "title": "iPhone 12 Pro",
        "price": 650.00,
        "condition": "Excellent",
        "category_id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "avatar_url": "uploads/users/42/avatar.jpg",
        "photo_count": 3,
        "created_at": "2026-03-25T10:00:00Z"
      }
    ],
    "total": 156,
    "limit": 20,
    "offset": 0
  },
  "code": 200
}
```

### GET /api/listings/filter-options

Get available filter values for UI.

**Response:**
```json
{
  "status": "success",
  "data": {
    "categories": [
      {"id": 1, "name": "Electronics"},
      {"id": 2, "name": "Furniture"}
    ],
    "conditions": ["Excellent", "Good", "Fair", "Poor"],
    "priceRange": {
      "min": 10.00,
      "max": 5000.00
    }
  },
  "code": 200
}
```

## Next Phase Readiness

- ✅ Search and filtering foundation complete
- ✅ Pagination pattern established throughout
- ✅ Geolocation caching working
- ✅ All queries use prepared statements and soft-delete filtering
- ⏳ Distance-based filtering (Phase 4) can now leverage this search infrastructure
- ⏳ Map markers (Phase 4) can use coordinates stored by geocoding

**Phase 3 Complete Status:**
- Phase 03-01 (Listings CRUD): ✓ COMPLETE
- Phase 03-02 (Photo Upload): ✓ COMPLETE
- Phase 03-03 (Search & Geolocation): ✓ COMPLETE

---

*Phase: 03-listings-photo-upload*  
*Completed: 2026-03-25*
*All requirements covered: GEO-01, LIST-07*

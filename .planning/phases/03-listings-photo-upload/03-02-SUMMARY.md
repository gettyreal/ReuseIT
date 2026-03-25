---
phase: 03-listings-photo-upload
plan: 02
subsystem: photo-upload, security
tags: [intervention/image, EXIF-stripping, file-upload, authorization]

# Dependency graph
requires:
  - phase: "03-01"
    provides: "Listing CRUD endpoints and ListingRepository"
  - phase: "02-01"
    provides: "User authentication and AuthMiddleware"
provides:
  - "PhotoUploadService with validation, EXIF stripping, and storage"
  - "POST /api/listings/:id/photos - secure listing photo uploads"
  - "POST /api/users/:id/avatar - user avatar uploads"
  - "ListingPhotoRepository for database persistence"
  - ".htaccess security configuration blocking PHP execution"
affects: ["04-discovery", "listings-display", "user-profiles"]

# Tech tracking
tech-stack:
  added: 
    - "intervention/image ^2.7 for image re-encoding and EXIF stripping"
  patterns:
    - "File validation pipeline: MIME type → file size → dimensions → magic bytes"
    - "Photo storage: {timestamp}_{random_hash}.jpg with directory isolation by user/listing"
    - "Authorization pattern: ownership verification before upload"
    - "Error response codes: 422 validation, 409 quota exceeded, 403 unauthorized, 401 unauthenticated"

key-files:
  created:
    - "src/Services/PhotoUploadService.php - secure photo upload handler"
    - "src/Repositories/ListingPhotoRepository.php - photo persistence"
    - "src/Controllers/ListingController.php - photo upload endpoints"
    - "public/uploads/.htaccess - PHP execution blocker"
  modified:
    - "src/Router.php - registered photo upload routes and dependency injection"
    - "composer.json - added intervention/image dependency"
    - ".env.example - documented photo upload configuration"

key-decisions:
  - "JPEG re-encoding chosen over PNG/WebP to ensure consistent EXIF stripping across all image formats"
  - "Randomized filenames with timestamp + MD5 hash prevent enumeration while maintaining debuggability"
  - "Directory structure users/{user_id}/listings/{listing_id}/ provides isolation and clear organization"
  - "Max 2000px height resize reduces storage while maintaining quality for thumbnails"
  - "Soft-delete strategy for photos enables listing restoration with photo recovery"

patterns-established:
  - "Service layer validates inputs and raises InvalidArgumentException/RuntimeException for controller handling"
  - "Controllers enforce authorization checks before delegating to services"
  - "PDO prepared statements required on all database queries (3 statements in photo flow)"
  - ".htaccess provides defense-in-depth even if file storage was accidentally in web root"
  - "File upload normalization handles both single and multiple file uploads uniformly"

requirements-completed: ["LIST-02", "USER-03"]

# Metrics
duration: 3 min
completed: 2026-03-25
---

# Phase 3 Plan 2: Photo Upload Security & Storage Summary

**Secure photo upload with EXIF stripping, file validation, and randomized storage; user avatar uploads enabled**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-25T21:27:08Z
- **Completed:** 2026-03-25T21:30:16Z
- **Tasks:** 3
- **Files created:** 4
- **Files modified:** 3

## Accomplishments

- **PhotoUploadService** with comprehensive validation: MIME type, file size (5MB max), dimensions (640×480 min, 8000×8000 max), magic byte verification
- **EXIF metadata stripping** via intervention/image re-encoding to JPEG (critical security requirement)
- **Secure file storage** with randomized filenames ({timestamp}_{md5_hash}.jpg) preventing enumeration attacks
- **Authorization enforcement** on both listing photos and user avatars (ownership verification)
- **Photo quota management** with 409 Conflict response if 10-photo limit exceeded
- **Filesystem security** via .htaccess blocking PHP execution in upload directories
- **Database integration** with ListingPhotoRepository for persistent storage and soft-delete support
- **Router integration** with dependency injection for PhotoUploadService and protected endpoint middleware

## Task Commits

Each task was committed atomically:

1. **task 1: Add intervention/image library and create PhotoUploadService** - `67e0d8c` (feat)
   - intervention/image ^2.7 added to composer.json
   - PhotoUploadService with validatePhoto, reencodeAndStore, deletePhoto methods
   - ListingPhotoRepository extending BaseRepository with soft-delete support
   - 3 prepared statements for secure photo queries (create, count, delete)

2. **task 2: Add photo upload endpoints and security configuration** - `eafb369` (feat)
   - ListingController with uploadPhotos and uploadAvatar methods
   - POST /api/listings/:id/photos - max 10 photos per listing
   - POST /api/users/:id/avatar - user profile photo
   - Router registered protected endpoints with AuthMiddleware
   - Dependency injection for PhotoUploadService
   - .htaccess blocks PHP execution in public/uploads/
   - Multipart form file normalization for multiple uploads

3. **task 3: Documentation and configuration** - `f2899db` (docs)
   - .env.example updated with photo upload configuration
   - UPLOAD_DIR, MAX_UPLOAD_SIZE_MB, MAX_PHOTOS_PER_LISTING, etc.

## Files Created/Modified

- `src/Services/PhotoUploadService.php` - Core secure upload handler with validation pipeline
- `src/Repositories/ListingPhotoRepository.php` - Photo persistence with soft-delete
- `src/Controllers/ListingController.php` - HTTP upload endpoint handlers
- `public/uploads/.htaccess` - PHP execution blocker for security
- `src/Router.php` - Route registration and dependency injection
- `composer.json` - intervention/image dependency added
- `.env.example` - Photo upload configuration documented

## Decisions Made

1. **JPEG re-encoding over PassThrough** - All images re-encoded to JPEG to guarantee EXIF removal across all source formats
2. **Timestamp + MD5 hash filenames** - Provides both debuggability (timestamp) and security (hash prevents enumeration)
3. **Directory isolation by user then listing** - Clear organization with built-in access control
4. **Soft-delete for photos** - Enables listing restoration with photo recovery
5. **5MB size limit** - Reasonable balance between image quality and storage cost

## Deviations from Plan

None - plan executed exactly as written.

## Security Controls Implemented

- **EXIF Stripping:** intervention/image re-encoding automatically removes all metadata
- **Filename Randomization:** {timestamp}_{md5_hash}.jpg prevents enumeration attacks
- **File Validation:** MIME type (finfo), file size, dimensions, magic byte verification
- **Directory Permissions:** 755 prevents directory browsing; .htaccess prevents PHP execution
- **Authorization:** All uploads require authentication; ownership verified before storage
- **Input Validation:** 422 response for validation failures; 409 for quota exceeded

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Photo upload foundation complete and secure
- Listing photos can be uploaded and displayed
- User avatars available for profile completion
- Ready for Phase 3 Plan 3 (Listing CRUD completion)
- Ready for Phase 4 (Discovery - can display photos in listings)

---
*Phase: 03-listings-photo-upload*
*Completed: 2026-03-25*

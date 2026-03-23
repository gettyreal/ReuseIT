# Phase 2: Authentication & User Profiles - Context

**Gathered:** 2026-03-23
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can identify themselves to the system through registration, login, and profile management. This phase enables user context for all subsequent features (listings, bookings, messaging, reviews). Registration, login, logout, and profile editing are in scope. Email verification is deferred to a later phase.

</domain>

<decisions>
## Implementation Decisions

### Registration Flow

- **Location Data Collection:** User provides full address (street, city, province, postal code, country) via form input
- **Geolocation Strategy:** 
  - Primary: Request browser geolocation permission → capture GPS coordinates (latitude, longitude)
  - Fallback: If GPS denied/unavailable → geocode address via Google Maps API to derive coordinates
  - All address components + coordinates are required fields
- **Signup Data Collected:** email, password, first_name, last_name, address (5 components), latitude, longitude
- **All fields required:** No optional fields at signup
- **Email Verification:** Disabled at signup — account activates immediately, email_verified flag set to false (verification deferred to future phase)

### Session & Security

- **Session Duration:** Idle-based with 30-minute inactivity timeout
  - User activity resets the timer
  - Inactivity > 30 min = automatic logout
- **"Remember Me" Option:** Not implemented
  - Every login creates new 30-minute idle session
  - No persistent "remember me" checkbox
- **Multi-Device Logout:** Single-device logout only
  - Logout on one device does not affect other active sessions
  - User remains logged in elsewhere
- **Session ID Regeneration:** Regenerated on every successful login (session fixation prevention)

### Profile Data & Display

- **Avatar Handling:**
  - Upload optional — users may upload profile photo
  - Default avatar provided if no photo uploaded (stored in `uploads/avatars/`)
  - Uploaded avatars saved to `uploads/avatars/` with consistent naming
- **Profile Visibility:** All profile information is public
  - Name (first_name, last_name), email, address (all components)
  - Bio/description, avatar
  - Statistics: completed sales count, average rating, active listings count
  - No private profile fields
- **Profile Edit Endpoints:** Users can edit:
  - first_name, last_name
  - bio/description
  - address (all 5 components: street, city, province, postal_code, country)
  - avatar (upload new photo)
  - **Not editable:** email, password, account settings (handled separately in future phases)

### Error Handling & Validation

- **Duplicate Email at Signup:**
  - Response: "Email already registered" with helpful redirect
  - Offers link to login page: "[Go to login]" or similar
  - Trade-off: improved UX over strict enumeration attack prevention
- **Failed Login Credentials:**
  - Generic error message: "Invalid credentials"
  - Does not reveal whether email exists or password is wrong
  - Prevents enumeration attacks
- **Password Strength Feedback:**
  - Visual strength meter displayed during signup: Weak → Fair → Good → Strong
  - Real-time feedback as user types
  - Minimum requirement: 8 characters (additional uppercase/numbers/special chars TBD by OpenCode)
- **Rate Limiting on Failed Logins:**
  - Hard lock after 5 failed login attempts
  - Account locked for 15 minutes
  - User receives email notification when account is locked
  - Account auto-unlocks after 15 minutes or via email link

### OpenCode's Discretion

- Exact password strength requirements (uppercase, numbers, special characters policy)
- Google Maps API error handling and retry logic
- Avatar image validation (file type, size, dimensions)
- Default avatar generation/styling if no photo uploaded
- Email notification templates and delivery
- Exact timeout handling (grace period, warning before logout, etc.)
- Address validation and normalization
- Geocoding error messages and fallback behavior

</decisions>

<specifics>
## Specific Ideas

- Location flow mirrors modern marketplace pattern (Airbnb, local services) — address field + GPS permission
- Avatar system allows organic growth (no forced image at signup, but always something to display)
- All-public profiles create transparency and trust in marketplace (users see who they're dealing with)
- 30-minute idle timeout balances security (sensitive marketplace data) with UX (not too annoying)
- Rate limiting prevents brute force but doesn't lock legitimate users out permanently

</specifics>

<deferred>
## Deferred Ideas

- **Email verification** — Double opt-in with confirmation link (Phase X)
- **Password reset flow** — User can reset forgotten password (Phase X)
- **Email change** — User can change email address after signup (Phase X)
- **Password change** — User can change password from account settings (Phase X)
- **Two-factor authentication** — 2FA for additional security (Phase X)
- **Social login** — Login via Google, Facebook, etc. (Phase X)
- **Private/selective profile visibility** — Users can hide certain fields (Phase X)
- **Verification badges** — Email verified, phone verified, etc. (Phase X)
- **Profile bio Markdown** — Rich formatting for bio (Phase X)

</deferred>

---

*Phase: 02-auth*
*Context gathered: 2026-03-23*

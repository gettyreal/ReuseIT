# Phase 9: Foundation & Authentication — User Vision

**Phase:** 09-foundation-auth  
**Goal:** Establish frontend entry point with working authentication and reusable component infrastructure.  
**Status:** Ready for planning  
**Requirements Addressed:** FE-AUTH-01, FE-AUTH-02, FE-AUTH-03, FE-AUTH-04, FE-UX-05

---

## What We're Building

Phase 9 is the foundation of the v2.0 frontend. Without it, nothing else works. The phase focuses on:

1. **Authentication UI** — Registration, login, password reset, logout
2. **Navigation infrastructure** — Header/sidebar visible on all pages
3. **Component library** — Reusable vanilla CSS patterns (buttons, forms, cards, modals)
4. **Session management** — Store auth token in localStorage, use in all API calls

This phase **does not** include:
- Profile pages (Phase 10)
- Listing browsing (Phase 11)
- Map features (Phase 12)
- Admin features (Phase 16)

---

## Success Criteria (What Must Be True)

When Phase 9 is complete:

1. ✅ User can register with email/password on a functional registration page with client-side validation
2. ✅ User can log in with email/password and session persists across browser refresh
3. ✅ User can reset forgotten password via email link simulation (frontend form + backend validation flow)
4. ✅ User can log out and return to login page with session fully cleared
5. ✅ Navigation header/sidebar visible and functional on all pages (root layout component)
6. ✅ Vanilla CSS component library initialized with reusable patterns (buttons, forms, cards, notifications)

---

## Key Decisions (Locked)

- **Tech stack:** Vanilla HTML/CSS/JavaScript only (no frameworks, no build tools)
- **State management:** localStorage for auth token + simple window state
- **API calls:** Direct fetch() to v1.0 backend (no abstraction layer)
- **Styling:** Vanilla CSS (grow incrementally, no Tailwind/Bootstrap)
- **Layout:** Single-page app with client-side routing (#/path)
- **Desktop-only:** Responsive design deferred to v2.1+

---

## Design Tokens Needed (From Figma)

Phase 9 will need extraction of:
- **Colors:** Primary, secondary, error, success, warning, neutral palette
- **Typography:** Font families, sizes, weights, line heights
- **Spacing:** 8px grid system (8, 16, 24, 32, 40px)
- **Button styles:** Primary, secondary, disabled, hover/active states
- **Form inputs:** Text, password, email, select styling
- **Cards & containers:** Border radius, shadows, spacing
- **Notifications:** Alert/error/success/info boxes

---

## API Integration

Phase 9 uses these v1.0 backend endpoints:

**Auth endpoints:**
- `POST /api/auth/register` — Create new user
- `POST /api/auth/login` — Authenticate user
- `POST /api/auth/logout` — End session
- `POST /api/auth/reset-password` — Initiate reset flow
- `POST /api/auth/reset-password-confirm` — Confirm reset with token

**Session:** After login, store `token` in localStorage and include in all API calls:
```
Authorization: Bearer {token}
```

---

## Component Library (Initialize)

Build reusable vanilla CSS components that will be used in all subsequent phases:

- **Button** — Primary, secondary, disabled, hover/active
- **Input** — Text, email, password, error states
- **Form** — Wrapper, label, field, validation messages
- **Card** — Container with padding, shadow, border
- **Modal** — Overlay, header, body, footer, close button
- **Navigation** — Header/sidebar with links
- **Alert** — Error, success, info, warning boxes
- **Loading** — Spinner, skeleton, progress indicator

---

## Figma Reference

User has complete Figma design specification. Phase 9 should extract:
- Design tokens (colors, typography, spacing)
- Component library specs (button styles, form fields, etc.)
- Page layouts (auth pages, navigation structure)

Use One CLI/MCP for Figma extraction during planning.

---

## Deferred to Later Phases

- Profile avatars (Phase 10)
- Listing creation/management (Phase 13)
- Chat messaging UI (Phase 15)
- Admin dashboard (Phase 16)
- Mobile responsive design (v2.1)
- Real-time WebSockets (v2.1)
- Email notifications (v2.1)

---

*Prepared for Phase 9 Planning*

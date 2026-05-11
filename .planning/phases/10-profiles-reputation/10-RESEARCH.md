# Phase 10: Profiles & Reputation - Research

**Research Date:** 2026-05-11  
**Objective:** What do I need to know to PLAN user profiles, reputation metrics, and review displays in vanilla JavaScript?

---

## RESEARCH COMPLETE

This document answers: **"What do I need to know to PLAN user profiles, reputation metrics, and review displays?"**

The research is organized around 6 key areas identified in the objective.

---

## 1. Profile UI Patterns

### Layout Approaches

**Profile as a Dashboard Layout**

For profiles, the standard SPA pattern is a vertical layout with multiple sections stacked:

```
┌─────────────────────────┐
│  Avatar + Quick Stats   │ (header section: photo, name, location, rating)
├─────────────────────────┤
│  Bio / About Section    │ (text content)
├─────────────────────────┤
│  Stats Cards Grid       │ (6 key metrics displayed as card grid)
├─────────────────────────┤
│  Reviews Section        │ (review list with sorting)
└─────────────────────────┘
```

**Two-View Approach (Own vs Other Users)**

- **Own Profile:** Shows editable fields + more detailed stats + draft recovery link (if applicable)
- **Other User's Profile:** Shows read-only stats + trust signals (reviews prominently featured)

This is implemented with a `?userId=` or just different page logic:

```javascript
// Route: #/profile or #/profile/[userId]
// Logic: Check if userId matches current user token
if (isOwnProfile) {
  showEditButton();
  loadOwnProfileWithDrafts();
} else {
  hideEditButton();
  loadOtherUserProfile(userId);
}
```

**Key CSS Pattern (from Phase 9 foundation):**

The Phase 9 component library provides:
- `.card` for grouping sections (with padding, shadows)
- `.d-grid` with `grid-template-columns: repeat(auto-fit, minmax(150px, 1fr))` for stat cards
- Utility classes for spacing: `.mt-24`, `.mb-32`, `.p-16`

### Edit Flow (Modal Dialog)

The user decision specifies **modal editing** for non-disruptive UX. In vanilla JS:

```javascript
// Pattern: Show/hide modal on button click
document.getElementById('editBtn').addEventListener('click', () => {
  showModal('profileEditModal');
  populateFormWithCurrentData();
});

// Modal cancel button
document.getElementById('cancelBtn').addEventListener('click', () => {
  hideModal('profileEditModal');
  resetForm(); // Clear any changes
});

// Modal submit
document.getElementById('saveBtn').addEventListener('click', async () => {
  if (!validateForm()) return; // Client-side validation
  const result = await updateProfile(formData); // Server call
  if (result.ok) {
    hideModal('profileEditModal');
    reloadProfileView(); // Show updated data
  } else {
    showFormError(result.error); // Display server errors inline
  }
});
```

**Modal Structure (using Phase 9 component library):**

```html
<div id="profileEditModal" class="modal" style="display: none;">
  <div class="modal-backdrop" onclick="hideModal('profileEditModal')"></div>
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Profile</h2>
    </div>
    <form id="profileForm" class="modal-body">
      <div class="form-group">
        <label for="name">Name</label>
        <input type="text" id="name" required>
        <span class="error-text" id="nameError"></span>
      </div>
      <!-- More fields: bio, location, avatar -->
    </form>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="hideModal('profileEditModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveProfile()">Save</button>
    </div>
  </div>
</div>
```

### Avatar Handling

**The Challenge:** Displaying user avatars across different sizes and devices

**The Pattern in Vanilla JS:**

1. **Upload Flow:**
   - User selects image file via `<input type="file">`
   - Client-side preview using `FileReader.readAsDataURL()`
   - Send as FormData to backend: `const fd = new FormData(); fd.append('avatar', fileInput.files[0]);`

2. **Display with Multiple Sizes:**
   - Backend stores original + multiple sizes (200px for profile, 48px for lists, 24px for thumbnails)
   - Frontend uses appropriate size for context:
     ```html
     <!-- Profile page (large) -->
     <img src="/api/v1/users/123/avatar?size=200" alt="User avatar" class="avatar-lg">
     
     <!-- Reviews list (small) -->
     <img src="/api/v1/users/123/avatar?size=48" alt="User avatar" class="avatar-sm">
     ```

3. **Fallback to Initials:**
   - If no avatar uploaded, render initials in a styled div
   - Use user's first/last letters + background color from palette
   
   ```javascript
   function renderAvatarOrInitials(user) {
     if (user.avatar_url) {
       return `<img src="${user.avatar_url}" alt="${user.name}" class="avatar">`;
     }
     const initials = user.name.split(' ').slice(0, 2).map(n => n[0]).join('');
     const color = getColorForUser(user.id); // Deterministic color based on ID
     return `<div class="avatar avatar-initials" style="background-color: ${color}">${initials}</div>`;
   }
   ```

**CSS for Avatar (from Phase 9 system):**

```css
.avatar {
  width: var(--size-48);
  height: var(--size-48);
  border-radius: var(--radius-full);
  object-fit: cover;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: var(--font-bold);
  color: white;
}

.avatar-lg { width: 200px; height: 200px; }
.avatar-sm { width: 48px; height: 48px; }
```

---

## 2. Review Display Patterns

### List vs Card Layouts

**Standard Review List Pattern** (as per user decision):

Reviews displayed as a simple, scannable list with clear separation:

```
┌─────────────────────────────────────┐
│ ⭐⭐⭐⭐⭐ Alice (2026-05-10)        │
│ "Great seller, items as described" │
│ Item: Used iPhone 12                │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ ⭐⭐⭐⭐ Bob (2026-05-08)            │
│ "Quick pickup, good communication"  │
│ Item: Laptop Stand                  │
└─────────────────────────────────────┘
```

**HTML Structure (vanilla approach):**

```html
<div class="reviews-section">
  <div class="reviews-header">
    <h3>Reviews ({reviewCount})</h3>
    <select id="sortBy" class="form-control">
      <option value="newest">Newest First</option>
      <option value="highest">Highest Rated First</option>
      <option value="oldest">Oldest First</option>
    </select>
  </div>
  
  <div id="reviewsList" class="reviews-list">
    <!-- Rendered reviews here -->
  </div>
</div>
```

### Sorting & Filtering

**User-Selectable Sorting** (as per context: newest first default, highest rated, oldest):

```javascript
let reviews = []; // fetched from API

document.getElementById('sortBy').addEventListener('change', (e) => {
  const sorted = sortReviews(reviews, e.target.value);
  renderReviews(sorted);
});

function sortReviews(reviews, sortType) {
  const copy = [...reviews];
  switch (sortType) {
    case 'newest':
      return copy.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    case 'highest':
      return copy.sort((a, b) => b.rating - a.rating);
    case 'oldest':
      return copy.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
  }
  return copy;
}
```

### Rating Visualization

**Stars + Aggregates Pattern:**

```javascript
// Show average rating at profile top
function renderRatingSummary(user) {
  const avgRating = (user.reviews.reduce((sum, r) => sum + r.rating, 0) / user.reviews.length).toFixed(1);
  return `
    <div class="rating-summary">
      <span class="rating-value">${avgRating}</span>
      <span class="rating-stars">${renderStars(avgRating)}</span>
      <span class="rating-count">from ${user.reviews.length} reviews</span>
    </div>
  `;
}

// Render individual stars for each review
function renderStars(rating) {
  let html = '';
  for (let i = 1; i <= 5; i++) {
    if (i <= Math.floor(rating)) {
      html += '⭐'; // Full star
    } else if (i - 0.5 === Math.ceil(rating - 0.5)) {
      html += '⭐'; // Half star (simplification: just show full)
    } else {
      html += '☆'; // Empty star
    }
  }
  return html;
}
```

**Breakdown Visualization** (optional but powerful):

```javascript
// Show distribution of ratings (e.g., "45% 5-star, 30% 4-star, etc.")
function renderRatingBreakdown(reviews) {
  const counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
  reviews.forEach(r => counts[r.rating]++);
  const total = reviews.length;
  
  return `
    <div class="rating-breakdown">
      ${[5, 4, 3, 2, 1].map(stars => {
        const percent = ((counts[stars] / total) * 100).toFixed(0);
        return `<div class="rating-row">
          <span>${stars}⭐</span>
          <div class="rating-bar" style="width: ${percent}%"></div>
          <span>${percent}%</span>
        </div>`;
      }).join('')}
    </div>
  `;
}
```

---

## 3. Backend Integration

### Fetching Profile Data

**Key Endpoints (from v1.0 backend context):**

```
GET  /api/v1/users/{userId}              → User profile data
GET  /api/v1/users/{userId}/reviews      → User's reviews (paginated)
GET  /api/v1/users/{userId}/listings     → User's listings
POST /api/v1/users/{userId}              → Update profile (name, bio, location)
POST /api/v1/users/{userId}/avatar       → Upload avatar (FormData)
```

**Fetch Pattern for Profile Load:**

```javascript
async function loadProfile(userId) {
  try {
    const [profile, reviews, listings] = await Promise.all([
      fetchAPI(`/api/v1/users/${userId}`),
      fetchAPI(`/api/v1/users/${userId}/reviews?limit=10`),
      fetchAPI(`/api/v1/users/${userId}/listings?limit=5`)
    ]);
    
    renderProfile(profile, reviews, listings);
  } catch (error) {
    showError('Failed to load profile');
  }
}

function fetchAPI(path, options = {}) {
  const headers = { ...options.headers };
  const token = localStorage.getItem('auth_token');
  if (token) headers['Authorization'] = `Bearer ${token}`;
  
  return fetch(path, { ...options, headers })
    .then(r => r.ok ? r.json() : Promise.reject(r))
    .catch(e => { throw e; });
}
```

### Handling Uploads

**Avatar Upload in Vanilla JS:**

```javascript
async function uploadAvatar(file) {
  const formData = new FormData();
  formData.append('avatar', file);
  
  try {
    const response = await fetch('/api/v1/users/me/avatar', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      },
      body: formData
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message);
    }
    
    const data = await response.json();
    // Update UI with new avatar URL
    document.querySelector('img.profile-avatar').src = data.avatar_url;
    showSuccess('Avatar updated');
  } catch (error) {
    showFormError('avatarError', error.message);
  }
}

// File input listener
document.getElementById('avatarInput').addEventListener('change', (e) => {
  const file = e.target.files[0];
  if (!file) return;
  
  // Client-side validation
  if (!file.type.startsWith('image/')) {
    showFormError('avatarError', 'Please select an image file');
    return;
  }
  if (file.size > 5 * 1024 * 1024) { // 5MB limit
    showFormError('avatarError', 'File must be less than 5MB');
    return;
  }
  
  // Preview before upload
  const reader = new FileReader();
  reader.onload = (evt) => {
    document.querySelector('.avatar-preview').src = evt.target.result;
  };
  reader.readAsDataURL(file);
  
  // Upload
  uploadAvatar(file);
});
```

### Review Pagination

**Fetching Reviews with Pagination:**

```javascript
let currentPage = 1;
const pageSize = 10;

async function loadReviews(userId, page = 1) {
  try {
    const response = await fetchAPI(
      `/api/v1/users/${userId}/reviews?page=${page}&limit=${pageSize}`
    );
    
    // Assuming response: { reviews: [], total, hasMore }
    renderReviews(response.reviews);
    currentPage = page;
    
    // Show "Load More" button if hasMore
    if (response.hasMore) {
      document.getElementById('loadMoreBtn').style.display = 'block';
    } else {
      document.getElementById('loadMoreBtn').style.display = 'none';
    }
  } catch (error) {
    showError('Failed to load reviews');
  }
}

document.getElementById('loadMoreBtn')?.addEventListener('click', () => {
  loadReviews(userId, currentPage + 1);
});
```

---

## 4. Performance Considerations

### Loading Many Reviews

**Pagination is Essential:**

- Load first 10 reviews on page load
- Provide "Load More" button or infinite scroll
- Avoid fetching all 500+ reviews at once

**Lazy-Load Images:**

```html
<img src="/api/v1/users/123/avatar?size=48" 
     alt="Avatar" 
     loading="lazy"
     class="avatar-sm">
```

### Image Optimization for Avatars

**Backend Serves Multiple Sizes:**

- **48px (thumbnail):** ~2-4KB
- **200px (profile):** ~8-15KB
- **Full resolution (original):** Keep stored for future use

**Frontend Use:**

```html
<!-- Profile page: use largest -->
<img src="/api/v1/users/123/avatar?size=200" alt="User" class="avatar-lg">

<!-- List of reviews: use smallest -->
<img src="/api/v1/users/123/avatar?size=48" alt="User" class="avatar-sm">

<!-- Fallback for missing avatar -->
<div class="avatar avatar-initials" style="background-color: hsl(${hash(userId) % 360}, 70%, 60%)">
  {initials}
</div>
```

### Caching Strategy

**localStorage for User Cache:**

```javascript
// Cache user profile to avoid refetch
function cacheUser(userId, profileData) {
  const cache = JSON.parse(localStorage.getItem('userCache') || '{}');
  cache[userId] = {
    data: profileData,
    timestamp: Date.now()
  };
  localStorage.setItem('userCache', JSON.stringify(cache));
}

function getCachedUser(userId) {
  const cache = JSON.parse(localStorage.getItem('userCache') || '{}');
  const cached = cache[userId];
  if (cached && Date.now() - cached.timestamp < 5 * 60 * 1000) { // 5 min
    return cached.data;
  }
  return null;
}

async function loadProfile(userId) {
  // Try cache first
  let profile = getCachedUser(userId);
  if (!profile) {
    profile = await fetchAPI(`/api/v1/users/${userId}`);
    cacheUser(userId, profile);
  }
  renderProfile(profile);
}
```

---

## 5. Validation & Error Handling

### Profile Form Validation

**Client-Side (Real-Time):**

```javascript
const profileForm = document.getElementById('profileForm');

// Validate individual fields on blur
['name', 'bio', 'location'].forEach(fieldId => {
  document.getElementById(fieldId).addEventListener('blur', () => {
    validateField(fieldId);
  });
});

function validateField(fieldId) {
  const input = document.getElementById(fieldId);
  const errorSpan = document.getElementById(`${fieldId}Error`);
  let isValid = true;
  let message = '';
  
  switch (fieldId) {
    case 'name':
      if (!input.value.trim()) {
        isValid = false;
        message = 'Name is required';
      } else if (input.value.length < 2) {
        isValid = false;
        message = 'Name must be at least 2 characters';
      } else if (input.value.length > 100) {
        isValid = false;
        message = 'Name must be less than 100 characters';
      }
      break;
    case 'bio':
      if (input.value.length > 500) {
        isValid = false;
        message = 'Bio must be less than 500 characters';
      }
      break;
    case 'location':
      if (input.value.length > 100) {
        isValid = false;
        message = 'Location must be less than 100 characters';
      }
      break;
  }
  
  if (isValid) {
    input.classList.remove('is-invalid');
    errorSpan.textContent = '';
  } else {
    input.classList.add('is-invalid');
    errorSpan.textContent = message;
  }
  
  return isValid;
}

function validateForm() {
  return ['name', 'bio', 'location'].every(fieldId => validateField(fieldId));
}
```

**Form CSS (from Phase 9 component library):**

```css
.form-group input.is-invalid {
  border-color: var(--color-error);
  background-color: rgba(220, 53, 69, 0.05);
}

.error-text {
  display: block;
  color: var(--color-error);
  font-size: var(--text-xs);
  margin-top: var(--space-4);
}
```

### Server Validation Errors

**Display Inline Errors from API:**

```javascript
async function saveProfile() {
  if (!validateForm()) {
    showError('Please fix validation errors');
    return;
  }
  
  const formData = {
    name: document.getElementById('name').value,
    bio: document.getElementById('bio').value,
    location: document.getElementById('location').value
  };
  
  try {
    const response = await fetch('/api/v1/users/me', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      },
      body: JSON.stringify(formData)
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      // Handle server validation errors
      if (data.errors) {
        // data.errors: { name: "Name is too short", bio: "..." }
        Object.entries(data.errors).forEach(([field, message]) => {
          const errorSpan = document.getElementById(`${field}Error`);
          if (errorSpan) {
            errorSpan.textContent = message;
            document.getElementById(field).classList.add('is-invalid');
          }
        });
      } else {
        showError(data.message || 'Failed to save profile');
      }
      return;
    }
    
    showSuccess('Profile updated');
    hideModal('profileEditModal');
    reloadProfileView();
  } catch (error) {
    showError('Network error: ' + error.message);
  }
}
```

### Network Error Handling

**Graceful Degradation:**

```javascript
async function fetchWithRetry(path, options = {}, maxRetries = 2) {
  let lastError;
  
  for (let attempt = 0; attempt < maxRetries; attempt++) {
    try {
      const response = await fetch(path, options);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return await response.json();
    } catch (error) {
      lastError = error;
      if (attempt < maxRetries - 1) {
        await new Promise(r => setTimeout(r, 1000)); // Retry after 1s
      }
    }
  }
  
  throw lastError;
}

// Usage
async function loadProfile(userId) {
  try {
    const profile = await fetchWithRetry(`/api/v1/users/${userId}`);
    renderProfile(profile);
  } catch (error) {
    // Check if offline
    if (!navigator.onLine) {
      showError('You are offline. Some data may be unavailable.');
      // Try to load from cache
      const cached = getCachedUser(userId);
      if (cached) renderProfile(cached);
    } else {
      showError('Failed to load profile. Please try again.');
    }
  }
}
```

---

## 6. Design System Integration (Phase 9 Components)

### Using Phase 9 Component Library

**Available Components for Phase 10:**

From the Phase 9 summary, these components are ready:

1. **Cards** (`.card`, `.card-header`, `.card-body`, `.card-footer`)
   - Perfect for: profile section, stat cards, review items

2. **Buttons** (`.btn`, `.btn-primary`, `.btn-secondary`, `.btn-danger`)
   - Edit button, Save button, Cancel button, Load More

3. **Forms** (`.form-group`, `.form-control`, `.is-invalid`, `.error-text`)
   - Profile edit fields, validation display

4. **Modals** (`.modal`, `.modal-backdrop`, `.modal-content`, `.modal-header`, `.modal-footer`)
   - Profile edit modal

5. **Spinners** (`.spinner`, `.spinner-sm`, `.spinner-lg`)
   - Loading state for avatar, reviews

6. **Utility Classes:**
   - `.d-grid` for stat cards layout
   - `.text-center` for rating display
   - `.mt-*`, `.mb-*`, `.p-*` for spacing
   - `.text-sm`, `.text-muted` for review metadata

### Component Composition Patterns

**Stat Cards Grid (using Phase 9 grid system):**

```html
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
  <div class="card text-center">
    <div class="card-body">
      <div class="stat-value">12</div>
      <div class="stat-label">Active Listings</div>
    </div>
  </div>
  
  <div class="card text-center">
    <div class="card-body">
      <div class="stat-value">45</div>
      <div class="stat-label">Completed Sales</div>
    </div>
  </div>
  
  <div class="card text-center">
    <div class="card-body">
      <div class="stat-value">4.8</div>
      <div class="stat-label">Average Rating</div>
    </div>
  </div>
</div>
```

**Review Item Card:**

```html
<div class="review-item card mb-16">
  <div class="card-body">
    <div class="d-flex gap-8 mb-8">
      <img src="avatar.jpg" alt="" class="avatar-sm rounded">
      <div>
        <div class="font-bold">Reviewer Name</div>
        <div class="text-sm text-muted">2026-05-10</div>
      </div>
      <div class="ml-auto">⭐⭐⭐⭐⭐</div>
    </div>
    <p class="mb-8">Review text content goes here...</p>
    <div class="text-sm text-muted">Item: Used iPhone 12</div>
  </div>
</div>
```

**CSS Additions Needed for Phase 10:**

While Phase 9 provides the foundation, Phase 10 will add:

```css
/* Avatar overrides for different contexts */
.avatar-profile { width: 200px; height: 200px; }

/* Rating display */
.rating-summary {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 18px;
  font-weight: bold;
}

/* Stat cards */
.stat-value { font-size: 32px; font-weight: bold; color: var(--color-primary); }
.stat-label { font-size: 12px; color: var(--color-text-secondary); margin-top: 8px; }

/* Review list */
.review-item { border-left: 3px solid var(--color-primary); }

/* Sort dropdown styling */
.reviews-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
```

---

## Key Takeaways for Planning Phase 10

### What You Need to Know

1. **Profile Views Are Dual-Mode**
   - Same page, different rendering based on `userId` parameter
   - Own profile shows editable form, other profiles show read-only stats
   - Use modal for editing to avoid page navigation

2. **Avatar Handling Is Multi-Faceted**
   - Upload as FormData, server returns multiple sizes
   - Display appropriate size per context (200px for profile, 48px for lists)
   - Fallback to initials with color based on user ID

3. **Reviews Are Paginated & Sortable**
   - Fetch first 10, provide "Load More" button
   - Client-side sorting (newest/highest-rated/oldest) is fast
   - Show rating breakdown for trust signals

4. **Validation Happens Twice**
   - Client-side real-time feedback (field-level on blur, form-level on submit)
   - Server-side business rules and constraints
   - Display server errors inline, not as alerts

5. **Performance Matters for Reputation**
   - Cache user profiles in localStorage (5-min TTL)
   - Use lazy loading for avatar images
   - Pagination prevents slow loads with many reviews

6. **Phase 9 Components Are Ready**
   - Cards for sections, buttons for actions, modals for editing
   - CSS grid for stat layout, utility classes for spacing
   - Form validation CSS already in place (.is-invalid, .error-text)

### Critical Implementation Decisions

- **Modal over Page:** Edit profile in a modal, not a new route (less context switching)
- **Initials Fallback:** Deterministic color based on user ID (consistent across app)
- **Pagination Required:** Don't load all reviews at once
- **Client + Server Validation:** Real-time feedback + server business rules
- **Multi-Size Avatars:** Backend handles resizing, frontend uses appropriate sizes

---

## RESEARCH COMPLETE

This research provides the foundation for creating a comprehensive Phase 10 plan. The research covers:

✅ Profile UI patterns (dual-view, layout, edit flow)
✅ Avatar handling (upload, multi-size, fallback)
✅ Review display patterns (list, sorting, ratings)
✅ Backend integration (endpoints, pagination, upload)
✅ Performance optimization (caching, lazy loading, pagination)
✅ Validation & error handling (client + server)
✅ Design system integration (Phase 9 components)

**Next Phase:** `/gsd-plan-phase 10` will create executable plans using this research + the context decisions from `/gsd-discuss-phase 10`.

---

*Research completed: 2026-05-11*
*Status: Ready for Planning Phase 10*

// Profile Page - Display user profiles with stats, avatar, and edit functionality

/**
 * Load user profile data from backend API
 * @param {number|string} userId - User ID to fetch profile for
 * @returns {Promise<Object>} User profile data
 */
async function loadUserProfile(userId) {
  // Check localStorage cache first (5-minute TTL)
  const cacheKey = `profile_${userId}`;
  const cached = localStorage.getItem(cacheKey);
  if (cached) {
    const cacheData = JSON.parse(cached);
    const cacheTime = new Date(cacheData.timestamp).getTime();
    const now = new Date().getTime();
    if (now - cacheTime < 5 * 60 * 1000) {
      return cacheData.data;
    }
  }

  try {
    const response = await app.apiCall('GET', `/api/v1/users/${userId}`);
    
    if (!response.ok) {
      if (response.status === 404) {
        throw new Error('User not found');
      } else if (response.status === 401) {
        app.navigate('login');
        throw new Error('Unauthorized');
      } else {
        throw new Error('Server error');
      }
    }

    const data = await response.json();
    
    // Cache the result
    localStorage.setItem(cacheKey, JSON.stringify({
      timestamp: new Date().toISOString(),
      data: data
    }));
    
    return data;
  } catch (error) {
    console.error('Error loading user profile:', error);
    throw error;
  }
}

/**
 * Render avatar image or initials fallback
 * @param {Object} user - User object with avatar_url and name
 * @returns {string} HTML for avatar element
 */
function renderAvatar(user) {
  // If avatar exists, use it
  if (user.avatar_url) {
    return `<img src="${user.avatar_url}" alt="${user.name}" class="avatar-image" />`;
  }

  // Otherwise, generate initials
  const names = user.name.split(' ');
  const initials = (names[0]?.[0] || '') + (names[1]?.[0] || '');
  
  // Generate deterministic color based on user ID
  const hue = (user.id * 137.508) % 360; // Golden angle for color distribution
  const color = `hsl(${hue}, 70%, 60%)`;

  return `<div class="avatar-initials" style="background-color: ${color}">${initials.toUpperCase()}</div>`;
}

/**
 * Format date to relative string (e.g., "Joined May 2026")
 * @param {string} dateString - ISO date string
 * @returns {string} Formatted date
 */
function formatDate(dateString) {
  const date = new Date(dateString);
  const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
  return `Joined ${months[date.getMonth()]} ${date.getFullYear()}`;
}

/**
 * Show profile page for a specific user or current user
 * @param {number|string} userId - User ID (optional, defaults to current user)
 */
async function showProfile(userId) {
  const appDiv = document.getElementById('app');
  
  // Determine which user to display
  const targetUserId = userId || app.user?.id;
  if (!targetUserId) {
    appDiv.innerHTML = '<div class="page"><p class="text-error">User not found</p></div>';
    return;
  }

  // Show loading state
  appDiv.innerHTML = '<div class="loading-container"><div class="spinner"></div><p>Loading profile...</p></div>';

  try {
    // Load profile data
    const user = await loadUserProfile(targetUserId);
    const isOwnProfile = app.user?.id === targetUserId;

    // Render profile page
    appDiv.innerHTML = `
      <div class="page">
        <div class="profile-header">
          <div class="avatar-container">
            ${renderAvatar(user)}
          </div>
          <div class="profile-info">
            <h1 class="profile-name">${user.name}</h1>
            ${user.bio ? `<p class="profile-bio">${user.bio}</p>` : ''}
            ${user.location ? `<p class="profile-location"><span class="location-icon">📍</span>${user.location}</p>` : ''}
          </div>
          <div class="profile-actions">
            ${isOwnProfile ? `<button class="btn btn-primary" id="editProfileBtn">Edit Profile</button>` : ''}
          </div>
        </div>

        <div class="profile-stats-grid">
          <div class="stat-card">
            <div class="stat-value">${user.listings_count || 0}</div>
            <div class="stat-label">Active Listings</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">${user.completed_sales || 0}</div>
            <div class="stat-label">Completed Sales</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">${user.avg_rating || 'N/A'}</div>
            <div class="stat-label">Average Rating</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">${formatDate(user.created_at)}</div>
            <div class="stat-label">Member Since</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">${user.response_time || 'Unknown'}</div>
            <div class="stat-label">Response Time</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">${user.review_count || 0}</div>
            <div class="stat-label">Total Reviews</div>
          </div>
        </div>

        <div class="profile-reviews">
          <h2>Reviews</h2>
          <p class="text-secondary">Reviews will be displayed here in future updates.</p>
        </div>
      </div>
    `;

    // Attach event listeners for own profile
    if (isOwnProfile) {
      const editBtn = document.getElementById('editProfileBtn');
      if (editBtn) {
        editBtn.addEventListener('click', () => {
          // Show edit modal (implemented in Plan 02)
          console.log('Edit profile clicked');
        });
      }
    }

  } catch (error) {
    console.error('Error displaying profile:', error);
    appDiv.innerHTML = `
      <div class="page">
        <h2>Error Loading Profile</h2>
        <p>${error.message}</p>
        <a href="#/" class="btn btn-primary mt-16">Go Home</a>
      </div>
    `;
  }
}

// Export for use in app.js
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { showProfile, loadUserProfile, renderAvatar };
}

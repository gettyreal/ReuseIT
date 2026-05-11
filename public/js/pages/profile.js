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

// ===== PROFILE EDITING MODAL =====

/**
 * Initialize profile edit modal with HTML and basic structure
 * Called once on page render
 */
function initializeEditModal() {
  const appDiv = document.getElementById('app');
  
  // Create modal HTML if it doesn't exist
  if (!document.getElementById('profileEditModal')) {
    const modalHTML = `
      <div id="profileEditModal" class="modal" style="display: none;">
        <div class="modal-backdrop" id="profileEditBackdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>Edit Profile</h2>
            <button class="modal-close" id="profileEditClose">×</button>
          </div>
          
          <div class="modal-body">
            <div id="profileEditAlert" class="alert alert-error" style="display: none;"></div>
            
            <form id="profileEditForm">
              <!-- Name Field -->
              <div class="form-group">
                <label for="profileNameInput">Name *</label>
                <input 
                  type="text" 
                  id="profileNameInput" 
                  name="name" 
                  class="form-control" 
                  required 
                  minlength="2" 
                  maxlength="100"
                  placeholder="Your full name"
                />
                <span id="profileNameError" class="error-text" style="display: none;"></span>
              </div>

              <!-- Bio Field -->
              <div class="form-group">
                <label for="profileBioInput">Bio</label>
                <textarea 
                  id="profileBioInput" 
                  name="bio" 
                  class="form-control" 
                  maxlength="500"
                  placeholder="Tell us about yourself"
                  rows="4"
                ></textarea>
                <span class="form-hint">Max 500 characters</span>
                <span id="profileBioError" class="error-text" style="display: none;"></span>
              </div>

              <!-- Location Field -->
              <div class="form-group">
                <label for="profileLocationInput">Location</label>
                <input 
                  type="text" 
                  id="profileLocationInput" 
                  name="location" 
                  class="form-control" 
                  maxlength="100"
                  placeholder="City, State or Region"
                />
                <span class="form-hint">Max 100 characters</span>
                <span id="profileLocationError" class="error-text" style="display: none;"></span>
              </div>

              <!-- Avatar Upload Section -->
              <div class="form-group avatar-upload-section">
                <div class="avatar-preview-container">
                  <img id="profileAvatarPreview" class="avatar-preview" src="" alt="Avatar preview" style="display: none;" />
                </div>
                <div class="avatar-upload-controls">
                  <label for="profileAvatarInput" class="file-input-label">
                    Choose Image
                  </label>
                  <input 
                    type="file" 
                    id="profileAvatarInput" 
                    name="avatar" 
                    accept="image/*"
                    style="display: none;"
                  />
                  <p class="form-hint">Max 5MB, JPG or PNG</p>
                  <span id="profileAvatarError" class="error-text" style="display: none;"></span>
                </div>
              </div>
            </form>
          </div>
          
          <div class="modal-footer">
            <button class="btn btn-secondary" id="profileEditCancel">Cancel</button>
            <button class="btn btn-primary" id="profileEditSave">Save Changes</button>
          </div>
        </div>
      </div>
    `;
    
    // Insert modal at end of app div
    appDiv.insertAdjacentHTML('afterend', modalHTML);
  }
}

/**
 * Show edit profile modal and populate form with current user data
 */
function showEditProfileModal() {
  const modal = document.getElementById('profileEditModal');
  if (!modal) {
    console.error('Modal not found');
    return;
  }

  // Populate form with current user data
  if (app.user) {
    document.getElementById('profileNameInput').value = app.user.name || '';
    document.getElementById('profileBioInput').value = app.user.bio || '';
    document.getElementById('profileLocationInput').value = app.user.location || '';
  }

  // Clear any previous error messages
  clearAllFormErrors();

  // Reset file input
  document.getElementById('profileAvatarInput').value = '';
  
  // Clear avatar preview
  const preview = document.getElementById('profileAvatarPreview');
  if (preview) {
    preview.style.display = 'none';
    preview.src = '';
  }

  // Hide alert
  const alert = document.getElementById('profileEditAlert');
  if (alert) {
    alert.style.display = 'none';
    alert.textContent = '';
  }

  // Show modal
  modal.style.display = 'block';
}

/**
 * Hide edit profile modal
 */
function hideEditProfileModal() {
  const modal = document.getElementById('profileEditModal');
  if (!modal) return;

  modal.style.display = 'none';
  
  // Clear form on close (privacy)
  document.getElementById('profileEditForm').reset();
  
  // Clear errors
  clearAllFormErrors();
  
  // Clear preview
  const preview = document.getElementById('profileAvatarPreview');
  if (preview) {
    preview.style.display = 'none';
    preview.src = '';
  }
}

/**
 * Clear all form errors
 */
function clearAllFormErrors() {
  document.getElementById('profileNameError').style.display = 'none';
  document.getElementById('profileBioError').style.display = 'none';
  document.getElementById('profileLocationError').style.display = 'none';
  document.getElementById('profileAvatarError').style.display = 'none';
  
  // Remove invalid classes
  ['profileNameInput', 'profileBioInput', 'profileLocationInput'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('is-invalid');
  });
}

/**
 * Attach event listeners to modal controls
 */
function attachModalListeners() {
  const modal = document.getElementById('profileEditModal');
  if (!modal) return;

  // Edit button opens modal
  const editBtn = document.getElementById('editProfileBtn');
  if (editBtn) {
    editBtn.addEventListener('click', showEditProfileModal);
  }

  // Modal backdrop click closes modal
  const backdrop = document.getElementById('profileEditBackdrop');
  if (backdrop) {
    backdrop.addEventListener('click', hideEditProfileModal);
  }

  // Cancel button closes modal
  const cancelBtn = document.getElementById('profileEditCancel');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', hideEditProfileModal);
  }

  // Close button closes modal
  const closeBtn = document.getElementById('profileEditClose');
  if (closeBtn) {
    closeBtn.addEventListener('click', hideEditProfileModal);
  }

  // Escape key closes modal
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      hideEditProfileModal();
    }
  });

  // Save button submits form
  const saveBtn = document.getElementById('profileEditSave');
  if (saveBtn) {
    saveBtn.addEventListener('click', saveProfile);
  }

  // Form field validation on blur
  const fields = ['profileNameInput', 'profileBioInput', 'profileLocationInput'];
  fields.forEach(fieldId => {
    const field = document.getElementById(fieldId);
    if (field) {
      field.addEventListener('blur', () => validateField(fieldId));
      // Clear error on user input
      field.addEventListener('input', () => {
        const errorSpan = document.getElementById(`${fieldId}Error`);
        if (errorSpan) {
          errorSpan.style.display = 'none';
          field.classList.remove('is-invalid');
        }
      });
    }
  });
}

/**
 * Validate a single form field
 * @param {string} fieldId - ID of the field to validate
 * @returns {boolean} True if field is valid
 */
function validateField(fieldId) {
  const field = document.getElementById(fieldId);
  const errorSpan = document.getElementById(`${fieldId}Error`);
  
  if (!field || !errorSpan) return true;

  let isValid = true;
  let errorMessage = '';

  // Validate based on field type
  if (fieldId === 'profileNameInput') {
    const value = field.value.trim();
    if (!value) {
      isValid = false;
      errorMessage = 'Name is required';
    } else if (value.length < 2) {
      isValid = false;
      errorMessage = 'Name must be at least 2 characters';
    } else if (value.length > 100) {
      isValid = false;
      errorMessage = 'Name must be less than 100 characters';
    }
  } else if (fieldId === 'profileBioInput') {
    const value = field.value;
    if (value.length > 500) {
      isValid = false;
      errorMessage = 'Bio must be less than 500 characters';
    }
  } else if (fieldId === 'profileLocationInput') {
    const value = field.value;
    if (value.length > 100) {
      isValid = false;
      errorMessage = 'Location must be less than 100 characters';
    }
  }

  // Update UI based on validation result
  if (isValid) {
    field.classList.remove('is-invalid');
    errorSpan.style.display = 'none';
    errorSpan.textContent = '';
  } else {
    field.classList.add('is-invalid');
    errorSpan.style.display = 'block';
    errorSpan.textContent = errorMessage;
  }

  return isValid;
}

/**
 * Validate entire form
 * @returns {boolean} True if all fields are valid
 */
function validateForm() {
  const fields = ['profileNameInput', 'profileBioInput', 'profileLocationInput'];
  let isValid = true;

  fields.forEach(fieldId => {
    if (!validateField(fieldId)) {
      isValid = false;
    }
  });

  // Update Save button state
  const saveBtn = document.getElementById('profileEditSave');
  if (saveBtn) {
    saveBtn.disabled = !isValid;
  }

  return isValid;
}

/**
 * Update Save button state based on form validity
 */
function updateSaveButtonState() {
  const saveBtn = document.getElementById('profileEditSave');
  if (!saveBtn) return;

  const isValid = validateForm();
  saveBtn.disabled = !isValid;
}

/**
 * Handle avatar file selection and preview
 */
function handleAvatarFileSelect() {
  const fileInput = document.getElementById('profileAvatarInput');
  const preview = document.getElementById('profileAvatarPreview');
  const errorSpan = document.getElementById('profileAvatarError');
  
  if (!fileInput || !preview || !errorSpan) return;

  fileInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    
    if (!file) {
      preview.style.display = 'none';
      preview.src = '';
      errorSpan.style.display = 'none';
      return;
    }

    // Validate file type (image only)
    if (!file.type.startsWith('image/')) {
      errorSpan.textContent = 'Please select an image file';
      errorSpan.style.display = 'block';
      preview.style.display = 'none';
      fileInput.value = '';
      return;
    }

    // Validate file size (5MB max)
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if (file.size > maxSize) {
      errorSpan.textContent = 'File must be less than 5MB';
      errorSpan.style.display = 'block';
      preview.style.display = 'none';
      fileInput.value = '';
      return;
    }

    // Read and display preview
    try {
      const reader = new FileReader();
      reader.onload = (event) => {
        preview.src = event.target.result;
        preview.style.display = 'block';
        errorSpan.style.display = 'none';
        errorSpan.textContent = '';
      };
      reader.readAsDataURL(file);
    } catch (error) {
      console.error('Error reading file:', error);
      errorSpan.textContent = 'Error loading image preview';
      errorSpan.style.display = 'block';
    }
  });

  // Allow clicking label to open file picker
  const label = document.querySelector('.file-input-label');
  if (label) {
    label.addEventListener('click', () => fileInput.click());
  }
}

/**
 * Save profile changes to backend
 */
async function saveProfile(e) {
  if (e) {
    e.preventDefault();
  }

  const saveBtn = document.getElementById('profileEditSave');
  const alert = document.getElementById('profileEditAlert');
  
  // Validate form first
  if (!validateForm()) {
    if (alert) {
      alert.textContent = 'Please fix validation errors';
      alert.style.display = 'block';
    }
    return;
  }

  // Disable save button and show loading state
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
  }

  try {
    // Collect form data
    const name = document.getElementById('profileNameInput').value;
    const bio = document.getElementById('profileBioInput').value;
    const location = document.getElementById('profileLocationInput').value;
    const avatarInput = document.getElementById('profileAvatarInput');
    const avatarFile = avatarInput?.files[0];

    // First, save text fields (name, bio, location)
    const textResponse = await app.apiCall('POST', '/api/v1/users/me', {
      name,
      bio,
      location
    });

    if (!textResponse.ok) {
      const errorData = await textResponse.json();
      
      // Handle field-specific errors
      if (errorData.errors) {
        Object.keys(errorData.errors).forEach(fieldName => {
          const fieldId = `profile${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)}Input`;
          const errorSpan = document.getElementById(`${fieldId}Error`);
          if (errorSpan) {
            errorSpan.textContent = errorData.errors[fieldName];
            errorSpan.style.display = 'block';
            const field = document.getElementById(fieldId);
            if (field) field.classList.add('is-invalid');
          }
        });
      } else {
        // Generic error
        if (alert) {
          alert.textContent = errorData.message || 'Error saving profile. Please try again.';
          alert.style.display = 'block';
        }
      }
      
      // Re-enable save button
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
      }
      return;
    }

    // If avatar was selected, upload it separately
    if (avatarFile) {
      const formData = new FormData();
      formData.append('avatar', avatarFile);

      const avatarResponse = await fetch('http://localhost:8000/api/v1/users/me/avatar', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${app.token}`
        },
        body: formData
      });

      if (!avatarResponse.ok) {
        const errorData = await avatarResponse.json();
        if (alert) {
          alert.textContent = errorData.message || 'Error uploading avatar. Profile fields were saved.';
          alert.style.display = 'block';
          alert.classList.remove('alert-error');
          alert.classList.add('alert-warning');
        }
        
        // Re-enable save button but allow continuing since text fields were saved
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save Changes';
        }
        return;
      }
    }

    // Success! Hide modal and reload profile
    if (alert) {
      alert.style.display = 'none';
    }
    
    hideEditProfileModal();
    
    // Clear cache and reload profile view
    localStorage.removeItem(`profile_${app.user.id}`);
    
    // Reload profile data
    const user = await loadUserProfile(app.user.id);
    app.user = { ...app.user, ...user }; // Update app.user with new data
    
    // Reload profile view
    showProfile();

  } catch (error) {
    console.error('Error saving profile:', error);
    if (alert) {
      alert.textContent = 'Network error. Please try again.';
      alert.style.display = 'block';
    }
    
    // Re-enable save button
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Changes';
    }
  }
}

/**
 * Update showProfile to include modal initialization
 */
// Store the original showProfile implementation and update it
const showProfileOriginal = showProfile;

async function showProfileWithModal(userId) {
  // Call original implementation
  await showProfileOriginal(userId);
  
  // Initialize modal after profile is rendered
  initializeEditModal();
  
  // Determine if this is own profile
  const targetUserId = userId || app.user?.id;
  const isOwnProfile = app.user?.id === targetUserId;
  
  // Only attach listeners if viewing own profile
  if (isOwnProfile) {
    attachModalListeners();
    handleAvatarFileSelect();
  }
}

// Replace the original function
showProfile = showProfileWithModal;

/**
 * reviews.js
 * 
 * Review submission and display functionality.
 * Provides modal interaction, API integration, and review history rendering.
 */

/**
 * Attach click listeners to star picker elements.
 * Handles hover and selection state visualization.
 * 
 * @param {string} modalId - ID of the modal container
 * @returns {void}
 */
function bindStarPicker(modalId = 'review-modal') {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    const starPicker = modal.querySelector('.star-picker');
    const stars = starPicker ? starPicker.querySelectorAll('.star') : [];
    const ratingInput = modal.querySelector('#star-rating');
    const ratingDisplay = modal.querySelector('#rating-display');
    const submitBtn = modal.querySelector('#review-submit');
    
    if (stars.length === 0) return;
    
    // Hover effect
    stars.forEach(star => {
        star.addEventListener('mouseenter', function() {
            const value = parseInt(this.dataset.value);
            highlightStars(starPicker, value);
        });
        
        // Click to select
        star.addEventListener('click', function(e) {
            e.preventDefault();
            const value = parseInt(this.dataset.value);
            selectRating(starPicker, value, ratingInput, ratingDisplay, submitBtn);
        });
    });
    
    // Mouse leave - show selected rating or empty
    starPicker.addEventListener('mouseleave', function() {
        const currentValue = parseInt(ratingInput.value) || 0;
        highlightStars(starPicker, currentValue);
    });
}

/**
 * Highlight stars up to the given value.
 * 
 * @param {Element} starPicker - Star picker container
 * @param {number} value - Number of stars to highlight (1-5)
 */
function highlightStars(starPicker, value) {
    const stars = starPicker.querySelectorAll('.star');
    stars.forEach((star, index) => {
        if (index < value) {
            star.textContent = '★';
            star.classList.add('filled');
        } else {
            star.textContent = '☆';
            star.classList.remove('filled');
        }
    });
}

/**
 * Set the selected rating and update UI.
 * 
 * @param {Element} starPicker - Star picker container
 * @param {number} value - Selected rating (1-5)
 * @param {Element} ratingInput - Hidden rating input
 * @param {Element} ratingDisplay - Display element for rating text
 * @param {Element} submitBtn - Submit button to enable
 */
function selectRating(starPicker, value, ratingInput, ratingDisplay, submitBtn) {
    ratingInput.value = value;
    highlightStars(starPicker, value);
    
    // Show rating text
    if (ratingDisplay) {
        ratingDisplay.textContent = `${value} star${value !== 1 ? 's' : ''} selected`;
        ratingDisplay.style.display = 'block';
    }
    
    // Enable submit button
    if (submitBtn) {
        submitBtn.disabled = false;
    }
}

/**
 * Submit review via POST /api/reviews.
 * 
 * @param {number} bookingId - Booking ID
 * @param {number} rating - Rating (1-5)
 * @param {string} comment - Optional comment
 * @returns {Promise<void>}
 */
async function submitReview(bookingId, rating, comment) {
    try {
        const response = await fetch('/api/reviews', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                booking_id: bookingId,
                rating: rating,
                comment: comment || null
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            // Success - close modal and show toast
            closeReviewModal();
            showToast('Review submitted successfully!', 'success');
            return;
        }
        
        // Handle error responses
        if (response.status === 429) {
            showReviewError('You can only submit one review per 24 hours');
        } else if (response.status === 403) {
            showReviewError('You are not eligible to review this transaction');
        } else if (response.status === 400) {
            const errorMsg = data.message || 'Invalid review data';
            showReviewError(errorMsg);
        } else {
            showReviewError('Failed to submit review. Please try again.');
        }
        
    } catch (error) {
        console.error('Review submission error:', error);
        showReviewError('Network error. Please check your connection and try again.');
    }
}

/**
 * Load review history for a user.
 * 
 * @param {number} userId - User ID
 * @param {number} page - Page number (0-indexed)
 * @param {number} limit - Reviews per page (default 10)
 * @returns {Promise<Array>} Array of review objects
 */
async function loadReviewHistory(userId, page = 0, limit = 10) {
    try {
        const offset = page * limit;
        const response = await fetch(`/api/reviews/user/${userId}?limit=${limit}&offset=${offset}`);
        
        if (!response.ok) {
            throw new Error('Failed to load reviews');
        }
        
        const data = await response.json();
        return data.data || [];
        
    } catch (error) {
        console.error('Review history load error:', error);
        return [];
    }
}

/**
 * Render review history in the DOM.
 * 
 * @param {Array} reviews - Array of review objects
 * @param {string} containerId - ID of container element
 * @returns {void}
 */
function renderReviewHistory(reviews, containerId = 'review-list-container') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (reviews.length === 0) {
        const emptyState = document.getElementById('review-empty-state');
        if (emptyState) {
            emptyState.style.display = 'block';
        }
        return;
    }
    
    const template = document.getElementById('review-item-template');
    if (!template) return;
    
    reviews.forEach(review => {
        const clone = template.content.cloneNode(true);
        
        // Populate reviewer info
        const avatar = clone.querySelector('.review-reviewer-avatar');
        if (avatar && review.reviewer && review.reviewer.avatar_url) {
            avatar.src = review.reviewer.avatar_url;
            avatar.alt = review.reviewer.name || 'Reviewer';
        }
        
        const nameEl = clone.querySelector('.review-reviewer-name');
        if (nameEl && review.reviewer) {
            nameEl.textContent = review.reviewer.name || 'Anonymous';
        }
        
        // Populate rating stars
        const starsEl = clone.querySelector('.review-rating-stars');
        if (starsEl) {
            starsEl.innerHTML = renderStars(review.rating);
        }
        
        // Populate date
        const dateEl = clone.querySelector('.review-date');
        if (dateEl && review.created_at) {
            dateEl.textContent = formatDate(review.created_at);
        }
        
        // Populate comment
        const previewEl = clone.querySelector('.review-comment-preview');
        const fullEl = clone.querySelector('.review-comment-full');
        const expandBtn = clone.querySelector('.review-expand-btn');
        
        if (review.comment) {
            if (previewEl) {
                previewEl.textContent = review.comment_preview || review.comment;
            }
            if (fullEl) {
                fullEl.textContent = review.comment;
            }
            
            // Show expand button if comment is truncated
            if (review.comment_truncated && expandBtn) {
                expandBtn.style.display = 'block';
                expandBtn.addEventListener('click', function() {
                    toggleCommentExpand(this);
                });
            }
        } else {
            if (previewEl) {
                previewEl.textContent = '(No comment)';
                previewEl.style.fontStyle = 'italic';
                previewEl.style.color = '#999';
            }
        }
        
        container.appendChild(clone);
    });
}

/**
 * Toggle comment expand/collapse.
 * 
 * @param {Element} button - Expand button element
 * @returns {void}
 */
function toggleCommentExpand(button) {
    const reviewItem = button.closest('.review-item');
    if (!reviewItem) return;
    
    const previewEl = reviewItem.querySelector('.review-comment-preview');
    const fullEl = reviewItem.querySelector('.review-comment-full');
    
    if (fullEl.style.display === 'none') {
        previewEl.style.display = 'none';
        fullEl.style.display = 'block';
        button.textContent = 'Show less';
    } else {
        previewEl.style.display = 'block';
        fullEl.style.display = 'none';
        button.textContent = 'Show more';
    }
}

/**
 * Initialize review modal and attach event listeners.
 * 
 * @param {string} modalId - ID of modal element
 * @returns {void}
 */
function initializeReviewModal(modalId = 'review-modal') {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    const form = modal.querySelector('#review-form');
    const closeBtn = modal.querySelector('.review-modal-close');
    const cancelBtn = modal.querySelector('#review-cancel');
    const textarea = modal.querySelector('#review-comment');
    const charCount = modal.querySelector('#char-count');
    
    // Bind star picker
    bindStarPicker(modalId);
    
    // Close modal handlers
    if (closeBtn) {
        closeBtn.addEventListener('click', closeReviewModal);
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeReviewModal);
    }
    
    // Close on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeReviewModal();
        }
    });
    
    // Character counter
    if (textarea && charCount) {
        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }
    
    // Form submission
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const bookingId = parseInt(modal.querySelector('#booking-id').value);
            const rating = parseInt(modal.querySelector('#star-rating').value);
            const comment = (modal.querySelector('#review-comment').value || '').trim();
            
            if (!bookingId || !rating) {
                showReviewError('Please select a rating');
                return;
            }
            
            // Disable submit button while processing
            const submitBtn = modal.querySelector('#review-submit');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            
            await submitReview(bookingId, rating, comment);
            
            // Re-enable button
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        });
    }
}

/**
 * Show review modal with booking ID.
 * 
 * @param {number} bookingId - Booking ID for review
 * @param {string} modalId - ID of modal element
 * @returns {void}
 */
function showReviewModal(bookingId, modalId = 'review-modal') {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    // Reset form
    modal.querySelector('#review-form').reset();
    modal.querySelector('#booking-id').value = bookingId;
    modal.querySelector('#star-rating').value = '';
    modal.querySelector('#review-comment').value = '';
    modal.querySelector('#char-count').textContent = '0';
    modal.querySelector('#rating-display').style.display = 'none';
    modal.querySelector('#review-error').style.display = 'none';
    modal.querySelector('#review-submit').disabled = true;
    
    // Clear star highlighting
    const starPicker = modal.querySelector('.star-picker');
    if (starPicker) {
        highlightStars(starPicker, 0);
    }
    
    modal.style.display = 'flex';
}

/**
 * Close review modal.
 * 
 * @param {string} modalId - ID of modal element
 * @returns {void}
 */
function closeReviewModal(modalId = 'review-modal') {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Show error message in review modal.
 * 
 * @param {string} message - Error message
 * @param {string} modalId - ID of modal element
 * @returns {void}
 */
function showReviewError(message, modalId = 'review-modal') {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    const errorEl = modal.querySelector('#review-error');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }
}

/**
 * Render star rating visualization.
 * 
 * @param {number} rating - Rating value (1-5)
 * @returns {string} HTML string of stars
 */
function renderStars(rating) {
    const filled = Math.round(rating);
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += i <= filled ? '★' : '☆';
    }
    return html;
}

/**
 * Format date string for display.
 * 
 * @param {string} dateStr - ISO date string
 * @returns {string} Formatted date
 */
function formatDate(dateStr) {
    try {
        const date = new Date(dateStr);
        const now = new Date();
        const diffTime = now - date;
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 0) {
            return 'Today';
        } else if (diffDays === 1) {
            return 'Yesterday';
        } else if (diffDays < 7) {
            return `${diffDays} days ago`;
        } else if (diffDays < 30) {
            return `${Math.floor(diffDays / 7)} weeks ago`;
        } else {
            return date.toLocaleDateString();
        }
    } catch (error) {
        return dateStr;
    }
}

/**
 * Show toast notification.
 * 
 * @param {string} message - Message to display
 * @param {string} type - Toast type (success, error, info)
 * @returns {void}
 */
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    toastContainer.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

/**
 * Create toast container if it doesn't exist.
 * 
 * @returns {Element} Toast container element
 */
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// Auto-initialize on page load if user is logged in
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is authenticated by looking for auth-related UI
    // Initialize review modal if page requires it
    initializeReviewModal();
});

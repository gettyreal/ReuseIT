// Client-side SPA Router and App Initialization
// Manages navigation, authentication state, and page rendering

const app = {
  // State management
  currentPage: null,
  token: localStorage.getItem('token'),
  user: null,
  
  /**
    * Initialize the app
    * - Check authentication status
    * - Validate token with backend
    * - Set up router listeners
    * - Navigate to initial page
    */
  async init() {
    console.log('App initializing...');
    
    // Read token from localStorage
    this.token = localStorage.getItem('token');
    
    // If user is logged in, validate token with backend
    if (this.token) {
      try {
        const response = await this.apiCall('GET', '/api/auth/validate');
        
        if (response.ok) {
          // Token is valid, load user data
          const data = await response.json();
          this.user = data.user;
          console.log('Token validated, user logged in:', this.user.email);
        } else {
          // Token is invalid or expired
          console.warn('Token validation failed:', response.status);
          this.logout(); // Force logout
          return;
        }
      } catch (e) {
        console.error('Token validation error:', e);
        // On network error, trust the token (user might be offline)
        // Only logout if we get a clear 401/403 response
      }
    } else {
      console.log('No token found, user is not logged in');
    }
    
    // Update header based on auth state
    this.updateHeader();
    
    // Set up hash-based router
    this.setupRouter();
    
    // Navigate to initial page based on hash
    const path = window.location.hash.slice(2) || '/';
    this.navigate(path);
  },
  
  /**
   * Set up hash change listener for client-side routing
   */
  setupRouter() {
    window.addEventListener('hashchange', () => {
      const path = window.location.hash.slice(2) || '/';
      this.navigate(path);
    });
    
    // Update active sidebar menu item on navigation
    document.addEventListener('hashchange', () => {
      this.updateActiveSidebarItem();
    });
  },
  
  /**
    * Navigate to a page
    * - Check if route is protected
    * - Redirect to login if not authenticated
    * - Load page content
    */
  navigate(path) {
    // Define which routes require authentication
    const protectedRoutes = [
      'profile',
      'edit-profile',
      'my-listings',
      'create-listing',
      'edit-listing',
      'bookings',
      'chat',
      'favorites',
      'reviews'
    ];
    
    // Check if this route requires authentication
    const isProtected = protectedRoutes.some(route => path.startsWith(route));
    
    // If route is protected and user is not logged in, redirect to login
    if (isProtected && !this.token) {
      console.warn('Attempted to access protected route without auth:', path);
      window.location.hash = '#/login';
      return;
    }
    
    console.log('Navigating to:', path);
    this.currentPage = path;
    this.loadPage(path);
  },
  
  /**
   * Load and render page content
   * Shows spinner while loading, renders appropriate page
   */
  async loadPage(path) {
    const appDiv = document.getElementById('app');
    
    // Show loading spinner
    appDiv.innerHTML = '<div class="loading-container"><div class="spinner"></div><p>Loading...</p></div>';
    
    try {
      // Route handling
      if (path === '/' || path === '') {
        this.showHome();
      } else if (path === 'login') {
        this.showLogin();
      } else if (path === 'register') {
        this.showRegister();
      } else if (path === 'reset-password') {
        this.showResetPassword();
      } else if (path.startsWith('reset-password-confirm')) {
        const token = new URLSearchParams(window.location.search).get('token');
        this.showResetPasswordConfirm(token);
      } else if (path === 'profile') {
        this.showProfile();
      } else if (path === 'favorites') {
        this.showFavorites();
      } else if (path === 'my-listings') {
        this.showMyListings();
      } else if (path === 'bookings') {
        this.showBookings();
      } else if (path === 'chat') {
        this.showChat();
      } else if (path === 'map') {
        this.showMap();
      } else if (path === 'logout') {
        this.handleLogout();
      } else {
        appDiv.innerHTML = `
          <div class="page">
            <h2>Page Not Found</h2>
            <p>The page you're looking for doesn't exist.</p>
            <a href="#/" class="btn btn-primary mt-16">Go Home</a>
          </div>
        `;
      }
      
      // Update active sidebar item
      this.updateActiveSidebarItem();
    } catch (e) {
      console.error('Error loading page:', e);
      appDiv.innerHTML = '<div class="page"><p class="text-error">Error loading page</p></div>';
    }
  },
  
  /**
   * Update active state on sidebar menu items based on current page
   */
  updateActiveSidebarItem() {
    // Remove active class from all menu items
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
      link.classList.remove('active');
    });
    
    // Add active class to current page link
    if (this.currentPage) {
      const activeLink = document.querySelector(`.sidebar-menu a[href="#/${this.currentPage}"]`);
      if (activeLink) {
        activeLink.classList.add('active');
      }
    }
    
    // Also check header nav links
    document.querySelectorAll('.header-nav a').forEach(link => {
      link.classList.remove('active');
    });
    
    const headerActiveLink = document.querySelector(`.header-nav a[href="#/${this.currentPage}"]`);
    if (headerActiveLink) {
      headerActiveLink.classList.add('active');
    }
  },
  
  // ===== PAGE RENDERERS (Placeholder implementations) =====
  
  showHome() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page">
        <div class="page-header">
          <h1 class="page-title">Welcome to ReuseIT</h1>
          <p class="page-subtitle">Buy and sell quality electronics peer-to-peer</p>
        </div>
        <div class="card">
          <p>Featured listings will appear here in Phase 10.</p>
        </div>
      </div>
    `;
  },
  
  showLogin() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page" style="max-width: 400px;">
        <div class="page-header">
          <h1 class="page-title">Sign In</h1>
          <p class="page-subtitle">Log in to your ReuseIT account</p>
        </div>
        <div class="card">
          <p>Login form will be implemented in Plan 09-02.</p>
          <a href="#/register" class="btn btn-primary mt-16">Create Account</a>
        </div>
      </div>
    `;
  },
  
  showRegister() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page" style="max-width: 400px;">
        <div class="page-header">
          <h1 class="page-title">Create Account</h1>
          <p class="page-subtitle">Join ReuseIT today</p>
        </div>
        <div class="card">
          <p>Registration form will be implemented in Plan 09-02.</p>
          <a href="#/login" class="btn btn-secondary mt-16">Sign In</a>
        </div>
      </div>
    `;
  },
  
  showProfile() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page">
        <div class="page-header">
          <h1 class="page-title">Profile</h1>
          <p class="page-subtitle">View and edit your profile</p>
        </div>
        <div class="card">
          <p>Profile page will be implemented in Phase 10.</p>
        </div>
      </div>
    `;
  },
  
  showFavorites() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page">
        <div class="page-header">
          <h1 class="page-title">Favorites</h1>
          <p class="page-subtitle">Your saved listings</p>
        </div>
        <div class="card">
          <p>Favorites list will be implemented in Phase 10.</p>
        </div>
      </div>
    `;
  },
  
  showMyListings() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page">
        <div class="page-header">
          <h1 class="page-title">My Listings</h1>
          <p class="page-subtitle">Manage your items for sale</p>
        </div>
        <div class="card">
          <p>Listings management will be implemented in Phase 13.</p>
        </div>
      </div>
    `;
  },
  
  showBookings() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page">
        <div class="page-header">
          <h1 class="page-title">My Bookings</h1>
          <p class="page-subtitle">Track your bookings and transactions</p>
        </div>
        <div class="card">
          <p>Booking management will be implemented in Phase 14.</p>
        </div>
      </div>
    `;
  },
  
  showChat() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page">
        <div class="page-header">
          <h1 class="page-title">Messages</h1>
          <p class="page-subtitle">Communicate with buyers and sellers</p>
        </div>
        <div class="card">
          <p>Chat interface will be implemented in Phase 15.</p>
        </div>
      </div>
    `;
  },
  
  showMap() {
    const appDiv = document.getElementById('app');
    appDiv.innerHTML = `
      <div class="page">
        <div class="page-header">
          <h1 class="page-title">Map View</h1>
          <p class="page-subtitle">Browse listings by location</p>
        </div>
        <div class="card">
          <p>Map interface will be implemented in Phase 12.</p>
        </div>
      </div>
    `;
  },
  
  // ===== API HELPERS =====
  
  /**
   * Make authenticated API call to backend
   * Includes Authorization header if token exists
   */
  async apiCall(method, endpoint, body = null) {
    const headers = { 'Content-Type': 'application/json' };
    
    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }
    
    const options = { method, headers };
    if (body) {
      options.body = JSON.stringify(body);
    }
    
    try {
      return await fetch(`http://localhost:8000${endpoint}`, options);
    } catch (e) {
      console.error('API call failed:', e);
      throw e;
    }
  },
  
  // ===== AUTHENTICATION =====

  /**
    * Clear local authentication state
    * Remove token, user data, and redirect to login
    */
  logout() {
    // Clear all localStorage auth data
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    
    // Clear app state
    this.token = null;
    this.user = null;
    this.currentPage = null;
    
    // Update header to show logged-out navigation
    this.updateHeader();
    
    // Redirect to login
    window.location.hash = '#/login';
    
    console.log('Logged out successfully');
  },

  /**
    * Handle logout action (API call + local state cleanup)
    * Called when user navigates to #/logout
    */
  async handleLogout() {
    try {
      // Call backend logout endpoint to clear server-side session
      const response = await this.apiCall('POST', '/api/auth/logout');
      
      if (!response.ok) {
        console.warn('Server logout failed, clearing client-side session anyway');
      }
    } catch (e) {
      console.error('Error calling logout endpoint:', e);
      // Continue with client-side logout even if API call fails
    }
    
    // Clear client-side session
    this.logout();
  },

  /**
    * Update header navigation based on authentication state
    * Shows authenticated nav when logged in, guest nav when logged out
    */
  updateHeader() {
    const headerNav = document.querySelector('.header-nav');
    
    if (this.token && this.user) {
      // User is logged in, show authenticated nav
      headerNav.innerHTML = `
        <a href="#/">Home</a>
        <a href="#/profile">Profile</a>
        <a href="#/my-listings">My Listings</a>
        <a href="#/chat">Messages</a>
        <a href="#/favorites">Favorites</a>
        <a href="#/logout">Logout</a>
      `;
    } else {
      // User is not logged in, show guest nav
      headerNav.innerHTML = `
        <a href="#/">Home</a>
        <a href="#/login">Login</a>
        <a href="#/register">Register</a>
      `;
    }
  }
};

// ===== APP START =====

// Initialize app when DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
  app.init();
});

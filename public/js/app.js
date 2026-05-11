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
   * - Set up router listeners
   * - Navigate to initial page
   */
  async init() {
    console.log('App initialized');
    
    // Check if user is logged in by verifying token
    if (this.token) {
      try {
        const response = await fetch('http://localhost:8000/api/auth/validate', {
          headers: { 'Authorization': `Bearer ${this.token}` }
        });
        
        if (!response.ok) {
          console.warn('Token validation failed, logging out');
          this.logout();
          return;
        }
        
        this.user = await response.json();
        console.log('User authenticated:', this.user);
      } catch (e) {
        console.error('Token validation failed:', e);
        this.logout();
      }
    }
    
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
    // Define protected routes (require authentication)
    const protectedRoutes = ['profile', 'my-listings', 'bookings', 'chat', 'favorites'];
    
    // Check if trying to access protected route without token
    if (protectedRoutes.some(r => path.startsWith(r)) && !this.token) {
      console.log('Protected route accessed without token, redirecting to login');
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
   * Remove token and redirect to login
   */
  logout() {
    localStorage.removeItem('token');
    this.token = null;
    this.user = null;
    console.log('Logged out');
    window.location.hash = '#/login';
  },
  
  /**
   * Handle logout action (API call + local state cleanup)
   */
  async handleLogout() {
    try {
      // Notify backend of logout
      await this.apiCall('POST', '/api/auth/logout');
    } catch (e) {
      console.warn('Logout API call failed (user may already be logged out):', e);
    }
    
    // Clear local state regardless of API result
    this.logout();
  }
};

// ===== APP START =====

// Initialize app when DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
  app.init();
});

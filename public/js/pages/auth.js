/**
 * Authentication Pages Module
 * Implements registration, login, and password reset flows
 */

/**
 * Validate email format
 * @param {string} email
 * @returns {boolean}
 */
const validateEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

/**
 * Validate password strength
 * @param {string} password
 * @returns {boolean}
 */
const validatePassword = (password) => password.length >= 8;

/**
 * Helper to get query parameter from URL
 * @param {string} param
 * @returns {string|null}
 */
const getQueryParam = (param) => {
  const params = new URLSearchParams(window.location.search);
  return params.get(param);
};

/**
 * Show registration page with form and validation
 */
app.showRegister = function() {
  const appDiv = document.getElementById('app');
  appDiv.innerHTML = `
    <div class="card" style="max-width: 400px; margin: 0 auto; margin-top: 40px;">
      <h2>Create Account</h2>
      <form id="registerForm">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
          <span class="error-message" id="emailError"></span>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
          <span class="error-message" id="passwordError"></span>
        </div>
        <div class="form-group">
          <label for="confirmPassword">Confirm Password</label>
          <input type="password" id="confirmPassword" name="confirmPassword" required>
          <span class="error-message" id="confirmPasswordError"></span>
        </div>
        <div class="form-group">
          <label><input type="checkbox" name="terms" required> I agree to Terms of Service</label>
          <span class="error-message" id="termsError"></span>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
        <p style="margin-top: 16px; text-align: center;">
          Already have an account? <a href="#/login">Login</a>
        </p>
      </form>
      <div id="registerMessage" class="alert alert-error d-none"></div>
    </div>
  `;

  // Get form elements
  const form = document.getElementById('registerForm');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const confirmPasswordInput = document.getElementById('confirmPassword');
  const termsInput = document.querySelector('input[name="terms"]');

  // Error message elements
  const emailError = document.getElementById('emailError');
  const passwordError = document.getElementById('passwordError');
  const confirmPasswordError = document.getElementById('confirmPasswordError');
  const termsError = document.getElementById('termsError');
  const messageDiv = document.getElementById('registerMessage');

  // Validation handler
  const validateForm = () => {
    let isValid = true;

    // Validate email
    if (!emailInput.value.trim()) {
      emailError.textContent = 'Email is required';
      emailError.style.display = 'block';
      isValid = false;
    } else if (!validateEmail(emailInput.value)) {
      emailError.textContent = 'Please enter a valid email address';
      emailError.style.display = 'block';
      isValid = false;
    } else {
      emailError.textContent = '';
      emailError.style.display = 'none';
    }

    // Validate password
    if (!passwordInput.value) {
      passwordError.textContent = 'Password is required';
      passwordError.style.display = 'block';
      isValid = false;
    } else if (!validatePassword(passwordInput.value)) {
      passwordError.textContent = 'Password must be at least 8 characters';
      passwordError.style.display = 'block';
      isValid = false;
    } else {
      passwordError.textContent = '';
      passwordError.style.display = 'none';
    }

    // Validate confirm password
    if (!confirmPasswordInput.value) {
      confirmPasswordError.textContent = 'Please confirm your password';
      confirmPasswordError.style.display = 'block';
      isValid = false;
    } else if (passwordInput.value !== confirmPasswordInput.value) {
      confirmPasswordError.textContent = 'Passwords do not match';
      confirmPasswordError.style.display = 'block';
      isValid = false;
    } else {
      confirmPasswordError.textContent = '';
      confirmPasswordError.style.display = 'none';
    }

    // Validate terms
    if (!termsInput.checked) {
      termsError.textContent = 'You must agree to Terms of Service';
      termsError.style.display = 'block';
      isValid = false;
    } else {
      termsError.textContent = '';
      termsError.style.display = 'none';
    }

    return isValid;
  };

  // Real-time validation
  [emailInput, passwordInput, confirmPasswordInput, termsInput].forEach(el => {
    el.addEventListener('change', validateForm);
    el.addEventListener('blur', validateForm);
  });

  // Form submission handler
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.classList.add('loading');

    try {
      const response = await app.apiCall('POST', '/api/auth/register', {
        email: emailInput.value,
        password: passwordInput.value
      });

      if (response.ok) {
        const data = await response.json();
        // Store token
        localStorage.setItem('token', data.token);
        app.token = data.token;
        app.user = data.user;
        // Redirect to home
        window.location.hash = '#/';
      } else {
        const errorData = await response.json();
        messageDiv.textContent = errorData.message || 'Registration failed. Please try again.';
        messageDiv.classList.remove('d-none');
        messageDiv.classList.add('alert-error');
      }
    } catch (error) {
      console.error('Registration error:', error);
      messageDiv.textContent = 'An error occurred. Please try again.';
      messageDiv.classList.remove('d-none');
      messageDiv.classList.add('alert-error');
    } finally {
      submitButton.disabled = false;
      submitButton.classList.remove('loading');
    }
  });
};

/**
 * Show login page with session persistence
 */
app.showLogin = function() {
  const appDiv = document.getElementById('app');
  appDiv.innerHTML = `
    <div class="card" style="max-width: 400px; margin: 0 auto; margin-top: 40px;">
      <h2>Login</h2>
      <form id="loginForm">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
          <span class="error-message" id="emailError"></span>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
          <span class="error-message" id="passwordError"></span>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        <p style="margin-top: 16px; text-align: center;">
          Don't have an account? <a href="#/register">Register</a><br>
          <a href="#/reset-password">Forgot password?</a>
        </p>
      </form>
      <div id="loginMessage" class="alert alert-error d-none"></div>
    </div>
  `;

  // Get form elements
  const form = document.getElementById('loginForm');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const emailError = document.getElementById('emailError');
  const passwordError = document.getElementById('passwordError');
  const messageDiv = document.getElementById('loginMessage');

  // Validation handler
  const validateForm = () => {
    let isValid = true;

    // Validate email
    if (!emailInput.value.trim()) {
      emailError.textContent = 'Email is required';
      emailError.style.display = 'block';
      isValid = false;
    } else if (!validateEmail(emailInput.value)) {
      emailError.textContent = 'Please enter a valid email address';
      emailError.style.display = 'block';
      isValid = false;
    } else {
      emailError.textContent = '';
      emailError.style.display = 'none';
    }

    // Validate password
    if (!passwordInput.value) {
      passwordError.textContent = 'Password is required';
      passwordError.style.display = 'block';
      isValid = false;
    } else {
      passwordError.textContent = '';
      passwordError.style.display = 'none';
    }

    return isValid;
  };

  // Real-time validation
  [emailInput, passwordInput].forEach(el => {
    el.addEventListener('change', validateForm);
    el.addEventListener('blur', validateForm);
  });

  // Form submission handler
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.classList.add('loading');

    try {
      const response = await app.apiCall('POST', '/api/auth/login', {
        email: emailInput.value,
        password: passwordInput.value
      });

      if (response.ok) {
        const data = await response.json();
        // Store token and user
        localStorage.setItem('token', data.token);
        app.token = data.token;
        app.user = data.user;
        // Redirect to home
        window.location.hash = '#/';
      } else {
        const errorData = await response.json();
        messageDiv.textContent = errorData.message || 'Login failed. Invalid credentials.';
        messageDiv.classList.remove('d-none');
        messageDiv.classList.add('alert-error');
      }
    } catch (error) {
      console.error('Login error:', error);
      messageDiv.textContent = 'An error occurred. Please try again.';
      messageDiv.classList.remove('d-none');
      messageDiv.classList.add('alert-error');
    } finally {
      submitButton.disabled = false;
      submitButton.classList.remove('loading');
    }
  });
};

/**
 * Show password reset request form
 */
app.showResetPassword = function() {
  const appDiv = document.getElementById('app');
  appDiv.innerHTML = `
    <div class="card" style="max-width: 400px; margin: 0 auto; margin-top: 40px;">
      <h2>Reset Password</h2>
      <p>Enter your email to receive a password reset link.</p>
      <form id="resetForm">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
          <span class="error-message" id="emailError"></span>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Send Reset Link</button>
        <p style="margin-top: 16px; text-align: center;">
          <a href="#/login">Back to Login</a>
        </p>
      </form>
      <div id="resetMessage" class="alert alert-success d-none"></div>
      <div id="resetError" class="alert alert-error d-none"></div>
    </div>
  `;

  // Get form elements
  const form = document.getElementById('resetForm');
  const emailInput = document.getElementById('email');
  const emailError = document.getElementById('emailError');
  const successDiv = document.getElementById('resetMessage');
  const errorDiv = document.getElementById('resetError');

  // Validation handler
  const validateForm = () => {
    if (!emailInput.value.trim()) {
      emailError.textContent = 'Email is required';
      emailError.style.display = 'block';
      return false;
    } else if (!validateEmail(emailInput.value)) {
      emailError.textContent = 'Please enter a valid email address';
      emailError.style.display = 'block';
      return false;
    } else {
      emailError.textContent = '';
      emailError.style.display = 'none';
      return true;
    }
  };

  // Real-time validation
  emailInput.addEventListener('change', validateForm);
  emailInput.addEventListener('blur', validateForm);

  // Form submission handler
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.classList.add('loading');

    try {
      const response = await app.apiCall('POST', '/api/auth/reset-password', {
        email: emailInput.value
      });

      if (response.ok) {
        successDiv.textContent = 'Reset link sent to your email. Check your inbox.';
        successDiv.classList.remove('d-none');
        errorDiv.classList.add('d-none');
        form.style.display = 'none';
      } else {
        const errorData = await response.json();
        errorDiv.textContent = errorData.message || 'Failed to send reset link.';
        errorDiv.classList.remove('d-none');
        successDiv.classList.add('d-none');
      }
    } catch (error) {
      console.error('Reset password error:', error);
      errorDiv.textContent = 'An error occurred. Please try again.';
      errorDiv.classList.remove('d-none');
      successDiv.classList.add('d-none');
    } finally {
      submitButton.disabled = false;
      submitButton.classList.remove('loading');
    }
  });
};

/**
 * Show password reset confirmation form
 * @param {string} token - Reset token from URL
 */
app.showResetPasswordConfirm = function(token) {
  const appDiv = document.getElementById('app');
  appDiv.innerHTML = `
    <div class="card" style="max-width: 400px; margin: 0 auto; margin-top: 40px;">
      <h2>Set New Password</h2>
      <form id="resetConfirmForm">
        <div class="form-group">
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" required>
          <span class="error-message" id="passwordError"></span>
        </div>
        <div class="form-group">
          <label for="confirmPassword">Confirm Password</label>
          <input type="password" id="confirmPassword" name="confirmPassword" required>
          <span class="error-message" id="confirmPasswordError"></span>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Reset Password</button>
      </form>
      <div id="resetConfirmMessage" class="alert alert-success d-none"></div>
      <div id="resetConfirmError" class="alert alert-error d-none"></div>
    </div>
  `;

  // Get form elements
  const form = document.getElementById('resetConfirmForm');
  const passwordInput = document.getElementById('password');
  const confirmPasswordInput = document.getElementById('confirmPassword');
  const passwordError = document.getElementById('passwordError');
  const confirmPasswordError = document.getElementById('confirmPasswordError');
  const successDiv = document.getElementById('resetConfirmMessage');
  const errorDiv = document.getElementById('resetConfirmError');

  // Validation handler
  const validateForm = () => {
    let isValid = true;

    // Validate password
    if (!passwordInput.value) {
      passwordError.textContent = 'Password is required';
      passwordError.style.display = 'block';
      isValid = false;
    } else if (!validatePassword(passwordInput.value)) {
      passwordError.textContent = 'Password must be at least 8 characters';
      passwordError.style.display = 'block';
      isValid = false;
    } else {
      passwordError.textContent = '';
      passwordError.style.display = 'none';
    }

    // Validate confirm password
    if (!confirmPasswordInput.value) {
      confirmPasswordError.textContent = 'Please confirm your password';
      confirmPasswordError.style.display = 'block';
      isValid = false;
    } else if (passwordInput.value !== confirmPasswordInput.value) {
      confirmPasswordError.textContent = 'Passwords do not match';
      confirmPasswordError.style.display = 'block';
      isValid = false;
    } else {
      confirmPasswordError.textContent = '';
      confirmPasswordError.style.display = 'none';
    }

    return isValid;
  };

  // Real-time validation
  [passwordInput, confirmPasswordInput].forEach(el => {
    el.addEventListener('change', validateForm);
    el.addEventListener('blur', validateForm);
  });

  // Form submission handler
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.classList.add('loading');

    try {
      const response = await app.apiCall('POST', '/api/auth/reset-password-confirm', {
        token,
        password: passwordInput.value
      });

      if (response.ok) {
        successDiv.textContent = 'Password reset successful. Redirecting to login...';
        successDiv.classList.remove('d-none');
        errorDiv.classList.add('d-none');
        setTimeout(() => {
          window.location.hash = '#/login';
        }, 2000);
      } else {
        const errorData = await response.json();
        errorDiv.textContent = errorData.message || 'Failed to reset password.';
        errorDiv.classList.remove('d-none');
        successDiv.classList.add('d-none');
      }
    } catch (error) {
      console.error('Reset password confirm error:', error);
      errorDiv.textContent = 'An error occurred. Please try again.';
      errorDiv.classList.remove('d-none');
      successDiv.classList.add('d-none');
    } finally {
      submitButton.disabled = false;
      submitButton.classList.remove('loading');
    }
  });
};

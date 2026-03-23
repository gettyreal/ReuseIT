<?php
namespace ReuseIT\Services;

use ReuseIT\Repositories\UserRepository;
use ReuseIT\Config\SessionHandler;
use Exception;

/**
 * AuthService
 * 
 * Encapsulates core authentication business logic.
 * Handles registration, login, logout, and user retrieval with password hashing and session management.
 * Integrates rate limiting to prevent brute-force attacks.
 */
class AuthService {
    
    private UserRepository $userRepo;
    private GeolocationService $geoService;
    private SessionHandler $session;
    private RateLimitService $rateLimiter;
    
    /**
     * Initialize AuthService with dependencies.
     * 
     * @param UserRepository $userRepo User data access layer
     * @param GeolocationService $geoService Address geocoding service
     * @param SessionHandler $session Session management
     * @param RateLimitService $rateLimiter Rate limiting service
     */
    public function __construct(UserRepository $userRepo, GeolocationService $geoService, SessionHandler $session, RateLimitService $rateLimiter) {
        $this->userRepo = $userRepo;
        $this->geoService = $geoService;
        $this->session = $session;
        $this->rateLimiter = $rateLimiter;
    }
    
    /**
     * Register a new user with email, password, name, and location.
     * 
     * @param string $email User email address
     * @param string $password User password (will be hashed)
     * @param string $firstName User first name
     * @param string $lastName User last name
     * @param array $address Address components (street, city, province, postal_code, country)
     * @param array|null $coordinates Optional GPS coordinates (lat, lng) - will geocode address if not provided
     * @return array Array with user_id
     * @throws Exception On validation error or registration failure
     */
    public function register(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        array $address,
        ?array $coordinates = null
    ): array {
        // Validate email uniqueness
        if ($this->userRepo->findByEmail($email)) {
            throw new Exception('Email already registered');
        }
        
        // Hash password using bcrypt with cost=12
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Geocode address if coordinates not provided
        if (!$coordinates) {
            $coordinates = $this->geoService->geocodeAddress($address);
            if (!$coordinates) {
                throw new Exception('Unable to geocode address. Please check the address and try again.');
            }
        }
        
        // Prepare user data for storage
        $userData = [
            'email' => $email,
            'password_hash' => $passwordHash,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'address_street' => $address['street'] ?? '',
            'address_city' => $address['city'] ?? '',
            'address_province' => $address['province'] ?? '',
            'address_postal_code' => $address['postal_code'] ?? '',
            'address_country' => $address['country'] ?? '',
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Create user record
        $userId = $this->userRepo->create($userData);
        
        // Create session for new user
        $this->session->login($userId);
        
        return ['user_id' => $userId];
    }
    
    /**
     * Authenticate user with email and password.
     * 
     * Enforces rate limiting before checking credentials to prevent brute-force attacks.
     * 
     * @param string $email User email address
     * @param string $password User password (plain text)
     * @return array Array with user_id and session_id
     * @throws Exception On authentication failure or rate limit lockout
     */
    public function login(string $email, string $password): array {
        // Get client IP address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Check if account is locked due to too many failed attempts
        // This check happens BEFORE credential validation to prevent enumeration attacks
        if ($this->rateLimiter->isLocked($email, $ipAddress)) {
            throw new Exception(RateLimitService::getLockoutMessage());
        }
        
        // Find user by email
        $user = $this->userRepo->findByEmail($email);
        
        // User not found or password incorrect - return generic error to prevent email enumeration
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Record the failed attempt for rate limiting
            $this->rateLimiter->recordFailedAttempt($email, $ipAddress);
            throw new Exception('Invalid credentials');
        }
        
        // Password is correct - clear failed attempts counter
        $this->rateLimiter->clearAttempts($email, $ipAddress);
        
        // Create session (regenerates session ID for security)
        $this->session->login($user['id']);
        
        return [
            'user_id' => $user['id'],
            'session_id' => session_id()
        ];
    }
    
    /**
     * Log out the current user.
     * 
     * Clears session from database and deletes cookie.
     * 
     * @return void
     */
    public function logout(): void {
        $sessionId = $_COOKIE['PHPSESSID'] ?? null;
        
        if ($sessionId) {
            $this->session->logout($sessionId);
        }
    }
    
    /**
     * Get current authenticated user data.
     * 
     * @return array|null User record or null if not authenticated
     */
    public function getCurrentUser(): ?array {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        
        return $this->userRepo->find($_SESSION['user_id']);
    }
}

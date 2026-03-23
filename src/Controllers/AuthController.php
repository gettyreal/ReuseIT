<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;
use ReuseIT\Services\AuthService;
use Exception;

/**
 * AuthController
 * 
 * HTTP endpoints for user authentication.
 * Handles registration, login, logout, and profile retrieval.
 */
class AuthController {
    
    private AuthService $authService;
    
    /**
     * Initialize AuthController with dependencies.
     * 
     * @param AuthService $authService Authentication service
     */
    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }
    
    /**
     * Register a new user.
     * 
     * POST /api/auth/register
     * 
     * Request body:
     * {
     *   "email": "user@example.com",
     *   "password": "securePassword123",
     *   "first_name": "John",
     *   "last_name": "Doe",
     *   "address": {
     *     "street": "123 Main St",
     *     "city": "Toronto",
     *     "province": "ON",
     *     "postal_code": "M5A 1A1",
     *     "country": "Canada"
     *   },
     *   "coordinates": {"lat": 43.6532, "lng": -79.3832} // optional
     * }
     * 
     * @param array $get GET parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function register(array $get, array $post, array $files, array $params): string {
        try {
            // Parse JSON request body
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Validate required fields
            $errors = [];
            
            if (empty($input['email'])) {
                $errors[] = ['field' => 'email', 'message' => 'Email is required'];
            } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['field' => 'email', 'message' => 'Invalid email format'];
            }
            
            if (empty($input['password'])) {
                $errors[] = ['field' => 'password', 'message' => 'Password is required'];
            } elseif (strlen($input['password']) < 8) {
                $errors[] = ['field' => 'password', 'message' => 'Password must be at least 8 characters'];
            }
            
            if (empty($input['first_name'])) {
                $errors[] = ['field' => 'first_name', 'message' => 'First name is required'];
            } elseif (strlen($input['first_name']) > 100) {
                $errors[] = ['field' => 'first_name', 'message' => 'First name must be less than 100 characters'];
            }
            
            if (empty($input['last_name'])) {
                $errors[] = ['field' => 'last_name', 'message' => 'Last name is required'];
            } elseif (strlen($input['last_name']) > 100) {
                $errors[] = ['field' => 'last_name', 'message' => 'Last name must be less than 100 characters'];
            }
            
            // Validate address
            if (empty($input['address'])) {
                $errors[] = ['field' => 'address', 'message' => 'Address is required'];
            } else {
                $address = $input['address'];
                $addressFields = ['street', 'city', 'province', 'postal_code', 'country'];
                
                foreach ($addressFields as $field) {
                    if (empty($address[$field])) {
                        $errors[] = ['field' => "address.{$field}", 'message' => ucfirst($field) . ' is required'];
                    }
                }
            }
            
            // Return validation errors if any
            if (!empty($errors)) {
                return Response::validationErrors($errors);
            }
            
            // Call AuthService to register
            $result = $this->authService->register(
                $input['email'],
                $input['password'],
                $input['first_name'],
                $input['last_name'],
                $input['address'],
                $input['coordinates'] ?? null
            );
            
            return Response::success($result, 201);
            
        } catch (Exception $e) {
            // Check if it's a validation error (e.g., duplicate email)
            if (strpos($e->getMessage(), 'already registered') !== false) {
                return Response::error($e->getMessage(), 400);
            }
            
            // Check for geocoding failures
            if (strpos($e->getMessage(), 'geocode') !== false) {
                return Response::error($e->getMessage(), 400);
            }
            
            // Generic server error
            error_log("Registration error: " . $e->getMessage());
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * Authenticate user with email and password.
     * 
     * POST /api/auth/login
     * 
     * Request body:
     * {
     *   "email": "user@example.com",
     *   "password": "securePassword123"
     * }
     * 
     * @param array $get GET parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response
     */
     public function login(array $get, array $post, array $files, array $params): string {
         try {
             // Parse JSON request body
             $input = json_decode(file_get_contents('php://input'), true) ?? [];
             
             // Validate required fields
             $errors = [];
             
             if (empty($input['email'])) {
                 $errors[] = ['field' => 'email', 'message' => 'Email is required'];
             }
             
             if (empty($input['password'])) {
                 $errors[] = ['field' => 'password', 'message' => 'Password is required'];
             }
             
             // Return validation errors if any
             if (!empty($errors)) {
                 return Response::validationErrors($errors);
             }
             
             // Call AuthService to login
             $result = $this->authService->login($input['email'], $input['password']);
             
             return Response::success($result, 200);
             
         } catch (Exception $e) {
             $message = $e->getMessage();
             
             // Check if it's a rate limit lockout
             if (strpos($message, 'Too many login attempts') !== false) {
                 return Response::error($message, 429);
             }
             
             // Invalid credentials or other login error
             error_log("Login error: " . $message);
             return Response::error('Invalid credentials', 401);
         }
     }
    
    /**
     * Logout the current user.
     * 
     * POST /api/auth/logout
     * 
     * @param array $get GET parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function logout(array $get, array $post, array $files, array $params): string {
        try {
            $this->authService->logout();
            return Response::success([], 200);
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * Get current authenticated user profile.
     * 
     * GET /api/auth/me
     * 
     * Requires authentication (valid session).
     * 
     * @param array $get GET parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function me(array $get, array $post, array $files, array $params): string {
        try {
            // Check if user is authenticated
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }
            
            // Get current user
            $user = $this->authService->getCurrentUser();
            
            if (!$user) {
                return Response::error('Unauthorized', 401);
            }
            
            return Response::success($user, 200);
            
        } catch (Exception $e) {
            error_log("Me endpoint error: " . $e->getMessage());
            return Response::error('Server error', 500);
        }
    }
}

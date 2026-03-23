<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;
use ReuseIT\Services\UserService;

/**
 * UserController
 * 
 * Handles user profile HTTP endpoints.
 * 
 * Public endpoints:
 * - GET /api/users/{id} - View user profile (public)
 * 
 * Protected endpoints (require authentication):
 * - PATCH /api/users/{id}/profile - Edit own profile (with authorization check)
 */
class UserController {
    private UserService $userService;
    private Response $response;
    
    /**
     * Initialize controller with service dependencies.
     * 
     * @param UserService $userService Service layer for profile operations
     * @param Response $response Response envelope helper
     */
    public function __construct(UserService $userService, Response $response = null) {
        $this->userService = $userService;
        $this->response = $response ?? new Response();
    }
    
    /**
     * GET /api/users/{id}
     * 
     * Public endpoint - view user profile without authentication.
     * Returns complete profile including address and statistics.
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function show(array $get, array $post, array $files, array $params): string {
        try {
            $userId = (int)($params['id'] ?? 0);
            
            if ($userId <= 0) {
                return Response::error('Invalid user ID', 400);
            }
            
            $profile = $this->userService->getProfile($userId);
            
            return Response::success($profile, 200);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return Response::error('User not found', 404);
            }
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * PATCH /api/users/{id}/profile
     * 
     * Protected endpoint - edit own profile with authentication and authorization.
     * User can only edit their own profile (authorization check enforced).
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * - $_SESSION['user_id'] must match the ID in URL
     * 
     * Allowed fields:
     * - first_name, last_name, bio
     * - address_street, address_city, address_province, address_postal_code, address_country
     * 
     * Rejected fields:
     * - email, password, other sensitive fields
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters (not used for PATCH, parsed from body)
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function update(array $get, array $post, array $files, array $params): string {
        try {
            $userId = (int)($params['id'] ?? 0);
            
            if ($userId <= 0) {
                return Response::error('Invalid user ID', 400);
            }
            
            // Authorization check: user can only edit their own profile
            if (empty($_SESSION['user_id']) || $_SESSION['user_id'] !== $userId) {
                return Response::error('Forbidden', 403);
            }
            
            // Parse JSON request body
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Validate input
            $errors = $this->validateProfileUpdate($input);
            if (!empty($errors)) {
                return Response::validationErrors($errors, 400);
            }
            
            // Update profile via service
            $success = $this->userService->updateProfile($userId, $input);
            
            if ($success) {
                return Response::success(['user_id' => $userId], 200);
            }
            
            return Response::error('Failed to update profile', 500);
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * Validate profile update input.
     * 
     * Checks:
     * - Each field is max 255 characters
     * - Address components are provided if any address field is updated
     * 
     * @param array $input User input to validate
     * @return array Array of validation errors (empty if valid)
     */
    private function validateProfileUpdate(array $input): array {
        $errors = [];
        $allowedFields = [
            'first_name',
            'last_name',
            'bio',
            'address_street',
            'address_city',
            'address_province',
            'address_postal_code',
            'address_country'
        ];
        
        foreach ($input as $field => $value) {
            if (!in_array($field, $allowedFields)) {
                // Skip fields not in whitelist (controller layer accepts but service layer filters)
                continue;
            }
            
            // Check field length
            if (is_string($value) && strlen($value) > 255) {
                $errors[] = [
                    'field' => $field,
                    'message' => 'Field must be 255 characters or less'
                ];
            }
        }
        
        return $errors;
    }
}

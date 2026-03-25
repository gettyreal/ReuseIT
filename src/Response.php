<?php
namespace ReuseIT;

/**
 * Response
 * 
 * Consistent API response envelope for all endpoints.
 * Ensures all responses follow the same format with success field and data/errors.
 */
class Response {
    /**
     * Success response with data payload.
     * 
     * Response format:
     * {
     *   "success": true,
     *   "data": {...}
     * }
     * 
     * @param mixed $data Response payload (array, object, or primitive)
     * @param int $statusCode HTTP status code (default 200 OK)
     * @return string JSON-encoded response
     */
    public static function success($data, int $statusCode = 200): string {
        http_response_code($statusCode);
        
        return json_encode([
            'success' => true,
            'data' => $data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Validation error response with field-level errors.
     * 
     * Response format:
     * {
     *   "success": false,
     *   "errors": [
     *     { "field": "email", "message": "Invalid email format" },
     *     { "field": "password", "message": "Must be at least 8 characters" }
     *   ]
     * }
     * 
     * @param array $errors Array of validation errors: [["field" => "name", "message" => "..."], ...]
     * @param int $statusCode HTTP status code (default 400 Bad Request)
     * @return string JSON-encoded response
     */
    public static function validationErrors(array $errors, int $statusCode = 400): string {
        http_response_code($statusCode);
        
        return json_encode([
            'success' => false,
            'errors' => $errors
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Server error response.
     * 
     * Response format:
     * {
     *   "success": false,
     *   "error": "Server error"
     * }
     * 
     * No internal details exposed to client.
     * Actual error is logged on server side only.
     * 
     * @param string $message User-friendly error message (never include technical details)
     * @param int $statusCode HTTP status code (default 500 Internal Server Error)
     * @return string JSON-encoded response
     */
    public static function error(string $message, int $statusCode = 500): string {
        http_response_code($statusCode);
        
        return json_encode([
            'success' => false,
            'error' => $message
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

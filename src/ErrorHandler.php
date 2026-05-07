<?php
namespace ReuseIT;

use ReuseIT\Exceptions\InvalidArgumentException;
use ReuseIT\Exceptions\NotFoundException;
use ReuseIT\Exceptions\AuthenticationException;
use ReuseIT\Exceptions\AuthorizationException;
use ReuseIT\Exceptions\ConflictException;
use ReuseIT\Exceptions\ValidationException;

/**
 * ErrorHandler
 * 
 * Centralized error handling for the application.
 * Converts exceptions to user-friendly HTTP responses.
 * Logs all errors server-side for debugging.
 */
class ErrorHandler {
    
    /**
     * Format an exception into a consistent error response envelope.
     * 
     * Response format:
     * {
     *   "status": "error",
     *   "message": "user-friendly message",
     *   "data": {...optional validation errors}
     * }
     * 
     * @param \Throwable $e Exception to format
     * @param int $statusCode HTTP status code (if not auto-detected)
     * @return array Error response array
     */
    public static function formatError(\Throwable $e, int $statusCode = 500): array {
        // Auto-detect status code if not provided (or if 500)
        if ($statusCode === 500) {
            $statusCode = self::getStatusCode($e);
        }
        
        $response = [
            'status' => 'error',
            'message' => self::getUserMessage($e, $statusCode)
        ];
        
        // Add validation errors if applicable
        if ($e instanceof ValidationException) {
            $response['data'] = [
                'validation_errors' => $e->getErrors()
            ];
        }
        
        return $response;
    }
    
    /**
     * Determine HTTP status code from exception type.
     * 
     * @param \Throwable $e Exception to inspect
     * @return int HTTP status code
     */
    public static function getStatusCode(\Throwable $e): int {
        if ($e instanceof InvalidArgumentException) {
            return 400;
        } elseif ($e instanceof NotFoundException) {
            return 404;
        } elseif ($e instanceof AuthenticationException) {
            return 401;
        } elseif ($e instanceof AuthorizationException) {
            return 403;
        } elseif ($e instanceof ConflictException) {
            return 409;
        } elseif ($e instanceof ValidationException) {
            return 422;
        }
        
        return 500;
    }
    
    /**
     * Check if exception message should be exposed to client.
     * 4xx errors are safe to expose, 5xx errors should be generic.
     * 
     * @param \Throwable $e Exception to inspect
     * @return bool True if message can be exposed to client
     */
    public static function shouldExposeMessage(\Throwable $e): bool {
        $statusCode = self::getStatusCode($e);
        return $statusCode < 500;
    }
    
    /**
     * Get user-friendly error message.
     * 5xx errors: generic, no details
     * 4xx errors: specific message (safe to expose)
     * 
     * @param \Throwable $e Exception to format
     * @param int $statusCode HTTP status code
     * @return string User-friendly error message
     */
    private static function getUserMessage(\Throwable $e, int $statusCode): string {
        // 5xx errors: generic message only
        if ($statusCode >= 500) {
            return 'An error occurred while processing your request. Please try again later.';
        }
        
        // 4xx errors: specific message
        // If exception has a custom message, use it (already user-friendly from service layer)
        if (!empty($e->getMessage()) && self::shouldExposeMessage($e)) {
            return $e->getMessage();
        }
        
        // Fallback to generic messages per status code
        switch ($statusCode) {
            case 400:
                return 'Invalid input. Please check your request and try again.';
            case 401:
                return 'You must log in to access this resource.';
            case 403:
                return 'You do not have permission to perform this action.';
            case 404:
                return 'Resource not found.';
            case 409:
                return 'Conflict: The requested action cannot be completed.';
            case 422:
                return 'Validation failed. Please check your input.';
            default:
                return 'An error occurred while processing your request. Please try again later.';
        }
    }
}

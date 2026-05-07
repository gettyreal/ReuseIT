<?php
namespace ReuseIT\Middleware;

use ReuseIT\ErrorHandler;

/**
 * ErrorHandlingMiddleware
 * 
 * Wraps request/response handling to catch all exceptions.
 * Converts exceptions to consistent error responses.
 * Logs all errors server-side for debugging.
 */
class ErrorHandlingMiddleware {
    
    /**
     * Handle the request, catching any exceptions and converting to error responses.
     * 
     * @param callable $handler Request handler (typically Router::dispatch)
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @return string JSON response
     */
    public static function handle(callable $handler, string $method, string $uri): string {
        try {
            return $handler($method, $uri);
        } catch (\Throwable $e) {
            // Log exception server-side with context
            self::logException($e, $method, $uri);
            
            // Format error response
            $statusCode = ErrorHandler::getStatusCode($e);
            $errorResponse = ErrorHandler::formatError($e, $statusCode);
            
            // Set response headers and status
            http_response_code($statusCode);
            header('Content-Type: application/json');
            
            // Return error response as JSON
            return json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
    
    /**
     * Log exception with context for debugging.
     * 
     * @param \Throwable $e Exception to log
     * @param string $method HTTP method
     * @param string $uri Request URI
     */
    private static function logException(\Throwable $e, string $method, string $uri): void {
        $timestamp = date('Y-m-d H:i:s');
        
        // Build log context
        $context = [
            'timestamp' => $timestamp,
            'method' => $method,
            'uri' => $uri,
            'user_id' => self::getCurrentUserId(),
            'exception_type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
        
        // Log to error.log (or use configured logger)
        $logDir = dirname(__DIR__) . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error.log';
        $logEntry = json_encode($context) . "\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Get current authenticated user ID from session.
     * 
     * @return int|null User ID if authenticated, null otherwise
     */
    private static function getCurrentUserId(): ?int {
        if (isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        return null;
    }
}

<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;

/**
 * Health Check Controller
 * 
 * Provides a simple health check endpoint that verifies the server is running.
 * Does NOT require database connectivity—useful for load balancers and monitoring.
 * 
 * Endpoint: GET /api/health
 * Response: {success: true, status: "ok", timestamp: "..."}
 */
class HealthController {
    /**
     * Health check endpoint.
     * 
     * Returns basic server health information:
     * - status: "ok" if server is running
     * - timestamp: current server time (ISO 8601)
     * - uptime: not provided (would require tracking)
     * 
     * @param array $get Query parameters
     * @param array $post Post parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function check(array $get, array $post, array $files, array $params): string {
        // Check system health status
        $healthData = [
            'status' => 'ok',
            'timestamp' => date('c'),  // ISO 8601 format
            'server' => [
                'name' => gethostname(),
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_limit' => ini_get('memory_limit'),
            ]
        ];
        
        // Return success response with health data
        http_response_code(200);
        return Response::success($healthData);
    }
}

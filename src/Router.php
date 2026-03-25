<?php
namespace ReuseIT;

use PDO;

/**
 * Router
 * 
 * HTTP request router for RESTful API.
 * Maps HTTP method + URI to controller actions.
 * Supports parameterized routes with :id syntax.
 */
class Router {
    private array $routes = [];
    private ?PDO $pdo = null;
    
    /**
     * Initialize router and register all routes.
     * 
     * @param PDO|null $pdo Database connection (optional, required for controllers that need it)
     */
    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
        $this->registerRoutes();
    }
    
    /**
     * Register all API endpoint routes.
     * 
     * Routes are defined as:
     *   $this->routes['METHOD']['/api/path/:id'] = ['ControllerClass', 'action']
     * 
     * The router will:
     * 1. Match URI against registered patterns (supports :id parameter)
     * 2. Instantiate controller
     * 3. Call action with parameters: ($GET, $POST, $FILES, $params)
     */
    private function registerRoutes(): void {
        // Phase 1: Placeholder routes for API validation
        // Real endpoints implemented in Phase 2+
        
        // Listing endpoints (Phase 3)
        $this->routes['GET']['/api/listings'] = ['ListingController', 'list'];
        $this->routes['GET']['/api/listings/:id'] = ['ListingController', 'show'];
        $this->routes['POST']['/api/listings'] = ['ListingController', 'create'];
        $this->routes['PATCH']['/api/listings/:id'] = ['ListingController', 'update'];
        $this->routes['DELETE']['/api/listings/:id'] = ['ListingController', 'delete'];
        
        // Photo upload endpoints (Phase 3)
        $this->routes['POST']['/api/listings/:id/photos'] = ['ListingController', 'uploadPhotos'];
        $this->routes['POST']['/api/users/:id/avatar'] = ['ListingController', 'uploadAvatar'];
        
        // User endpoints (Phase 2)
        $this->routes['POST']['/api/auth/register'] = ['AuthController', 'register'];
        $this->routes['POST']['/api/auth/login'] = ['AuthController', 'login'];
        $this->routes['POST']['/api/auth/logout'] = ['AuthController', 'logout'];
        $this->routes['GET']['/api/auth/me'] = ['AuthController', 'me'];
        $this->routes['GET']['/api/users/:id'] = ['UserController', 'show'];
        $this->routes['PATCH']['/api/users/:id/profile'] = ['UserController', 'update'];
        
        // Health check (Phase 1)
        $this->routes['GET']['/api/health'] = ['HealthController', 'check'];
    }
    
    /**
     * Dispatch incoming request to appropriate controller action.
     * 
     * Handles:
     * 1. Route matching with parameterized URIs
     * 2. Authentication middleware for protected endpoints
     * 3. Controller instantiation and action execution
     * 4. Error handling (returns 401 if authentication required but missing)
     * 
     * @param string $method HTTP method (GET, POST, PATCH, DELETE, etc.)
     * @param string $uri Request URI (e.g., /api/listings/42)
     * @return string Response from controller action
     */
    public function dispatch(string $method, string $uri): string {
        $method = strtoupper($method);
        
        // Protected endpoints that require authentication
        $protectedEndpoints = [
            'UserController:update',
            'AuthController:me',
            'ListingController:create',
            'ListingController:update',
            'ListingController:delete',
            'ListingController:uploadPhotos',
            'ListingController:uploadAvatar',
        ];
        
        // Iterate through registered routes for this method
        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $params = [];
            if ($this->matches($pattern, $uri, $params)) {
                [$controllerClass, $action] = $handler;
                
                try {
                    // Check if endpoint requires authentication
                    $endpointKey = "{$controllerClass}:{$action}";
                    if (in_array($endpointKey, $protectedEndpoints)) {
                        $middleware = new \ReuseIT\Middleware\AuthMiddleware();
                        try {
                            $middleware->requireAuth();
                        } catch (\Exception $e) {
                            return \ReuseIT\Response::error('Unauthorized - user must be logged in (debug)', 401);
                        }
                    }
                    
                    // Instantiate controller with full namespace
                    $controllerNamespace = 'ReuseIT\\Controllers\\' . $controllerClass;
                    
                    // Special handling for AuthController - inject dependencies
                    if ($controllerClass === 'AuthController' && $this->pdo !== null) {
                        // AuthController requires AuthService with its dependencies
                        $userRepo = new \ReuseIT\Repositories\UserRepository($this->pdo);
                        $geoService = new \ReuseIT\Services\GeolocationService($this->pdo);
                        $sessionHandler = new \ReuseIT\Config\SessionHandler($this->pdo);
                        $loginAttemptRepo = new \ReuseIT\Repositories\LoginAttemptRepository($this->pdo);
                        $rateLimiter = new \ReuseIT\Services\RateLimitService($loginAttemptRepo);
                        $authService = new \ReuseIT\Services\AuthService($userRepo, $geoService, $sessionHandler, $rateLimiter);
                        $controller = new $controllerNamespace($authService);
                    } elseif ($controllerClass === 'UserController' && $this->pdo !== null) {
                        // UserController requires UserService with its dependencies
                        $userRepo = new \ReuseIT\Repositories\UserRepository($this->pdo);
                        $userService = new \ReuseIT\Services\UserService($userRepo);
                        $response = new \ReuseIT\Response();
                        $controller = new $controllerNamespace($userService, $response);
                    } elseif ($controllerClass === 'ListingController' && $this->pdo !== null) {
                        // ListingController requires ListingService and PhotoUploadService with dependencies
                        $listingRepo = new \ReuseIT\Repositories\ListingRepository($this->pdo);
                        $photoRepo = new \ReuseIT\Repositories\ListingPhotoRepository($this->pdo);
                        $geoService = new \ReuseIT\Services\GeolocationService($this->pdo);
                        $listingService = new \ReuseIT\Services\ListingService($this->pdo, $listingRepo, $photoRepo, $geoService);
                        $photoUploadService = new \ReuseIT\Services\PhotoUploadService($this->pdo, $photoRepo);
                        $response = new \ReuseIT\Response();
                        $controller = new $controllerNamespace($photoUploadService, $photoRepo, $this->pdo, $response, $listingService, $listingRepo);
                    } else {
                        // Default controller instantiation (no dependencies)
                        $controller = new $controllerNamespace();
                    }
                    
                    // Call action with request data and URI parameters
                    return $controller->$action($_GET, $_POST, $_FILES, $params);
                } catch (\Throwable $e) {
                    // Controller errors will be caught by front controller's try-catch
                    throw $e;
                }
            }
        }
        
        // No route found
        http_response_code(404);
        return json_encode(['success' => false, 'error' => 'Not found']);
    }
    
    /**
     * Check if URI matches route pattern.
     * Supports :id syntax for parameterized routes.
     * 
     * @param string $pattern Route pattern (e.g., '/api/listings/:id')
     * @param string $uri Request URI (e.g., '/api/listings/42')
     * @param array $params Reference array to populate with matched parameters
     * @return bool True if URI matches pattern
     */
     private function matches(string $pattern, string $uri, array &$params): bool {
         // Convert pattern to regex
         // Replace :id with named group (?P<id>\d+)
         // Replace :word with named group (?P<word>[a-z0-9_-]+)
         $regex = preg_replace_callback(
             '/:(id|word|slug)/',
             function($m) {
                 $paramType = $m[1];
                 $patterns = [
                     'id' => '(?P<id>\d+)',
                     'word' => '(?P<word>[a-z0-9_-]+)',
                     'slug' => '(?P<slug>[a-z0-9_-]+)',
                 ];
                 return $patterns[$paramType] ?? '(?P<param>[a-z0-9_-]+)';
             },
             $pattern
         );
         
         // Anchor pattern
         $regex = '^' . $regex . '$';
         
         // Match URI against pattern
         if (preg_match('/' . str_replace('/', '\/', $regex) . '/', $uri, $matches)) {
             // Extract named groups
             foreach ($matches as $key => $value) {
                 if (!is_numeric($key)) {
                     $params[$key] = $value;
                 }
             }
             return true;
         }
         
         return false;
     }
}

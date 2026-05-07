<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;
use ReuseIT\Services\FavoritesService;

/**
 * FavoritesController
 * 
 * Handles favorite management HTTP endpoints.
 * 
 * Protected endpoints (require authentication):
 * - POST /api/listings/:id/favorite - Toggle favorite status
 * - GET /api/favorites - Get user's favorites list
 */
class FavoritesController {
    private FavoritesService $favoritesService;
    
    /**
     * Initialize controller with service dependencies.
     * 
     * @param FavoritesService $favoritesService Service layer for favorite operations
     */
    public function __construct(FavoritesService $favoritesService) {
        $this->favoritesService = $favoritesService;
    }
    
    /**
     * POST /api/listings/:id/favorite
     * 
     * Toggle favorite status for a listing.
     * Idempotent: first call adds to favorites, second call removes.
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id' for listing_id)
     * @return string JSON response
     */
    public function toggleFavorite(array $get, array $post, array $files, array $params): string {
        try {
            // Extract listing ID from URL parameter
            $listingId = (int)($params['id'] ?? 0);
            
            if ($listingId <= 0) {
                return Response::error('Invalid listing ID', 400);
            }
            
            // Get authenticated user ID from session
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }
            $userId = (int)$_SESSION['user_id'];
            
            // Toggle favorite status
            $result = $this->favoritesService->toggleFavorite($userId, $listingId);
            
            // Return 200 with favorited state
            $data = [
                'favorited' => $result['favorited'],
            ];
            
            return Response::success($data, 200);
        } catch (\InvalidArgumentException $e) {
            // Validation errors from service layer
            $message = $e->getMessage();
            
            if (str_contains($message, 'not found')) {
                return Response::error($message, 404);
            } elseif (str_contains($message, 'Invalid')) {
                return Response::error($message, 400);
            } elseif (str_contains($message, 'own')) {
                return Response::error($message, 422);
            }
            
            return Response::error($message, 422);
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
    
    /**
     * GET /api/favorites
     * 
     * Get paginated list of user's favorite listings.
     * Includes full listing details (title, price, photos, seller info).
     * 
     * Query parameters:
     * - limit: Number of results (default 20, max 100)
     * - offset: Pagination offset (default 0)
     * 
     * Authorization:
     * - Must be authenticated (checked by AuthMiddleware)
     * 
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response with pagination metadata
     */
    public function getFavorites(array $get, array $post, array $files, array $params): string {
        try {
            // Get authenticated user ID from session
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }
            $userId = (int)$_SESSION['user_id'];
            
            // Parse pagination parameters
            $limit = (int)($get['limit'] ?? 20);
            $offset = (int)($get['offset'] ?? 0);
            
            // Validate pagination parameters
            if ($limit <= 0 || $limit > 100) {
                return Response::error('Limit must be between 1 and 100', 400);
            }
            if ($offset < 0) {
                return Response::error('Offset cannot be negative', 400);
            }
            
            // Get user's favorites
            $result = $this->favoritesService->getUserFavorites($userId, $limit, $offset);
            
            // Format response with pagination metadata
            $data = [
                'favorites' => $result['favorites'],
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $result['total'],
                ],
            ];
            
            return Response::success($data, 200);
        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }
}

<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;
use ReuseIT\Services\PhotoUploadService;
use ReuseIT\Repositories\ListingPhotoRepository;
use InvalidArgumentException;
use RuntimeException;
use PDO;

/**
 * ListingController
 *
 * Handles listing HTTP endpoints including photo uploads.
 *
 * Protected endpoints (require authentication):
 * - POST /api/listings/:id/photos - Upload photos for listing
 * - POST /api/users/:id/avatar - Upload user avatar
 */
class ListingController
{
    private PhotoUploadService $photoUploadService;
    private ListingPhotoRepository $photoRepository;
    private Response $response;
    private PDO $pdo;

    /**
     * Initialize controller with service dependencies.
     *
     * @param PhotoUploadService $photoUploadService Service for photo handling
     * @param ListingPhotoRepository $photoRepository Repository for photo persistence
     * @param PDO $pdo Database connection
     * @param Response $response Response envelope helper
     */
    public function __construct(
        PhotoUploadService $photoUploadService,
        ListingPhotoRepository $photoRepository,
        PDO $pdo,
        Response $response = null
    ) {
        $this->photoUploadService = $photoUploadService;
        $this->photoRepository = $photoRepository;
        $this->pdo = $pdo;
        $this->response = $response ?? new Response();
    }

    /**
     * POST /api/listings/:id/photos
     *
     * Upload photos for a listing.
     * Only the listing owner can upload photos.
     * Max 10 photos per listing (409 if exceeded).
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files $_FILES array containing 'photos' multipart upload
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function uploadPhotos(array $get, array $post, array $files, array $params): string
    {
        try {
            // Verify authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $listingId = (int)($params['id'] ?? 0);

            if ($listingId <= 0) {
                return Response::error('Invalid listing ID', 400);
            }

            // Verify listing exists and user owns it
            $stmt = $this->pdo->prepare("
                SELECT id, seller_id FROM listings WHERE id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$listingId]);
            $listing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$listing) {
                return Response::error('Listing not found', 404);
            }

            if ((int)$listing['seller_id'] !== $userId) {
                return Response::error('Forbidden - you do not own this listing', 403);
            }

            // Get photos from $_FILES
            $uploadedFiles = $_FILES['photos'] ?? null;
            if (!$uploadedFiles || !is_array($uploadedFiles['tmp_name'])) {
                return Response::error('No files uploaded', 400);
            }

            // Normalize $_FILES structure for multiple file uploads
            $files = $this->normalizeMultipleFiles($uploadedFiles);
            
            if (count($files) > 10) {
                return Response::error('Maximum 10 photos per upload', 409);
            }

            // Check current photo count
            $currentCount = $this->photoRepository->countByListingId($listingId);
            if ($currentCount + count($files) > 10) {
                return Response::error('Maximum 10 photos per listing', 409);
            }

            // Upload each photo
            $uploadedPhotos = [];
            $displayOrder = $currentCount;

            foreach ($files as $file) {
                try {
                    $photoUrl = $this->photoUploadService->reencodeAndStore(
                        $file,
                        $userId,
                        $listingId,
                        $displayOrder
                    );
                    $uploadedPhotos[] = ['photo_url' => $photoUrl];
                    $displayOrder++;
                } catch (InvalidArgumentException $e) {
                    return Response::error('Validation failed: ' . $e->getMessage(), 422);
                } catch (RuntimeException $e) {
                    return Response::error('Upload failed: ' . $e->getMessage(), 500);
                }
            }

            // Update listing photo count (denormalization for performance)
            $this->updateListingPhotoCount($listingId);

            return Response::success([
                'photos_uploaded' => count($uploadedPhotos),
                'photos' => $uploadedPhotos
            ], 201);

        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * POST /api/users/:id/avatar
     *
     * Upload user avatar image.
     * Only the authenticated user can upload their own avatar.
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files $_FILES array containing 'avatar' file
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function uploadAvatar(array $get, array $post, array $files, array $params): string
    {
        try {
            // Verify authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $targetUserId = (int)($params['id'] ?? 0);

            if ($targetUserId <= 0) {
                return Response::error('Invalid user ID', 400);
            }

            // Authorization check: users can only upload their own avatar
            if ($userId !== $targetUserId) {
                return Response::error('Forbidden', 403);
            }

            // Get avatar file
            $avatarFile = $_FILES['avatar'] ?? null;
            if (!$avatarFile || !is_array($avatarFile)) {
                return Response::error('No file uploaded', 400);
            }

            // Validate avatar file
            $validation = $this->photoUploadService->validatePhoto($avatarFile);
            if (!$validation['is_valid']) {
                return Response::error('Validation failed: ' . implode('; ', $validation['errors']), 422);
            }

            try {
                // Upload avatar (no listing_id or display_order for user avatar)
                $avatarUrl = $this->photoUploadService->reencodeAndStore(
                    $avatarFile,
                    $userId,
                    null,
                    null
                );

                // Update user's avatar_url in database
                $this->updateUserAvatar($userId, $avatarUrl);

                return Response::success(['avatar_url' => $avatarUrl], 201);

            } catch (RuntimeException $e) {
                return Response::error('Upload failed: ' . $e->getMessage(), 500);
            }

        } catch (\Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * Normalize $_FILES structure for multiple file uploads.
     *
     * $_FILES['photos'] with multiple files comes as:
     * [
     *   'name' => ['file1.jpg', 'file2.jpg'],
     *   'tmp_name' => ['/tmp/php123', '/tmp/php456'],
     *   'type' => ['image/jpeg', 'image/jpeg'],
     *   'size' => [12345, 67890],
     *   'error' => [0, 0]
     * ]
     *
     * This method normalizes to:
     * [
     *   ['name' => 'file1.jpg', 'tmp_name' => '/tmp/php123', ...],
     *   ['name' => 'file2.jpg', 'tmp_name' => '/tmp/php456', ...]
     * ]
     *
     * @param array $files $_FILES array element
     * @return array Array of normalized file arrays
     */
    private function normalizeMultipleFiles(array $files): array
    {
        if (empty($files['name'])) {
            return [];
        }

        $normalized = [];
        $count = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            if (is_array($files['name'])) {
                // Multiple files uploaded
                $normalized[] = [
                    'name' => $files['name'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'type' => $files['type'][$i] ?? '',
                    'size' => $files['size'][$i] ?? 0,
                    'error' => $files['error'][$i] ?? 4
                ];
            } else {
                // Single file uploaded (shouldn't happen with 'photos' array, but handle it)
                $normalized[] = $files;
            }
        }

        return $normalized;
    }

    /**
     * Update listing photo count for denormalization.
     *
     * @param int $listingId
     * @return void
     */
    private function updateListingPhotoCount(int $listingId): void
    {
        $count = $this->photoRepository->countByListingId($listingId);
        $stmt = $this->pdo->prepare("
            UPDATE listings SET photo_count = ? WHERE id = ?
        ");
        $stmt->execute([$count, $listingId]);
    }

    /**
     * Update user avatar URL in database.
     *
     * @param int $userId
     * @param string $avatarUrl
     * @return void
     */
    private function updateUserAvatar(int $userId, string $avatarUrl): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET avatar_url = ? WHERE id = ?
        ");
        $stmt->execute([$avatarUrl, $userId]);
    }
}

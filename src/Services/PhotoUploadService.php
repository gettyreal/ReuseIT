<?php

namespace ReuseIT\Services;

use InvalidArgumentException;
use RuntimeException;
use PDO;
use ReuseIT\Repositories\ListingPhotoRepository;

/**
 * PhotoUploadService
 *
 * Handles secure photo uploads with EXIF stripping, validation, and storage.
 * Uses intervention/image library for re-encoding to remove metadata.
 */
class PhotoUploadService
{
    private PDO $pdo;
    private ListingPhotoRepository $photoRepo;
    private int $maxUploadSizeMB;
    private int $maxPhotosPerListing;
    private int $imageMaxDimension;
    private int $imageMinDimension;
    private int $jpegQuality;

    public function __construct(
        PDO $pdo,
        ListingPhotoRepository $photoRepo,
        int $maxUploadSizeMB = 5,
        int $maxPhotosPerListing = 10,
        int $imageMaxDimension = 8000,
        int $imageMinDimension = 640,
        int $jpegQuality = 90
    ) {
        $this->pdo = $pdo;
        $this->photoRepo = $photoRepo;
        $this->maxUploadSizeMB = $maxUploadSizeMB;
        $this->maxPhotosPerListing = $maxPhotosPerListing;
        $this->imageMaxDimension = $imageMaxDimension;
        $this->imageMinDimension = $imageMinDimension;
        $this->jpegQuality = $jpegQuality;
    }

    /**
     * Validate a photo file
     *
     * @param array $file $_FILES array element
     * @return array ['is_valid' => bool, 'errors' => string[]]
     */
    public function validatePhoto(array $file): array
    {
        $errors = [];

        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['is_valid' => false, 'errors' => ['No file uploaded']];
        }

        // Check file size
        $fileSizeBytes = filesize($file['tmp_name']);
        $maxSizeBytes = $this->maxUploadSizeMB * 1024 * 1024;
        if ($fileSizeBytes < 1 || $fileSizeBytes > $maxSizeBytes) {
            $errors[] = "File size must be between 1B and {$this->maxUploadSizeMB}MB";
        }

        // Check MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = "Only JPEG, PNG, and WebP images are allowed";
        }

        // Check magic bytes (file header)
        if (!$this->validateMagicBytes($file['tmp_name'], $mimeType)) {
            $errors[] = "File header does not match MIME type (possible malicious file)";
        }

        // Load image and check dimensions
        try {
            $image = \Intervention\Image\ImageManagerStatic::make($file['tmp_name']);
            $width = $image->width();
            $height = $image->height();

            if ($width < $this->imageMinDimension || $height < $this->imageMinDimension) {
                $errors[] = "Image dimensions must be at least {$this->imageMinDimension}x{$this->imageMinDimension}";
            }

            if ($width > $this->imageMaxDimension || $height > $this->imageMaxDimension) {
                $errors[] = "Image dimensions must not exceed {$this->imageMaxDimension}x{$this->imageMaxDimension}";
            }
        } catch (\Exception $e) {
            $errors[] = "Could not load image file: " . $e->getMessage();
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Re-encode and store photo, stripping EXIF metadata
     *
     * @param array $file $_FILES array element
     * @param int $userId Current user ID
     * @param int|null $listingId Listing ID (null for avatar)
     * @param int|null $displayOrder Display order (null for avatar)
     * @return string Photo URL (relative path)
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function reencodeAndStore(
        array $file,
        int $userId,
        ?int $listingId = null,
        ?int $displayOrder = null
    ): string {
        // Validate first
        $validation = $this->validatePhoto($file);
        if (!$validation['is_valid']) {
            throw new InvalidArgumentException(implode('; ', $validation['errors']));
        }

        try {
            // Create directory structure
            if ($listingId) {
                $directory = "public/uploads/users/{$userId}/listings/{$listingId}";
            } else {
                $directory = "public/uploads/users/{$userId}";
            }

            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new RuntimeException("Could not create directory: {$directory}");
                }
            }

            // Generate filename with timestamp and random hash
            $timestamp = time();
            $random = substr(md5(uniqid()), 0, 6);
            $ext = 'jpg'; // Always store as JPEG for consistency
            $filename = "{$timestamp}_{$random}.{$ext}";
            $destinationPath = "{$directory}/{$filename}";

            // Load and re-encode image (strips EXIF)
            $image = \Intervention\Image\ImageManagerStatic::make($file['tmp_name']);
            
            // Resize to max 2000px height while maintaining aspect ratio
            $image->resize(null, 2000, function ($constraint) {
                $constraint->aspectRatio();
            });

            // Fix orientation based on EXIF (if present in original)
            $image->orientate();

            // Save as JPEG (automatically strips all metadata)
            $image->save($destinationPath, $this->jpegQuality);

            // Store in database
            $relativeUrl = str_replace(getcwd() . '/', '', $destinationPath);
            
            $photoId = $this->photoRepo->create([
                'listing_id' => $listingId,
                'photo_url' => $relativeUrl,
                'display_order' => $displayOrder
            ]);

            return $relativeUrl;
        } catch (\Exception $e) {
            throw new RuntimeException("Photo upload failed: " . $e->getMessage());
        }
    }

    /**
     * Delete a photo by ID
     *
     * Verifies ownership first, then soft-deletes database record and removes file.
     *
     * @param int $photoId Photo ID
     * @param int $userId Current user ID
     * @throws InvalidArgumentException
     */
    public function deletePhoto(int $photoId, int $userId): void
    {
        // Get photo record
        $photo = $this->photoRepo->find($photoId);
        if (!$photo) {
            throw new InvalidArgumentException("Photo not found");
        }

        // Verify ownership via listing
        if ($photo['listing_id']) {
            $stmt = $this->pdo->prepare("
                SELECT seller_id FROM listings WHERE id = ?
            ");
            $stmt->execute([$photo['listing_id']]);
            $listing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$listing || (int)$listing['seller_id'] !== $userId) {
                throw new InvalidArgumentException("You do not have permission to delete this photo");
            }
        }

        // Delete file from filesystem
        $this->photoRepo->deletePhotoFile($photo['photo_url']);

        // Soft delete database record
        $this->photoRepo->delete($photoId);
    }

    /**
     * Validate magic bytes (file header) matches MIME type
     *
     * @param string $filePath
     * @param string $mimeType
     * @return bool
     */
    private function validateMagicBytes(string $filePath, string $mimeType): bool
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return false;
        }

        $bytes = fread($handle, 12); // Read first 12 bytes
        fclose($handle);

        // JPEG: FF D8 FF
        if ($mimeType === 'image/jpeg' && strpos($bytes, "\xFF\xD8\xFF") === 0) {
            return true;
        }

        // PNG: 89 50 4E 47
        if ($mimeType === 'image/png' && strpos($bytes, "\x89PNG") === 0) {
            return true;
        }

        // WebP: RIFF ... WEBP
        if ($mimeType === 'image/webp' && strpos($bytes, "RIFF") === 0 && strpos($bytes, "WEBP") === 8) {
            return true;
        }

        return false;
    }
}

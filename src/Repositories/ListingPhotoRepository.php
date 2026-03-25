<?php

namespace ReuseIT\Repositories;

class ListingPhotoRepository extends BaseRepository
{
    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo, 'listing_photos');
    }

    /**
     * Find photos by listing ID (excluding deleted)
     *
     * @param int|string $listingId
     * @return array
     */
    public function findByListingId($listingId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE listing_id = ?" . $this->applyDeleteFilter() . "
                ORDER BY display_order ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$listingId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count non-deleted photos for a listing
     *
     * @param int|string $listingId
     * @return int
     */
    public function countByListingId($listingId): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE listing_id = ?" . $this->applyDeleteFilter();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$listingId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Hard delete a photo from filesystem only (no DB change)
     *
     * @param string $photoUrl
     * @return void
     */
    public function deletePhotoFile(string $photoUrl): void
    {
        $filePath = getcwd() . '/' . $photoUrl;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}

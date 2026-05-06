<?php
namespace ReuseIT\ValueObjects;

/**
 * User
 * 
 * Immutable domain value object for a user.
 * Represents a user with authentication and role information.
 */
class User {
    private int $id;
    private string $email;
    private string $name;
    private string $phone;
    private ?string $address;
    private ?string $city;
    private ?string $state;
    private ?string $postal_code;
    private ?string $country;
    private ?float $latitude;
    private ?float $longitude;
    private ?string $avatar_url;
    private ?float $avg_rating;
    private int $total_reviews;
    private bool $is_admin;
    private string $created_at;

    /**
     * Create a User value object.
     * 
     * @param int $id Unique user identifier
     * @param string $email Email address (unique)
     * @param string $name Full name
     * @param string $phone Phone number
     * @param string|null $address Street address
     * @param string|null $city City
     * @param string|null $state State/province
     * @param string|null $postal_code Postal code
     * @param string|null $country Country
     * @param float|null $latitude Latitude for location-based search
     * @param float|null $longitude Longitude for location-based search
     * @param string|null $avatar_url URL to user's avatar image
     * @param float|null $avg_rating Average review rating (0-5)
     * @param int $total_reviews Count of reviews received
     * @param bool $is_admin Whether user has admin privileges
     * @param string $created_at ISO 8601 timestamp when user was created
     */
    public function __construct(
        int $id,
        string $email,
        string $name,
        string $phone,
        ?string $address,
        ?string $city,
        ?string $state,
        ?string $postal_code,
        ?string $country,
        ?float $latitude,
        ?float $longitude,
        ?string $avatar_url,
        ?float $avg_rating,
        int $total_reviews,
        bool $is_admin,
        string $created_at
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
        $this->phone = $phone;
        $this->address = $address;
        $this->city = $city;
        $this->state = $state;
        $this->postal_code = $postal_code;
        $this->country = $country;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->avatar_url = $avatar_url;
        $this->avg_rating = $avg_rating;
        $this->total_reviews = $total_reviews;
        $this->is_admin = $is_admin;
        $this->created_at = $created_at;
    }

    /**
     * Get the user's unique identifier.
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * Get the user's email address.
     */
    public function getEmail(): string {
        return $this->email;
    }

    /**
     * Get the user's full name.
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Get the user's phone number.
     */
    public function getPhone(): string {
        return $this->phone;
    }

    /**
     * Get the user's street address.
     */
    public function getAddress(): ?string {
        return $this->address;
    }

    /**
     * Get the user's city.
     */
    public function getCity(): ?string {
        return $this->city;
    }

    /**
     * Get the user's state/province.
     */
    public function getState(): ?string {
        return $this->state;
    }

    /**
     * Get the user's postal code.
     */
    public function getPostalCode(): ?string {
        return $this->postal_code;
    }

    /**
     * Get the user's country.
     */
    public function getCountry(): ?string {
        return $this->country;
    }

    /**
     * Get the user's latitude for location-based search.
     */
    public function getLatitude(): ?float {
        return $this->latitude;
    }

    /**
     * Get the user's longitude for location-based search.
     */
    public function getLongitude(): ?float {
        return $this->longitude;
    }

    /**
     * Get the URL to the user's avatar image.
     */
    public function getAvatarUrl(): ?string {
        return $this->avatar_url;
    }

    /**
     * Get the user's average rating from reviews.
     */
    public function getAvgRating(): ?float {
        return $this->avg_rating;
    }

    /**
     * Get the total number of reviews received by this user.
     */
    public function getTotalReviews(): int {
        return $this->total_reviews;
    }

    /**
     * Check if the user has admin privileges.
     * 
     * @return bool True if user is an admin, false otherwise
     */
    public function isAdmin(): bool {
        return $this->is_admin;
    }

    /**
     * Get the timestamp when the user was created.
     */
    public function getCreatedAt(): string {
        return $this->created_at;
    }

    /**
     * Create a User from database row.
     * 
     * @param array $row Associative array from database
     * @return self
     */
    public static function fromDatabase(array $row): self {
        return new self(
            (int) $row['id'],
            $row['email'],
            $row['name'],
            $row['phone'],
            $row['address'] ?? null,
            $row['city'] ?? null,
            $row['state'] ?? null,
            $row['postal_code'] ?? null,
            $row['country'] ?? null,
            $row['latitude'] !== null ? (float) $row['latitude'] : null,
            $row['longitude'] !== null ? (float) $row['longitude'] : null,
            $row['avatar_url'] ?? null,
            $row['avg_rating'] !== null ? (float) $row['avg_rating'] : null,
            (int) ($row['total_reviews'] ?? 0),
            (bool) ($row['is_admin'] ?? false),
            $row['created_at']
        );
    }
}

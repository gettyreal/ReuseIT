<?php
namespace ReuseIT\ValueObjects;

use InvalidArgumentException;

/**
 * Rating
 *
 * Immutable value object for 1-5 star ratings.
 * Enforces whole-number rating constraint and provides star visualization.
 */
class Rating {
    private int $value;

    /**
     * Construct a rating value.
     *
     * @param int $value Rating value (must be 1-5 inclusive)
     * @throws InvalidArgumentException If value is not between 1 and 5
     */
    public function __construct(int $value) {
        if ($value < 1 || $value > 5) {
            throw new InvalidArgumentException("Rating must be between 1 and 5, got {$value}");
        }
        $this->value = $value;
    }

    /**
     * Get the numeric rating value.
     *
     * @return int Rating value (1-5)
     */
    public function getValue(): int {
        return $this->value;
    }

    /**
     * Get visual star representation.
     *
     * @return string Stars representation (e.g., "★★★★☆" for 4 stars)
     */
    public function getAsStars(): string {
        $filled = str_repeat('★', $this->value);
        $empty = str_repeat('☆', 5 - $this->value);
        return $filled . $empty;
    }

    /**
     * Compare two ratings for equality.
     *
     * @param Rating $other Rating to compare
     * @return bool True if ratings are equal
     */
    public function equals(Rating $other): bool {
        return $this->value === $other->value;
    }

    /**
     * Get string representation.
     *
     * @return string Numeric string "1" through "5"
     */
    public function __toString(): string {
        return (string) $this->value;
    }
}

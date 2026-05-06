-- Phase 7: Reviews & Reputation System Migration
-- Date: 2026-05-06
-- Purpose: Add reviews table with user denormalization and immutability constraints

-- ============================================================================
-- 1. Create reviews table with booking reference and immutability
-- ============================================================================

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reviewer_user_id INT NOT NULL,
  reviewed_user_id INT NOT NULL,
  booking_id INT NOT NULL UNIQUE,
  rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
  comment VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  
  -- Indexes for query performance
  INDEX idx_reviewed_user_created (reviewed_user_id, created_at),
  INDEX idx_booking_id (booking_id),
  INDEX idx_reviewer_user_created (reviewer_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. Add denormalization columns to users table for average rating display
-- ============================================================================

ALTER TABLE users ADD COLUMN IF NOT EXISTS avg_rating DECIMAL(3,2) NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS total_reviews INT DEFAULT 0;

-- Index for "top-rated" queries
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_avg_rating (avg_rating);

-- ============================================================================
-- 3. Create review_rate_limits table for spam prevention (1 per 24 hours)
-- ============================================================================

CREATE TABLE IF NOT EXISTS review_rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  submitted_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  
  -- Indexes for rate limit lookups
  INDEX idx_user_submitted (user_id, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

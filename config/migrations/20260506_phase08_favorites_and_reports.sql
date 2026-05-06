-- Phase 8: Favorites & Admin Reporting System Migration
-- Date: 2026-05-06
-- Purpose: Add favorites and reports tables for phase 8 polish

-- ============================================================================
-- 1. Create favorites table with user-listing uniqueness constraint
-- ============================================================================

CREATE TABLE IF NOT EXISTS favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  
  -- Prevent duplicate favorites for same user-listing pair
  UNIQUE KEY unique_user_listing_active (user_id, listing_id, deleted_at),
  
  -- Indexes for efficient queries
  INDEX idx_user_created (user_id, created_at DESC),
  INDEX idx_listing_created (listing_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. Create reports table for content moderation
-- ============================================================================

CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT NOT NULL,
  reported_type ENUM('listing', 'user') NOT NULL,
  reported_id INT NOT NULL,
  reason ENUM('spam', 'fake', 'illegal', 'harmful') NOT NULL,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  
  -- Prevent self-reporting of users (business logic validation in repository)
  -- Constraint checked: if reported_type='user' then reporter_id != reported_id
  
  -- Indexes for efficient queries
  INDEX idx_status_created (status, created_at DESC),
  INDEX idx_reported_content (reported_type, reported_id),
  INDEX idx_reporter_created (reporter_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. Add admin role field to users table
-- ============================================================================

ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_is_admin (is_admin);

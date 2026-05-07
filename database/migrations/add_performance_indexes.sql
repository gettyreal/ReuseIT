-- Performance Indexes for Phase 8 - Favorites and Reports
-- This script adds strategic indexes on hot query paths

-- Favorites table indexes
-- Purpose: getFavorites(user_id) paginated list - orders by created_at DESC
ALTER TABLE favorites 
ADD INDEX idx_user_id_created_at (user_id, created_at DESC);

-- Reports table indexes
-- Purpose: getReportQueue(status) paginated list - orders by created_at DESC
ALTER TABLE reports 
ADD INDEX idx_status_created_at (status, created_at DESC);

-- Purpose: getReportsByReporter(reporter_id) 
ALTER TABLE reports 
ADD INDEX idx_reporter_id (reporter_id);

-- Purpose: getReportsByContent(reported_type, reported_id) - check existing reports
ALTER TABLE reports 
ADD INDEX idx_reported_content (reported_type, reported_id);

-- Listings table
-- Purpose: User's listings list endpoint - user_id with created_at DESC ordering
ALTER TABLE listings 
ADD INDEX idx_user_id_created_at (user_id, created_at DESC);

-- Verify indexes were created
-- Run these queries to verify:
-- SHOW INDEXES FROM favorites WHERE Key_name = 'idx_user_id_created_at';
-- SHOW INDEXES FROM reports WHERE Key_name IN ('idx_status_created_at', 'idx_reporter_id', 'idx_reported_content');
-- SHOW INDEXES FROM listings WHERE Key_name = 'idx_user_id_created_at';

-- Performance verification queries:
-- EXPLAIN SELECT * FROM favorites WHERE user_id=1 ORDER BY created_at DESC LIMIT 20;
-- EXPLAIN SELECT * FROM reports WHERE status='pending' ORDER BY created_at DESC LIMIT 20;
-- EXPLAIN SELECT * FROM listings WHERE user_id=1 AND hidden_at IS NULL ORDER BY created_at DESC LIMIT 20;

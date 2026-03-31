-- Phase 06 Plan 01: Booking workflow schema foundation
-- Safe for existing environments by using IF NOT EXISTS guards.

ALTER TABLE bookings
    MODIFY COLUMN booking_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NULL AFTER booking_status,
    ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL AFTER completed_at,
    ADD COLUMN IF NOT EXISTS cancelled_by_user_id BIGINT NULL AFTER cancelled_at,
    ADD COLUMN IF NOT EXISTS cancel_reason_code VARCHAR(50) NULL AFTER cancelled_by_user_id,
    ADD COLUMN IF NOT EXISTS cancel_reason_note TEXT NULL AFTER cancel_reason_code,
    ADD COLUMN IF NOT EXISTS active_listing_id BIGINT
      AS (
        CASE
          WHEN booking_status IN ('pending', 'confirmed') AND deleted_at IS NULL THEN listing_id
          ELSE NULL
        END
      ) STORED;

SET @booking_status_check_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND CONSTRAINT_TYPE = 'CHECK'
      AND CONSTRAINT_NAME = 'chk_bookings_status'
);

SET @booking_status_check_sql := IF(
    @booking_status_check_exists = 0,
    'ALTER TABLE bookings ADD CONSTRAINT chk_bookings_status CHECK (booking_status IN (''pending'',''confirmed'',''completed'',''cancelled''))',
    'SELECT 1'
);

PREPARE booking_status_check_stmt FROM @booking_status_check_sql;
EXECUTE booking_status_check_stmt;
DEALLOCATE PREPARE booking_status_check_stmt;

CREATE INDEX IF NOT EXISTS idx_bookings_expires_at ON bookings (expires_at);
CREATE INDEX IF NOT EXISTS idx_bookings_updated_at ON bookings (updated_at);
CREATE INDEX IF NOT EXISTS idx_bookings_buyer_status_urgency ON bookings (buyer_id, booking_status, expires_at, updated_at);
CREATE INDEX IF NOT EXISTS idx_bookings_seller_status_urgency ON bookings (seller_id, booking_status, expires_at, updated_at);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_bookings_active_listing ON bookings (active_listing_id);

ALTER TABLE bookings
    ADD CONSTRAINT fk_bookings_cancelled_by_user
    FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS booking_pickup_windows (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    booking_id BIGINT NOT NULL,
    proposed_by_user_id BIGINT NOT NULL,
    responded_by_user_id BIGINT NULL,
    window_start_at TIMESTAMP NOT NULL,
    window_end_at TIMESTAMP NOT NULL,
    proposal_status VARCHAR(20) NOT NULL DEFAULT 'proposed',
    note TEXT NULL,
    responded_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_booking_pickup_windows_booking
      FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_pickup_windows_proposed_by
      FOREIGN KEY (proposed_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_pickup_windows_responded_by
      FOREIGN KEY (responded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT chk_booking_pickup_windows_status
      CHECK (proposal_status IN ('proposed', 'countered', 'accepted', 'superseded', 'cancelled')),
    CONSTRAINT chk_booking_pickup_windows_window
      CHECK (window_end_at > window_start_at),

    INDEX idx_booking_pickup_windows_booking (booking_id),
    INDEX idx_booking_pickup_windows_booking_status (booking_id, proposal_status),
    INDEX idx_booking_pickup_windows_start (window_start_at),
    INDEX idx_booking_pickup_windows_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    booking_id BIGINT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    actor_user_id BIGINT NULL,
    reason_code VARCHAR(50) NULL,
    note TEXT NULL,
    event_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_booking_events_booking
      FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_events_actor_user
      FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT chk_booking_events_type
      CHECK (event_type IN ('created', 'confirmed', 'pickup_proposed', 'pickup_countered', 'pickup_accepted', 'cancelled', 'expired', 'completed')),

    INDEX idx_booking_events_booking_event_at (booking_id, event_at),
    INDEX idx_booking_events_booking_id (booking_id),
    INDEX idx_booking_events_event_at (event_at),
    INDEX idx_booking_events_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

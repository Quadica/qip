-- ============================================================================
-- Micro-ID Decode Logs Table
-- ============================================================================
-- Purpose: Log all Micro-ID decode attempts for troubleshooting and analytics
-- Plugin: qsa-engraving
-- Created: 2026-01-13
-- ============================================================================
--
-- INSTALLATION INSTRUCTIONS:
-- 1. Replace {prefix} with your WordPress table prefix:
--    - luxeonstar.com: lw_
--    - handlaidtrack.com: fwp_
-- 2. Run this script via phpMyAdmin on the target database
-- 3. Verify table creation with: SHOW TABLES LIKE '%quad_microid_decode_logs%';
--
-- ============================================================================

CREATE TABLE IF NOT EXISTS {prefix}quad_microid_decode_logs (
    -- Primary key
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Session tracking (for grouping multiple attempts)
    session_id VARCHAR(64) NOT NULL COMMENT 'Unique session identifier for grouping attempts',

    -- Image metadata
    image_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of uploaded image for deduplication',
    image_path VARCHAR(500) DEFAULT NULL COMMENT 'Path to stored image file (if retained)',
    image_size_bytes INT UNSIGNED DEFAULT NULL COMMENT 'Original image file size',
    image_width INT UNSIGNED DEFAULT NULL COMMENT 'Image width in pixels',
    image_height INT UNSIGNED DEFAULT NULL COMMENT 'Image height in pixels',

    -- Decode results
    decoded_serial VARCHAR(8) DEFAULT NULL COMMENT '8-digit serial number if successfully decoded',
    serial_found TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether serial exists in quad_serial_numbers',

    -- Status tracking
    decode_status ENUM('success', 'failed', 'error', 'invalid_image') NOT NULL COMMENT 'Overall decode outcome',
    error_code VARCHAR(50) DEFAULT NULL COMMENT 'Machine-readable error code',
    error_message TEXT DEFAULT NULL COMMENT 'Human-readable error description',

    -- API metrics
    api_response_time_ms INT UNSIGNED DEFAULT NULL COMMENT 'Claude API response time in milliseconds',
    api_tokens_used INT UNSIGNED DEFAULT NULL COMMENT 'API tokens consumed (for cost tracking)',

    -- Request metadata
    client_ip VARCHAR(45) DEFAULT NULL COMMENT 'Client IP address (IPv4 or IPv6)',
    user_agent VARCHAR(500) DEFAULT NULL COMMENT 'Browser/client user agent string',
    user_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'WordPress user ID if logged in',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When decode was attempted',

    -- Constraints
    PRIMARY KEY (id),
    INDEX idx_session_id (session_id),
    INDEX idx_decoded_serial (decoded_serial),
    INDEX idx_created_at (created_at),
    INDEX idx_decode_status (decode_status),
    INDEX idx_image_hash (image_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs all Micro-ID decode attempts for troubleshooting and analytics';

-- ============================================================================
-- Verification Query
-- ============================================================================
-- After running, verify with:
-- DESCRIBE {prefix}quad_microid_decode_logs;
-- ============================================================================

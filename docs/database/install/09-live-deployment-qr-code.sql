-- =============================================================================
-- QSA Engraving - Live Site Deployment (QR Code Implementation)
-- =============================================================================
-- Date: 2026-01-09
-- Purpose: Fresh deployment with QR code support
-- Author: Claude Code (database-specialist)
--
-- IMPORTANT: This script assumes you want to DELETE all existing engraving data
-- and start fresh. Run this AFTER copying plugin files.
--
-- Order of Operations:
--   1. Truncate operational data tables
--   2. Create new QR code tables
--   3. Modify config table for QR code support
--   4. Seed QR code configuration
--
-- Post-Deployment:
--   1. Flush WordPress permalinks (Settings -> Permalinks -> Save)
--   2. Add Kinsta redirect for quadi.ca (if not already done)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- STEP 1: Clear ALL Operational Data (as requested)
-- -----------------------------------------------------------------------------
-- Order matters due to foreign key-like relationships

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `lw_quad_serial_numbers`;
TRUNCATE TABLE `lw_quad_engraved_modules`;
TRUNCATE TABLE `lw_quad_engraving_batches`;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- STEP 2: Create QSA Design Sequences Table (Counter for atomic ID allocation)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lw_quad_qsa_design_sequences` (
    `design` VARCHAR(10) NOT NULL
        COMMENT 'Design name (e.g., "CUBE", "STAR", "PICO")',

    `current_sequence` INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Last allocated sequence number for this design',

    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'When counter was last incremented',

    PRIMARY KEY (`design`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-design sequence counters for atomic QSA ID allocation';

-- -----------------------------------------------------------------------------
-- STEP 3: Create QSA Identifiers Table
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lw_quad_qsa_identifiers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Primary key',

    `qsa_id` VARCHAR(20) NOT NULL
        COMMENT 'Human-readable QSA identifier (e.g., "CUBE00076")',

    `design` VARCHAR(10) NOT NULL
        COMMENT 'Base design name (e.g., "CUBE", "STAR", "PICO")',

    `sequence_number` INT UNSIGNED NOT NULL
        COMMENT 'Per-design sequential number (1, 2, 3... per design)',

    `batch_id` BIGINT UNSIGNED NOT NULL
        COMMENT 'FK to quad_engraving_batches.id',

    `qsa_sequence` SMALLINT UNSIGNED NOT NULL
        COMMENT 'QSA sequence within the batch (1, 2, 3...)',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When this QSA ID was assigned',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_qsa_id` (`qsa_id`),
    UNIQUE KEY `uk_design_sequence` (`design`, `sequence_number`),
    UNIQUE KEY `uk_batch_qsa` (`batch_id`, `qsa_sequence`),
    KEY `idx_design` (`design`),

    CONSTRAINT `chk_qsa_id_format` CHECK (
        `qsa_id` REGEXP '^[A-Z]{1,10}[0-9]{5}$'
    ),
    CONSTRAINT `chk_sequence_number_positive` CHECK (
        `sequence_number` >= 1
    ),
    CONSTRAINT `chk_qsa_sequence_positive` CHECK (
        `qsa_sequence` >= 1
    ),
    CONSTRAINT `chk_design_uppercase` CHECK (
        `design` REGEXP '^[A-Z]+$'
    )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='QSA-level identifiers linking arrays to QR codes for reporting';

-- -----------------------------------------------------------------------------
-- STEP 4: Add element_size Column to Config Table
-- -----------------------------------------------------------------------------
-- Check if column exists first (idempotent)

SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'lw_quad_qsa_config'
    AND COLUMN_NAME = 'element_size'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `lw_quad_qsa_config` ADD COLUMN `element_size` DECIMAL(5,2) DEFAULT NULL COMMENT ''Element size in mm. NULL = use element-specific default (QR: 10mm)'' AFTER `text_height`',
    'SELECT ''element_size column already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- STEP 5: Remove All Data Matrix Entries from Config
-- -----------------------------------------------------------------------------

DELETE FROM `lw_quad_qsa_config` WHERE `element_type` = 'datamatrix';

-- -----------------------------------------------------------------------------
-- STEP 6: Modify element_type ENUM (replace datamatrix with qr_code)
-- -----------------------------------------------------------------------------

ALTER TABLE `lw_quad_qsa_config`
    MODIFY COLUMN `element_type` ENUM(
        'micro_id',
        'qr_code',
        'module_id',
        'serial_url',
        'led_code_1',
        'led_code_2',
        'led_code_3',
        'led_code_4',
        'led_code_5',
        'led_code_6',
        'led_code_7',
        'led_code_8',
        'led_code_9'
    ) NOT NULL
        COMMENT 'Type of element to engrave (qr_code replaces datamatrix)';

-- -----------------------------------------------------------------------------
-- STEP 7: Seed QR Code Configuration (position=0 = design-level)
-- -----------------------------------------------------------------------------
-- Coordinates from Ron: x=139.1167, y=56.85, size=10mm
-- IDEMPOTENT: Uses INSERT ... ON DUPLICATE KEY UPDATE

-- STARa QR code
INSERT INTO `lw_quad_qsa_config`
    (qsa_design, revision, position, element_type, origin_x, origin_y, rotation, text_height, element_size, is_active)
VALUES
    ('STAR', 'a', 0, 'qr_code', 139.117, 56.850, 0, NULL, 10.00, 1)
ON DUPLICATE KEY UPDATE
    origin_x = VALUES(origin_x),
    origin_y = VALUES(origin_y),
    element_size = VALUES(element_size),
    is_active = VALUES(is_active);

-- CUBEa QR code
INSERT INTO `lw_quad_qsa_config`
    (qsa_design, revision, position, element_type, origin_x, origin_y, rotation, text_height, element_size, is_active)
VALUES
    ('CUBE', 'a', 0, 'qr_code', 139.117, 56.850, 0, NULL, 10.00, 1)
ON DUPLICATE KEY UPDATE
    origin_x = VALUES(origin_x),
    origin_y = VALUES(origin_y),
    element_size = VALUES(element_size),
    is_active = VALUES(is_active);

-- PICOa QR code
INSERT INTO `lw_quad_qsa_config`
    (qsa_design, revision, position, element_type, origin_x, origin_y, rotation, text_height, element_size, is_active)
VALUES
    ('PICO', 'a', 0, 'qr_code', 139.117, 56.850, 0, NULL, 10.00, 1)
ON DUPLICATE KEY UPDATE
    origin_x = VALUES(origin_x),
    origin_y = VALUES(origin_y),
    element_size = VALUES(element_size),
    is_active = VALUES(is_active);

-- -----------------------------------------------------------------------------
-- VERIFICATION QUERIES (run these after to confirm success)
-- -----------------------------------------------------------------------------

-- Check new tables exist
-- SHOW TABLES LIKE 'lw_quad_qsa%';

-- Check QR code configurations
-- SELECT qsa_design, revision, position, element_type, origin_x, origin_y, element_size
-- FROM lw_quad_qsa_config
-- WHERE element_type = 'qr_code' AND position = 0;

-- Check element_type ENUM includes qr_code
-- SHOW COLUMNS FROM lw_quad_qsa_config LIKE 'element_type';

-- =============================================================================
-- END OF DEPLOYMENT SCRIPT
-- =============================================================================

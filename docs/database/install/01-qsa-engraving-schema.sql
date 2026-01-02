-- =============================================================================
-- QSA Engraving System - Initial Schema
-- =============================================================================
-- Purpose:     Create core tables for QSA module engraving tracking
-- Author:      Claude Code (database-specialist)
-- Date:        2025-12-31
-- Version:     1.0
--
-- Dependencies: None (initial schema)
-- WordPress:   6.8+
-- WooCommerce: 9.9+
--
-- Execution:   Run manually via phpMyAdmin or MySQL CLI
-- Environment: Both staging and production
--
-- Table Prefix: This script uses {prefix} placeholder.
--               Replace with actual prefix before execution:
--               - luxeonstar.com: lw_
--               - handlaidtrack.com: fwp_
--
-- IMPORTANT: Review and test on staging before production deployment
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table 1: Serial Numbers
-- -----------------------------------------------------------------------------
-- Purpose: Track all serial numbers assigned to engraved modules
--
-- Key Constraints:
--   - serial_number: 8-char zero-padded string (00000001 to 01048575)
--   - serial_integer: Numeric representation for range queries (max 2^20 - 1)
--   - Status lifecycle: reserved -> engraved OR reserved -> voided
--
-- Relationships:
--   - engraving_batch_id -> {prefix}quad_engraving_batches.id
--   - production_batch_id -> oms_batch_items.batch_id (legacy OM system, read-only)
--   - order_id -> wp_posts.ID where post_type = 'shop_order'
--
-- Volume Estimate: ~1M max capacity, ~85k/year growth = 12+ years runway
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `{prefix}quad_serial_numbers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Primary key',

    `serial_number` CHAR(8) NOT NULL
        COMMENT 'Zero-padded serial string (e.g., "00123456")',

    `serial_integer` INT UNSIGNED NOT NULL
        COMMENT 'Numeric value for range queries. Range: 1 to 1048575 (2^20-1)',

    `module_sku` VARCHAR(20) NOT NULL
        COMMENT 'Module SKU from oms_batch_items.assembly_sku (e.g., "STAR-34924")',

    `engraving_batch_id` BIGINT UNSIGNED NOT NULL
        COMMENT 'FK to quad_engraving_batches.id',

    `production_batch_id` BIGINT UNSIGNED NOT NULL
        COMMENT 'FK to legacy oms_batch_items.batch_id',

    `order_id` BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'FK to wp_posts.ID (shop_order). NULL if not yet associated',

    `qsa_sequence` SMALLINT UNSIGNED NOT NULL
        COMMENT 'Which QSA in the engraving batch (1, 2, 3...)',

    `array_position` TINYINT UNSIGNED NOT NULL
        COMMENT 'Position 1-8 on the QSA board',

    `status` ENUM('reserved', 'engraved', 'voided') NOT NULL DEFAULT 'reserved'
        COMMENT 'Lifecycle state. reserved->engraved (success) or reserved->voided (cancelled/retry)',

    `reserved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When serial was reserved (SVG generated)',

    `engraved_at` DATETIME DEFAULT NULL
        COMMENT 'When engraving was confirmed by operator',

    `voided_at` DATETIME DEFAULT NULL
        COMMENT 'When serial was voided (retry/cancel)',

    `created_by` BIGINT UNSIGNED NOT NULL
        COMMENT 'WordPress user ID who created the batch',

    PRIMARY KEY (`id`),

    -- Uniqueness constraints - serial numbers are never recycled
    UNIQUE KEY `uk_serial_integer` (`serial_integer`),
    UNIQUE KEY `uk_serial_number` (`serial_number`),

    -- Query optimization indexes
    KEY `idx_engraving_batch` (`engraving_batch_id`),
    KEY `idx_production_batch` (`production_batch_id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_status` (`status`),
    KEY `idx_module_sku` (`module_sku`),

    -- Composite index for common query: find serials by batch and status
    KEY `idx_batch_status` (`engraving_batch_id`, `status`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Serial numbers for engraved QSA modules. Max capacity: 1,048,575';


-- -----------------------------------------------------------------------------
-- Table 2: Engraving Batches
-- -----------------------------------------------------------------------------
-- Purpose: Track operator-created engraving sessions
--
-- A batch represents a single engraving session where the operator selects
-- modules to engrave. Each batch contains one or more QSAs (rows in the
-- engraved_modules table).
--
-- Status Transitions:
--   in_progress: Batch created, operator actively engraving
--   completed: All rows marked done, batch finalized
--
-- Volume Estimate: ~20-50 batches/week = ~1-2.5k/year
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `{prefix}quad_engraving_batches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Primary key, referenced by serial_numbers and engraved_modules',

    `batch_name` VARCHAR(100) DEFAULT NULL
        COMMENT 'Optional descriptive name for the batch',

    `module_count` INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Total number of modules in this batch',

    `qsa_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Number of QSA arrays in this batch',

    `status` ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress'
        COMMENT 'Batch status. in_progress->completed when all rows done',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When batch was created',

    `created_by` BIGINT UNSIGNED NOT NULL
        COMMENT 'WordPress user ID who created the batch',

    `completed_at` DATETIME DEFAULT NULL
        COMMENT 'When batch was marked completed',

    PRIMARY KEY (`id`),

    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_created_by` (`created_by`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Engraving batch sessions created by operators';


-- -----------------------------------------------------------------------------
-- Table 3: Engraved Modules
-- -----------------------------------------------------------------------------
-- Purpose: Track individual modules within engraving batches
--
-- Each row represents one module on one QSA. This table prevents duplicate
-- engraving by tracking which modules from oms_batch_items have been processed.
--
-- Key Integration:
--   - Links to oms_batch_items via production_batch_id + module_sku + order_id
--   - Used to filter "modules awaiting engraving" list
--
-- Row Status:
--   pending: Module assigned to batch, not yet engraved
--   done: Module successfully engraved
--
-- Volume Estimate: ~85k modules/year
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `{prefix}quad_engraved_modules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Primary key',

    `engraving_batch_id` BIGINT UNSIGNED NOT NULL
        COMMENT 'FK to quad_engraving_batches.id',

    `production_batch_id` BIGINT UNSIGNED NOT NULL
        COMMENT 'FK to legacy oms_batch_items.batch_id',

    `module_sku` VARCHAR(20) NOT NULL
        COMMENT 'Module SKU from oms_batch_items.assembly_sku',

    `order_id` BIGINT UNSIGNED NOT NULL
        COMMENT 'FK to wp_posts.ID (shop_order) from oms_batch_items.order_no',

    `serial_number` CHAR(8) NOT NULL
        COMMENT 'Assigned serial number (FK to quad_serial_numbers.serial_number)',

    `qsa_sequence` SMALLINT UNSIGNED NOT NULL
        COMMENT 'Which QSA in the batch this module is on (1, 2, 3...)',

    `array_position` TINYINT UNSIGNED NOT NULL
        COMMENT 'Position 1-8 on the QSA board',

    `row_status` ENUM('pending', 'done') NOT NULL DEFAULT 'pending'
        COMMENT 'Engraving status for this module row',

    `engraved_at` DATETIME DEFAULT NULL
        COMMENT 'When row was marked done',

    PRIMARY KEY (`id`),

    -- Ensure each position in a batch is unique
    UNIQUE KEY `uk_batch_position` (`engraving_batch_id`, `qsa_sequence`, `array_position`),

    KEY `idx_engraving_batch` (`engraving_batch_id`),
    KEY `idx_production_batch` (`production_batch_id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_row_status` (`row_status`),
    KEY `idx_serial_number` (`serial_number`),

    -- Composite index for "modules already engraved" query
    KEY `idx_done_modules` (`production_batch_id`, `module_sku`, `row_status`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual modules within engraving batches';


-- -----------------------------------------------------------------------------
-- Table 4: QSA Engraving Configuration
-- -----------------------------------------------------------------------------
-- Purpose: Store element coordinates for each QSA design
--
-- Each QSA design (CORE, SOLO, STAR, etc.) has different physical layouts.
-- This table stores the X/Y coordinates for each engravable element at each
-- of the 8 module positions.
--
-- Coordinate System:
--   - Origin: Bottom-left of QSA (CAD format)
--   - Units: Millimeters
--   - Note: SVG uses top-left origin, so transform: svg_y = 113.7 - cad_y
--
-- Element Types:
--   - micro_id: 5x5 Micro-ID dot matrix (1.0mm x 1.0mm)
--   - datamatrix: ECC 200 barcode (14mm x 6.5mm)
--   - module_id: Module ID text (e.g., "CORE-91247")
--   - serial_url: Serial URL text (e.g., "quadi.ca/00123456")
--   - led_code_1 through led_code_9: LED code positions
--
-- Revision Handling:
--   - revision = NULL: Configuration applies to all revisions of this design
--   - revision = 'a', 'b', etc.: Configuration specific to that revision
--
-- Volume Estimate: ~50-100 rows per design, ~10-20 designs = ~500-2000 rows total
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `{prefix}quad_qsa_config` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Primary key',

    `qsa_design` VARCHAR(10) NOT NULL
        COMMENT 'Base design name (e.g., "CORE", "SOLO", "STAR")',

    `revision` CHAR(1) DEFAULT NULL
        COMMENT 'Revision letter (e.g., "a") or NULL for all revisions',

    `position` TINYINT UNSIGNED NOT NULL
        COMMENT 'Module position on QSA (1-8)',

    `element_type` ENUM(
        'micro_id',
        'datamatrix',
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
        COMMENT 'Type of element to engrave',

    `origin_x` DECIMAL(8,3) NOT NULL
        COMMENT 'X coordinate in mm from bottom-left of QSA',

    `origin_y` DECIMAL(8,3) NOT NULL
        COMMENT 'Y coordinate in mm from bottom-left of QSA',

    `rotation` SMALLINT NOT NULL DEFAULT 0
        COMMENT 'Rotation in degrees (0, 90, 180, 270)',

    `text_height` DECIMAL(5,2) DEFAULT NULL
        COMMENT 'Text height in mm (for text elements only)',

    `is_active` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Whether this configuration is active (soft delete)',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When configuration was created',

    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'When configuration was last modified',

    `created_by` BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'WordPress user ID who created this config',

    PRIMARY KEY (`id`),

    -- Ensure unique element per design/revision/position
    UNIQUE KEY `uk_design_element` (`qsa_design`, `revision`, `position`, `element_type`),

    KEY `idx_qsa_design` (`qsa_design`),
    KEY `idx_design_revision` (`qsa_design`, `revision`),
    KEY `idx_active` (`is_active`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Engraving element coordinates per QSA design and position';


-- =============================================================================
-- End of Schema
-- =============================================================================

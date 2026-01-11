-- =============================================================================
-- QSA Engraving System - QSA Identifiers Schema
-- =============================================================================
-- Purpose:     Create QSA-level identifier table for QR code system
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-08
-- Version:     1.1 (added CHECK constraints, sequence counter table, updated comments)
--
-- Dependencies: 01-qsa-engraving-schema.sql (quad_engraving_batches table)
-- WordPress:   6.8+
-- WooCommerce: 9.9+
-- MariaDB:     11.4+ (required for CHECK constraints)
--
-- Execution:   Run manually via phpMyAdmin or MySQL CLI
-- Environment: Both staging and production
--
-- Table Prefix: lw_ (luxeonstar.com only - this plugin is not used on handlaidtrack.com)
--
-- IMPORTANT: Review and test on staging before production deployment
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table 1: QSA Design Sequences (Counter Table)
-- -----------------------------------------------------------------------------
-- Purpose: Concurrency-safe per-design sequence allocation
--
-- This counter table pattern ensures atomic sequence number allocation:
--   1. INSERT ... ON DUPLICATE KEY UPDATE increments counter atomically
--   2. LAST_INSERT_ID() retrieves the allocated sequence number
--   3. Unique constraint on identifiers table catches any edge cases
--
-- Usage in repository:
--   INSERT INTO lw_quad_qsa_design_sequences (design, current_sequence)
--   VALUES ('CUBE', LAST_INSERT_ID(1))
--   ON DUPLICATE KEY UPDATE current_sequence = LAST_INSERT_ID(current_sequence + 1);
--   SELECT LAST_INSERT_ID() AS next_sequence;
--
-- Note: Gaps may occur if transactions roll back or errors occur during insert.
--       This is acceptable - sequence numbers need not be gapless, only unique.
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
-- Table 2: QSA Identifiers
-- -----------------------------------------------------------------------------
-- Purpose: Track QSA-level identifiers that link arrays to QR codes
--
-- Each QSA array receives a unique identifier (e.g., CUBE00076) that is:
--   - Encoded in the QR code as quadi.ca/{qsa_id}
--   - Assigned at SVG generation time (Start Row click)
--   - Persistent across regenerations (same batch/sequence keeps same ID)
--   - Sequential per design (CUBE00001, CUBE00002... separate from STAR00001...)
--
-- Key Constraints:
--   - qsa_id: Unique human-readable identifier (format: {DESIGN}{5-digit})
--   - design + sequence_number: Unique pairing per design (gaps allowed)
--   - batch_id + qsa_sequence: Prevents duplicate IDs for same physical array
--   - CHECK constraints validate format and bounds
--
-- Relationships:
--   - batch_id -> lw_quad_engraving_batches.id
--   - Linked to modules via batch_id + qsa_sequence join
--
-- Volume Estimate: ~10k QSAs/year (85k modules / 8 per QSA)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lw_quad_qsa_identifiers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Primary key',

    `qsa_id` VARCHAR(20) NOT NULL
        COMMENT 'Human-readable QSA identifier (e.g., "CUBE00076")',

    `design` VARCHAR(10) NOT NULL
        COMMENT 'Base design name (e.g., "CUBE", "STAR", "PICO")',

    `sequence_number` INT UNSIGNED NOT NULL
        COMMENT 'Per-design sequential number (1, 2, 3... per design). Gaps may occur.',

    `batch_id` BIGINT UNSIGNED NOT NULL
        COMMENT 'FK to quad_engraving_batches.id',

    `qsa_sequence` SMALLINT UNSIGNED NOT NULL
        COMMENT 'QSA sequence within the batch (1, 2, 3...)',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When this QSA ID was assigned',

    PRIMARY KEY (`id`),

    -- Ensure each QSA ID is globally unique
    UNIQUE KEY `uk_qsa_id` (`qsa_id`),

    -- Ensure unique pairing per design (note: gaps may occur, see counter table)
    UNIQUE KEY `uk_design_sequence` (`design`, `sequence_number`),

    -- Prevent duplicate IDs for same physical array in a batch
    UNIQUE KEY `uk_batch_qsa` (`batch_id`, `qsa_sequence`),

    -- Index for filtering by design (e.g., "show all CUBE arrays")
    KEY `idx_design` (`design`),

    -- CHECK: qsa_id must be uppercase alphanumeric followed by 5 digits
    -- Format: {1-10 uppercase alphanumeric}{exactly 5 digits} e.g., CUBE00076 or SP0300001
    CONSTRAINT `chk_qsa_id_format` CHECK (
        `qsa_id` REGEXP '^[A-Z0-9]{1,10}[0-9]{5}$'
    ),

    -- CHECK: sequence_number must be positive (1 or greater)
    CONSTRAINT `chk_sequence_number_positive` CHECK (
        `sequence_number` >= 1
    ),

    -- CHECK: qsa_sequence must be positive (1 or greater)
    CONSTRAINT `chk_qsa_sequence_positive` CHECK (
        `qsa_sequence` >= 1
    ),

    -- CHECK: design must be uppercase alphanumeric (letters and/or numbers)
    -- Supports both native QSA designs (CUBE, STAR) and legacy codes (SP03, SP01)
    CONSTRAINT `chk_design_alphanumeric` CHECK (
        `design` REGEXP '^[A-Z0-9]+$'
    )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='QSA-level identifiers linking arrays to QR codes for reporting';


-- =============================================================================
-- End of Schema
-- =============================================================================

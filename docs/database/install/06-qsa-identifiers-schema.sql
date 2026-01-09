-- =============================================================================
-- QSA Engraving System - QSA Identifiers Schema
-- =============================================================================
-- Purpose:     Create QSA-level identifier table for QR code system
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-08
-- Version:     1.0
--
-- Dependencies: 01-qsa-engraving-schema.sql (quad_engraving_batches table)
-- WordPress:   6.8+
-- WooCommerce: 9.9+
--
-- Execution:   Run manually via phpMyAdmin or MySQL CLI
-- Environment: Both staging and production
--
-- Table Prefix: lw_ (luxeonstar.com only - this plugin is not used on handlaidtrack.com)
--
-- IMPORTANT: Review and test on staging before production deployment
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: QSA Identifiers
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
--   - design + sequence_number: Ensures sequential numbering per design
--   - batch_id + qsa_sequence: Prevents duplicate IDs for same physical array
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
        COMMENT 'Per-design sequential number (1, 2, 3... per design)',

    `batch_id` BIGINT UNSIGNED NOT NULL
        COMMENT 'FK to quad_engraving_batches.id',

    `qsa_sequence` SMALLINT UNSIGNED NOT NULL
        COMMENT 'QSA sequence within the batch (1, 2, 3...)',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When this QSA ID was assigned',

    PRIMARY KEY (`id`),

    -- Ensure each QSA ID is globally unique
    UNIQUE KEY `uk_qsa_id` (`qsa_id`),

    -- Ensure sequential numbering per design (no gaps from concurrent inserts)
    UNIQUE KEY `uk_design_sequence` (`design`, `sequence_number`),

    -- Prevent duplicate IDs for same physical array in a batch
    UNIQUE KEY `uk_batch_qsa` (`batch_id`, `qsa_sequence`),

    -- Index for filtering by design (e.g., "show all CUBE arrays")
    KEY `idx_design` (`design`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='QSA-level identifiers linking arrays to QR codes for reporting';


-- =============================================================================
-- End of Schema
-- =============================================================================

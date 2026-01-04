-- =============================================================================
-- QSA Engraving System - Add original_qsa_sequence Column
-- =============================================================================
-- Purpose:     Track original QSA sequence assignment for row grouping
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-04
-- Version:     1.1
--
-- Dependencies: 01-qsa-engraving-schema.sql (quad_engraved_modules table)
--
-- Execution:   Run manually via phpMyAdmin or MySQL CLI
-- Environment: Both staging and production
--
-- Table Prefix: Replace {prefix} with actual prefix before execution:
--               - luxeonstar.com: lw_
--               - handlaidtrack.com: fwp_
--
-- IMPORTANT: Review and test on staging before production deployment
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Add original_qsa_sequence column to quad_engraved_modules
-- -----------------------------------------------------------------------------
-- Purpose: Track the QSA sequence originally assigned at batch creation time.
--          This allows row grouping to work correctly even after modules are
--          redistributed across multiple arrays due to start position changes.
--
-- When a row is redistributed (e.g., start position changed from 1 to 5),
-- modules may move to new QSA sequences. The original_qsa_sequence preserves
-- the original grouping so that get_row_qsa_sequences() can identify all
-- modules belonging to the same logical row.
-- -----------------------------------------------------------------------------

ALTER TABLE `{prefix}quad_engraved_modules`
    ADD COLUMN `original_qsa_sequence` SMALLINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Original QSA sequence assigned at batch creation (for row grouping)'
    AFTER `qsa_sequence`;

-- -----------------------------------------------------------------------------
-- Backfill existing data
-- -----------------------------------------------------------------------------
-- For existing modules that haven't been redistributed, set original_qsa_sequence
-- equal to their current qsa_sequence.
-- -----------------------------------------------------------------------------

UPDATE `{prefix}quad_engraved_modules`
SET `original_qsa_sequence` = `qsa_sequence`
WHERE `original_qsa_sequence` = 0;

-- -----------------------------------------------------------------------------
-- Add index for row grouping queries
-- -----------------------------------------------------------------------------
-- This index optimizes the get_row_qsa_sequences() function which groups
-- modules by original_qsa_sequence.
-- -----------------------------------------------------------------------------

ALTER TABLE `{prefix}quad_engraved_modules`
    ADD KEY `idx_original_qsa` (`engraving_batch_id`, `original_qsa_sequence`);

-- =============================================================================
-- End of Migration
-- =============================================================================

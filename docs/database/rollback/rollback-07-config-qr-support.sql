-- =============================================================================
-- QSA Engraving System - Rollback Script for 07-config-qr-support.sql
-- =============================================================================
-- Purpose:     Revert config table to pre-QR code state
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-08
-- Version:     1.0
--
-- WARNING: This script reverts the config table structure!
--          Any qr_code configuration entries will be lost.
--          Data Matrix config would need to be re-seeded separately.
--
-- Table Prefix: lw_ (luxeonstar.com only)
--
-- IMPORTANT: Create a database backup before running this script!
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Step 1: Delete any QR code configuration entries
-- -----------------------------------------------------------------------------
-- Must delete before reverting ENUM or values become invalid

DELETE FROM `lw_quad_qsa_config`
WHERE `element_type` = 'qr_code';


-- -----------------------------------------------------------------------------
-- Step 2: Revert element_type ENUM to original definition
-- -----------------------------------------------------------------------------
-- Restores 'datamatrix' in place of 'qr_code'
-- Note: Data Matrix config data is NOT restored (would need re-seeding)

ALTER TABLE `lw_quad_qsa_config`
    MODIFY COLUMN `element_type` ENUM(
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
        COMMENT 'Type of element to engrave';


-- -----------------------------------------------------------------------------
-- Step 3: Remove element_size column
-- -----------------------------------------------------------------------------

ALTER TABLE `lw_quad_qsa_config`
    DROP COLUMN `element_size`;


-- =============================================================================
-- End of Rollback Script
-- =============================================================================

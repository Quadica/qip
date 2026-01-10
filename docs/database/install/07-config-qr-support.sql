-- =============================================================================
-- QSA Engraving System - Config Table QR Code Support
-- =============================================================================
-- Purpose:     Add QR code support to config table, remove Data Matrix
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-08
-- Version:     1.0
--
-- Dependencies: 01-qsa-engraving-schema.sql (quad_qsa_config table)
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
-- Step 1: Add element_size column
-- -----------------------------------------------------------------------------
-- Purpose: Store configurable element size in mm (primarily for QR codes)
--
-- Placement: After text_height column (related sizing configuration)
-- Default: NULL - column is shared by multiple element types
--
-- IMPORTANT: Repository/renderer code MUST coalesce NULL to type-appropriate default:
--   - QR codes: COALESCE(element_size, 10.0) for 10mm default
--   - Other element types may define their own defaults as needed
--
-- Rationale for NULL default instead of 10.0:
--   - This column may be used by element types other than qr_code in future
--   - A universal default would be semantically incorrect for non-QR elements
--   - Application layer handles the default via QR_Code_Renderer::DEFAULT_SIZE
-- -----------------------------------------------------------------------------

ALTER TABLE `lw_quad_qsa_config`
    ADD COLUMN `element_size` DECIMAL(5,2) DEFAULT NULL
        COMMENT 'Element size in mm. NULL = use element-specific default (QR: 10mm)'
    AFTER `text_height`;


-- -----------------------------------------------------------------------------
-- Step 2: Remove Data Matrix configuration entries
-- -----------------------------------------------------------------------------
-- Purpose: Clean up test data for removed Data Matrix functionality
--
-- Safety: Confirmed safe to purge - this is test data only
-- Impact: Removes all rows where element_type = 'datamatrix'
-- Reversibility: Would require re-seeding if Data Matrix ever restored
-- -----------------------------------------------------------------------------

DELETE FROM `lw_quad_qsa_config`
WHERE `element_type` = 'datamatrix';


-- -----------------------------------------------------------------------------
-- Step 3: Modify element_type ENUM to replace datamatrix with qr_code
-- -----------------------------------------------------------------------------
-- Purpose: Update ENUM definition to support QR codes
--
-- Changes:
--   - Remove 'datamatrix' from ENUM (data already deleted above)
--   - Add 'qr_code' in same position for logical grouping
--
-- ENUM Order (preserved from original, with datamatrix->qr_code):
--   micro_id, qr_code, module_id, serial_url, led_code_1..9
--
-- Note: MySQL requires specifying ALL enum values when modifying
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


-- =============================================================================
-- End of Migration
-- =============================================================================

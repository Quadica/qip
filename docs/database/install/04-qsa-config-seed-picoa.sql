-- =============================================================================
-- QSA Engraving System - PICOa Configuration Seed Data
-- =============================================================================
-- Purpose:     Populate engraving coordinates for PICOa QSA design
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-02
-- Version:     1.0
--
-- Source Data: docs/sample-data/qsa-sample-svg-data.csv
--              (Bottom-left origin, CAD format)
--
-- Dependencies: 01-qsa-engraving-schema.sql must be executed first
-- WordPress:   6.8+
-- WooCommerce: 9.9+
--
-- Execution:   Run manually via phpMyAdmin or MySQL CLI
-- Environment: Both staging and production
--
-- Table Prefix: lw_ (luxeonstar.com only - this plugin is not used on handlaidtrack.com)
--
-- Design Notes:
--   - PICOa modules have a single LED position per module
--   - All coordinates are in mm from bottom-left of QSA
--   - Canvas size: 148mm x 113.7mm
--   - Text uses Roboto Thin font, centered (text-anchor: middle)
--
-- IMPORTANT: Review and test on staging before production deployment
-- =============================================================================

-- Clear existing PICOa configuration (for re-seeding)
DELETE FROM `lw_quad_qsa_config` WHERE `qsa_design` = 'PICO' AND `revision` = 'a';

-- -----------------------------------------------------------------------------
-- PICOa Position 1 (Top-Left)
-- -----------------------------------------------------------------------------
INSERT INTO `lw_quad_qsa_config`
    (`qsa_design`, `revision`, `position`, `element_type`, `origin_x`, `origin_y`, `rotation`, `text_height`, `is_active`, `created_by`)
VALUES
    ('PICO', 'a', 1, 'datamatrix',   29.7215, 95.2849, 0, NULL, 1, 1),
    ('PICO', 'a', 1, 'led_code_1',   30.4483, 85.0944, 0, 1.20, 1, 1),
    ('PICO', 'a', 1, 'micro_id',     32.0244, 79.1399, 0, NULL, 1, 1),
    ('PICO', 'a', 1, 'module_id',    29.7609, 88.1077, 0, 1.30, 1, 1),
    ('PICO', 'a', 1, 'serial_url',   29.7609, 90.3350, 0, 1.20, 1, 1);

-- -----------------------------------------------------------------------------
-- PICOa Position 2
-- -----------------------------------------------------------------------------
INSERT INTO `lw_quad_qsa_config`
    (`qsa_design`, `revision`, `position`, `element_type`, `origin_x`, `origin_y`, `rotation`, `text_height`, `is_active`, `created_by`)
VALUES
    ('PICO', 'a', 2, 'datamatrix',   59.2124, 95.2849, 0, NULL, 1, 1),
    ('PICO', 'a', 2, 'led_code_1',   59.9392, 85.0944, 0, 1.20, 1, 1),
    ('PICO', 'a', 2, 'micro_id',     61.5244, 79.1399, 0, NULL, 1, 1),
    ('PICO', 'a', 2, 'module_id',    59.2518, 88.1077, 0, 1.30, 1, 1),
    ('PICO', 'a', 2, 'serial_url',   59.2518, 90.3350, 0, 1.20, 1, 1);

-- -----------------------------------------------------------------------------
-- PICOa Position 3
-- -----------------------------------------------------------------------------
INSERT INTO `lw_quad_qsa_config`
    (`qsa_design`, `revision`, `position`, `element_type`, `origin_x`, `origin_y`, `rotation`, `text_height`, `is_active`, `created_by`)
VALUES
    ('PICO', 'a', 3, 'datamatrix',   88.7124, 95.2849, 0, NULL, 1, 1),
    ('PICO', 'a', 3, 'led_code_1',   89.4392, 85.0944, 0, 1.20, 1, 1),
    ('PICO', 'a', 3, 'micro_id',     91.0244, 79.1399, 0, NULL, 1, 1),
    ('PICO', 'a', 3, 'module_id',    88.7518, 88.1077, 0, 1.30, 1, 1),
    ('PICO', 'a', 3, 'serial_url',   88.7518, 90.3350, 0, 1.20, 1, 1);

-- -----------------------------------------------------------------------------
-- PICOa Position 4 (Top-Right)
-- -----------------------------------------------------------------------------
INSERT INTO `lw_quad_qsa_config`
    (`qsa_design`, `revision`, `position`, `element_type`, `origin_x`, `origin_y`, `rotation`, `text_height`, `is_active`, `created_by`)
VALUES
    ('PICO', 'a', 4, 'datamatrix',  118.2124, 95.2849, 0, NULL, 1, 1),
    ('PICO', 'a', 4, 'led_code_1',  118.9392, 85.0944, 0, 1.20, 1, 1),
    ('PICO', 'a', 4, 'micro_id',    120.5244, 79.1399, 0, NULL, 1, 1),
    ('PICO', 'a', 4, 'module_id',   118.2518, 88.1077, 0, 1.30, 1, 1),
    ('PICO', 'a', 4, 'serial_url',  118.2518, 90.3350, 0, 1.20, 1, 1);

-- -----------------------------------------------------------------------------
-- PICOa Position 5 (Bottom-Left)
-- -----------------------------------------------------------------------------
INSERT INTO `lw_quad_qsa_config`
    (`qsa_design`, `revision`, `position`, `element_type`, `origin_x`, `origin_y`, `rotation`, `text_height`, `is_active`, `created_by`)
VALUES
    ('PICO', 'a', 5, 'datamatrix',   29.7215, 18.4151, 0, NULL, 1, 1),
    ('PICO', 'a', 5, 'led_code_1',   30.4483, 28.6056, 0, 1.20, 1, 1),
    ('PICO', 'a', 5, 'micro_id',     32.0125, 31.9863, 0, NULL, 1, 1),
    ('PICO', 'a', 5, 'module_id',    29.7609, 25.5923, 0, 1.30, 1, 1),
    ('PICO', 'a', 5, 'serial_url',   29.7609, 23.3650, 0, 1.20, 1, 1);

-- -----------------------------------------------------------------------------
-- PICOa Position 6
-- -----------------------------------------------------------------------------
INSERT INTO `lw_quad_qsa_config`
    (`qsa_design`, `revision`, `position`, `element_type`, `origin_x`, `origin_y`, `rotation`, `text_height`, `is_active`, `created_by`)
VALUES
    ('PICO', 'a', 6, 'datamatrix',   59.2124, 18.4151, 0, NULL, 1, 1),
    ('PICO', 'a', 6, 'led_code_1',   59.9392, 28.6056, 0, 1.20, 1, 1),
    ('PICO', 'a', 6, 'micro_id',     61.5033, 31.9863, 0, NULL, 1, 1),
    ('PICO', 'a', 6, 'module_id',    59.2518, 25.5923, 0, 1.30, 1, 1),
    ('PICO', 'a', 6, 'serial_url',   59.2518, 23.3650, 0, 1.20, 1, 1);

-- -----------------------------------------------------------------------------
-- PICOa Position 7
-- -----------------------------------------------------------------------------
INSERT INTO `lw_quad_qsa_config`
    (`qsa_design`, `revision`, `position`, `element_type`, `origin_x`, `origin_y`, `rotation`, `text_height`, `is_active`, `created_by`)
VALUES
    ('PICO', 'a', 7, 'datamatrix',   88.7124, 18.4151, 0, NULL, 1, 1),
    ('PICO', 'a', 7, 'led_code_1',   89.4392, 28.6056, 0, 1.20, 1, 1),
    ('PICO', 'a', 7, 'micro_id',     91.0033, 31.9863, 0, NULL, 1, 1),
    ('PICO', 'a', 7, 'module_id',    88.7518, 25.5923, 0, 1.30, 1, 1),
    ('PICO', 'a', 7, 'serial_url',   88.7518, 23.3650, 0, 1.20, 1, 1);

-- -----------------------------------------------------------------------------
-- PICOa Position 8 (Bottom-Right)
-- -----------------------------------------------------------------------------
INSERT INTO `lw_quad_qsa_config`
    (`qsa_design`, `revision`, `position`, `element_type`, `origin_x`, `origin_y`, `rotation`, `text_height`, `is_active`, `created_by`)
VALUES
    ('PICO', 'a', 8, 'datamatrix',  118.2124, 18.4151, 0, NULL, 1, 1),
    ('PICO', 'a', 8, 'led_code_1',  118.9392, 28.6056, 0, 1.20, 1, 1),
    ('PICO', 'a', 8, 'micro_id',    120.5033, 31.9863, 0, NULL, 1, 1),
    ('PICO', 'a', 8, 'module_id',   118.2518, 25.5923, 0, 1.30, 1, 1),
    ('PICO', 'a', 8, 'serial_url',  118.2518, 23.3650, 0, 1.20, 1, 1);

-- =============================================================================
-- Verification Query
-- =============================================================================
-- Run this after INSERT to verify data:
-- SELECT qsa_design, revision, position, element_type, origin_x, origin_y, text_height
-- FROM `lw_quad_qsa_config`
-- WHERE qsa_design = 'PICO' AND revision = 'a'
-- ORDER BY position, element_type;
--
-- Expected: 40 rows (8 positions × 5 elements each)
-- =============================================================================

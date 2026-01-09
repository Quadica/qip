-- ============================================================================
-- QR Code Configuration Seed Data
-- Phase 5: QSA QR Code Implementation
-- Date: 2026-01-09
-- ============================================================================
-- Adds position=0 (design-level) QR code configuration for each Base ID design.
-- QR codes appear once per QSA array, not per module.
-- Coordinates provided by Ron: x=139.1167, y=56.85, size=10mm
--
-- IDEMPOTENT: Uses INSERT ... ON DUPLICATE KEY UPDATE so re-running is safe.
-- Unique key: uk_design_element (qsa_design, revision, position, element_type)
-- ============================================================================

-- STARa QR code configuration
INSERT INTO `lw_quad_qsa_config`
    (qsa_design, revision, position, element_type, origin_x, origin_y, rotation, text_height, element_size, is_active)
VALUES
    ('STAR', 'a', 0, 'qr_code', 139.117, 56.850, 0, NULL, 10.00, 1)
ON DUPLICATE KEY UPDATE
    origin_x = VALUES(origin_x),
    origin_y = VALUES(origin_y),
    element_size = VALUES(element_size),
    is_active = VALUES(is_active);

-- CUBEa QR code configuration
INSERT INTO `lw_quad_qsa_config`
    (qsa_design, revision, position, element_type, origin_x, origin_y, rotation, text_height, element_size, is_active)
VALUES
    ('CUBE', 'a', 0, 'qr_code', 139.117, 56.850, 0, NULL, 10.00, 1)
ON DUPLICATE KEY UPDATE
    origin_x = VALUES(origin_x),
    origin_y = VALUES(origin_y),
    element_size = VALUES(element_size),
    is_active = VALUES(is_active);

-- PICOa QR code configuration
INSERT INTO `lw_quad_qsa_config`
    (qsa_design, revision, position, element_type, origin_x, origin_y, rotation, text_height, element_size, is_active)
VALUES
    ('PICO', 'a', 0, 'qr_code', 139.117, 56.850, 0, NULL, 10.00, 1)
ON DUPLICATE KEY UPDATE
    origin_x = VALUES(origin_x),
    origin_y = VALUES(origin_y),
    element_size = VALUES(element_size),
    is_active = VALUES(is_active);

-- ============================================================================
-- Verification query (run after insert to confirm):
-- SELECT qsa_design, revision, position, element_type, origin_x, origin_y, element_size
-- FROM lw_quad_qsa_config
-- WHERE element_type = 'qr_code' AND position = 0;
-- ============================================================================

-- =============================================================================
-- QSA Engraving System - Alphanumeric Design Constraint Migration
-- =============================================================================
-- Purpose:     Update CHECK constraints to allow alphanumeric design names
--              for legacy SKU support (e.g., SP03, SP01, etc.)
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-10
-- Version:     1.0
--
-- Dependencies: 06-qsa-identifiers-schema.sql (must be run first)
-- WordPress:   6.8+
-- WooCommerce: 9.9+
-- MariaDB:     11.4+ (required for CHECK constraints)
--
-- Execution:   Run manually via phpMyAdmin or MySQL CLI
-- Environment: Both staging and production
--
-- Table Prefix: lw_ (luxeonstar.com only)
--
-- IMPORTANT: Review and test on staging before production deployment
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Background
-- -----------------------------------------------------------------------------
-- The original CHECK constraints only allowed uppercase letters in the design
-- column. Legacy SKU mappings (e.g., SP-03 -> SP03) require alphanumeric
-- support because canonical codes may contain numbers.
--
-- This migration updates:
-- 1. chk_design_uppercase: Allow A-Z and 0-9 in design column
-- 2. chk_qsa_id_format: Allow A-Z and 0-9 in the design portion of qsa_id
-- -----------------------------------------------------------------------------

-- -----------------------------------------------------------------------------
-- Step 1: Drop existing CHECK constraints on qsa_identifiers table
-- -----------------------------------------------------------------------------

ALTER TABLE `lw_quad_qsa_identifiers`
    DROP CONSTRAINT IF EXISTS `chk_design_uppercase`;

ALTER TABLE `lw_quad_qsa_identifiers`
    DROP CONSTRAINT IF EXISTS `chk_qsa_id_format`;

-- -----------------------------------------------------------------------------
-- Step 2: Add updated CHECK constraints with alphanumeric support
-- -----------------------------------------------------------------------------

-- CHECK: design must be uppercase letters and/or numbers (alphanumeric)
-- Supports both native QSA designs (CUBE, STAR) and legacy codes (SP03, SP01)
ALTER TABLE `lw_quad_qsa_identifiers`
    ADD CONSTRAINT `chk_design_alphanumeric` CHECK (
        `design` REGEXP '^[A-Z0-9]+$'
    );

-- CHECK: qsa_id must be uppercase alphanumeric followed by 5 digits
-- Format: {1-10 uppercase alphanumeric}{exactly 5 digits} e.g., CUBE00076 or SP0300001
ALTER TABLE `lw_quad_qsa_identifiers`
    ADD CONSTRAINT `chk_qsa_id_format` CHECK (
        `qsa_id` REGEXP '^[A-Z0-9]{1,10}[0-9]{5}$'
    );

-- -----------------------------------------------------------------------------
-- Verification Query
-- -----------------------------------------------------------------------------
-- Run this to verify constraints were updated:
--
-- SELECT CONSTRAINT_NAME, CHECK_CLAUSE
-- FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS
-- WHERE CONSTRAINT_SCHEMA = DATABASE()
--   AND TABLE_NAME = 'lw_quad_qsa_identifiers';
--
-- Expected results:
-- - chk_design_alphanumeric: `design` REGEXP '^[A-Z0-9]+$'
-- - chk_qsa_id_format: `qsa_id` REGEXP '^[A-Z0-9]{1,10}[0-9]{5}$'
-- - chk_sequence_number_positive: `sequence_number` >= 1
-- - chk_qsa_sequence_positive: `qsa_sequence` >= 1
-- -----------------------------------------------------------------------------

-- =============================================================================
-- End of Migration
-- =============================================================================

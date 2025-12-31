-- =============================================================================
-- QSA Engraving System - Rollback Script for 01-qsa-engraving-schema.sql
-- =============================================================================
-- Purpose:     Remove all tables created by 01-qsa-engraving-schema.sql
-- Author:      Claude Code (database-specialist)
-- Date:        2025-12-31
-- Version:     1.0
--
-- WARNING: This script PERMANENTLY DELETES all data in these tables!
--          Only run this script if you need to completely remove the
--          QSA Engraving system tables.
--
-- Table Prefix: Replace {prefix} with actual prefix before execution:
--               - luxeonstar.com: lw_
--               - handlaidtrack.com: fwp_
--
-- IMPORTANT: Create a database backup before running this script!
-- =============================================================================

-- Drop tables in reverse dependency order
-- (tables with foreign key references are dropped first)

-- Drop engraved_modules first (references engraving_batches and serial_numbers)
DROP TABLE IF EXISTS `{prefix}quad_engraved_modules`;

-- Drop serial_numbers (references engraving_batches)
DROP TABLE IF EXISTS `{prefix}quad_serial_numbers`;

-- Drop engraving_batches (parent table)
DROP TABLE IF EXISTS `{prefix}quad_engraving_batches`;

-- Drop qsa_config (standalone table)
DROP TABLE IF EXISTS `{prefix}quad_qsa_config`;

-- =============================================================================
-- End of Rollback Script
-- =============================================================================

-- =============================================================================
-- QSA Engraving System - Rollback Script for 06-qsa-identifiers-schema.sql
-- =============================================================================
-- Purpose:     Remove QSA identifiers table
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-08
-- Version:     1.0
--
-- WARNING: This script PERMANENTLY DELETES all QSA identifier data!
--          Only run this script if you need to completely remove the
--          QSA identifier system.
--
-- Table Prefix: lw_ (luxeonstar.com only)
--
-- IMPORTANT: Create a database backup before running this script!
-- =============================================================================

-- Drop the QSA identifiers table
DROP TABLE IF EXISTS `lw_quad_qsa_identifiers`;

-- =============================================================================
-- End of Rollback Script
-- =============================================================================

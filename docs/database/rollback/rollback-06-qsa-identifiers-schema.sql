-- =============================================================================
-- QSA Engraving System - Rollback Script for 06-qsa-identifiers-schema.sql
-- =============================================================================
-- Purpose:     Remove QSA identifiers and sequence counter tables
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-08
-- Version:     1.1 (added sequence counter table drop)
--
-- WARNING: This script PERMANENTLY DELETES all QSA identifier data!
--          Only run this script if you need to completely remove the
--          QSA identifier system.
--
-- Table Prefix: lw_ (luxeonstar.com only)
--
-- IMPORTANT: Create a database backup before running this script!
-- =============================================================================

-- Drop the QSA identifiers table (drop this first due to logical dependency)
DROP TABLE IF EXISTS `lw_quad_qsa_identifiers`;

-- Drop the design sequences counter table
DROP TABLE IF EXISTS `lw_quad_qsa_design_sequences`;

-- =============================================================================
-- End of Rollback Script
-- =============================================================================

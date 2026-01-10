-- =============================================================================
-- Legacy SKU Mappings Schema
-- =============================================================================
-- Purpose:     Create table to map legacy module SKU patterns to canonical
--              4-letter design codes for QSA engraving compatibility
-- Author:      Claude Code (database-specialist)
-- Date:        2026-01-10
-- Version:     1.0
--
-- Dependencies: 01-qsa-engraving-schema.sql (base tables must exist)
-- WordPress:   6.8+
-- WooCommerce: 9.9+
--
-- Execution:   Run manually via phpMyAdmin or MySQL CLI
-- Environment: Both staging and production
--
-- Table Prefix: lw_ (luxeonstar.com only)
--
-- IMPORTANT: Review and test on staging before production deployment
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: SKU Mappings
-- -----------------------------------------------------------------------------
-- Purpose: Map legacy SKU patterns to canonical 4-letter QSA design codes
--
-- Legacy SKUs use various formats: 'SP-01', 'SZ-01', 'MR-*-10S', '234356-1'
-- This table allows mapping them to standard 4-letter codes (e.g., SP01, MR1S)
-- so they can be processed through the QSA engraving workflow.
--
-- Match Types:
--   exact  - Pattern must match SKU exactly (case-sensitive)
--   prefix - SKU must start with pattern
--   suffix - SKU must end with pattern
--   regex  - Pattern is a MySQL regular expression
--
-- Priority System:
--   Lower priority value = higher precedence
--   Allows specific patterns to override general ones
--   Default priority: 100
--   Example: 'SP-01' exact (priority 50) overrides 'SP-' prefix (priority 100)
--
-- Volume Estimate: ~50-200 mappings for all legacy designs
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lw_quad_sku_mappings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Primary key',

    `legacy_pattern` VARCHAR(50) NOT NULL
        COMMENT 'Pattern to match (exact string or pattern with wildcards)',

    `match_type` ENUM('exact', 'prefix', 'suffix', 'regex') NOT NULL DEFAULT 'exact'
        COMMENT 'How to interpret legacy_pattern',

    `canonical_code` CHAR(4) NOT NULL
        COMMENT 'Target 4-letter design code (e.g., SP01, MR1S)',

    `revision` CHAR(1) DEFAULT NULL
        COMMENT 'Optional revision letter (a-z)',

    `description` VARCHAR(255) DEFAULT NULL
        COMMENT 'Human-readable description of what this mapping covers',

    `priority` SMALLINT UNSIGNED NOT NULL DEFAULT 100
        COMMENT 'Resolution order - lower value = higher priority',

    `is_active` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1 = active, 0 = disabled',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT 'When mapping was created',

    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'When mapping was last modified',

    `created_by` BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'WordPress user ID who created this mapping',

    PRIMARY KEY (`id`),

    -- Ensure unique pattern + match type combinations
    UNIQUE KEY `uk_pattern_type` (`legacy_pattern`, `match_type`),

    -- Index for lookups by canonical code
    KEY `idx_canonical` (`canonical_code`),

    -- Composite index for active priority-ordered queries
    KEY `idx_active_priority` (`is_active`, `priority`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Maps legacy SKU patterns to canonical 4-letter QSA design codes';


-- -----------------------------------------------------------------------------
-- Modification: Add original_sku column to engraved_modules table
-- -----------------------------------------------------------------------------
-- Purpose: Track original SKU for traceability when mapping legacy SKUs
--
-- This column stores the original SKU before mapping. For native QSA format
-- SKUs, this will be NULL. For mapped legacy SKUs, it preserves the original
-- SKU for reporting and debugging purposes.
-- -----------------------------------------------------------------------------

ALTER TABLE `lw_quad_engraved_modules`
ADD COLUMN `original_sku` VARCHAR(50) DEFAULT NULL
    COMMENT 'Original SKU before mapping (NULL if native QSA format)'
AFTER `module_sku`;


-- =============================================================================
-- Sample Mappings (commented out - for reference only)
-- =============================================================================
-- Uncomment and customize when ready to add mappings for specific legacy SKUs.
-- Each legacy design needs corresponding config entries in lw_quad_qsa_config.
-- =============================================================================

/*
-- Example: SinkPAD Single Rebel LED
INSERT INTO `lw_quad_sku_mappings`
    (legacy_pattern, match_type, canonical_code, description, priority, is_active, created_by)
VALUES
    ('SP-01', 'exact', 'SP01', 'SinkPAD Single Rebel LED', 100, 1, 1);

-- Example: Prefix match for all SZ- modules
INSERT INTO `lw_quad_sku_mappings`
    (legacy_pattern, match_type, canonical_code, description, priority, is_active, created_by)
VALUES
    ('SZ-', 'prefix', 'SZ01', 'All SZ series modules', 100, 1, 1);

-- Example: Suffix match for 10S modules
INSERT INTO `lw_quad_sku_mappings`
    (legacy_pattern, match_type, canonical_code, description, priority, is_active, created_by)
VALUES
    ('-10S', 'suffix', 'MR1S', 'All 10S suffix modules', 100, 1, 1);

-- Example: Regex for MR modules with numeric middle section
INSERT INTO `lw_quad_sku_mappings`
    (legacy_pattern, match_type, canonical_code, description, priority, is_active, created_by)
VALUES
    ('^MR-[0-9]+-10S$', 'regex', 'MR1S', 'MR-nnn-10S pattern modules', 100, 1, 1);
*/


-- =============================================================================
-- End of Schema
-- =============================================================================

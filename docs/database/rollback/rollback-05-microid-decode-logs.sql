-- Rollback script for Micro-ID decode logs table
-- Run this to remove the decode logs table if it was created
-- Replace {prefix} with your WordPress table prefix (e.g., lw_ or fwp_)

DROP TABLE IF EXISTS `{prefix}quad_microid_decode_logs`;

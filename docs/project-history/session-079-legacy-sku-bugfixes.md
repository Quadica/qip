# Session 079: Legacy SKU Mapping Bug Fixes
- Date/Time: 2026-01-10 22:29
- Session Type(s): bugfix
- Primary Focus Area(s): backend, database

## Overview
Fixed two critical bugs in the Legacy SKU Mapping feature that prevented legacy SKU modules from being properly displayed and engraved. Resolved SSH connection issues and GitHub Actions deployment problems that blocked testing.

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/includes/Ajax/class-queue-ajax-handler.php`: Injected Legacy_SKU_Resolver and updated `extract_base_type()` to use it for proper canonical code resolution
- `wp-content/plugins/qsa-engraving/includes/Database/class-qsa-identifier-repository.php`: Updated QSA identifier validation from `/^[A-Za-z]+$/` to `/^[A-Za-z0-9]+$/` to allow alphanumeric design names
- `wp-content/plugins/qsa-engraving/qsa-engraving.php`: Updated Queue_Ajax_Handler instantiation to inject Legacy_SKU_Resolver
- `wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php`: Added TC-PLW-004 and TC-QSA-006a smoke tests
- `docs/database/install/06-qsa-identifiers-schema.sql`: Updated CHECK constraints to allow alphanumeric design names
- `docs/database/install/11-alphanumeric-design-constraint.sql`: NEW migration script for updating production database constraints

### Tasks Addressed
- `docs/plans/legacy-sku-mapping-plan.md` - Phase 8: Plugin Wiring - Bug fixes for Queue_Ajax_Handler integration
- Queue_Ajax_Handler was missing Legacy_SKU_Resolver injection (addressed in Phase 5 of the plan but not wired in Phase 8)

### New Functionality Added
- None - this session focused on bug fixes for existing functionality

### Problems & Bugs Fixed

1. **SSH Key Configuration Issue**
   - Problem: SSH connection to staging server failed with "Permission denied" error
   - Root Cause: SSH username was incorrectly set as `rlux` (the key filename) instead of `luxeonstarleds` (the actual SSH user)
   - Solution: Updated SSH commands to use `luxeonstarleds@34.71.83.227` with the `-i ~/.ssh/rlux` key file

2. **GitHub Actions Deployment Failure**
   - Problem: Deployment workflow failed after user updated SSH keys in Kinsta and GitHub
   - Solution: Re-ran the failed workflow after user confirmed key updates were complete; deployment now works

3. **"UNKNOWN" Display Bug in Engraving Queue**
   - Problem: Legacy SKU mapped modules (e.g., SP-03 mapped to SP03) displayed "UNKNOWN" as their base type in the Engraving Queue UI instead of the canonical code
   - Root Cause: `Queue_Ajax_Handler::extract_base_type()` was not using the `Legacy_SKU_Resolver` to resolve legacy SKUs
   - Solution: Injected `Legacy_SKU_Resolver` into `Queue_Ajax_Handler` constructor and updated `extract_base_type()` to use resolver when no canonical_code is pre-resolved
   - New Smoke Test: TC-PLW-004 verifies Queue_Ajax_Handler has Legacy_SKU_Resolver injected

4. **"Design name must contain only letters" SVG Generation Error**
   - Problem: SVG generation failed for legacy SKUs with error "Design name must contain only letters, numbers, and underscores"
   - Root Cause: QSA identifier validation in `class-qsa-identifier-repository.php` only allowed letters (`/^[A-Za-z]+$/`), but legacy SKU canonical codes like "SP03" contain numbers
   - Solution: Updated validation regex to `/^[A-Za-z0-9]+$/` to allow alphanumeric design names
   - Database Migration: Created `11-alphanumeric-design-constraint.sql` to update CHECK constraints for production
   - New Smoke Test: TC-QSA-006a verifies `get_or_create()` accepts alphanumeric design names

### Git Commits
Key commits from this session (newest first):
- `272e2e1` - Allow alphanumeric design names for legacy SKU support
- `ab44ac8` - Wire Legacy_SKU_Resolver to Queue_Ajax_Handler for base type display

## Technical Decisions
- **Legacy_SKU_Resolver in Queue_Ajax_Handler**: Added resolver injection to Queue_Ajax_Handler even though the plan specified it only for Batch_Ajax_Handler. Queue_Ajax_Handler also needs resolution for displaying base types in the queue UI.
- **Alphanumeric Design Names**: Changed validation from letters-only to alphanumeric to support legacy SKU canonical codes like "SP03", "MR1S", etc. Underscores are not needed per current use cases.
- **Backward Compatibility**: Used nullable type with default `?Legacy_SKU_Resolver = null` in Queue_Ajax_Handler constructor to maintain compatibility with any code instantiating the class directly.

## Current State
The Legacy SKU Mapping system is now fully operational. All 178 smoke tests pass, including:
- TC-PLW-001 through TC-PLW-004: Plugin wiring verification
- TC-QSA-006a: Alphanumeric design name validation
- TC-LEG-001 through TC-LEG-012: Legacy SKU resolver tests

End-to-end workflow confirmed working:
1. Legacy SKUs with mappings appear in Batch Creator UI
2. Batch creation succeeds with legacy modules
3. Engraving Queue displays correct canonical codes (not "UNKNOWN")
4. SVG generation works with alphanumeric design names

## Next Steps
### Immediate Tasks
- [x] Deploy to production (user confirmed testing complete)
- [ ] Run SQL migration scripts on production before plugin upload:
  1. `docs/database/install/10-sku-mappings-schema.sql` - Creates SKU mappings table
  2. `docs/database/install/11-alphanumeric-design-constraint.sql` - Updates CHECK constraints

### Known Issues
- None identified - all bugs from this session have been resolved

## Notes for Next Session
**Production Deployment Checklist:**
1. Run `10-sku-mappings-schema.sql` in production phpMyAdmin (creates `lw_quad_sku_mappings` table)
2. Run `11-alphanumeric-design-constraint.sql` in production phpMyAdmin (updates CHECK constraints on `lw_quad_qsa_identifiers`)
3. Deploy plugin code to production
4. Test legacy SKU workflow end-to-end

**Adding Legacy SKU Mappings:**
After deployment, to add a new legacy module design:
1. Go to QSA Engraving > SKU Mappings in WordPress admin
2. Add mapping (e.g., `SP-03` exact match to `SP03`)
3. Add QSA configuration coordinates for the canonical code in `quad_qsa_config` table
4. Legacy modules matching the pattern will appear in Batch Creator

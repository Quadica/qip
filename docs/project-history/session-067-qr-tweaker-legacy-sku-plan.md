# Session 067: QR Tweaker Bug Fix and Legacy SKU Mapping Plan
- Date/Time: 2026-01-10 11:11
- Session Type(s): bugfix, documentation
- Primary Focus Area(s): backend, database

## Overview
Fixed a bug preventing Position 0 (QR Code) configuration in the Engraving Dashboard Tweaker UI, and created a comprehensive implementation plan for supporting legacy module SKU formats alongside the standard QSA pattern.

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/includes/Admin/class-admin-menu.php`: Added Position 0 option to dropdown with "(QR Code)" label, added element_size field rendering for QR code size configuration
- `wp-content/plugins/qsa-engraving/includes/Ajax/class-lightburn-ajax-handler.php`: Changed validation from `< 1` to `< 0` to allow Position 0, added element_size to query and save operations
- `wp-content/plugins/qsa-engraving/includes/Database/class-config-repository.php`: Added element_size parameter to set_element_config() method with proper UPDATE/INSERT handling

### Files Created
- `docs/plans/legacy-sku-mapping-plan.md`: Complete implementation plan for legacy SKU mapping support (609 lines)

### Tasks Addressed
- `DEVELOPMENT-PLAN.md` - No specific phase addressed (this was a bug fix for existing functionality)
- `docs/plans/legacy-sku-mapping-plan.md` - New planning document created for future implementation

### New Functionality Added
- **Position 0 Tweaker Support**: The Engraving Dashboard Tweaker now allows configuration of Position 0 (design-level elements like QR codes). Previously, the dropdown only showed positions 1-8.
- **QR Size Field**: When Position 0 is selected, a "QR Size (mm)" field appears allowing configuration of the element_size value (default 10mm).

### Problems & Bugs Fixed
- **QR Code Tweaker Missing Position 0**:
  - Root cause: Position dropdown was hardcoded to loop from 1-8 (excluding 0)
  - Root cause: AJAX validation rejected `position < 1`, blocking position 0 submissions
  - Root cause: element_size field was not being queried, rendered, or saved
  - Solution: Extended dropdown to include 0 with "(QR Code)" label, relaxed validation to `< 0`, added element_size field support throughout the save/load cycle

### Git Commits
Key commits from this session (newest first):
- `2754692` - Fix Tweaker UI to support Position 0 (QR Code) configuration

## Technical Decisions
- **Position 0 for Design-Level Elements**: Position 0 represents elements that appear once per QSA array (like QR codes) rather than per-module positions 1-8. This aligns with the existing DESIGN_LEVEL_ELEMENTS constant in the codebase.
- **element_size Field**: The QR code size is stored in the element_size column (in mm). Default is 10mm. This field was already in the database schema but wasn't being utilized by the Tweaker UI.
- **Legacy SKU Mapping Approach**: Decided on an opt-in mapping system where only explicitly mapped legacy SKUs are included in the engraving workflow. Unmapped legacy SKUs continue to be ignored, allowing incremental adoption without breaking existing workflows.
- **Canonical Code Convention**: Legacy SKUs map to 4-letter canonical codes (e.g., SP-01 maps to SP01) to maintain compatibility with the existing QSA config lookup system.

## Current State
The Engraving Dashboard Tweaker now fully supports configuring Position 0 (QR Code) elements including:
- Selecting Position 0 from the dropdown (shows "(QR Code)" label)
- Entering origin X/Y coordinates
- Setting element_size (QR code size in mm)
- Saving and loading these configurations correctly

The legacy SKU mapping feature is fully planned but not yet implemented. The plan document (`docs/plans/legacy-sku-mapping-plan.md`) outlines:
- Database schema for `quad_sku_mappings` table
- SKU_Mapping_Repository class
- Legacy_SKU_Resolver service
- Integration points in Module_Selector, Batch_Ajax_Handler, and Config_Loader
- Admin UI for managing mappings
- 8 implementation phases

## Next Steps
### Immediate Tasks
- [ ] Verify Position 0 QR Tweaker fix on staging site with production data
- [ ] Deploy QR Tweaker fix to production when verified
- [ ] Await approval to begin legacy SKU mapping implementation

### Known Issues
- None introduced in this session

## Notes for Next Session
- The legacy SKU mapping plan is comprehensive and ready for implementation. It addresses ~90 legacy module designs (40 standard + 50 special order).
- The plan includes a testing approach with 12 smoke tests and manual verification steps.
- Implementation is phased: Phase 1 (schema) through Phase 8 (plugin wiring) can be done incrementally.
- Key architectural decision: unmapped legacy SKUs are silently ignored, ensuring no disruption to current workflows.

# Session 056: QR Code Phases 1-2 Complete

- Date/Time: 2026-01-08 22:54
- Session Type(s): feature|implementation|refactor
- Primary Focus Area(s): database|backend

## Overview

Extended development session completing Phases 1 and 2 of the QR Code implementation plan. This work replaces per-module Data Matrix barcodes with a single QR code per QSA array, introducing a new QSA ID system for array-level identification. The session spanned three sub-sessions (053-055) covering database schema creation, code review fixes, and complete removal of Data Matrix code from the plugin.

## Changes Made

### Files Created

| File | Purpose | Lines |
|------|---------|-------|
| `docs/database/install/06-qsa-identifiers-schema.sql` | QSA ID tracking table with CHECK constraints | 88 |
| `docs/database/install/07-config-qr-support.sql` | Config modifications for QR code support | 87 |
| `docs/database/rollback/rollback-06-qsa-identifiers-schema.sql` | Rollback for identifiers table | 23 |
| `docs/database/rollback/rollback-07-config-qr-support.sql` | Rollback for config changes | 62 |
| `docs/plans/qsa-qr-code-implementation-plan.md` | Complete 9-phase implementation plan | 461 |

### Files Deleted

| File | Reason | Lines Removed |
|------|--------|---------------|
| `includes/SVG/class-datamatrix-renderer.php` | Data Matrix replaced by QR code | 347 |

### Files Modified

| File | Changes |
|------|---------|
| `includes/SVG/class-svg-document.php` | Removed `render_datamatrix()` method (lines 677-697) and datamatrix conditional block in `render_module()` (lines 580-586), updated doc comments |
| `includes/SVG/class-coordinate-transformer.php` | Removed `get_datamatrix_position()` method (lines 258-282) |
| `includes/Database/class-config-repository.php` | Changed ELEMENT_TYPES constant from 'datamatrix' to 'qr_code' |
| `includes/Services/class-config-loader.php` | Removed 'datamatrix' from required elements in `validate_config()` |
| `includes/Ajax/class-lightburn-ajax-handler.php` | Updated 2 ORDER BY FIELD() clauses to use 'qr_code' |
| `includes/Admin/class-admin-menu.php` | Updated JS `hasTextHeight` condition to check for 'qr_code' |
| `includes/Services/class-svg-generator.php` | Updated dependency check message from "Data Matrix" to "QR codes" |
| `tests/smoke/wp-smoke.php` | Removed 4 tests (TC-SVG-005, TC-DM-001/002/003), updated 5 element count tests |

### Tasks Addressed

- `docs/plans/qsa-qr-code-implementation-plan.md`
  - Phase 1: Database Schema Changes - **COMPLETE**
    - Section 1.1: New Table `lw_quad_qsa_identifiers` - COMPLETE
    - Section 1.2: Modify Config Table - COMPLETE
  - Phase 2: Remove Data Matrix Code - **COMPLETE**
    - Section 2.1: Files to Delete - COMPLETE
    - Section 2.2: Files to Modify - All 8 files updated

### New Functionality Added

#### QSA Identifiers Table (`lw_quad_qsa_identifiers`)
Tracks QSA-level identifiers linking arrays to batches for future reporting:
- `qsa_id`: Formatted identifier (e.g., CUBE00076)
- `design`: Design name (CUBE, STAR, PICO)
- `sequence_number`: Per-design sequential number (supports 99,999 arrays per design)
- `batch_id`: Foreign key to engraving_batches table
- `qsa_sequence`: QSA sequence within batch
- Three unique constraints ensure data integrity

#### Counter Table (`lw_quad_qsa_design_sequences`)
Enables atomic, concurrency-safe sequence allocation:
```sql
INSERT INTO lw_quad_qsa_design_sequences (design, current_sequence)
VALUES ('CUBE', LAST_INSERT_ID(1))
ON DUPLICATE KEY UPDATE current_sequence = LAST_INSERT_ID(current_sequence + 1);
SELECT LAST_INSERT_ID() AS next_sequence;
```

#### MariaDB CHECK Constraints
Added database-level validation (MariaDB 11.4):
- `chk_qsa_id_format`: Validates `^[A-Z]{1,10}[0-9]{5}$`
- `chk_sequence_number_positive`: Ensures sequence_number >= 1
- `chk_qsa_sequence_positive`: Ensures qsa_sequence >= 1
- `chk_design_uppercase`: Validates design `^[A-Z]+$`

#### Config Table Updates
- Added `element_size` DECIMAL(5,2) column for configurable QR code size
- Changed `element_type` ENUM: replaced 'datamatrix' with 'qr_code'
- Deleted 24 orphaned datamatrix configuration entries (test data cleanup)

### Problems & Bugs Fixed

| Problem | Solution |
|---------|----------|
| Concurrency-safe sequencing | Created counter table with INSERT...ON DUPLICATE KEY UPDATE pattern |
| No DB-level validation | Added MariaDB CHECK constraints for format and range validation |
| element_size default confusion | Added comprehensive COALESCE documentation and column comments |
| Smoke test element count mismatch | Updated expected counts after datamatrix removal (STARa: 5->4, CUBEa: 8->7, PICOa: 5->4) |

### Git Commits

Key commits from this session (newest first):
- `e562c36` - Add review request header to session 055 report
- `3a9ef30` - Add session 055: QR Code Phase 2 - Data Matrix removal
- `1504938` - Fix smoke tests for Phase 2 element count changes
- `8c913d9` - Phase 2: Remove Data Matrix code and replace with QR code references
- `a197ad4` - Add review request header to session 054 report
- `2dd1daa` - Add session 054: Phase 1 code review fixes
- `87e079c` - Fix code review issues: concurrency, validation, documentation
- `8e09ade` - Add session 053: QR Code Phase 1 database schema implementation
- `cc5a8eb` - Add session 053: QR code Phase 1 database schema changes
- `97849f9` - Phase 1: Add database schema for QR code + QSA ID system

## Technical Decisions

### Counter Table for Concurrency
- **Decision:** Use INSERT...ON DUPLICATE KEY UPDATE with LAST_INSERT_ID() pattern
- **Rationale:** Simpler than SELECT FOR UPDATE, well-documented pattern for MySQL/MariaDB, naturally handles InnoDB auto-increment gap behavior
- **Implication:** Repository implementation (Phase 3) must follow documented pattern exactly

### MariaDB CHECK Constraints
- **Decision:** Implement CHECK constraints for data validation
- **Rationale:** MariaDB 11.4 on Kinsta staging confirmed to support CHECK constraints
- **Implication:** Invalid data rejected at database level; PHP must validate first for user-friendly error messages

### NULL Default for element_size
- **Decision:** Keep NULL default with COALESCE documentation
- **Rationale:** Column shared by multiple element types; 10mm default only applies to QR codes
- **Implication:** PHP code must use `COALESCE(element_size, 10.0)` or `QR_Code_Renderer::DEFAULT_SIZE` constant

### tc-lib-barcode Library Retained
- **Decision:** Keep library despite removing Data Matrix code
- **Rationale:** Same library supports QR code generation for Phase 4
- **Implication:** No composer changes required; will use QRCODE,H format (30% error correction)

### QR Code is Design-Level (position=0)
- **Decision:** Single QR code per SVG at position 0, not per-module
- **Rationale:** Replaces 8 Data Matrix barcodes with 1 QR code for faster engraving
- **Implication:** Config Repository must handle position=0 as special case in Phase 6

## Current State

The plugin is now in a transitional state between Data Matrix and QR code:

1. **Database Ready:** All schema changes applied
   - `lw_quad_qsa_identifiers` table exists with CHECK constraints
   - `lw_quad_qsa_design_sequences` counter table exists
   - `element_size` column added to config table
   - ENUM updated to 'qr_code'

2. **Data Matrix Removed:** No rendering code remains
   - 347 lines of renderer code deleted
   - 8 source files updated to remove datamatrix references
   - All tests updated for new element counts

3. **QR Code Not Yet Implemented:** Phases 3-9 pending
   - No QSA ID generation yet
   - No QR code rendering yet
   - Config positions not seeded (Ron to provide coordinates)

4. **All Tests Passing:** 98 smoke tests pass
   - 4 Data Matrix tests removed
   - 5 element count tests updated

## Lines of Code Changed

| Category | Lines |
|----------|-------|
| Created (SQL + plan) | ~700 |
| Deleted (renderer) | 347 |
| Modified (8 source files) | ~30 |
| Tests removed/modified | ~120 |

## Database Changes Applied

All migrations applied to staging server and verified:

```sql
-- Tables created
lw_quad_qsa_identifiers (with 4 CHECK constraints)
lw_quad_qsa_design_sequences (counter table)

-- Column added
lw_quad_qsa_config.element_size DECIMAL(5,2) DEFAULT NULL

-- ENUM changed
lw_quad_qsa_config.element_type: 'datamatrix' -> 'qr_code'

-- Data cleaned
24 datamatrix config entries deleted (test data)
```

## Open Questions Resolved

| Question | Answer | Source |
|----------|--------|--------|
| QR Code Position | Per Base ID (Ron provides coordinates in Phase 9) | Ron interview |
| URL Format | Always lowercase (quadi.ca/cube00076) | Ron interview |
| Error Correction | High (H - 30% recovery) | Ron interview |
| Test Data | Can be purged during development | Ron interview |

## Next Steps

### Immediate Tasks (Phases 3-4)
- [ ] Phase 3: Create `class-qsa-identifier-repository.php`
  - `get_or_create()` for QSA ID assignment
  - `get_next_sequence()` using counter table pattern
  - `format_qsa_id()` for ID formatting (e.g., CUBE00076)
- [ ] Phase 4: Create `class-qr-code-renderer.php`
  - Use tc-lib-barcode with QRCODE,H format
  - Configurable size (default 10mm)
  - Position at design-level coordinates

### Remaining Phases (5-9)
- [ ] Phase 5: Integrate QR code into SVG Document
- [ ] Phase 6: Update Config Repository for position=0 elements
- [ ] Phase 7: Update LightBurn Handler to assign QSA IDs
- [ ] Phase 8: Frontend updates to display QSA ID in Engraving Queue
- [ ] Phase 9: Seed QR code config data (Ron to provide coordinates)

### Known Issues
- None identified from this session

## Notes for Next Session

### Repository Implementation Critical
The Phase 3 repository must use the counter table pattern exactly as documented:
```sql
INSERT INTO lw_quad_qsa_design_sequences (design, current_sequence)
VALUES ('CUBE', LAST_INSERT_ID(1))
ON DUPLICATE KEY UPDATE current_sequence = LAST_INSERT_ID(current_sequence + 1);
SELECT LAST_INSERT_ID() AS next_sequence;
```

### CHECK Constraints Active
CHECK constraints are enforced at database level. PHP code should validate before insert to provide user-friendly error messages rather than generic constraint violation errors.

### COALESCE Required for element_size
When implementing QR code retrieval, use:
```php
COALESCE(element_size, 10.0)
// or
QR_Code_Renderer::DEFAULT_SIZE
```

### QR Code Content Format
URL format: `quadi.ca/{qsa_id}` (lowercase)
Example: `quadi.ca/cube00076`

### Position 0 Convention
Position 0 = design-level element (single per SVG, not per-module)
This differs from positions 1-8 which are per-module.

### Implementation Plan Location
Full 9-phase plan: `docs/plans/qsa-qr-code-implementation-plan.md`

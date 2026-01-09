# Session 054: Phase 1 Code Review Fixes
- Date/Time: 2026-01-08 22:27
- Session Type(s): bugfix
- Primary Focus Area(s): database

## Overview
Addressed code review feedback on the Phase 1 database schema for the QR Code implementation. Fixed three issues: concurrency-safe sequence allocation, database-level validation constraints, and element_size default value documentation.

## Changes Made
### Files Modified
- `docs/database/install/06-qsa-identifiers-schema.sql`: Added counter table, CHECK constraints, improved comments
- `docs/database/install/07-config-qr-support.sql`: Enhanced documentation for element_size NULL default rationale
- `docs/database/rollback/rollback-06-qsa-identifiers-schema.sql`: Added counter table drop statement

### Tasks Addressed
- `docs/plans/qsa-qr-code-implementation-plan.md` - Phase 1: Database Schema Changes - refinement
- Code review feedback on Phase 1 schema implementation

### Problems & Bugs Fixed

#### Issue 1: Concurrency-safe sequencing (Line 71)
**Problem:** The unique index on (design, sequence_number) does not guarantee gapless or concurrency-safe sequencing; if the app uses MAX()+1, concurrent inserts can collide.

**Solution:**
- Created new `lw_quad_qsa_design_sequences` counter table
- Implements atomic counter pattern using INSERT ... ON DUPLICATE KEY UPDATE + LAST_INSERT_ID()
- Updated comments to clarify that gaps may occur (acceptable - sequence numbers need not be gapless)
- Documented usage pattern in SQL comments for repository implementation

```sql
CREATE TABLE IF NOT EXISTS `lw_quad_qsa_design_sequences` (
    `design` VARCHAR(10) NOT NULL,
    `current_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`design`)
);
```

#### Issue 2: DB-level validation (Line 48)
**Problem:** No DB-level validation for qsa_id format or bounds checking.

**Solution (MariaDB 11.4 CHECK constraints):**
- `chk_qsa_id_format`: Validates format `^[A-Z]{1,10}[0-9]{5}$` (uppercase letters + 5 digits)
- `chk_sequence_number_positive`: Ensures sequence_number >= 1
- `chk_qsa_sequence_positive`: Ensures qsa_sequence >= 1
- `chk_design_uppercase`: Validates design `^[A-Z]+$` (uppercase only)

#### Issue 3: element_size default value (Line 31)
**Problem:** element_size defaults to NULL but QR codes need 10mm default.

**Solution:**
- Kept NULL default (column shared by multiple element types)
- Added comprehensive documentation explaining:
  - Why NULL is correct (semantically incorrect for non-QR elements to have 10mm default)
  - COALESCE requirement: code MUST use `COALESCE(element_size, 10.0)` for QR codes
  - Application layer handles via `QR_Code_Renderer::DEFAULT_SIZE`
- Updated column comment: "NULL = use element-specific default (QR: 10mm)"

### Git Commits
Key commits from this session (newest first):
- `87e079c` - Fix code review issues: concurrency, validation, documentation

## Technical Decisions

### Counter Table Pattern for Concurrency
- **Decision:** Use INSERT ... ON DUPLICATE KEY UPDATE with LAST_INSERT_ID() pattern
- **Rationale:** Simpler than SELECT FOR UPDATE, well-documented pattern for MySQL/MariaDB, naturally handles InnoDB auto-increment gap behavior
- **Implication:** Repository implementation must follow the documented usage pattern in SQL comments

### MariaDB CHECK Constraints
- **Decision:** Implement CHECK constraints for data validation
- **Rationale:** MariaDB 11.4 on Kinsta staging confirmed to support CHECK constraints
- **Implication:** Invalid data will be rejected at database level, adding defense in depth beyond PHP validation

### NULL Default for element_size
- **Decision:** Keep NULL default with COALESCE documentation
- **Rationale:** Column is shared by multiple element types; a universal 10mm default would be semantically incorrect for non-QR elements
- **Implication:** PHP code must use COALESCE or default constant when retrieving QR code configuration

## Current State
- Phase 1 database schema is now complete with:
  - `lw_quad_qsa_identifiers` table with CHECK constraints for format validation
  - `lw_quad_qsa_design_sequences` counter table for atomic sequence allocation
  - `element_size` column on config table with documented NULL handling
  - Rollback script updated to handle both tables
- Migrations applied and verified on staging server
- CHECK constraints tested (invalid insert rejected, valid insert accepted)

## Next Steps
### Immediate Tasks
- [ ] Phase 2: Remove Data Matrix code from PHP classes
- [ ] Phase 3: Create QSA Identifier Repository (implement counter table pattern)
- [ ] Phase 4: Create QR Code Renderer

### Known Issues
- None identified from this session

## Notes for Next Session
- The repository implementation (Phase 3) must use the counter table pattern exactly as documented in the SQL comments:
  ```sql
  INSERT INTO lw_quad_qsa_design_sequences (design, current_sequence)
  VALUES ('CUBE', LAST_INSERT_ID(1))
  ON DUPLICATE KEY UPDATE current_sequence = LAST_INSERT_ID(current_sequence + 1);
  SELECT LAST_INSERT_ID() AS next_sequence;
  ```
- CHECK constraints are enforced - ensure PHP code validates data before insert to provide user-friendly error messages rather than generic constraint violation errors
- When implementing QR code retrieval, use `COALESCE(element_size, 10.0)` or the `QR_Code_Renderer::DEFAULT_SIZE` constant

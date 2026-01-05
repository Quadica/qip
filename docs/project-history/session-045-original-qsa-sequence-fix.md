# Session 045: Original QSA Sequence Row Grouping Fix
- Date/Time: 2026-01-04 16:55
- Session Type(s): bugfix
- Primary Focus Area(s): backend, database

## Overview
This session fixed a bug where Mixed ID rows couldn't be split across arrays when changing start position, and then fixed a subsequent issue where Same ID rows were incorrectly displayed as multiple separate rows. The solution introduced an `original_qsa_sequence` column to track logical row grouping independently from physical array assignments.

## Changes Made
### Files Modified
- `includes/Ajax/class-queue-ajax-handler.php`: Updated `build_queue_items()` to group by `original_qsa_sequence`; updated `get_row_qsa_sequences()` to use `original_qsa_sequence` for row identification; removed Mixed ID split validation (no longer needed); simplified row grouping logic
- `includes/Ajax/class-batch-ajax-handler.php`: Added SKU grouping logic in `handle_create_batch()` to calculate `original_qsa_sequence` - all modules with the same SKU get the minimum QSA sequence for that SKU; added similar logic for `handle_create_batch_from_history()`
- `includes/Database/class-batch-repository.php`: Updated `add_module()` to accept optional `original_qsa_sequence` parameter; removed debug logging from `redistribute_row_modules()`
- `docs/database/install/05-add-original-qsa-sequence.sql`: New SQL migration file to add the column, backfill existing data, and add an index for row grouping queries

### Tasks Addressed
- Bug: Mixed ID rows could not be split across multiple arrays when start position changed
- Bug: Same ID rows (same SKU spanning multiple arrays) incorrectly displayed as separate rows in the UI
- Database schema extension for proper row tracking

### New Functionality Added
- **`original_qsa_sequence` Column**: Tracks the QSA sequence assigned at batch creation time. This value stays constant even when modules are redistributed across arrays due to start position changes. All modules with the same SKU receive the minimum QSA sequence for that SKU, ensuring Same ID rows group together.

### Problems & Bugs Fixed
- **Mixed ID Row Split Prevention (Initial Approach - Superseded)**: First commit `b851e69` added validation to prevent Mixed ID rows from splitting across arrays. This was later removed as the `original_qsa_sequence` solution handles it properly.
- **Same ID Row Splitting**: The initial `original_qsa_sequence` implementation set `original_qsa_sequence = qsa_sequence` for each module. This caused Same ID rows (same SKU spanning multiple arrays) to appear as separate rows because each array had a different sequence. Fixed by calculating the minimum `qsa_sequence` for each SKU and using that as `original_qsa_sequence` for all modules with that SKU.
- **Batch 14 Data Correction**: Existing batch 14 on staging had incorrect `original_qsa_sequence` values. Fixed via SQL UPDATE to use MIN(qsa_sequence) grouped by module_sku.

### Git Commits
Key commits from this session (newest first):
- `e06e285` - Fix original_qsa_sequence to group Same ID rows by SKU
- `3dd9510` - Add original_qsa_sequence for proper row grouping during redistribution
- `b851e69` - Prevent Mixed ID rows from splitting across arrays + remove debug logs

## Technical Decisions
- **`original_qsa_sequence` vs Row ID**: Rather than introducing a new row_id column, we track original assignment which preserves logical grouping. This is simpler and works with the existing qsa_sequence-based architecture.
- **SKU-based Grouping**: For Same ID rows, all modules with the same SKU share the same `original_qsa_sequence`. This ensures they display as a single queue item even when distributed across multiple physical arrays.
- **Fallback for Legacy Data**: The code falls back to `qsa_sequence` if `original_qsa_sequence` is 0 or empty, providing backward compatibility for data created before this migration.
- **Two-Pass Row Grouping Removed**: The complex validation that tried to prevent Mixed ID splits was removed since `original_qsa_sequence` handles this naturally.

## Current State
After these changes:
- **Row Grouping**: The UI groups modules by `original_qsa_sequence` (with fallback to `qsa_sequence`)
- **Start Position Changes**: When start position is changed, modules redistribute across arrays with new `qsa_sequence` values, but `original_qsa_sequence` stays constant so row grouping is preserved
- **Same ID Rows**: Multiple arrays with the same SKU display as a single row in the queue UI (e.g., "Same ID x Full, 3 arrays")
- **Mixed ID Rows**: Rows with multiple SKUs remain grouped together even when redistributed
- **All 102 smoke tests pass**

### How `original_qsa_sequence` Works
1. **At Batch Creation**: Calculate minimum `qsa_sequence` for each SKU, use that as `original_qsa_sequence` for all modules with that SKU
2. **During Redistribution**: `qsa_sequence` and `array_position` update, but `original_qsa_sequence` remains unchanged
3. **Row Grouping in UI**: `build_queue_items()` groups by `original_qsa_sequence` to identify logical rows
4. **Row Identification**: `get_row_qsa_sequences()` finds all current `qsa_sequence` values that share the same `original_qsa_sequence`

## Next Steps
### Immediate Tasks
- [ ] Run SQL migration on production when ready
- [ ] Monitor queue UI for proper row grouping on new batches

### Known Issues
- None identified in this session

## Notes for Next Session
- The `original_qsa_sequence` column must be added to production databases before deploying this code
- SQL migration file: `docs/database/install/05-add-original-qsa-sequence.sql`
- The backfill UPDATE should be safe for production as it sets `original_qsa_sequence = qsa_sequence` for existing modules
- Batch 14 on staging was fixed manually; production batches may need similar fix if they have Same ID rows that were created before the code fix


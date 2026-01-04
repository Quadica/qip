# Session 039: Start Position Redistribution and Status-Based Grouping Fixes

**Date:** 2026-01-04
**Focus:** Fixing start position redistribution logic and status-based row grouping

## Summary

This session addressed multiple interconnected bugs related to how modules are redistributed when start_position changes, and how rows are grouped in the Queue UI based on workflow status.

## Issues Addressed

### Issue 1: Start Position Change Not Updating Array Count Display
**Symptom:** When changing start_position from 1 to a higher value (e.g., 6), the displayed array count didn't update even though the backend redistribution was working.

**Root Causes:**
1. **Backend:** The AJAX response returned the ORIGINAL `qsa_sequences` array (e.g., `[1, 2, 3]`), not the NEW sequences after redistribution (e.g., `[1, 2, 3, 7]`).
2. **Frontend:** The `handleStartPositionChange` function only updated `startPosition` in state, not `qsa_sequences`.
3. **Display:** `totalArrays` was derived from `qsaSequences.length`, which wasn't being updated.

**Files Changed:**
- `includes/Ajax/class-queue-ajax-handler.php` (lines 1302-1312)
- `assets/js/src/engraving-queue/components/EngravingQueue.js` (lines 670-683)

### Issue 2: New QSA Sequences Conflicting with Existing Rows
**Symptom:** When redistributing 24 modules with start_position=6 (requiring 4 arrays instead of 3), the 4th array tried to use QSA sequence 4, which was already occupied by another row (Mixed ID).

**Root Cause:** The `redistribute_row_modules()` function allocated new sequences by simply incrementing from the row's first sequence, without checking if those sequences were already in use by other rows.

**Fix:** New QSA sequences are now allocated AFTER the batch's current MAX `qsa_sequence`. For batch 36 with max=6, the new sequence would be 7 (not 4).

**File Changed:**
- `includes/Database/class-batch-repository.php` (lines 564-621)

### Issue 3: Wrong Array Count for Small Rows (2 modules showing 2 arrays)
**Symptom:** A row with only 2 modules was showing 2 arrays when it should show 1 (since 2 modules fit in positions 1-2 of a single array).

**Root Cause:** The display used `qsaSequences.length` (database state) instead of the calculated array count based on modules and start position. If 2 modules were originally created in 2 separate QSA sequences (1 module each), the display showed 2 arrays.

**Fix:** Changed `totalArrays` calculation in QueueItem.js to use `arrays.length` (calculated from module count and start position) instead of `qsaSequences.length`.

**File Changed:**
- `assets/js/src/engraving-queue/components/QueueItem.js` (lines 157-167)

### Issue 4: "Cannot update start position: in_progress" Error on Pending Rows
**Symptom:** Trying to change start_position on a pending row (e.g., CUBE-98345 with QSAs 5,6) returned error "some modules have status in_progress".

**Root Cause:** After redistributing Mixed ID row 2 (QSA 4), one CUBE-98345 module overflowed to new QSA 8. This QSA 8 became "Same ID" (single SKU) and was incorrectly grouped with the original CUBE-98345 row (QSAs 5,6). QSA 8 was still 'in_progress' from row 2's workflow, contaminating the "pending" row 3.

**Fix:** Modified both `build_queue_items()` and `get_row_qsa_sequences()` to group sequences by SKU AND status. Sequences with different workflow statuses are no longer merged into the same row, even if they have the same SKU.

**Files Changed:**
- `includes/Ajax/class-queue-ajax-handler.php`:
  - `build_queue_items()` now uses `SKU|status` as group key (lines 388-416, 424-426)
  - `get_row_qsa_sequences()` now filters by matching status (lines 1365-1381)

## Database State Analysis (Batch 36)

After user processed rows 1 and 2 with custom start positions:

```
qsa_sequence  module_sku    array_position  row_status
1             CUBE-88546    4-8             done        (5 modules, start_pos=4)
2             CUBE-88546    1-8             done        (8 modules)
3             CUBE-88546    1-8             done        (8 modules)
4             CUBE-88546    3-6             done        (4 modules, start_pos=3)
4             CUBE-98345    7-8             done        (2 modules)
5             CUBE-98345    1               pending     (1 module) ← Original row 3
6             CUBE-98345    1               pending     (1 module) ← Original row 3
7             CUBE-88546    1-3             in_progress (3 modules) ← Overflow from row 1
8             CUBE-98345    1               in_progress (1 module) ← Overflow from row 2
```

**Key Insight:** QSA 8 (CUBE-98345, in_progress) was being grouped with QSAs 5,6 (CUBE-98345, pending) because they share the same SKU. After the fix, they will be separate rows based on status.

## Code Changes Detail

### 1. Backend: Return NEW qsa_sequences in response

```php
// Before (Queue_Ajax_Handler.php)
'qsa_sequences' => $row_qsa_sequences,  // Original sequences

// After
$new_qsa_sequences = array_map(
    fn( $arr ) => (int) $arr['sequence'],
    $result['arrays']
);
'qsa_sequences' => $new_qsa_sequences,  // NEW sequences from redistribution
```

### 2. Frontend: Update qsa_sequences from response

```javascript
// Before (EngravingQueue.js)
setQueueItems((prev) =>
    prev.map((i) =>
        i.id === itemId ? { ...i, startPosition } : i
    )
);

// After
setQueueItems((prev) =>
    prev.map((i) =>
        i.id === itemId
            ? {
                ...i,
                startPosition,
                qsa_sequences: data.data.qsa_sequences || i.qsa_sequences,
              }
            : i
    )
);
```

### 3. Frontend: Use calculated array count for display

```javascript
// Before (QueueItem.js)
const qsaSequences = item.qsa_sequences || [ item.id ];
const totalArrays = qsaSequences.length;

// After
const qsaSequences = item.qsa_sequences || [ item.id ];
const arrays = calculateArrayBreakdown( item.totalModules, startPos, item.serials || [] );
const totalArrays = arrays.length;  // Calculated, not from database
```

### 4. Backend: Allocate new sequences beyond batch max

```php
// Before (Batch_Repository.php)
$first_qsa = min( $qsa_sequences );
$current_qsa = $first_qsa;
// Simply incremented, could conflict with other rows

// After
if ( $needed_qsa_count > count( $available_sequences ) ) {
    $max_qsa = $this->wpdb->get_var(...); // Get batch max
    $extra_needed = $needed_qsa_count - count( $available_sequences );
    for ( $i = 1; $i <= $extra_needed; $i++ ) {
        $available_sequences[] = $max_qsa + $i;  // Allocate beyond max
    }
}
$sequences_to_use = array_slice( $available_sequences, 0, $needed_qsa_count );
```

### 5. Backend: Group by SKU AND status

```php
// Before (build_queue_items)
$same_id_groups[$sku][$qsa_seq] = $qsa_modules;

// After
$qsa_status = $this->normalize_row_status( $qsa_modules[0]['row_status'] ?? null );
$group_key = $sku . '|' . $qsa_status;
$same_id_groups[$group_key] = array(
    'sku' => $sku,
    'status' => $qsa_status,
    'qsas' => array(),
);
$same_id_groups[$group_key]['qsas'][$qsa_seq] = $qsa_modules;
```

### 6. Backend: Filter row sequences by status

```php
// Before (get_row_qsa_sequences)
if ( count( $skus ) === 1 && $skus[0] === $target_sku ) {
    $row_sequences[] = $qsa;
}

// After
if ( count( $skus ) === 1 && $skus[0] === $target_sku ) {
    $qsa_status = $this->normalize_row_status( $modules[0]['row_status'] ?? null );
    if ( $qsa_status === $target_status ) {  // Must match status too
        $row_sequences[] = $qsa;
    }
}
```

## Commits Made

1. **5bb75d3** - Fix redistribute_row_modules to allocate new QSA sequences beyond batch max
2. **01af4e4** - Fix array count display after start position change

## Pending Work

1. **Rebuild JS bundle** - `npm run build` completed, needs commit
2. **Run smoke tests** - Verify all 102 tests still pass
3. **Commit final changes** - Status-based grouping changes not yet committed
4. **Test on batch 36** - Verify:
   - Row 3 (CUBE-98345 pending) shows correct array count (1 array for 2 modules)
   - Row 3 can have start_position changed (no "in_progress" error)
   - In-progress overflow sequences appear as separate rows

## Expected Behavior After Fixes

For batch 36:
- **Row 1:** Same ID CUBE-88546 (QSAs 1,2,3) - status: done
- **Row 2:** Mixed ID (QSA 4) - status: done
- **Row 3:** Same ID CUBE-98345 (QSAs 5,6) - status: pending, 2 modules, 1 array
- **Row 4:** Same ID CUBE-88546 (QSA 7) - status: in_progress, 3 modules
- **Row 5:** Same ID CUBE-98345 (QSA 8) - status: in_progress, 1 module

Note: Rows 4 and 5 are overflow from rows 1 and 2 redistribution. They appear as separate rows because they have different statuses from their "parent" SKU groups.

## Architectural Notes

The status-based grouping is a significant change to how rows are determined. Previously, rows were grouped purely by SKU composition. Now they're grouped by SKU AND workflow status. This prevents:

1. Contamination of pending rows by in_progress sequences from other workflows
2. Start position updates being blocked by unrelated in_progress modules
3. Confusion about array counts when sequences have different lifecycle states

However, this means a single "logical" group of modules (same SKU) may now appear as multiple rows if they have different statuses. This is intentional - it reflects the actual workflow state and prevents cross-contamination.

## Files Modified This Session

1. `includes/Database/class-batch-repository.php`
   - `redistribute_row_modules()` - Allocate new sequences beyond batch max

2. `includes/Ajax/class-queue-ajax-handler.php`
   - `handle_update_start_position()` - Return new qsa_sequences in response
   - `build_queue_items()` - Group by SKU + status
   - `get_row_qsa_sequences()` - Filter by matching status

3. `assets/js/src/engraving-queue/components/EngravingQueue.js`
   - `handleStartPositionChange()` - Update qsa_sequences from response
   - `handleNextArray()` - Use calculated array count
   - Keyboard handler - Use calculated array count

4. `assets/js/src/engraving-queue/components/QueueItem.js`
   - `totalArrays` - Use calculated value instead of qsa_sequences.length

5. `assets/js/build/engraving-queue.js` - Rebuilt bundle

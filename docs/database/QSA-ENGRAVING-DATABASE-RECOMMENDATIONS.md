# QSA Engraving System - Database Recommendations

**Version:** 1.0
**Date:** 2025-12-31
**Author:** Claude Code (database-specialist)

---

## Executive Summary

This document provides database schema recommendations for the QSA Engraving System based on the requirements in `qsa-engraving-discovery.md`. The schema has been designed to:

1. Support the complete serial number lifecycle (reserved -> engraved/voided)
2. Track engraving batches and individual module engraving status
3. Store QSA-specific engraving coordinates for SVG generation
4. Integrate with the legacy `oms_batch_items` table (read-only)
5. Optimize for Quadica's data volumes (~85k modules/year)

---

## 1. Schema Comparison: Discovery vs Recommended

### 1.1 Serial Numbers Table

| Aspect | Discovery Spec | Recommendation | Rationale |
|--------|---------------|----------------|-----------|
| Table Name | `lw_quad_serial_numbers` | `{prefix}quad_serial_numbers` | Use dynamic prefix for both sites |
| `qsa_id` field | Array reference | **Removed** | Redundant - use `engraving_batch_id` + `qsa_sequence` |
| `batch_id` field | Production batch ref | Renamed to `production_batch_id` | Clarity: distinguish from `engraving_batch_id` |
| `module_id` field | VARCHAR | Renamed to `module_sku` | Consistency with oms_batch_items.assembly_sku |
| `serial_integer` field | Not specified | **Added** | Enables efficient range queries and MAX() for next serial |
| `created_by` field | Not specified | **Added** | Audit trail for serial generation |

### 1.2 Engraving Batches Table

| Aspect | Discovery Spec | Recommendation | Rationale |
|--------|---------------|----------------|-----------|
| Table Name | `lw_quad_engraving_batches` | `{prefix}quad_engraving_batches` | Dynamic prefix |
| `batch_name` field | Not specified | **Added** | Optional descriptive identifier |
| `module_count` field | Not specified | **Added** | Quick reference without subquery |
| `qsa_count` field | Not specified | **Added** | Quick reference for UI display |

### 1.3 Engraved Modules Table

| Aspect | Discovery Spec | Recommendation | Rationale |
|--------|---------------|----------------|-----------|
| `qsa_sequence` field | Specified | Kept | Which QSA in the batch (1, 2, 3...) |
| `serial_number` FK | Specified | **Enhanced** | Added as CHAR(8) for direct joins |
| Unique constraint | Not specified | **Added** | Prevent duplicate engraving of same module |

### 1.4 QSA Configuration Table

| Aspect | Discovery Spec | Recommendation | Rationale |
|--------|---------------|----------------|-----------|
| Table Name | Not specified | `{prefix}quad_qsa_config` | Concise, follows naming pattern |
| `is_active` field | Not specified | **Added** | Soft delete for historical configs |
| LED code positions | `led_code_x` (variable) | `led_code_1` through `led_code_9` | ENUM provides validation |

---

## 2. Phasing Recommendations

### Phase 1: Core Tables (Must Have)

Deploy these tables first - they are required for basic functionality:

```
01-qsa-engraving-schema.sql contains:
  1. {prefix}quad_serial_numbers
  2. {prefix}quad_engraving_batches
  3. {prefix}quad_engraved_modules
  4. {prefix}quad_qsa_config
```

**Deployment Order:**
1. `quad_engraving_batches` - No dependencies
2. `quad_serial_numbers` - References engraving_batches
3. `quad_engraved_modules` - References batches and serial_numbers
4. `quad_qsa_config` - No dependencies (configuration data)

### Phase 2: Seed Data (After Tables)

Create a separate script for initial QSA configuration data:

```
02-qsa-config-seed-data.sql
  - STARa position coordinates (from stara-qsa-sample-svg-data.csv)
  - Other QSA designs as they are documented
```

### Phase 3: Future Enhancements (If Needed)

Consider these additions only if requirements evolve:

- `{prefix}quad_svg_cache` - Store generated SVG files (currently ephemeral)
- `{prefix}quad_engraving_log` - Detailed audit trail per operation
- `{prefix}quad_serial_ranges` - If multiple serial pools are needed

---

## 3. Schema Improvements and Normalization

### 3.1 Recommended Changes from Discovery Spec

**A. Remove `qsa_id` in Favor of Composite Key**

The discovery spec mentions `qsa_id` as an "array reference". This is redundant because:
- A QSA is uniquely identified by `engraving_batch_id` + `qsa_sequence`
- Adding a separate `qsa_id` would require a new table or create denormalization

**Recommendation:** Use `engraving_batch_id` + `qsa_sequence` as the composite identifier for QSAs.

**B. Add `serial_integer` Column**

The discovery spec only mentions the 8-character zero-padded string. Adding an integer column:
- Enables `MAX(serial_integer) + 1` for next serial generation
- Supports efficient range queries for capacity tracking
- Maintains UNIQUE constraint at database level

**C. Rename `module_id` to `module_sku`**

The discovery spec uses `module_id` (e.g., "STAR-34924"), but this is actually a SKU:
- `module_sku` aligns with `oms_batch_items.assembly_sku`
- Prevents confusion with WordPress post IDs
- Makes joins more self-documenting

**D. Add `batch_name` to Engraving Batches**

Allows operators to give meaningful names to batches (e.g., "Monday AM Rush Order").

### 3.2 Normalization Assessment

The schema is in **Third Normal Form (3NF)**:

- No repeating groups (1NF)
- All non-key columns depend on the entire primary key (2NF)
- No transitive dependencies (3NF)

**Intentional Denormalization:**
- `module_count` and `qsa_count` in `quad_engraving_batches` are denormalized for performance
- `module_sku` is repeated in both `serial_numbers` and `engraved_modules` for query efficiency

---

## 4. Query Patterns for Key Operations

### 4.1 Get Next Serial Number

```php
// Get the next available serial number (atomic operation)
$next_serial = $wpdb->get_var("
    SELECT COALESCE(MAX(serial_integer), 0) + 1
    FROM {$wpdb->prefix}quad_serial_numbers
");

// Validate against max capacity
if ($next_serial > 1048575) {
    return new WP_Error('serial_exhausted', 'Serial number capacity exceeded');
}

// Format as 8-character zero-padded string
$serial_string = str_pad($next_serial, 8, '0', STR_PAD_LEFT);
```

### 4.2 Find Modules Awaiting Engraving

```php
// Modules from oms_batch_items that haven't been engraved yet
$sql = $wpdb->prepare("
    SELECT
        bi.batch_id AS production_batch_id,
        bi.assembly_sku AS module_sku,
        bi.order_no AS order_id,
        bi.build_qty,
        bi.qty_received,
        (bi.build_qty - COALESCE(bi.qty_received, 0)) AS qty_needed,
        COALESCE(em.qty_engraved, 0) AS qty_engraved
    FROM {$wpdb->prefix}oms_batch_items bi
    LEFT JOIN (
        SELECT
            production_batch_id,
            module_sku,
            order_id,
            COUNT(*) AS qty_engraved
        FROM {$wpdb->prefix}quad_engraved_modules
        WHERE row_status = 'done'
        GROUP BY production_batch_id, module_sku, order_id
    ) em ON bi.batch_id = em.production_batch_id
        AND bi.assembly_sku = em.module_sku
        AND bi.order_no = em.order_id
    WHERE bi.assembly_sku REGEXP '^[A-Z]{4}[a-z]?-[0-9]{5}$'
      AND (bi.build_qty - COALESCE(bi.qty_received, 0)) > COALESCE(em.qty_engraved, 0)
    ORDER BY bi.batch_id, bi.assembly_sku
");
```

### 4.3 Reserve Serial Numbers for a Batch

```php
// Start transaction for atomic serial reservation
$wpdb->query('START TRANSACTION');

try {
    // Lock the serial_numbers table to prevent race conditions
    $wpdb->query("SELECT MAX(serial_integer) FROM {$wpdb->prefix}quad_serial_numbers FOR UPDATE");

    // Get starting serial
    $start_serial = (int) $wpdb->get_var("
        SELECT COALESCE(MAX(serial_integer), 0) + 1
        FROM {$wpdb->prefix}quad_serial_numbers
    ");

    // Insert reserved serials
    foreach ($modules as $index => $module) {
        $serial = $start_serial + $index;
        $wpdb->insert(
            "{$wpdb->prefix}quad_serial_numbers",
            [
                'serial_number' => str_pad($serial, 8, '0', STR_PAD_LEFT),
                'serial_integer' => $serial,
                'module_sku' => $module['sku'],
                'engraving_batch_id' => $batch_id,
                'production_batch_id' => $module['production_batch_id'],
                'order_id' => $module['order_id'],
                'qsa_sequence' => $module['qsa_sequence'],
                'array_position' => $module['array_position'],
                'status' => 'reserved',
                'created_by' => get_current_user_id(),
            ],
            ['%s', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d']
        );
    }

    $wpdb->query('COMMIT');
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    throw $e;
}
```

### 4.4 Commit Engraved Serials

```php
// Mark serials as engraved when operator completes a QSA
$wpdb->query($wpdb->prepare("
    UPDATE {$wpdb->prefix}quad_serial_numbers
    SET
        status = 'engraved',
        engraved_at = NOW()
    WHERE engraving_batch_id = %d
      AND qsa_sequence = %d
      AND status = 'reserved'
", $batch_id, $qsa_sequence));

// Also update engraved_modules table
$wpdb->query($wpdb->prepare("
    UPDATE {$wpdb->prefix}quad_engraved_modules
    SET
        row_status = 'done',
        engraved_at = NOW()
    WHERE engraving_batch_id = %d
      AND qsa_sequence = %d
      AND row_status = 'pending'
", $batch_id, $qsa_sequence));
```

### 4.5 Void Serials (Retry/Cancel)

```php
// Void serials when operator uses Retry or cancels
$wpdb->query($wpdb->prepare("
    UPDATE {$wpdb->prefix}quad_serial_numbers
    SET
        status = 'voided',
        voided_at = NOW()
    WHERE engraving_batch_id = %d
      AND qsa_sequence = %d
      AND status = 'reserved'
", $batch_id, $qsa_sequence));
```

### 4.6 Get QSA Configuration for SVG Generation

```php
// Get all element configurations for a specific module design
$config = $wpdb->get_results($wpdb->prepare("
    SELECT
        position,
        element_type,
        origin_x,
        origin_y,
        rotation,
        text_height
    FROM {$wpdb->prefix}quad_qsa_config
    WHERE qsa_design = %s
      AND (revision IS NULL OR revision = %s)
      AND is_active = 1
    ORDER BY position, element_type
", $design_base, $revision));
```

### 4.7 Check Serial Number Capacity

```php
// Get remaining serial number capacity
$used = (int) $wpdb->get_var("
    SELECT COALESCE(MAX(serial_integer), 0)
    FROM {$wpdb->prefix}quad_serial_numbers
");
$remaining = 1048575 - $used;
$percentage = round(($remaining / 1048575) * 100, 1);

// Return capacity info
return [
    'used' => $used,
    'remaining' => $remaining,
    'total' => 1048575,
    'percentage_remaining' => $percentage,
    'warning' => $remaining < 10000,
    'critical' => $remaining < 1000,
];
```

---

## 5. Index Strategy

### 5.1 Primary Query Patterns and Supporting Indexes

| Query Pattern | Table | Index Used |
|---------------|-------|------------|
| Get next serial number | serial_numbers | `uk_serial_integer` |
| Find modules awaiting engraving | engraved_modules | `idx_done_modules` |
| Get batch status | engraving_batches | `idx_status` |
| Get serials by batch | serial_numbers | `idx_batch_status` |
| Get config by design | qsa_config | `idx_design_revision` |

### 5.2 Index Recommendations

The schema includes these indexes optimized for Quadica's query patterns:

**Serial Numbers Table:**
- `uk_serial_integer` - UNIQUE, used for next serial generation
- `uk_serial_number` - UNIQUE, used for lookups by string
- `idx_batch_status` - Composite for batch + status queries
- `idx_module_sku` - For queries filtering by SKU

**Engraved Modules Table:**
- `uk_production_module` - Prevents duplicate engraving
- `idx_done_modules` - Composite for "modules already done" query

**QSA Config Table:**
- `uk_design_element` - Ensures unique element per design/position
- `idx_design_revision` - For config lookups by design

---

## 6. Data Volume Estimates

| Table | Annual Growth | 5-Year Projection | Notes |
|-------|--------------|-------------------|-------|
| serial_numbers | ~85k rows | ~425k rows | Based on current production |
| engraving_batches | ~2k rows | ~10k rows | ~20-50 batches/week |
| engraved_modules | ~85k rows | ~425k rows | 1:1 with serial_numbers |
| qsa_config | ~500 rows | ~1k rows | Slow growth, config only |

**Storage Estimate (5 years):**
- serial_numbers: ~100MB (850k rows average)
- engraved_modules: ~80MB
- engraving_batches: <5MB
- qsa_config: <1MB

**Total: ~200MB** - Well within comfortable limits for MySQL.

---

## 7. Integration Notes

### 7.1 Legacy oms_batch_items Integration

The schema reads from but never writes to `oms_batch_items`:

```php
// Fields accessed from oms_batch_items:
// - batch_id: Production batch identifier
// - assembly_sku: Module SKU (e.g., "STAR-34924")
// - build_qty: Quantity to build
// - qty_received: Quantity already built
// - order_no: WooCommerce order ID
```

### 7.2 Order BOM CPT Integration

LED information is retrieved from the Order BOM CPT (`quad_order_bom`):

```php
// Lookup pattern:
// 1. Get order_no from oms_batch_items
// 2. Query quad_order_bom posts for that order
// 3. Get LED SKUs and positions from post meta
// 4. Lookup led_shortcode_3 from WooCommerce product meta
```

### 7.3 WooCommerce Product Integration

LED codes are stored in WooCommerce product meta:

```php
// Get LED code for an LED product
$led_code = get_post_meta($led_product_id, 'led_shortcode_3', true);
```

---

## 8. Deployment Instructions

### 8.1 Fresh Installation

1. **Backup the database** (via Kinsta or phpMyAdmin)

2. **Prepare the SQL script:**
   ```bash
   # Replace {prefix} with actual prefix
   sed 's/{prefix}/lw_/g' 01-qsa-engraving-schema.sql > 01-lw-schema.sql
   ```

3. **Run via phpMyAdmin:**
   - Open phpMyAdmin for the site
   - Select the database
   - Go to Import tab
   - Upload the prepared SQL file
   - Execute

4. **Verify installation:**
   ```sql
   SHOW TABLES LIKE '%quad%';
   -- Should show 4 tables:
   -- lw_quad_engraving_batches
   -- lw_quad_engraved_modules
   -- lw_quad_qsa_config
   -- lw_quad_serial_numbers
   ```

### 8.2 Rollback Procedure

If issues are discovered after deployment:

1. **Backup first** (captures any data that was created)

2. **Run rollback script:**
   ```bash
   sed 's/{prefix}/lw_/g' rollback-01-qsa-engraving-schema.sql > rollback-lw.sql
   ```

3. **Execute via phpMyAdmin**

---

## 9. Testing Notes

### 9.1 Manual Table Cleanup for Testing

When testing the engraving functionality, you may need to empty tables:

**Please manually empty these tables via phpMyAdmin:**
- `lw_quad_serial_numbers` - To reset serial number sequence
- `lw_quad_engraving_batches` - To clear test batches
- `lw_quad_engraved_modules` - To allow re-engraving of test modules

### 9.2 Verification Queries

After running tests, verify data integrity:

```sql
-- Check serial number sequence
SELECT MAX(serial_integer) AS highest_serial FROM lw_quad_serial_numbers;

-- Check for orphaned records
SELECT COUNT(*) AS orphaned_serials
FROM lw_quad_serial_numbers s
LEFT JOIN lw_quad_engraving_batches b ON s.engraving_batch_id = b.id
WHERE b.id IS NULL;

-- Verify status consistency
SELECT status, COUNT(*) AS count
FROM lw_quad_serial_numbers
GROUP BY status;
```

---

## 10. Files Created

| File | Path | Purpose |
|------|------|---------|
| Schema Script | `/docs/database/install/01-qsa-engraving-schema.sql` | Creates all 4 tables |
| Rollback Script | `/docs/database/rollback/rollback-01-qsa-engraving-schema.sql` | Drops all 4 tables |
| This Document | `/docs/database/QSA-ENGRAVING-DATABASE-RECOMMENDATIONS.md` | Recommendations and guidance |

---

## Appendix A: Table Relationship Diagram

```
                                    +------------------+
                                    | oms_batch_items  |
                                    | (legacy, read)   |
                                    +--------+---------+
                                             |
                                             | batch_id, assembly_sku, order_no
                                             v
+------------------------+         +-------------------------+
| quad_engraving_batches |<--------| quad_serial_numbers     |
+------------------------+         +-------------------------+
| id (PK)                |         | id (PK)                 |
| batch_name             |         | serial_number (UK)      |
| module_count           |         | serial_integer (UK)     |
| qsa_count              |         | module_sku              |
| status                 |         | engraving_batch_id (FK) |
| created_at             |         | production_batch_id     |
| created_by             |         | order_id                |
| completed_at           |         | qsa_sequence            |
+------------------------+         | array_position          |
         |                         | status                  |
         |                         | reserved_at             |
         |                         | engraved_at             |
         |                         | voided_at               |
         |                         +-------------------------+
         |
         |                         +-------------------------+
         +------------------------>| quad_engraved_modules   |
                                   +-------------------------+
                                   | id (PK)                 |
                                   | engraving_batch_id (FK) |
                                   | production_batch_id     |
                                   | module_sku              |
                                   | order_id                |
                                   | serial_number           |
                                   | qsa_sequence            |
                                   | array_position          |
                                   | row_status              |
                                   | engraved_at             |
                                   +-------------------------+


+-------------------------+
| quad_qsa_config         |
+-------------------------+
| id (PK)                 |
| qsa_design              |
| revision                |
| position                |
| element_type            |
| origin_x                |
| origin_y                |
| rotation                |
| text_height             |
| is_active               |
+-------------------------+
(standalone configuration)
```

---

**Document Status:** Complete - Ready for Review

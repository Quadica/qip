# Implementation Plan: Legacy Module SKU Mapping Support

**Date:** 2026-01-10
**Status:** Ready for Implementation Approval
**Author:** Claude (with Ron's requirements)

---

## Executive Summary

Extend the QSA engraving system to support legacy module SKU formats alongside the standard QSA pattern. This enables gradual transition from legacy module designs to the new QSA system, allowing legacy modules to be engraved as they are redesigned.

**Key Design Decision:** Only legacy modules with an explicit SKU mapping are included in the engraving workflow. Unmapped legacy SKUs continue to be ignored, allowing incremental adoption.

---

## Background

### Current State
- QSA engraving system accepts only SKUs matching `^[A-Z]{4}[a-z]?-[0-9]{5}$`
- Examples: `STARa-34924`, `CUBE-91247`, `PICOa-12345`
- All processing built around 4-letter design codes (STAR, CUBE, PICO)

### Problem
- Legacy modules use varied SKU formats: `SP-01`, `SZ-01`, `MR-*-10S`, `234356-1`
- Cannot be engraved using current system
- Need to support gradual redesign/transition to QSA format

### Solution
- Create SKU mapping table: legacy pattern → 4-letter canonical code
- Only modules with mappings are included in processing
- Unmapped legacy modules remain invisible to the system (no disruption)

---

## Requirements Summary

| Requirement | Decision |
|-------------|----------|
| Mapping Storage | Database table (`quad_sku_mappings`) |
| Mapping Management | Admin UI under QSA Engraving settings |
| Pattern Support | Exact match, prefix, suffix, regex |
| Canonical Code | 4-letter uppercase code (e.g., SP01, MR1S) |
| Unmapped SKUs | Ignored (continue existing behavior) |
| Original SKU | Preserved for traceability |
| Config Required | Each canonical code needs coordinates in `quad_qsa_config` |

---

## Implementation Phases

### Phase 1: Database Schema

**New Table: `{prefix}quad_sku_mappings`**

```sql
CREATE TABLE IF NOT EXISTS `{prefix}quad_sku_mappings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `legacy_pattern` VARCHAR(50) NOT NULL
        COMMENT 'Pattern to match (exact string or pattern with wildcards)',

    `match_type` ENUM('exact', 'prefix', 'suffix', 'regex') NOT NULL DEFAULT 'exact'
        COMMENT 'How to interpret legacy_pattern',

    `canonical_code` CHAR(4) NOT NULL
        COMMENT 'Target 4-letter design code (e.g., SP01, MR1S)',

    `revision` CHAR(1) DEFAULT NULL
        COMMENT 'Optional revision letter (a-z)',

    `description` VARCHAR(255) DEFAULT NULL
        COMMENT 'Human-readable description',

    `priority` SMALLINT UNSIGNED NOT NULL DEFAULT 100
        COMMENT 'Resolution order - lower value = higher priority',

    `is_active` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1 = active, 0 = disabled',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `created_by` BIGINT UNSIGNED DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pattern` (`legacy_pattern`, `match_type`),
    KEY `idx_canonical` (`canonical_code`),
    KEY `idx_active_priority` (`is_active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maps legacy SKU patterns to canonical 4-letter QSA design codes';
```

**Optional: Add original SKU tracking to engraved modules:**

```sql
ALTER TABLE `{prefix}quad_engraved_modules`
ADD COLUMN `original_sku` VARCHAR(50) DEFAULT NULL
    COMMENT 'Original SKU before mapping (NULL if native QSA format)'
AFTER `module_sku`;
```

**File:** `docs/database/install/10-sku-mappings-schema.sql`

---

### Phase 2: SKU Mapping Repository

**New File:** `includes/Database/class-sku-mapping-repository.php`

```php
class SKU_Mapping_Repository {

    /**
     * Find matching mapping for a SKU.
     * Returns mapping with lowest priority (highest precedence).
     * Returns null if no mapping matches.
     */
    public function find_mapping(string $sku): ?array;

    /**
     * Get all mappings (for admin listing).
     */
    public function get_all(bool $active_only = true): array;

    /**
     * Create new mapping.
     */
    public function create(array $data): int|WP_Error;

    /**
     * Update existing mapping.
     */
    public function update(int $id, array $data): bool|WP_Error;

    /**
     * Delete mapping.
     */
    public function delete(int $id): bool;

    /**
     * Test if a pattern matches a test SKU.
     */
    public function test_pattern(string $pattern, string $match_type, string $test_sku): bool;
}
```

**Pattern Matching SQL:**

| Match Type | SQL Condition |
|------------|---------------|
| exact | `WHERE legacy_pattern = %s` |
| prefix | `WHERE %s LIKE CONCAT(legacy_pattern, '%')` |
| suffix | `WHERE %s LIKE CONCAT('%', legacy_pattern)` |
| regex | `WHERE %s REGEXP legacy_pattern` |

**Priority Handling:**
- Query ordered by `priority ASC` (lower = higher precedence)
- First matching pattern wins
- Allows specific patterns to override general ones

---

### Phase 3: Legacy SKU Resolver Service

**New File:** `includes/Services/class-legacy-sku-resolver.php`

```php
class Legacy_SKU_Resolver {

    private SKU_Mapping_Repository $repository;
    private array $cache = [];

    /**
     * Resolve a SKU to its canonical form.
     *
     * Returns structured array if resolvable:
     * - is_legacy: bool - Whether this is a legacy SKU
     * - original_sku: string - The input SKU
     * - canonical_code: string - 4-letter design code
     * - revision: string|null - Revision letter
     * - canonical_sku: string - Synthetic SKU for config lookup
     * - mapping_id: int|null - ID of matching mapping (null for QSA)
     *
     * Returns null if SKU cannot be resolved (unknown format).
     */
    public function resolve(string $sku): ?array;

    /**
     * Check if SKU is a legacy format (not QSA pattern).
     */
    public function is_legacy_sku(string $sku): bool;

    /**
     * Check if SKU matches QSA pattern.
     */
    public function is_qsa_sku(string $sku): bool;

    /**
     * Clear resolution cache.
     */
    public function clear_cache(): void;
}
```

**Resolution Logic:**

```
resolve(sku):
  1. Check cache → return if found

  2. If sku matches QSA pattern:
     - Return {is_legacy: false, canonical_code: extracted, ...}
     - No mapping needed

  3. Query mapping table for matching pattern

  4. If no mapping found:
     - Return null (SKU unknown - will be ignored)

  5. Build result:
     - canonical_sku: "{CODE}{rev}-LEGAC" (e.g., SP01-LEGAC)
     - Store in cache
     - Return result
```

**Critical Behavior:** Unmapped legacy SKUs return `null`, causing them to be filtered out of the module selection. This ensures only explicitly mapped legacy modules are included.

---

### Phase 4: Module Selector Integration

**Modify:** `includes/Services/class-module-selector.php`

**Current Query (line 154):**
```sql
WHERE bi.assembly_sku REGEXP '^[A-Z]{4}[a-z]?-[0-9]{5}$'
```

**New Approach:**
1. Remove the REGEXP filter from SQL
2. Fetch all modules with positive qty_to_engrave
3. Filter in PHP using the resolver

**Changes:**

```php
// Constructor - inject resolver
public function __construct(
    Batch_Repository $batch_repository,
    Legacy_SKU_Resolver $legacy_resolver
) {
    $this->batch_repository = $batch_repository;
    $this->legacy_resolver = $legacy_resolver;
}

// get_modules_awaiting() - remove REGEXP, add resolution
public function get_modules_awaiting(): array {
    // ... query WITHOUT SKU filter ...

    // Filter and resolve
    $resolved = $this->resolve_and_filter_modules($results);

    return $this->group_by_base_type($resolved);
}

private function resolve_and_filter_modules(array $modules): array {
    $resolved = [];

    foreach ($modules as $module) {
        $resolution = $this->legacy_resolver->resolve($module['module_sku']);

        // Skip unknown formats (unmapped legacy or invalid)
        if (null === $resolution) {
            continue;
        }

        // Augment with resolution data
        $module['original_sku'] = $resolution['original_sku'];
        $module['canonical_code'] = $resolution['canonical_code'];
        $module['canonical_sku'] = $resolution['canonical_sku'];
        $module['revision'] = $resolution['revision'];
        $module['is_legacy'] = $resolution['is_legacy'];

        $resolved[] = $module;
    }

    return $resolved;
}

// group_by_base_type() - use canonical_code
private function group_by_base_type(array $modules): array {
    foreach ($modules as $module) {
        // Use canonical_code (works for both QSA and legacy)
        $base_type = $module['canonical_code'];
        if (!empty($module['revision'])) {
            $base_type .= $module['revision'];
        }
        // ... existing grouping logic ...
    }
}
```

---

### Phase 5: Batch Creation Integration

**Modify:** `includes/Ajax/class-batch-ajax-handler.php`

**Changes to `validate_selection()` (line 699):**

```php
// BEFORE
if (!Module_Selector::is_qsa_compatible($validated['module_sku'])) {
    return new WP_Error('invalid_sku_format', ...);
}

// AFTER
$resolution = $this->legacy_resolver->resolve($validated['module_sku']);
if (null === $resolution) {
    return new WP_Error(
        'unknown_sku_format',
        sprintf(
            __('SKU %s is not recognized. Add a mapping for legacy SKUs.', 'qsa-engraving'),
            $validated['module_sku']
        )
    );
}

// Attach resolution data
$validated['canonical_code'] = $resolution['canonical_code'];
$validated['canonical_sku'] = $resolution['canonical_sku'];
$validated['original_sku'] = $resolution['original_sku'];
$validated['revision'] = $resolution['revision'];
$validated['is_legacy'] = $resolution['is_legacy'];
```

**Changes to `extract_base_type()` (line 780):**

```php
private function extract_base_type(string $sku, ?array $module_data = null): string {
    // Use pre-resolved canonical code if available
    if (isset($module_data['canonical_code'])) {
        $base = $module_data['canonical_code'];
        if (!empty($module_data['revision'])) {
            $base .= $module_data['revision'];
        }
        return $base;
    }

    // Fallback to existing extraction for QSA SKUs
    if (preg_match('/^([A-Z]{4}[a-z]?)/', $sku, $matches)) {
        return $matches[1];
    }

    return 'UNKNOWN';
}
```

---

### Phase 6: Config Loader Integration

**Modify:** `includes/Services/class-config-loader.php`

**Update `parse_sku()` to accept legacy canonical format:**

```php
public function parse_sku(string $sku): array|WP_Error {
    // Standard QSA format OR synthetic legacy format (xxx-LEGAC)
    if (preg_match('/^([A-Z]{4})([a-z])?-(\d{5}|LEGAC)$/', $sku, $matches)) {
        return [
            'design' => $matches[1],
            'revision' => $matches[2] ?: null,
            'config' => $matches[3],
        ];
    }

    // Try legacy resolution
    $resolution = $this->legacy_resolver->resolve($sku);
    if (null === $resolution) {
        return new WP_Error(
            'invalid_sku_format',
            sprintf(__('Cannot parse SKU: %s', 'qsa-engraving'), $sku)
        );
    }

    return [
        'design' => $resolution['canonical_code'],
        'revision' => $resolution['revision'],
        'config' => 'LEGAC',
    ];
}
```

---

### Phase 7: Admin UI for Mapping Management

**Add settings tab:** "SKU Mappings"

**Components:**

1. **Mapping List Table**
   - Columns: Pattern, Match Type, Canonical Code, Description, Priority, Active
   - Actions: Edit, Delete, Toggle Active
   - Sortable by priority

2. **Add/Edit Form**
   - Legacy Pattern (text input with validation)
   - Match Type (dropdown: exact, prefix, suffix, regex)
   - Canonical Code (4-letter input, uppercase enforced)
   - Revision (optional, single letter dropdown)
   - Description (text area)
   - Priority (number, default 100)
   - Active (checkbox)

3. **Test Tool**
   - SKU input field
   - "Test Resolution" button
   - Shows: Matched pattern, canonical result, config exists (yes/no)

**New Files:**
- `includes/Admin/class-sku-mapping-admin.php`
- `includes/Ajax/class-sku-mapping-ajax-handler.php`
- `assets/js/src/sku-mappings/MappingManager.js`

**AJAX Endpoints:**
| Endpoint | Purpose |
|----------|---------|
| `qsa_get_sku_mappings` | List mappings (pagination, search) |
| `qsa_add_sku_mapping` | Create new mapping |
| `qsa_update_sku_mapping` | Update existing |
| `qsa_delete_sku_mapping` | Delete mapping |
| `qsa_test_sku_resolution` | Test single SKU |

---

### Phase 8: Plugin Wiring

**Modify:** `qsa-engraving.php`

```php
// Add properties
private ?SKU_Mapping_Repository $sku_mapping_repository = null;
private ?Legacy_SKU_Resolver $legacy_sku_resolver = null;

// Initialize in init_repositories()
$this->sku_mapping_repository = new SKU_Mapping_Repository();

// Initialize in init_services()
$this->legacy_sku_resolver = new Legacy_SKU_Resolver(
    $this->sku_mapping_repository
);

// Update Module_Selector instantiation
$this->module_selector = new Module_Selector(
    $this->batch_repository,
    $this->legacy_sku_resolver
);

// Inject into other services as needed
// - Batch_Ajax_Handler
// - Config_Loader

// Add getter
public function get_legacy_sku_resolver(): Legacy_SKU_Resolver {
    return $this->legacy_sku_resolver;
}
```

---

## Files Summary

### New Files (7)

| File | Purpose |
|------|---------|
| `docs/database/install/10-sku-mappings-schema.sql` | Database schema |
| `includes/Database/class-sku-mapping-repository.php` | CRUD for mappings |
| `includes/Services/class-legacy-sku-resolver.php` | SKU resolution service |
| `includes/Admin/class-sku-mapping-admin.php` | Admin page |
| `includes/Ajax/class-sku-mapping-ajax-handler.php` | Admin AJAX |
| `assets/js/src/sku-mappings/MappingManager.js` | React UI |
| `docs/plans/legacy-sku-mapping-plan.md` | This document |

### Modified Files (5)

| File | Changes |
|------|---------|
| `includes/Services/class-module-selector.php` | Remove REGEXP, add resolution step |
| `includes/Ajax/class-batch-ajax-handler.php` | Update validation and base type extraction |
| `includes/Services/class-config-loader.php` | Accept legacy SKUs in parse_sku() |
| `includes/Admin/class-admin-menu.php` | Add SKU Mappings tab |
| `qsa-engraving.php` | Wire up new repository and service |

---

## Adding a New Legacy Module Design

When ready to transition a legacy module design:

### Step 1: Create Mapping

Via Admin UI or SQL:
```sql
INSERT INTO `lw_quad_sku_mappings`
  (legacy_pattern, match_type, canonical_code, description, priority)
VALUES
  ('SP-01', 'exact', 'SP01', 'SinkPAD Single Rebel LED', 100);
```

### Step 2: Add Configuration Coordinates

Create config entries for the canonical code:
```sql
INSERT INTO `lw_quad_qsa_config`
  (qsa_design, revision, position, element_type, origin_x, origin_y, rotation, text_height, is_active, created_by)
VALUES
  ('SP01', NULL, 1, 'micro_id', X.XXX, Y.YYY, 0, NULL, 1, 1),
  ('SP01', NULL, 1, 'module_id', X.XXX, Y.YYY, 0, 1.30, 1, 1),
  ('SP01', NULL, 1, 'serial_url', X.XXX, Y.YYY, 0, 1.20, 1, 1),
  ('SP01', NULL, 1, 'led_code_1', X.XXX, Y.YYY, 0, 1.20, 1, 1),
  -- Positions 2-8 as needed for array layout
  ('SP01', NULL, 0, 'qr_code', 139.117, 56.850, 0, NULL, 1, 1);
```

### Step 3: Verify

1. Legacy SKU now appears in Batch Creator UI
2. Test SKU resolution via Admin UI test tool
3. Create test batch and verify SVG generation

---

## Testing Plan

### Smoke Tests

| Test ID | Description |
|---------|-------------|
| TC-LEG-001 | Mapping table created with correct schema |
| TC-LEG-002 | Resolver passes through QSA SKUs unchanged |
| TC-LEG-003 | Resolver resolves exact-match legacy SKU |
| TC-LEG-004 | Resolver resolves prefix-match legacy SKU |
| TC-LEG-005 | Resolver resolves suffix-match legacy SKU |
| TC-LEG-006 | Resolver resolves regex-match legacy SKU |
| TC-LEG-007 | Resolver returns null for unmapped legacy SKU |
| TC-LEG-008 | Module selector includes mapped legacy modules |
| TC-LEG-009 | Module selector excludes unmapped legacy modules |
| TC-LEG-010 | Batch creation works with legacy modules |
| TC-LEG-011 | Config lookup works for legacy canonical codes |
| TC-LEG-012 | Priority ordering works (lower priority wins) |

### Manual Testing

1. Add test mapping: `TEST-01` → `TST1`
2. Add minimal config for `TST1` (position 1 elements)
3. Create record in `oms_batch_items` with `assembly_sku = 'TEST-01'`
4. Verify:
   - Module appears in Batch Creator
   - Grouped under "TST1" base type
   - Can create batch with this module
   - SVG generates correctly
5. Remove mapping - verify module disappears from Batch Creator
6. Clean up test data

---

## Transition Workflow

```
Phase 1: Infrastructure (This Plan)
  ├── Build mapping system
  └── Deploy to staging/production

Phase 2: Initial Adoption
  ├── Redesign first legacy module for QSA
  ├── Add mapping entry
  ├── Add config coordinates
  └── Test end-to-end

Phase 3: Gradual Migration
  ├── Redesign additional legacy modules
  ├── Add mapping + config for each
  └── Legacy modules without mapping remain in old workflow

Phase 4: Completion
  └── All legacy modules transitioned to QSA system
```

---

## Notes

- **No Disruption:** Unmapped legacy SKUs are silently ignored - existing workflow unaffected
- **Incremental:** Add mappings as designs are transitioned
- **Traceability:** Original SKU preserved in `original_sku` column
- **Config Required:** Each canonical code needs coordinate data before engraving works
- **Same Grouping Rules:** Legacy modules are grouped separately from QSA modules (different base types can't share arrays)

---

## Approval Checklist

- [ ] Database schema approved
- [ ] Admin UI design approved
- [ ] Integration approach approved
- [ ] Ready to begin Phase 1 implementation

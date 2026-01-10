# Implementation Plan: Replace Data Matrix with QR Code + QSA ID System

**Date:** 2026-01-08
**Status:** Questions Resolved - Ready for Implementation Approval
**Author:** Claude (with Ron's requirements)

---

## Executive Summary

Replace per-module Data Matrix barcodes with a single QR code per QSA array, introducing a new QSA ID system for array-level identification.

**Current State:**
- Each module has a Data Matrix barcode (8 per QSA array)
- Data Matrix encodes `quadi.ca/{serial_number}` for each module
- Significantly slows down engraving process

**Proposed State:**
- Single QR code per QSA array
- QR code encodes `quadi.ca/{qsa_id}` (e.g., `quadi.ca/cube00076`)
- QSA ID links to all modules on that array for future reporting
- Module serial numbers and other elements remain unchanged

---

## Requirements Summary

Based on interview with Ron:

| Requirement | Decision |
|-------------|----------|
| QSA ID Format | `{DESIGN}{5-digit}` (e.g., CUBE00076) |
| ID Sequencing | Per-design sequential (CUBE00001, CUBE00002...) |
| ID Assignment | At SVG generation (Start Row click) |
| ID Persistence | Keep same ID on regeneration |
| ID Scope | Per physical array (each SVG gets unique ID) |
| QR Position | Fixed per design (stored in config, position=0) |
| QR Size | Configurable per design (default 10mm) |
| QR Content | `quadi.ca/{qsa_id}` |
| Data Matrix | Complete removal |
| Module Serials | Keep (8-digit per module) |
| Serial URL Text | Keep (human-readable per module) |
| Storage | New table linking QSA ID to batch/sequence |
| UI Display | Show QSA ID in Engraving Queue after generation |

---

## Phase 1: Database Schema Changes

### 1.1 New Table: `lw_quad_qsa_identifiers`

**Purpose:** Track QSA ID assignments, linking to batch/sequence for future reporting.

```sql
CREATE TABLE IF NOT EXISTS `lw_quad_qsa_identifiers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `qsa_id` VARCHAR(20) NOT NULL COMMENT 'e.g., CUBE00076',
    `design` VARCHAR(10) NOT NULL COMMENT 'Design name (CUBE, STAR, etc.)',
    `sequence_number` INT UNSIGNED NOT NULL COMMENT 'Per-design sequential number',
    `batch_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to engraving_batches',
    `qsa_sequence` SMALLINT UNSIGNED NOT NULL COMMENT 'QSA sequence within batch',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_qsa_id` (`qsa_id`),
    UNIQUE KEY `uk_design_sequence` (`design`, `sequence_number`),
    UNIQUE KEY `uk_batch_qsa` (`batch_id`, `qsa_sequence`),
    KEY `idx_design` (`design`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='QSA-level identifiers linking arrays to modules for reporting';
```

**File:** `docs/database/install/06-qsa-identifiers-schema.sql`

### 1.2 Modify Config Table

**Purpose:** Support design-level elements (position=0) and QR code size.

```sql
-- Add element_size column for configurable QR code size
ALTER TABLE `lw_quad_qsa_config`
ADD COLUMN `element_size` DECIMAL(5,2) DEFAULT NULL
COMMENT 'Element size in mm (used for qr_code)'
AFTER `text_height`;

-- Delete all datamatrix configuration entries (test data only)
DELETE FROM `lw_quad_qsa_config` WHERE `element_type` = 'datamatrix';
```

**File:** `docs/database/install/07-config-qr-support.sql`

---

## Phase 2: Remove Data Matrix Code

### 2.1 Files to Delete

| File | Lines | Reason |
|------|-------|--------|
| `includes/SVG/class-datamatrix-renderer.php` | 347 | Entire class no longer needed |

### 2.2 Files to Modify

| File | Changes |
|------|---------|
| `includes/SVG/class-svg-document.php` | Remove `render_datamatrix()` method (lines 677-697) and conditional block in `render_module()` (lines 580-586) |
| `includes/SVG/class-coordinate-transformer.php` | Remove `get_datamatrix_position()` method (lines 258-282) |
| `includes/Database/class-config-repository.php` | Remove 'datamatrix' from ELEMENT_TYPES constant, add 'qr_code' |
| `includes/Services/class-config-loader.php` | Remove 'datamatrix' from required elements in `validate_config()` |
| `includes/Ajax/class-lightburn-ajax-handler.php` | Remove 'datamatrix' from ORDER BY FIELD() clauses (lines 874, 892) |
| `includes/Admin/class-admin-menu.php` | Remove datamatrix condition from JavaScript (line 986) |
| `includes/Services/class-svg-generator.php` | Update `check_dependencies()` message to reference QR codes |
| `tests/smoke/wp-smoke.php` | Remove all Data Matrix test methods |

**Note:** Keep `tecnickcom/tc-lib-barcode` library - it supports QR codes.

---

## Phase 3: QSA ID Repository

### 3.1 New Repository Class

**File:** `includes/Database/class-qsa-identifier-repository.php`

**Key Methods:**

```php
class QSA_Identifier_Repository {
    /**
     * Get existing QSA ID or create new one.
     * Ensures regeneration keeps same ID.
     */
    public function get_or_create(
        int $batch_id,
        int $qsa_sequence,
        string $design
    ): string|WP_Error;

    /**
     * Get next available sequence number for a design.
     */
    private function get_next_sequence(string $design): int;

    /**
     * Format QSA ID from design and sequence.
     * e.g., format_qsa_id('CUBE', 76) => 'CUBE00076'
     */
    private function format_qsa_id(string $design, int $sequence): string;

    /**
     * Get QSA ID by batch and sequence (for lookup).
     */
    public function get_by_batch(int $batch_id, int $qsa_sequence): ?array;

    /**
     * Get all modules linked to a QSA ID (for future reporting).
     */
    public function get_modules_for_qsa(string $qsa_id): array;
}
```

### 3.2 Register in Plugin

**File:** `qsa-engraving.php`

- Add property `$qsa_identifier_repository`
- Initialize in `init_repositories()`
- Add getter `get_qsa_identifier_repository()`
- Inject into `LightBurn_Ajax_Handler` constructor

---

## Phase 4: QR Code Renderer

### 4.1 New Renderer Class

**File:** `includes/SVG/class-qr-code-renderer.php`

**Pattern:** Follow existing `Datamatrix_Renderer` structure.

```php
class QR_Code_Renderer {
    public const DEFAULT_SIZE = 10.0; // mm
    public const MODULE_FILL = '#000000';

    /**
     * Check if tc-lib-barcode is available.
     */
    public static function is_library_available(): bool;

    /**
     * Render QR code SVG content.
     */
    public static function render(
        string $data,
        float $size = self::DEFAULT_SIZE
    ): string|WP_Error;

    /**
     * Render QR code positioned at coordinates.
     */
    public static function render_positioned(
        string $data,
        float $x,
        float $y,
        float $size = self::DEFAULT_SIZE,
        string $id = ''
    ): string|WP_Error;
}
```

**QR Code Specs:**
- Type: `QRCODE,H` (High error correction - 30%)
- Size: Configurable, default 10mm
- Content: `quadi.ca/{qsa_id}` (lowercase)
- Color: #000000 (same as other elements)

---

## Phase 5: SVG Document Integration

### 5.1 Add QR Code Support

**File:** `includes/SVG/class-svg-document.php`

**New Properties:**
```php
private ?string $qr_code_data = null;
private ?array $qr_code_config = null;
```

**New Methods:**
```php
public function set_qr_code(string $data, array $config): self;
private function render_qr_code(): string|WP_Error;
```

**Modify `render()` method:**

QR code renders at design-level, after alignment marks, before modules:

```
SVG Structure:
├── XML declaration
├── SVG opening tag
├── <defs/>
├── Rotation group (if needed)
├── Boundary rectangle (alignment)
├── Center crosshair (alignment)
├── [QR CODE HERE] ← NEW design-level element
├── Offset group (if needed)
├── Module 1..8 (per-position elements)
└── Close groups
```

---

## Phase 6: Config Repository Updates

### 6.1 Support Position 0

**File:** `includes/Database/class-config-repository.php`

**Modify ELEMENT_TYPES:**
```php
public const ELEMENT_TYPES = array(
    'micro_id',
    'qr_code',      // NEW - replaces datamatrix
    'module_id',
    'serial_url',
    'led_code_1',
    // ... through led_code_9
);
```

**Modify position validation:**
```php
// Change: if ($position < 1 || $position > 8)
// To:     if ($position < 0 || $position > 8)
```

**Modify `get_config()` to return position 0 elements separately.**

---

## Phase 7: LightBurn Handler Integration

### 7.1 Update SVG Generation Flow

**File:** `includes/Ajax/class-lightburn-ajax-handler.php`

**Modify `generate_svg_for_qsa()`:**

```php
// Get or create QSA ID for this array
$qsa_id = $this->qsa_identifier_repository->get_or_create(
    $batch_id,
    $qsa_sequence,
    $parsed['design']
);

// Pass QR code data to SVG generator
$options['qr_code_data'] = 'quadi.ca/' . strtolower($qsa_id);

// Return QSA ID in response for UI display
return array(
    'svg' => $svg,
    'qsa_id' => $qsa_id,
    // ...
);
```

---

## Phase 8: Frontend Updates

### 8.1 Display QSA ID in Engraving Queue

**File:** `assets/js/src/engraving-queue/components/EngravingQueue.js`

- After SVG generation, display assigned QSA ID
- Format: "QSA ID: CUBE00076" badge in row details
- Rebuild React bundle after changes

---

## Phase 9: QSA Config Seeding

### 9.1 Add QR Code Config for Each Base ID

**File:** `docs/database/install/08-qr-code-seed.sql`

Ron will provide specific coordinates for each Base ID. Example format:

```sql
-- STARa QR code position (coordinates provided by Ron)
INSERT INTO `lw_quad_qsa_config`
(qsa_design, revision, position, element_type, origin_x, origin_y, element_size, is_active)
VALUES
('STAR', 'a', 0, 'qr_code', 139.1167, 33.0122, 10.0, 1);

-- Additional Base IDs will be added as Ron provides coordinates
-- Format: design, revision, position=0, 'qr_code', x, y, size_mm, active
```

**Note:** Coordinates are specific to each Base ID layout. Ron will provide values during this phase.

---

## File Summary

### New Files (4)

| File | Purpose |
|------|---------|
| `docs/database/install/06-qsa-identifiers-schema.sql` | New QSA ID table |
| `docs/database/install/07-config-qr-support.sql` | Config table modifications |
| `includes/Database/class-qsa-identifier-repository.php` | QSA ID repository |
| `includes/SVG/class-qr-code-renderer.php` | QR code SVG renderer |

### Deleted Files (1)

| File | Reason |
|------|--------|
| `includes/SVG/class-datamatrix-renderer.php` | Replaced by QR code |

### Modified Files (10)

| File | Changes |
|------|---------|
| `includes/SVG/class-svg-document.php` | Remove datamatrix, add QR code |
| `includes/SVG/class-coordinate-transformer.php` | Remove `get_datamatrix_position()` |
| `includes/Database/class-config-repository.php` | Update ELEMENT_TYPES, support position=0 |
| `includes/Services/class-config-loader.php` | Remove datamatrix from required |
| `includes/Services/class-svg-generator.php` | Pass QR code data to document |
| `includes/Ajax/class-lightburn-ajax-handler.php` | Get/create QSA ID, pass to SVG |
| `includes/Admin/class-admin-menu.php` | Remove datamatrix JavaScript condition |
| `qsa-engraving.php` | Register QSA Identifier Repository |
| `assets/js/src/engraving-queue/*` | Display QSA ID |
| `tests/smoke/wp-smoke.php` | Remove datamatrix tests, add QR tests |

---

## Implementation Order

1. **Phase 1:** Run database migrations (06, 07 SQL files)
2. **Phase 2:** Remove Data Matrix code
3. **Phase 3:** Create QSA Identifier Repository
4. **Phase 4:** Create QR Code Renderer
5. **Phase 5:** Integrate into SVG Document
6. **Phase 6:** Update Config Repository for position=0
7. **Phase 7:** Update LightBurn Handler
8. **Phase 8:** Frontend updates + rebuild
9. **Phase 9:** Seed QR code config data
10. **Testing:** Full verification

---

## Verification Checklist

### Manual Testing

- [ ] Create new batch with CUBE modules
- [ ] Start row (generate SVG)
- [ ] Verify QSA ID assigned (e.g., CUBE00001)
- [ ] Verify QSA ID displayed in Engraving Queue UI
- [ ] Download SVG and verify:
  - [ ] Contains QR code (not Data Matrix)
  - [ ] QR code is single element (not per-module)
  - [ ] QR code positioned at design-level coordinates
  - [ ] QR code approximately 10mm square
- [ ] Scan QR code with smartphone
- [ ] Verify URL: `quadi.ca/cube00001`
- [ ] Verify module elements unchanged:
  - [ ] Micro-ID still present
  - [ ] Serial URL text still present
  - [ ] LED codes still present
  - [ ] Module ID text still present
- [ ] Regenerate SVG - verify same QSA ID kept
- [ ] Complete row, start next - verify QSA ID increments
- [ ] Test second design (STAR) - verify separate sequence

### Smoke Tests

```bash
ssh -p 21264 luxeonstarleds@34.71.83.227 \
  'wp --path=/www/luxeonstarleds_546/public eval-file \
   wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php'
```

---

## Resolved Questions

1. **QR Code Position:** Ron will define specific coordinates per Base ID. Example: STARa uses x=139.1167, y=33.0122, size=10mm. Positions will be provided during Phase 9 config seeding.

2. **URL Format:** Always lowercase (e.g., `quadi.ca/cube00076`). Confirmed.

3. **Error Correction Level:** High ('H' - 30% recovery). Confirmed.

4. **Existing Test Data:** Can be purged during coding/testing. Confirmed.

---

## Rollback Plan

If issues arise:

1. **Database:** Rollback SQL files in `docs/database/rollback/`
2. **Code:** Revert to previous git commit
3. **Data Matrix restoration:** Would require re-adding removed code and re-seeding config

---

## Approval

- [ ] Requirements confirmed by Ron
- [ ] Database schema approved
- [ ] Implementation approach approved
- [ ] Ready to begin Phase 1

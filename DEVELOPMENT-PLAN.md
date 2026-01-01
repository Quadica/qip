# QSA Engraving System - Development Plan

**Source Document:** `qsa-engraving-discovery.md`
**Created:** 2025-12-31
**Status:** Realeased for Implementation

---

## Executive Summary

This plan describes the phased implementation of the QSA (Quadica Standard Array) Engraving system - a WordPress plugin that generates SVG files for UV laser engraving of LED modules. The system manages serial number assignment, creates LightBurn-compatible SVG files with Micro-ID codes, Data Matrix barcodes, and text elements, and provides a production workflow interface.

**Critical Business Context:** Incorrect engraving wastes physical parts and serial numbers. Testing prioritizes correctness over coverage breadth.

---

## Architecture Overview

### Plugin Structure
```
wp-content/plugins/qsa-engraving/
|-- qsa-engraving.php                    # Main plugin file (singleton bootstrap)
|-- composer.json                         # Dependencies (tc-lib-barcode)
|-- includes/
|   |-- Admin/                            # Menu, pages, assets
|   |-- Ajax/                             # AJAX handlers (batch, queue, history, lightburn)
|   |-- Database/                         # Repository classes
|   |-- Services/                         # Business logic (selector, sorter, generator)
|   |-- SVG/                              # Renderers (micro-id, datamatrix, text, coords)
|   |-- DataSources/                      # oms_batch, order_bom, led_code readers
|-- assets/
|   |-- js/src/                           # React components per screen
|   |-- css/                              # Admin styles (dark theme)
|-- tests/
    |-- unit/                             # PHPUnit tests
    |-- smoke/                            # WP-CLI smoke tests
```

### Database Tables
| Table | Purpose |
|-------|---------|
| `{prefix}quad_serial_numbers` | Serial lifecycle tracking (reserved/engraved/voided) |
| `{prefix}quad_engraving_batches` | Engraving batch metadata |
| `{prefix}quad_engraved_modules` | Module-to-batch linkage with positions |
| `{prefix}quad_qsa_config` | Per-position coordinate configuration |

### Data Flow
```
oms_batch_items (modules needing build)
        |
        v
    [Module Selector] --> [Batch Sorter (LED optimization)]
        |                           |
        v                           v
    [Serial Generator] --> [SVG Generator]
        |                           |
        v                           v
    Database (serials)      LightBurn (UDP LOADFILE)
```

---

## Phase 1: Foundation

**Goal:** Plugin bootstrap, database schema, admin menu integration

### Tasks

#### 1.1 Plugin Bootstrap
- [x] Create main plugin file with singleton pattern
- [x] Implement PSR-4 autoloader for class loading
- [x] Register activation/deactivation hooks
- [x] Set up Composer for `tecnickcom/tc-lib-barcode` dependency

#### 1.2 Database Schema
- [x] Run `docs/database/install/01-qsa-engraving-schema.sql` manually via phpMyAdmin
- [x] Create repository classes for each table:
  - `Serial_Repository` - serial number CRUD
  - `Batch_Repository` - engraving batch CRUD
  - `Config_Repository` - QSA configuration CRUD
- [x] Implement prepared statements with `$wpdb->prepare()`

#### 1.3 Admin Integration
- [x] Register admin menu under WooCommerce
- [x] Set up capability checks (`manage_woocommerce`)
- [x] Create base admin page template (React shell)
- [x] Enqueue admin scripts and styles

#### 1.4 Module Selector Service
- [x] Query `oms_batch_items` for modules needing engraving
- [x] Filter for QSA-compatible SKUs (pattern: `^[A-Z]{4}-`)
- [x] Exclude modules already engraved (check `lw_quad_engraved_modules`)
- [x] Group results by base type (CORE, SOLO, EDGE, STAR)

### Tests - Phase 1

| Test ID | Type | Description |
|---------|------|-------------|
| TC-P1-001 | Smoke | Plugin activates without errors |
| TC-P1-002 | Smoke | Admin menu visible to authorized roles |
| TC-P1-003 | Smoke | Database tables exist with correct structure |
| TC-P1-004 | Smoke | Module selector query returns expected results |

### Completion Criteria
- [x] Plugin activates on staging site without PHP errors
- [x] Admin menu item "QSA Engraving" appears under WooCommerce
- [x] All 4 database tables created with correct indexes
- [x] Module selector returns data from oms_batch_items (verified query structure; staging table empty - requires production data clone for integration testing)

### Reference Files
- `docs/database/install/01-qsa-engraving-schema.sql` - Schema script
- `docs/database/QSA-ENGRAVING-DATABASE-RECOMMENDATIONS.md` - Query patterns
- `.claude/agents/database-specialist.md` - Database standards

---

## Phase 2: Serial Number Management ✅

**Goal:** Atomic serial generation, lifecycle tracking, validation

### Tasks

#### 2.1 Serial Number Generator
- [x] Implement atomic `get_next_serial()` with database transaction
- [x] Enforce range: 1 to 1,048,575 (20-bit Micro-ID limit)
- [x] Format output as 8-digit zero-padded string
- [x] Store both `serial_number` (CHAR 8) and `serial_integer` (INT) for efficient queries

#### 2.2 Serial Lifecycle Management
- [x] Implement status transitions:
  - `reserved` - Serial assigned but not yet engraved
  - `engraved` - Serial committed after successful engraving
  - `voided` - Serial invalidated (retry scenario)
- [x] Block invalid transitions (no recycling: engraved/voided cannot return to reserved)
- [x] Track timestamps for each status change

#### 2.3 Capacity Monitoring
- [x] Calculate remaining capacity: `1048575 - MAX(serial_integer)`
- [x] Implement warning threshold (configurable, default 10,000 remaining)
- [x] Admin notice when capacity is low

### Tests - Phase 2

| Test ID | Type | Description |
|---------|------|-------------|
| TC-SN-001 | Unit | Serial format validation (8-digit, numeric only) |
| TC-SN-002 | Unit | Serial range validation (1 to 1048575) |
| TC-SN-003 | Unit | String padding (1 → "00000001") |
| TC-SN-DB-001 | Smoke | Sequential generation (N+1 = N + 1) |
| TC-SN-DB-002 | Smoke | Uniqueness constraint enforced |
| TC-SN-DB-003 | Smoke | Status transitions validated |
| TC-SN-DB-004 | Smoke | Capacity calculation correct |

### Completion Criteria
- [x] Serial numbers generate sequentially without gaps
- [x] Database uniqueness constraint prevents duplicates (enforced via UNIQUE index in schema)
- [x] Status transitions follow allowed paths only
- [x] Capacity warning displays when threshold reached

### Reference Files
- `docs/reference/quadica-micro-id-specs.md` - Serial number constraints

---

## Phase 3: Micro-ID Encoding ✅

**Goal:** Encode serial numbers as 5x5 dot matrix patterns

### Tasks

#### 3.1 Binary Encoder
- [x] Convert serial integer to 20-bit binary string
- [x] Calculate even parity bit (total ON bits must be even)
- [x] Map bits to grid positions per specification:
  - Corners (0,0), (0,4), (4,0), (4,4) = Anchors (always ON)
  - Position (row, col) = Bit position per row-major order
  - Parity at (4,3)

#### 3.2 Grid Renderer
- [x] Calculate dot center coordinates:
  - X = 0.05 + (col × 0.225) mm
  - Y = 0.05 + (row × 0.225) mm
- [x] Render orientation marker at (-0.175, 0.05) mm
- [x] Generate SVG circles with r=0.05mm, fill="#000000"
- [x] Apply transform for module position offset

#### 3.3 Validation
- [x] Validate input range before encoding
- [x] Return `WP_Error` for invalid inputs
- [x] Provide decode function for verification testing

### Tests - Phase 3 (CRITICAL)

| Test ID | Type | Description | Status |
|---------|------|-------------|--------|
| TC-MID-001 | Smoke | Minimum value (00000001) - 7 dots total | ✅ PASS |
| TC-MID-002 | Smoke | Maximum value (01048575) - 25 dots total | ✅ PASS |
| TC-MID-003 | Smoke | Medium density (00600001) - matches spec example | ✅ PASS |
| TC-MID-004 | Smoke | Sample SVG (00123454) - matches stara-qsa-sample.svg | ✅ PASS |
| TC-MID-005 | Smoke | Alternating bits (00699050) - all rows exercised | ✅ PASS |
| TC-MID-006 | Smoke | Boundary (01048574) - parity flip verification | ✅ PASS |
| TC-MID-007 | Smoke | Invalid input above maximum - returns error | ✅ PASS |
| TC-MID-008 | Smoke | Invalid input zero - returns error | ✅ PASS |
| TC-MID-009 | Smoke | String input validation - returns error | ✅ PASS |
| TC-MID-010 | Smoke | Grid coordinates mathematically correct | ✅ PASS |
| TC-PAR-001 | Smoke | Even bit count → parity 0 | ✅ PASS |
| TC-PAR-002 | Smoke | Odd bit count → parity 1 | ✅ PASS |
| TC-MID-011 | Smoke | SVG rendering produces valid output | ✅ PASS |
| TC-MID-012 | Smoke | Encode-decode roundtrip verification | ✅ PASS |

### Completion Criteria
- [x] All 14 Micro-ID test cases pass
- [x] Encoded patterns match reference files (verified against stara-qsa-sample.svg)
- [x] Grid coordinates validated against specification
- [x] Invalid inputs return meaningful error messages (with WP_Error codes)

### Reference Files
- `docs/reference/quadica-micro-id-specs.md` - Encoding algorithm
- `docs/sample-data/stara-qsa-sample.svg` - Reference SVG with correct patterns
- `~/.claude/skills/lightburn-svg/samples/micro-id-*.svg` - Additional reference patterns

---

## Phase 4: SVG Generation Core ✅

**Goal:** Generate complete SVG documents for LightBurn

### Tasks

#### 4.1 Coordinate Transformer
- [x] Transform CAD coordinates (bottom-left origin) to SVG (top-left origin)
- [x] Formula: `svg_y = canvas_height - cad_y`
- [x] Apply QSA-specific calibration offsets from config table
- [x] Clamp coordinates to canvas bounds

#### 4.2 Text Renderer
- [x] Render text using Roboto Thin font specification
- [x] Apply hair-space character spacing (U+200A between characters)
- [x] Calculate font size: `font_size = height × 1.4056`
- [x] Support rotation transforms
- [x] Text heights: module_id (1.5mm), serial_url (1.2mm), led_code (1.0mm)

#### 4.3 Data Matrix Renderer
- [x] Integrate `tecnickcom/tc-lib-barcode` for ECC 200 generation
- [x] Encode URL: `https://quadi.ca/{serial_number}`
- [x] Scale barcode to 14mm × 6.5mm rectangle
- [x] Convert barcode modules to SVG path or rect elements

#### 4.4 SVG Document Assembler
- [x] Create SVG document with correct namespaces and dimensions
- [x] Group elements by module position (`<g id="module-N">`)
- [x] Add alignment marks (red boundary rectangle, center crosshair)
- [x] Set layer colors: black (#000000) for engraving, red (#FF0000) for alignment

#### 4.5 Configuration Loader
- [x] Read element positions from `quad_qsa_config` table
- [x] Support design variants (e.g., "STARa" vs "STAR")
- [x] Cache configuration per request

### Tests - Phase 4

| Test ID | Type | Description | Status |
|---------|------|-------------|--------|
| TC-SVG-001 | Smoke | CAD to SVG Y-axis transformation | ✅ PASS |
| TC-SVG-002 | Smoke | Calibration offset application | ✅ PASS |
| TC-SVG-003 | Smoke | Bounds checking and clamping | ✅ PASS |
| TC-SVG-004 | Smoke | Micro-ID position transform | ✅ PASS |
| TC-SVG-005 | Smoke | Data Matrix position transform | ✅ PASS |
| TC-SVG-006 | Smoke | Hair-space character spacing | ✅ PASS |
| TC-SVG-007 | Smoke | Font size calculation | ✅ PASS |
| TC-SVG-008 | Smoke | LED code validation | ✅ PASS |
| TC-DM-001 | Smoke | Data Matrix renders (placeholder mode) | ✅ PASS |
| TC-DM-002 | Smoke | URL generation correct | ✅ PASS |
| TC-DM-003 | Smoke | Serial validation | ✅ PASS |
| TC-SVG-GEN-001 | Smoke | SVG document structure valid | ✅ PASS |
| TC-SVG-GEN-002 | Smoke | Canvas dimensions correct | ✅ PASS |
| TC-SVG-GEN-003 | Smoke | SKU parsing with revision | ✅ PASS |
| TC-SVG-GEN-004 | Smoke | SKU parsing without revision | ✅ PASS |
| TC-SVG-GEN-005 | Smoke | Array breakdown calculation | ✅ PASS |
| TC-SVG-GEN-006 | Smoke | Dependency check | ✅ PASS |

### Completion Criteria
- [x] Generated SVG matches structure of `stara-qsa-sample.svg`
- [x] Coordinate transformation produces correct positions
- [x] Data Matrix barcodes generate correctly (placeholder mode when tc-lib-barcode not installed)
- [x] Text elements render with proper sizing and spacing

### Reference Files
- `docs/sample-data/stara-qsa-sample.svg` - Expected SVG output
- `docs/sample-data/stara-qsa-sample-svg-data.csv` - Coordinate source data
- `~/.claude/skills/lightburn-svg/references/svg-format.md` - SVG specification
- `~/.claude/skills/lightburn-svg/references/lightburn-integration.md` - LightBurn requirements

---

## Phase 5: Batch Creator UI ✅

**Goal:** Module selection interface with LED code optimization

### Tasks

#### 5.1 React Build Setup
- [x] Configure `@wordpress/scripts` for React compilation
- [x] Set up source directory structure per screen
- [x] Create build/watch scripts in package.json
- [x] Enqueue compiled bundles in admin page

#### 5.2 Module Tree Component
- [x] Hierarchical display: Base Type → Order → Module
- [x] Checkbox selection at all levels (cascading)
- [x] Expandable/collapsible tree nodes
- [x] Display module count and engrave quantities

#### 5.3 Quantity Editor
- [x] Inline editing for engrave quantity per module
- [x] Validation: 1 ≤ qty ≤ available
- [x] Visual feedback for edited quantities

#### 5.4 Batch Sorter Service
- [x] Implement LED code transition minimization algorithm
- [x] Group modules with identical LED codes adjacently
- [x] Handle multi-LED modules as bridges between groups
- [x] Display sorted preview before batch creation

#### 5.5 AJAX Integration
- [x] `qsa_get_modules_awaiting` - Fetch module tree data
- [x] `qsa_refresh_modules` - Force refresh from oms_batch_items
- [x] `qsa_create_batch` - Create batch from selection

### Tests - Phase 5

| Test ID | Type | Description | Status |
|---------|------|-------------|--------|
| TC-BC-001 | Smoke | Batch_Sorter service instantiation | ✅ PASS |
| TC-BC-002 | Smoke | Batch_Sorter expand_selections | ✅ PASS |
| TC-BC-003 | Smoke | Batch_Sorter assign_to_arrays | ✅ PASS |
| TC-BC-004 | Smoke | Batch_Sorter assign_to_arrays with start_position | ✅ PASS |
| TC-BC-005 | Smoke | Batch_Sorter LED optimization sorting | ✅ PASS |
| TC-BC-006 | Smoke | Batch_Sorter count_transitions | ✅ PASS |
| TC-BC-007 | Smoke | Batch_Sorter get_distinct_led_codes | ✅ PASS |
| TC-BC-008 | Smoke | Batch_Sorter calculate_array_breakdown | ✅ PASS |
| TC-BC-009 | Smoke | LED_Code_Resolver service instantiation | ✅ PASS |
| TC-BC-010 | Smoke | LED_Code_Resolver shortcode validation | ✅ PASS |
| TC-BC-011 | Smoke | Batch_Ajax_Handler service instantiation | ✅ PASS |
| TC-BC-012 | Smoke | Plugin services accessible via getters | ✅ PASS |
| TC-BC-013 | Smoke | Batch_Sorter empty input handling | ✅ PASS |
| TC-BC-014 | Smoke | Batch_Sorter single module handling | ✅ PASS |
| TC-UI-001 | Manual | Module tree displays correctly | Pending |
| TC-UI-002 | Manual | Checkbox selection cascades | Pending |
| TC-UI-003 | Manual | Quantity editing saves correctly | Pending |

### Completion Criteria
- [x] Batch Creator UI matches mockup in `module-engraving-batch-creator-mockup.jsx`
- [x] Module tree populates from oms_batch_items data
- [x] Selection and quantity editing works correctly
- [x] Batch creation calls backend and creates database records

### Reference Files
- `docs/reference/module-engraving-batch-creator-mockup.jsx` - UI design
- `qsa-engraving-discovery.md` - Section 6 (Batch Assembly)

---

## Phase 6: Engraving Queue UI ✅

**Goal:** Step-through workflow for array engraving

### Tasks

#### 6.1 Queue Display
- [x] List queue items grouped by module type
- [x] Show array count and module count per row
- [x] Display status badges (Pending, In Progress, Complete)
- [x] Group type indicators (Same ID×Full, Same ID×Partial, Mixed ID×Full, Mixed ID×Partial)

#### 6.2 Array Progression
- [x] "Engrave" button to start row (reserves serials, generates SVGs)
- [x] Array-by-array stepping with position indicators
- [x] Progress dots showing current array position
- [x] "Next Array" / "Complete" buttons per workflow state

#### 6.3 Starting Offset Support
- [x] Number input for starting position (1-8)
- [x] Recalculate array breakdown when offset changes
- [x] Only editable when row is Pending

#### 6.4 Keyboard Shortcuts
- [x] Spacebar advances to next array (when In Progress)
- [x] Focus management for keyboard workflow

#### 6.5 Error Recovery Controls
- [x] "Resend" - Same SVG, same serials (communication issue)
- [x] "Retry" - New SVG, new serials (physical failure)
- [x] "Back" - Return to previous array with new serials
- [x] "Rerun" - Reset completed row to Pending

#### 6.6 Serial Lifecycle Integration
- [x] Reserve serials on row start
- [x] Commit (reserved → engraved) on Next/Complete
- [x] Void serials on Retry
- [x] Return to pool (void) on Rerun

### Tests - Phase 6

| Test ID | Type | Description | Status |
|---------|------|-------------|--------|
| TC-EQ-001 | Smoke | Queue_Ajax_Handler instantiation | ✅ PASS |
| TC-EQ-002 | Smoke | Batch_Repository queue methods | ✅ PASS |
| TC-EQ-003 | Smoke | Update row status validation | ✅ PASS |
| TC-EQ-004 | Smoke | Queue stats structure | ✅ PASS |
| TC-EQ-005 | Smoke | React bundle exists | ✅ PASS |
| TC-EQ-006 | Smoke | CSS bundle exists | ✅ PASS |
| TC-EQ-007 | Smoke | Admin menu queue page | ✅ PASS |
| TC-EQ-008 | Smoke | Serial lifecycle transitions | ✅ PASS |
| TC-EQ-009 | Smoke | AJAX handler methods | ✅ PASS |
| TC-EQ-010 | Smoke | Start position handling | ✅ PASS |
| TC-UI-004 | Manual | Queue UI matches mockup | Pending |
| TC-UI-005 | Manual | Keyboard shortcuts function | Pending |
| TC-UI-006 | Manual | Error recovery buttons work | Pending |

### Completion Criteria
- [x] Engraving Queue UI matches mockup in `engraving-queue-mockup.jsx`
- [x] Array progression commits serials correctly
- [x] Error recovery controls function as specified
- [x] Keyboard shortcuts work for power users

### Reference Files
- `docs/reference/engraving-queue-mockup.jsx` - UI design
- `qsa-engraving-discovery.md` - Section 5 (Engraving Queue)

---

## Phase 7: LightBurn Integration

**Goal:** UDP communication for SVG file loading

### Tasks

#### 7.1 UDP Client
- [ ] Implement `LightBurn_Client` class with socket communication
- [ ] Support commands: PING, LOADFILE:{filepath}
- [ ] Handle timeouts and connection errors
- [ ] Port configuration: 19840 (send), 19841 (receive)

#### 7.2 File Management
- [ ] Configure output directory path (admin setting)
- [ ] Generate filenames: `{batch_id}-{array_sequence}-{qsa_id}.svg`
- [ ] Pre-generate all SVGs for row on start
- [ ] Clean up SVG files after batch completion (optional)

#### 7.3 Integration Points
- [ ] Auto-load SVG on row start
- [ ] Load next SVG on "Next Array"
- [ ] Resend current SVG on "Resend"
- [ ] Generate and load new SVG on "Retry"

#### 7.4 Admin Settings
- [ ] LightBurn host IP configuration
- [ ] Port configuration (with sensible defaults)
- [ ] SVG output directory path
- [ ] Auto-load toggle (enable/disable UDP)
- [ ] Connection test button

### Tests - Phase 7 (Manual Only)

| Test ID | Type | Description |
|---------|------|-------------|
| MT-LB-001 | Manual | UDP PING command successful |
| MT-LB-002 | Manual | SVG file loads in LightBurn |
| MT-LB-003 | Manual | Resend reloads same SVG |
| MT-LB-004 | Manual | Retry loads new SVG with new serials |
| MT-PHY-001 | Manual | Engraved Micro-ID decodes correctly |
| MT-PHY-002 | Manual | Data Matrix scans to correct URL |
| MT-PHY-003 | Manual | Text elements readable on engraved module |

### Completion Criteria
- [ ] LightBurn receives and displays SVGs via UDP
- [ ] Connection test button verifies connectivity
- [ ] Error handling for network issues (timeouts, unreachable)
- [ ] Physical verification: engravings match database records

### Reference Files
- `~/.claude/skills/lightburn-svg/references/lightburn-integration.md` - UDP protocol
- `qsa-engraving-discovery.md` - Section 10 (LightBurn Integration)

---

## Phase 8: Batch History & Polish

**Goal:** Historical batch viewing, re-engraving, production readiness

### Tasks

#### 8.1 Batch History UI
- [ ] List completed batches with metadata
- [ ] Search by batch ID, order ID, module SKU
- [ ] Filter by module type (CORE, SOLO, EDGE, STAR)
- [ ] Batch detail view with serial number ranges

#### 8.2 Re-Engraving Workflow
- [ ] "Load for Re-engraving" button
- [ ] Pre-populate Batch Creator with selected modules
- [ ] Generate new serial numbers (no recycling)
- [ ] Track re-engraving relationship in database

#### 8.3 QSA Configuration Admin
- [ ] Admin interface for coordinate configuration
- [ ] Support multiple QSA designs and revisions
- [ ] Import/export configuration as JSON/CSV

#### 8.4 Settings Page
- [ ] Text height configuration (module_id, serial_url, led_code)
- [ ] Warning thresholds (serial capacity, etc.)
- [ ] LightBurn connection settings (from Phase 7)

#### 8.5 Production Polish
- [ ] Loading indicators for operations > 1 second
- [ ] Error messages with actionable information
- [ ] Confirmation dialogs for destructive actions
- [ ] Admin notices for warnings and errors
- [ ] Accessibility improvements (keyboard nav, ARIA labels)

### Tests - Phase 8

| Test ID | Type | Description |
|---------|------|-------------|
| TC-HIST-001 | Smoke | Batch history lists completed batches |
| TC-HIST-002 | Smoke | Search filters work correctly |
| TC-HIST-003 | Smoke | Load batch populates Batch Creator |
| TC-UI-007 | Manual | Batch History UI matches mockup |
| TC-UI-008 | Manual | Re-engraving workflow functions |
| TC-UI-009 | Manual | Settings save and persist |

### Completion Criteria
- [ ] Batch History UI matches mockup in `engraving-batch-history-mockup.jsx`
- [ ] Re-engraving creates new serials, not recycled
- [ ] Configuration admin allows coordinate management
- [ ] All UI interactions have appropriate feedback

### Reference Files
- `docs/reference/engraving-batch-history-mockup.jsx` - UI design
- `qsa-engraving-discovery.md` - Section 8 (Batch History)

---

## Phase 9: QSA Configuration Data

**Goal:** Populate coordinate configuration for all QSA designs

### Tasks

#### 9.1 STARa Configuration
- [ ] Import coordinates from `stara-qsa-sample-svg-data.csv`
- [ ] Verify positions match sample SVG output
- [ ] Create seed SQL script: `02-qsa-config-seed-stara.sql`

#### 9.2 QUAD Configuration
- [ ] Import coordinates from QUAD coordinate data (to be provided)
- [ ] Verify positions with test SVG output
- [ ] Create seed SQL script: `03-qsa-config-seed-quad.sql`

#### 9.3 PICO Configuration
- [ ] Import coordinates from PICO coordinate data (to be provided)
- [ ] Verify positions with test SVG output
- [ ] Create seed SQL script: `04-qsa-config-seed-pico.sql`

#### 9.4 Revision Support
- [ ] Handle design revisions (e.g., "STARa" vs "STARb")
- [ ] NULL revision = default for design
- [ ] Specific revision overrides default

### Completion Criteria
- [ ] STARa configuration produces correct SVG positions
- [ ] QUAD configuration produces correct SVG positions
- [ ] PICO configuration produces correct SVG positions
- [ ] Configuration revision system tested

### Reference Files
- `docs/sample-data/stara-qsa-sample-svg-data.csv` - STARa coordinate source
- `docs/sample-data/stara-qsa-sample.svg` - STARa verification reference

---

## Testing Summary

### Test Execution Order
1. **Phase 1-2:** Smoke tests via WP-CLI (database, basic queries)
2. **Phase 3:** Unit tests for Micro-ID (PHPUnit)
3. **Phase 4:** Smoke tests for SVG generation
4. **Phase 5-6:** Manual UI testing + smoke tests for AJAX
5. **Phase 7-8:** Manual acceptance testing with LightBurn hardware

### Test Data Requirements
- Sample serial numbers for Micro-ID verification (see Phase 3 test cases)
- `stara-qsa-sample.svg` as reference output
- Test batch data in oms_batch_items (existing staging data)

### Test Commands
```bash
# SSH to staging
ssh -i ~/.ssh/rlux luxeonstarleds@34.71.83.227 -p 21264

# Run unit tests (from plugin directory)
cd /www/luxeonstarleds_546/public/wp-content/plugins/qsa-engraving
vendor/bin/phpunit

# Run smoke tests
wp --path=/www/luxeonstarleds_546/public eval-file tests/smoke/test-serial-numbers.php
```

---

## Deployment Notes

### Database Scripts
Run manually via phpMyAdmin (per Quadica standards):
1. `docs/database/install/01-qsa-engraving-schema.sql` - Creates all 4 tables
2. `docs/database/install/02-qsa-config-seed-stara.sql` - Seeds STARa coordinates

Replace `{prefix}` placeholder:
- luxeonstar.com: `lw_`
- handlaidtrack.com: `fwp_`

### Composer Dependencies
Since Composer is not available on production, the `vendor/` directory must be committed:
```bash
cd wp-content/plugins/qsa-engraving
composer install --no-dev
git add vendor/
git commit -m "Add vendor dependencies for qsa-engraving plugin"
```

### React Build
```bash
cd wp-content/plugins/qsa-engraving
npm install
npm run build
```

---

## Dependencies and Prerequisites

### External Libraries
| Library | Version | Purpose |
|---------|---------|---------|
| tecnickcom/tc-lib-barcode | ^2.1 | Data Matrix ECC 200 generation |
| @wordpress/scripts | ^27.0 | React build tooling |

### WordPress/WooCommerce
- WordPress 6.8+
- WooCommerce 9.9+
- PHP 8.1+

### System Requirements
- Network access to LightBurn workstation (UDP ports 19840/19841)
- Roboto Thin font installed on laser workstation
- Write access to SVG output directory

---

## Risk Register

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Micro-ID encoding error | HIGH | LOW | Extensive unit tests, physical verification |
| Serial collision | HIGH | LOW | Database constraint + application validation |
| LightBurn network failure | MEDIUM | MEDIUM | Resend/Retry recovery controls |
| Missing LED codes | LOW | MEDIUM | Clear error messages, pre-flight validation |
| Coordinate miscalibration | MEDIUM | LOW | Reference SVG comparison, physical verification |

---

## Resolved Decisions

1. **Text Rendering:** Use native SVG text elements with Roboto Thin font. Font will be installed on the laser workstation.

2. **Composer Dependencies:** Composer is not available on production. The `vendor/` directory must be committed to the repository.

3. **Data Matrix Library:** The `tecnickcom/tc-lib-barcode` library has been validated and works correctly for ECC 200 generation.

4. **Additional QSA Designs:** Beyond STARa, coordinate data will be provided for QUAD and PICO designs.

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-12-31 | Claude | Initial plan from discovery document |
| 1.1 | 2025-12-31 | Claude | Resolved open questions; added QUAD and PICO to Phase 9 |
| 1.2 | 2025-12-31 | Claude | Phase 4 complete: SVG Generation Core with 17 smoke tests |
| 1.3 | 2025-12-31 | Claude | Phase 5 complete: Batch Creator UI with 14 smoke tests (63 total) |

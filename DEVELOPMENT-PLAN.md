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

## Phase 2: Serial Number Management

**Goal:** Atomic serial generation, lifecycle tracking, validation

### Tasks

#### 2.1 Serial Number Generator
- [ ] Implement atomic `get_next_serial()` with database transaction
- [ ] Enforce range: 1 to 1,048,575 (20-bit Micro-ID limit)
- [ ] Format output as 8-digit zero-padded string
- [ ] Store both `serial_number` (CHAR 8) and `serial_integer` (INT) for efficient queries

#### 2.2 Serial Lifecycle Management
- [ ] Implement status transitions:
  - `reserved` - Serial assigned but not yet engraved
  - `engraved` - Serial committed after successful engraving
  - `voided` - Serial invalidated (retry scenario)
- [ ] Block invalid transitions (no recycling: engraved/voided cannot return to reserved)
- [ ] Track timestamps for each status change

#### 2.3 Capacity Monitoring
- [ ] Calculate remaining capacity: `1048575 - MAX(serial_integer)`
- [ ] Implement warning threshold (configurable, default 10,000 remaining)
- [ ] Admin notice when capacity is low

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
- [ ] Serial numbers generate sequentially without gaps
- [ ] Database uniqueness constraint prevents duplicates
- [ ] Status transitions follow allowed paths only
- [ ] Capacity warning displays when threshold reached

### Reference Files
- `docs/reference/quadica-micro-id-specs.md` - Serial number constraints

---

## Phase 3: Micro-ID Encoding

**Goal:** Encode serial numbers as 5x5 dot matrix patterns

### Tasks

#### 3.1 Binary Encoder
- [ ] Convert serial integer to 20-bit binary string
- [ ] Calculate even parity bit (total ON bits must be even)
- [ ] Map bits to grid positions per specification:
  - Corners (0,0), (0,4), (4,0), (4,4) = Anchors (always ON)
  - Position (row, col) = Bit position per row-major order
  - Parity at (4,3)

#### 3.2 Grid Renderer
- [ ] Calculate dot center coordinates:
  - X = 0.05 + (col × 0.225) mm
  - Y = 0.05 + (row × 0.225) mm
- [ ] Render orientation marker at (-0.175, 0.05) mm
- [ ] Generate SVG circles with r=0.05mm, fill="#000000"
- [ ] Apply transform for module position offset

#### 3.3 Validation
- [ ] Validate input range before encoding
- [ ] Return `WP_Error` for invalid inputs
- [ ] Provide decode function for verification testing

### Tests - Phase 3 (CRITICAL)

| Test ID | Type | Description |
|---------|------|-------------|
| TC-MID-001 | Unit | Minimum value (00000001) - 7 dots total |
| TC-MID-002 | Unit | Maximum value (01048575) - 25 dots total |
| TC-MID-003 | Unit | Medium density (00600001) - matches spec example |
| TC-MID-004 | Unit | Sample SVG (00123454) - matches stara-qsa-sample.svg |
| TC-MID-005 | Unit | Alternating bits (00699050) - all rows exercised |
| TC-MID-006 | Unit | Boundary (01048574) - parity flip verification |
| TC-MID-007 | Unit | Invalid input above maximum - returns error |
| TC-MID-008 | Unit | Invalid input zero - returns error |
| TC-MID-009 | Unit | Non-numeric input - returns error |
| TC-MID-010 | Unit | Grid coordinates mathematically correct |
| TC-PAR-001 | Unit | Even bit count → parity 0 |
| TC-PAR-002 | Unit | Odd bit count → parity 1 |

### Completion Criteria
- [ ] All 12 Micro-ID test cases pass
- [ ] Encoded patterns match reference files
- [ ] Grid coordinates validated against specification
- [ ] Invalid inputs return meaningful error messages

### Reference Files
- `docs/reference/quadica-micro-id-specs.md` - Encoding algorithm
- `docs/sample-data/stara-qsa-sample.svg` - Reference SVG with correct patterns
- `~/.claude/skills/lightburn-svg/samples/micro-id-*.svg` - Additional reference patterns

---

## Phase 4: SVG Generation Core

**Goal:** Generate complete SVG documents for LightBurn

### Tasks

#### 4.1 Coordinate Transformer
- [ ] Transform CAD coordinates (bottom-left origin) to SVG (top-left origin)
- [ ] Formula: `svg_y = canvas_height - cad_y`
- [ ] Apply QSA-specific calibration offsets from config table
- [ ] Clamp coordinates to canvas bounds

#### 4.2 Text Renderer
- [ ] Render text using Roboto Thin font specification
- [ ] Apply hair-space character spacing (U+200A between characters)
- [ ] Calculate font size: `font_size = height × 1.4056`
- [ ] Support rotation transforms
- [ ] Text heights: module_id (1.5mm), serial_url (1.2mm), led_code (1.0mm)

#### 4.3 Data Matrix Renderer
- [ ] Integrate `tecnickcom/tc-lib-barcode` for ECC 200 generation
- [ ] Encode URL: `https://quadi.ca/{serial_number}`
- [ ] Scale barcode to 14mm × 6.5mm rectangle
- [ ] Convert barcode modules to SVG path or rect elements

#### 4.4 SVG Document Assembler
- [ ] Create SVG document with correct namespaces and dimensions
- [ ] Group elements by module position (`<g id="module-N">`)
- [ ] Add alignment marks (red boundary rectangle, center crosshair)
- [ ] Set layer colors: black (#000000) for engraving, red (#FF0000) for alignment

#### 4.5 Configuration Loader
- [ ] Read element positions from `quad_qsa_config` table
- [ ] Support design variants (e.g., "STARa" vs "STAR")
- [ ] Cache configuration per request

### Tests - Phase 4

| Test ID | Type | Description |
|---------|------|-------------|
| TC-SVG-001 | Unit | CAD to SVG Y-axis transformation |
| TC-SVG-002 | Unit | Sample data coordinates match expected |
| TC-SVG-GEN-001 | Smoke | SVG document structure valid |
| TC-SVG-GEN-002 | Smoke | Module grouping correct (8 positions max) |
| TC-SVG-GEN-003 | Smoke | All element types present per module |
| TC-DM-001 | Smoke | Data Matrix generates valid barcode |
| TC-DM-002 | Smoke | Barcode scans to correct URL |

### Completion Criteria
- [ ] Generated SVG matches structure of `stara-qsa-sample.svg`
- [ ] Coordinate transformation produces correct positions
- [ ] Data Matrix barcodes scan correctly to quadi.ca URLs
- [ ] Text elements render with proper sizing and spacing

### Reference Files
- `docs/sample-data/stara-qsa-sample.svg` - Expected SVG output
- `docs/sample-data/stara-qsa-sample-svg-data.csv` - Coordinate source data
- `~/.claude/skills/lightburn-svg/references/svg-format.md` - SVG specification
- `~/.claude/skills/lightburn-svg/references/lightburn-integration.md` - LightBurn requirements

---

## Phase 5: Batch Creator UI

**Goal:** Module selection interface with LED code optimization

### Tasks

#### 5.1 React Build Setup
- [ ] Configure `@wordpress/scripts` for React compilation
- [ ] Set up source directory structure per screen
- [ ] Create build/watch scripts in package.json
- [ ] Enqueue compiled bundles in admin page

#### 5.2 Module Tree Component
- [ ] Hierarchical display: Base Type → Order → Module
- [ ] Checkbox selection at all levels (cascading)
- [ ] Expandable/collapsible tree nodes
- [ ] Display module count and engrave quantities

#### 5.3 Quantity Editor
- [ ] Inline editing for engrave quantity per module
- [ ] Validation: 1 ≤ qty ≤ available
- [ ] Visual feedback for edited quantities

#### 5.4 Batch Sorter Service
- [ ] Implement LED code transition minimization algorithm
- [ ] Group modules with identical LED codes adjacently
- [ ] Handle multi-LED modules as bridges between groups
- [ ] Display sorted preview before batch creation

#### 5.5 AJAX Integration
- [ ] `qsa_get_modules_awaiting` - Fetch module tree data
- [ ] `qsa_refresh_modules` - Force refresh from oms_batch_items
- [ ] `qsa_create_batch` - Create batch from selection

### Tests - Phase 5

| Test ID | Type | Description |
|---------|------|-------------|
| TC-SORT-001 | Unit | Identical LED codes grouped |
| TC-SORT-002 | Unit | Multi-LED modules placed as bridges |
| TC-SORT-003 | Unit | Example from discovery doc - 3 transitions |
| TC-SORT-004 | Unit | Single LED type - 1 transition |
| TC-UI-001 | Manual | Module tree displays correctly |
| TC-UI-002 | Manual | Checkbox selection cascades |
| TC-UI-003 | Manual | Quantity editing saves correctly |

### Completion Criteria
- [ ] Batch Creator UI matches mockup in `module-engraving-batch-creator-mockup.jsx`
- [ ] Module tree populates from oms_batch_items data
- [ ] Selection and quantity editing works correctly
- [ ] Batch creation calls backend and creates database records

### Reference Files
- `docs/reference/module-engraving-batch-creator-mockup.jsx` - UI design
- `qsa-engraving-discovery.md` - Section 6 (Batch Assembly)

---

## Phase 6: Engraving Queue UI

**Goal:** Step-through workflow for array engraving

### Tasks

#### 6.1 Queue Display
- [ ] List queue items grouped by module type
- [ ] Show array count and module count per row
- [ ] Display status badges (Pending, In Progress, Complete)
- [ ] Group type indicators (Same ID×Full, Same ID×Partial, Mixed ID×Full, Mixed ID×Partial)

#### 6.2 Array Progression
- [ ] "Engrave" button to start row (reserves serials, generates SVGs)
- [ ] Array-by-array stepping with position indicators
- [ ] Progress dots showing current array position
- [ ] "Next Array" / "Complete" buttons per workflow state

#### 6.3 Starting Offset Support
- [ ] Number input for starting position (1-8)
- [ ] Recalculate array breakdown when offset changes
- [ ] Only editable when row is Pending

#### 6.4 Keyboard Shortcuts
- [ ] Spacebar advances to next array (when In Progress)
- [ ] Focus management for keyboard workflow

#### 6.5 Error Recovery Controls
- [ ] "Resend" - Same SVG, same serials (communication issue)
- [ ] "Retry" - New SVG, new serials (physical failure)
- [ ] "Back" - Return to previous array with new serials
- [ ] "Rerun" - Reset completed row to Pending

#### 6.6 Serial Lifecycle Integration
- [ ] Reserve serials on row start
- [ ] Commit (reserved → engraved) on Next/Complete
- [ ] Void serials on Retry
- [ ] Return to pool (void) on Rerun

### Tests - Phase 6

| Test ID | Type | Description |
|---------|------|-------------|
| TC-QUEUE-001 | Smoke | Queue displays correct items after batch creation |
| TC-QUEUE-002 | Smoke | Serial reservation creates database records |
| TC-QUEUE-003 | Smoke | Status transitions on Next/Complete |
| TC-QUEUE-004 | Smoke | Retry voids old serials, creates new |
| TC-UI-004 | Manual | Queue UI matches mockup |
| TC-UI-005 | Manual | Keyboard shortcuts function |
| TC-UI-006 | Manual | Error recovery buttons work |

### Completion Criteria
- [ ] Engraving Queue UI matches mockup in `engraving-queue-mockup.jsx`
- [ ] Array progression commits serials correctly
- [ ] Error recovery controls function as specified
- [ ] Keyboard shortcuts work for power users

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

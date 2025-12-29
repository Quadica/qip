# Quadica Integrated Platform (QIP) - Requirements Document

**Version:** 2.0
**Last Update:** December 11, 2025
**Author:** Ron Warris, Chris Warris + Claude Code
**Status:** Draft/ON HOLD - We are working on the qsa discovery document instead
**Previous Versions:** docs/archive/

## Revisions
- v2.0: Restructured to match LMB PRD format with numbered requirements
- v1.1: Initial draft with narrative structure

---

## 1. Overview

The Quadica Integrated Platform (QIP) plugin will eventually replace the legacy Order Management (OM) system located at `/om/`. This PRD covers **Part 1** of QIP development: establishing the plugin foundation and implementing the **Engraving Module** for generating SVG files used in laser engraving of new-style LED modules.

### 1.1 Background

Quadica is transitioning from legacy LED module designs (SinkPAD, Rebel-based) to a new standardized module system featuring:

- **Standard Array format**: 148mm x 113.7mm carrier arrays with up to 8 module positions
- **New identification system**: Base ID (4-letter word) + Config Code (5-digit number) + Serial Number (8-digit unique identifier)
- **Quadica 5x5 Micro-ID**: Proprietary encoding for serial numbers engraved directly on modules
- **Data Matrix barcodes**: URL-encoded barcodes linking to module information pages

The existing engraving software handles legacy modules. QIP's Engraving Module will provide equivalent functionality for new-style modules, generating Lightburn-compatible SVG files.

---

## 2. Goals

Each goal below summarizes the intended experience, the primary data dependencies, and the acceptance criteria that clarify "done." Cross-references point to the detailed requirements that expand the behavior.

### G-001: Extensible Plugin Foundation
- Create a plugin architecture that can accommodate future modules (production batching, shipping, reporting, etc.) without modification to the core.
- Source of truth: WordPress plugin standards, PSR-4 autoloading.
- Success Criteria: Plugin activates without errors alongside existing plugins, module registration system allows adding new modules via hooks (see REQ-001 – REQ-005).

### G-002: Serial Number Management
- Generate and persist unique 8-digit serial numbers for each manufactured module within the Micro-ID encoding capacity.
- Data dependencies: Serial number database table, Micro-ID 20-bit constraint.
- Success Criteria: Serial numbers are generated within valid range (00000001 - 01048575), never duplicated, capacity warnings trigger at configurable threshold (see REQ-006 – REQ-014).

### G-003: SVG Engraving File Generation
- Generate Lightburn-compatible SVG files containing all required engraving elements for new-style modules.
- Data dependencies: Legacy OM batch data, QPM BOM data, WooCommerce LED codes.
- Success Criteria: SVG files render correctly in Lightburn, Micro-ID codes decode correctly to their serial numbers, Data Matrix barcodes scan to correct module URLs (see REQ-015 – REQ-035).

### G-004: Engraving Queue Management
- Provide a system for queuing and tracking SVG files for sequential engraving with status tracking.
- Data dependencies: Engraving jobs table, array status tracking.
- Success Criteria: Users can process engraving queue sequentially, job status updates automatically, arrays can be marked individually as engraved (see REQ-036 – REQ-045).

### G-005: Legacy System Integration
- Read batch and module data from the legacy OM system and QPM plugin without modification to those systems.
- Data dependencies: `oms_batch_items` table, QPM BOM records, WooCommerce products.
- Success Criteria: Correct data retrieval from all source systems, new-style modules correctly identified by SKU pattern (see REQ-046 – REQ-055).

---

## 3. Definitions

- **Array**: A carrier panel containing up to 8 module positions (148mm x 113.7mm)
- **Base ID**: 4-letter word identifying a base design (e.g., STAR, NORD, ATOM, APEX)
- **BOM**: Bill of Materials - components required to build a module
- **Config Code**: 5-digit number identifying an LED configuration
- **Fret**: Production slang for array
- **LED Code**: 3-character code identifying an LED type for engraving (restricted character set)
- **Lightburn**: Laser engraving software that consumes SVG files
- **Micro-ID**: Quadica's proprietary 5x5 dot matrix serial encoding (20-bit capacity)
- **Module ID**: Base ID + revision + Config Code (e.g., "STARa-34924")
- **New-Style Module**: Module with SKU matching pattern: 4 uppercase letters + lowercase revision + hyphen + 5 digits
- **Serial Number**: 8-digit unique identifier for each manufactured module (e.g., "00123456")
- **Shall OR Must**: Indicates a mandatory requirement; must be implemented and verifiable
- **Will**: States a fact, role, or declaration; not a requirement, just descriptive
- **Should**: Expresses a recommendation or non-binding goal; not strictly required
- **May**: Indicates permission or an allowable option; the feature is optional

---

## 4. Data Sources

### REQ-001: Legacy OM Database Integration
The system shall retrieve production batch information from the legacy OM database tables.

**Supporting Information:**
- **Primary Tables**: `oms_prod_batch`, `oms_batch_items`
- **Access Mode**: Read-only
- **Usage**: Batch data source for engraving job creation
- **Data Integrity**: Validate batch exists before processing

### REQ-002: QPM BOM Integration
The system shall retrieve BOM data from the Quadica Purchasing Management (QPM) plugin.

**Supporting Information:**
- **Data Source**: QPM BOM records (`sku-default-bom` post type)
- **Access Mode**: Read-only
- **Usage**: LED SKU assignments per module position
- **Integration**: Use existing QPM field constants and functions

### REQ-003: WooCommerce Product Integration
The system shall retrieve LED codes and product data from WooCommerce.

**Supporting Information:**
- **Data Types**: LED codes, product SKUs, product metadata
- **Access Mode**: Read-only
- **Field Name**: `led_shortcode` meta key for 3-character LED codes
- **Integration**: Standard WooCommerce product functions

### REQ-004: Serial Number Database
The system shall store serial numbers in a dedicated database table.

**Supporting Information:**
- **Table Name**: `{prefix}_quad_serial_numbers`
- **Purpose**: Serial number generation, tracking, and uniqueness enforcement
- **Relationship**: Links to engraving jobs and arrays

### REQ-005: Engraving Job Database
The system shall store engraving job and array data in dedicated database tables.

**Supporting Information:**
- **Tables**: `{prefix}_quad_engraving_jobs`, `{prefix}_quad_engraving_arrays`
- **Purpose**: Job tracking, SVG storage, status management
- **Relationship**: Jobs contain arrays, arrays reference serial numbers

---

## 5. Functional Requirements

### 5.1 Plugin Foundation

#### REQ-006: Plugin Activation
The system shall activate without errors alongside existing WordPress plugins.

**Supporting Information:**
- **Dependencies**: WordPress 6.8+, WooCommerce 9.9+, PHP 8.1+
- **Activation Hook**: Create required database tables on activation
- **Deactivation**: Preserve all data on deactivation
- **Uninstall**: Optional data removal with confirmation

#### REQ-007: Module Registration System
The system shall provide a registration system for adding functional modules.

**Supporting Information:**
- **Pattern**: Hook-based module registration
- **Initial Module**: Engraving Module
- **Future Modules**: Production Batching, Shipping, Reporting, Inventory
- **Extensibility**: Third-party modules can register via documented hooks

#### REQ-008: Admin Menu Integration
The system shall add menu items within the WordPress admin.

**Supporting Information:**
- **Menu Location**: Within Quadica menu group (if exists) or standalone top-level menu
- **Icon**: Custom Quadica icon or dashicons-admin-generic
- **Capability**: `manage_woocommerce` required for access
- **Submenus**: One per registered module

### 5.2 Serial Number Management

#### REQ-009: Serial Number Range Constraints
The system shall enforce serial number range constraints based on Micro-ID encoding capacity.

**Supporting Information:**
- **Minimum Value**: 00000001 (1)
- **Maximum Value**: 01048575 (2^20 - 1)
- **Total Capacity**: 1,048,575 unique serial numbers
- **Format**: 8-character zero-padded string
- **Constraint Source**: Micro-ID 20-bit encoding limit

#### REQ-010: Serial Number Generation
The system shall generate serial numbers sequentially within the valid range.

**Supporting Information:**
- **Method**: Sequential assignment from last used value
- **Atomicity**: Use database transactions to prevent race conditions
- **Batch Generation**: Support generating multiple serials in single operation
- **Return Format**: Array of 8-character zero-padded strings

**Formula:**
```
next_serial = MAX(serial_integer) + 1
WHERE serial_integer < 1048575
```

#### REQ-011: Serial Number Uniqueness Enforcement
The system shall enforce uniqueness at the database level.

**Supporting Information:**
- **Database Constraint**: UNIQUE index on `serial_integer` column
- **Application Check**: Verify uniqueness before insert
- **Collision Handling**: Return WP_Error if duplicate attempted
- **Recovery**: No automatic retry; surface error to user

#### REQ-012: Serial Number Capacity Tracking
The system shall track remaining serial number capacity.

**Supporting Information:**
- **Calculation**: `1048575 - MAX(serial_integer)`
- **Display**: Show remaining capacity in admin dashboard
- **Warning Threshold**: Configurable, default 10,000 remaining
- **Critical Threshold**: 1,000 remaining triggers prominent warning

**Capacity Estimate (based on historical production):**
| Scenario | Annual Rate | Years Until Exhaustion |
|----------|-------------|------------------------|
| Current average | 85,000/year | ~12 years |
| High growth | 105,000/year | ~10 years |
| Aggressive growth | 130,000/year | ~8 years |

#### REQ-013: Serial Number Data Storage
The system shall store serial number records with the following data.

**Supporting Information:**
- **Database Table**: `{prefix}_quad_serial_numbers`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key, auto-increment |
| `serial_number` | CHAR(8) | Zero-padded string (e.g., "00123456") |
| `serial_integer` | INT | Numeric value for range management |
| `module_id` | VARCHAR(20) | Associated module identifier (e.g., "STARa-34924") |
| `batch_id` | BIGINT | Reference to source production batch |
| `order_id` | BIGINT | Reference to customer order (nullable) |
| `array_position` | TINYINT | Position 1-8 within the carrier array |
| `job_id` | BIGINT | Reference to engraving job |
| `array_id` | BIGINT | Reference to engraving array |
| `status` | ENUM | 'generated', 'engraved', 'shipped', 'scrapped' |
| `created_at` | DATETIME | Creation timestamp |
| `engraved_at` | DATETIME | Engraving timestamp (nullable) |
| `created_by` | BIGINT | User ID who generated the serial |

#### REQ-014: Serial Number Status Transitions
The system shall enforce valid status transitions for serial numbers.

**Supporting Information:**
- **Valid Transitions**:
  - `generated` → `engraved` (when array marked complete)
  - `generated` → `scrapped` (manual action)
  - `engraved` → `shipped` (future module)
  - `engraved` → `scrapped` (manual action)
- **Invalid Transitions**: All others shall return error
- **Audit Trail**: Log all status changes with timestamp and user

### 5.3 Micro-ID Encoding

#### REQ-015: Micro-ID Grid Specification
The system shall generate Micro-ID codes according to the Quadica 5x5 specification.

**Supporting Information:**
- **Visual Reference**: [Micro-ID Grid Layout](docs/screenshots/dev/micro-id-grid.png) (placeholder)
- **Grid Size**: 1.0mm x 1.0mm total area
- **Dot Diameter**: 0.10mm
- **Dot Pitch**: 0.225mm center-to-center
- **Grid Layout**: 5x5 matrix (25 positions)

#### REQ-016: Micro-ID Encoding Capacity
The system shall encode serial numbers using 20-bit binary representation.

**Supporting Information:**
- **Encoding Capacity**: 20 bits = 0 to 1,048,575
- **Data Bits**: 20 bits for serial number
- **Parity Bit**: 1 bit for error detection (even parity)
- **Total Data**: 21 bits mapped to grid positions

**Encoding Formula:**
```
binary_value = serial_integer (20 bits)
parity_bit = (popcount(binary_value) % 2) XOR to make even
data_bits = binary_value + (parity_bit << 20)
```

#### REQ-017: Micro-ID Anchor Dots
The system shall include 4 corner anchor dots that are always ON.

**Supporting Information:**
- **Purpose**: Provide fixed reference points for decoding
- **Positions**: Four corners of 5x5 grid
- **State**: Always filled (ON) regardless of data
- **Coordinates** (relative to grid origin):
  - Top-left: (0.05, 0.05)
  - Top-right: (0.95, 0.05)
  - Bottom-left: (0.05, 0.95)
  - Bottom-right: (0.95, 0.95)

#### REQ-018: Micro-ID Orientation Marker
The system shall include an orientation marker dot outside the main grid.

**Supporting Information:**
- **Purpose**: Indicate correct reading orientation
- **Position**: (-0.175mm, 0.05mm) relative to grid origin
- **State**: Always filled (ON)
- **Diameter**: Same as data dots (0.10mm)

#### REQ-019: Micro-ID Bit-to-Grid Mapping
The system shall map the 21 data bits to specific grid positions.

**Supporting Information:**
- **Available Positions**: 21 positions (25 total - 4 corner anchors)
- **Mapping Order**: Left-to-right, top-to-bottom, skipping corners
- **Bit 0**: Position (0, 1) - second column, first row
- **Bit 20**: Parity bit at final mapped position

**Grid Position Map (0-indexed, excluding corners):**
```
Row 0: [anchor] [bit0]  [bit1]  [bit2]  [anchor]
Row 1: [bit3]   [bit4]  [bit5]  [bit6]  [bit7]
Row 2: [bit8]   [bit9]  [bit10] [bit11] [bit12]
Row 3: [bit13]  [bit14] [bit15] [bit16] [bit17]
Row 4: [anchor] [bit18] [bit19] [bit20] [anchor]
```

#### REQ-020: Micro-ID SVG Output
The system shall render Micro-ID as SVG circle elements.

**Supporting Information:**
- **Element Type**: `<circle>` for each dot
- **Grouping**: Wrapped in `<g>` element with ID
- **Fill**: `black` for ON dots
- **Positioning**: `transform="translate(x,y)"` on group

**SVG Output Example:**
```xml
<g id="micro-id-1" transform="translate(5.2,8.1)">
  <!-- Orientation marker -->
  <circle cx="-0.175" cy="0.05" r="0.05" fill="black"/>
  <!-- Corner anchors -->
  <circle cx="0.05" cy="0.05" r="0.05" fill="black"/>
  <circle cx="0.95" cy="0.05" r="0.05" fill="black"/>
  <circle cx="0.05" cy="0.95" r="0.05" fill="black"/>
  <circle cx="0.95" cy="0.95" r="0.05" fill="black"/>
  <!-- Data dots (example for serial 00123456) -->
  <circle cx="0.275" cy="0.05" r="0.05" fill="black"/>
  <!-- ... additional data dots based on encoding -->
</g>
```

### 5.4 Data Matrix Barcode

#### REQ-021: Data Matrix Format
The system shall generate Data Matrix ECC 200 barcodes.

**Supporting Information:**
- **Format**: ECC 200 (error correction capable)
- **Library**: `tecnickcom/tc-lib-barcode` via Composer
- **Error Correction**: Built-in to ECC 200 standard

#### REQ-022: Data Matrix Content
The system shall encode module URLs in Data Matrix barcodes.

**Supporting Information:**
- **URL Format**: `https://quadi.ca/{serial_number}`
- **Example**: `https://quadi.ca/00123456`
- **Validation**: Serial number must be valid 8-digit format

#### REQ-023: Data Matrix Size
The system shall render Data Matrix at configurable size.

**Supporting Information:**
- **Default Size**: 3.0mm x 3.0mm
- **Configurable**: Size specified per element in job data
- **Scaling**: Library output scaled to target dimensions
- **Aspect Ratio**: Always 1:1 (square)

#### REQ-024: Data Matrix SVG Output
The system shall output Data Matrix as SVG elements.

**Supporting Information:**
- **Element Type**: `<rect>` elements for modules
- **Grouping**: Wrapped in `<g>` element with ID
- **Fill**: `black` for filled modules
- **Positioning**: `transform="translate(x,y)"` on group

### 5.5 Text Rendering

#### REQ-025: Text as Path Outlines
The system shall render all text as SVG path outlines, not font references.

**Supporting Information:**
- **Purpose**: Ensure Lightburn compatibility without font dependencies
- **Method**: Stroke font with predefined character paths
- **Output**: `<path>` elements for each character

#### REQ-026: Character Set Support
The system shall support a restricted character set for engraving.

**Supporting Information:**
- **Uppercase Letters**: A-Z (26 characters)
- **Lowercase Letters**: a (revision suffix only)
- **Digits**: 0-9 (10 characters)
- **Punctuation**: . - / : (4 characters)
- **Total**: 41 characters

#### REQ-027: LED Code Character Set
The system shall validate LED codes against the restricted character set.

**Supporting Information:**
- **Valid Characters**: `1234789CEFHJKLPRT` (17 characters)
- **Code Length**: Exactly 3 characters
- **Validation**: Reject invalid characters with error message
- **Source**: `led_shortcode` product meta field

#### REQ-028: Text Sizing
The system shall support configurable text sizes.

**Supporting Information:**
- **Size Unit**: Millimeters
- **Default Sizes**:
  - Module ID: 1.5mm height
  - Serial URL: 1.2mm height
  - LED Code: 1.0mm height
- **Scaling**: Character paths scaled proportionally

#### REQ-029: Text Anchor Positions
The system shall support text anchor positioning.

**Supporting Information:**
- **Options**: start, middle, end
- **Default**: start (left-aligned)
- **Calculation**: Adjust X position based on text width and anchor

### 5.6 SVG Generation

#### REQ-030: SVG Document Structure
The system shall generate valid SVG documents with millimeter units.

**Supporting Information:**
- **Dimensions**: `width="148mm" height="113.7mm"`
- **ViewBox**: `viewBox="0 0 148 113.7"`
- **Namespace**: `xmlns="http://www.w3.org/2000/svg"`
- **Encoding**: UTF-8

**SVG Document Template:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"
     width="148mm" height="113.7mm"
     viewBox="0 0 148 113.7">
  <!-- Module groups -->
</svg>
```

#### REQ-031: Element Grouping
The system shall logically group SVG elements by module position.

**Supporting Information:**
- **Module Group**: `<g id="module-{position}">`
- **Element Groups**: Nested groups for micro-id, datamatrix, text
- **Purpose**: Organize output, enable selective editing in Lightburn

#### REQ-032: Element Positioning
The system shall position elements using transform attributes.

**Supporting Information:**
- **Method**: `transform="translate(x,y)"` on group elements
- **Coordinate System**: Origin at top-left of array
- **Units**: Millimeters matching viewBox

#### REQ-033: Lightburn Compatibility
The system shall output SVG compatible with Lightburn auto-import.

**Supporting Information:**
- **Requirements**:
  - No external references (fonts, images, stylesheets)
  - Inline styles only (no CSS classes)
  - Black fill/stroke for engraving elements
  - Self-contained document
- **Testing**: Manual verification in Lightburn required

#### REQ-034: SVG Storage
The system shall store generated SVG content in the database.

**Supporting Information:**
- **Storage Location**: `svg_content` column in `qip_engraving_arrays` table
- **Format**: Complete SVG document as TEXT
- **Compression**: None (human-readable for debugging)
- **Export**: Optional filesystem export for Lightburn watched directory

#### REQ-035: SVG Filename Generation
The system shall generate descriptive filenames for exported SVG files.

**Supporting Information:**
- **Format**: `{job_id}-{sequence}-{batch_id}.svg`
- **Example**: `42-003-1234.svg` (job 42, array 3, batch 1234)
- **Sanitization**: Remove/replace invalid filesystem characters
- **Uniqueness**: Combination of job_id and sequence ensures uniqueness

### 5.7 Engraving Queue Management

#### REQ-036: Engraving Job Data Storage
The system shall store engraving job records with the following data.

**Supporting Information:**
- **Database Table**: `{prefix}_quad_engraving_jobs`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key, auto-increment |
| `batch_id` | BIGINT | Source production batch reference |
| `job_name` | VARCHAR(100) | Descriptive identifier |
| `array_count` | INT | Number of arrays in this job |
| `module_count` | INT | Total modules to engrave |
| `status` | ENUM | Job status (see REQ-038) |
| `created_at` | DATETIME | Creation timestamp |
| `started_at` | DATETIME | When processing began (nullable) |
| `completed_at` | DATETIME | Completion timestamp (nullable) |
| `error_message` | TEXT | Error details if failed (nullable) |
| `created_by` | BIGINT | User ID who created the job |

#### REQ-037: Engraving Array Data Storage
The system shall store engraving array records with the following data.

**Supporting Information:**
- **Database Table**: `{prefix}_quad_engraving_arrays`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key, auto-increment |
| `job_id` | BIGINT | Parent engraving job reference |
| `sequence` | INT | Order within job (1-indexed) |
| `svg_filename` | VARCHAR(100) | Generated file identifier |
| `svg_content` | LONGTEXT | The generated SVG data |
| `module_count` | TINYINT | Number of modules in this array (1-8) |
| `status` | ENUM | 'pending', 'engraved', 'failed' |
| `engraved_at` | DATETIME | When marked complete (nullable) |
| `engraved_by` | BIGINT | User ID who marked engraved (nullable) |

#### REQ-038: Job Status States
The system shall track job status through defined states.

**Supporting Information:**

| Status | Description |
|--------|-------------|
| `pending` | Job created, SVG generation not started |
| `ready` | SVG files generated, available for engraving |
| `in_progress` | User actively engraving arrays from this job |
| `completed` | All arrays engraved |
| `failed` | Error during processing |
| `cancelled` | Job cancelled by user |

#### REQ-039: Job Status Transitions
The system shall enforce valid job status transitions.

**Supporting Information:**
- **Valid Transitions**:
  - `pending` → `ready` (SVG generation complete)
  - `pending` → `failed` (SVG generation error)
  - `pending` → `cancelled` (user cancellation)
  - `ready` → `in_progress` (first array downloaded/viewed)
  - `ready` → `cancelled` (user cancellation)
  - `in_progress` → `completed` (all arrays marked engraved)
  - `in_progress` → `failed` (processing error)
  - `in_progress` → `cancelled` (user cancellation)
- **Invalid Transitions**: Return WP_Error with descriptive message

#### REQ-040: Sequential Array Processing
The system shall process arrays sequentially within a job.

**Supporting Information:**
- **Order**: By sequence number ascending
- **Enforcement**: UI presents arrays in order, allows marking current or previous
- **Flexibility**: User may skip/revisit arrays but sequence tracked

#### REQ-041: Array Status Update
The system shall update array status when marked as engraved.

**Supporting Information:**
- **Trigger**: User clicks "Mark as Engraved" button
- **Updates**:
  - Array `status` → 'engraved'
  - Array `engraved_at` → current timestamp
  - Array `engraved_by` → current user ID
  - Associated serial numbers `status` → 'engraved'
  - Associated serial numbers `engraved_at` → current timestamp

#### REQ-042: Job Completion Detection
The system shall automatically update job status when all arrays complete.

**Supporting Information:**
- **Trigger**: Array marked as engraved
- **Check**: Count arrays where status != 'engraved'
- **Action**: If count = 0, set job status to 'completed' and `completed_at` timestamp

#### REQ-043: Job Cancellation
The system shall allow job cancellation with appropriate cleanup.

**Supporting Information:**
- **Allowed States**: pending, ready, in_progress
- **Actions on Cancel**:
  - Set job status to 'cancelled'
  - Mark all associated serial numbers as 'scrapped'
  - Retain SVG content for audit purposes
- **Confirmation**: Require user confirmation before cancellation

#### REQ-044: Job Error Handling
The system shall handle and record job processing errors.

**Supporting Information:**
- **Error Capture**: Store error message in `error_message` column
- **Status Update**: Set job status to 'failed'
- **User Notification**: Display error in admin interface
- **Recovery**: Allow retry from failed state (creates new job)

#### REQ-045: Array Count Calculation
The system shall calculate the number of arrays needed for a batch.

**Supporting Information:**
- **Modules Per Array**: Maximum 8
- **Formula**: `array_count = CEIL(module_count / 8)`
- **Example**: 19 modules = 3 arrays (8 + 8 + 3)

### 5.8 Legacy System Integration

#### REQ-046: New-Style Module Identification
The system shall identify new-style modules by SKU pattern.

**Supporting Information:**
- **Pattern**: 4 uppercase letters + 1 lowercase letter + hyphen + 5 digits
- **Regex**: `^[A-Z]{4}[a-z]-[0-9]{5}$`
- **Examples**: "STARa-34924", "NORDa-17051", "ATOMa-34924", "APEXa-20035"
- **Non-Matches**: "SP-01-WW", "SR-01-M0100", "MR-M0090-20S"

#### REQ-047: Production Batch Data Retrieval
The system shall retrieve batch data from the legacy OM system.

**Supporting Information:**
- **Source Table**: `oms_prod_batch`
- **Join Table**: `oms_batch_items`
- **Filter**: Only items where `assembly_sku` matches new-style pattern
- **Fields Required**:
  - `batch_id` - Batch identifier
  - `batch_date` - Batch creation date
  - `assembly_sku` - Module SKU
  - `build_qty` - Quantity to build
  - `order_no` - Associated order number

**Query Pattern:**
```sql
SELECT b.batch_id, b.batch_date, bi.assembly_sku, bi.build_qty, bi.order_no
FROM oms_prod_batch b
JOIN oms_batch_items bi ON b.batch_id = bi.batch_id
WHERE bi.assembly_sku REGEXP '^[A-Z]{4}[a-z]-[0-9]{5}$'
  AND b.status = 'Pending'
ORDER BY b.batch_date, bi.assembly_sku
```

#### REQ-048: QPM BOM Data Retrieval
The system shall retrieve LED configuration from QPM BOMs.

**Supporting Information:**
- **Source**: `sku-default-bom` custom post type
- **Lookup**: By module SKU (assembly_sku)
- **Data Required**:
  - LED SKUs per position
  - Position numbers (1-8)
  - LED quantities

#### REQ-049: LED Code Retrieval
The system shall retrieve LED codes from WooCommerce products.

**Supporting Information:**
- **Source**: WooCommerce product meta
- **Meta Key**: `led_shortcode`
- **Lookup**: By LED product SKU from BOM
- **Validation**: Must match LED code character set (REQ-027)
- **Fallback**: If no led_shortcode, log warning and skip LED code engraving

#### REQ-050: Module Position Coordinates
The system shall use predefined coordinates for each module position on the array.

**Supporting Information:**
- **Visual Reference**: [Standard Array Layout](docs/reference/quadica-standard-array.jpg)
- **Coordinate System**: Origin at top-left of array
- **Units**: Millimeters

**Position Coordinates (to be finalized from CAD):**
| Position | Origin X (mm) | Origin Y (mm) |
|----------|---------------|---------------|
| 1 | TBD | TBD |
| 2 | TBD | TBD |
| 3 | TBD | TBD |
| 4 | TBD | TBD |
| 5 | TBD | TBD |
| 6 | TBD | TBD |
| 7 | TBD | TBD |
| 8 | TBD | TBD |

#### REQ-051: Element Offsets Within Position
The system shall use predefined offsets for each element type within a module position.

**Supporting Information:**
- **Coordinate System**: Relative to position origin

**Element Offsets (to be finalized from CAD):**
| Element | X Offset (mm) | Y Offset (mm) |
|---------|---------------|---------------|
| Micro-ID | TBD | TBD |
| Data Matrix | TBD | TBD |
| Module ID Text | TBD | TBD |
| Serial URL Text | TBD | TBD |
| LED Code 1 | TBD | TBD |
| LED Code 2 | TBD | TBD |
| LED Code 3+ | TBD | TBD |

#### REQ-052: Batch Validation
The system shall validate batch data before job creation.

**Supporting Information:**
- **Checks**:
  - Batch exists and is in 'Pending' status
  - Batch contains at least one new-style module
  - All modules have valid BOM data in QPM
  - All LED SKUs have valid LED codes (or skip with warning)
- **Error Handling**: Return WP_Error with specific validation failures

#### REQ-053: Module Data Assembly
The system shall assemble complete module data for SVG generation.

**Supporting Information:**
- **Data Structure Per Module**:
```php
[
    'module_id' => 'STARa-34924',      // From batch_items
    'serial_number' => '00123456',      // Generated
    'position' => 1,                    // Array position (1-8)
    'leds' => [
        ['position' => 1, 'sku' => 'LXML-PWC2', 'code' => 'K7P'],
        ['position' => 2, 'sku' => 'LXML-PWC2', 'code' => 'K7P'],
        // ... additional LEDs
    ]
]
```

#### REQ-054: Data Caching
The system should cache frequently accessed data during job processing.

**Supporting Information:**
- **Cache Targets**: LED codes by SKU, BOM data by module SKU
- **Cache Duration**: Per-request only (no persistent caching)
- **Purpose**: Reduce database queries during batch processing

#### REQ-055: Error Logging
The system shall log integration errors for debugging.

**Supporting Information:**
- **Log Location**: WordPress debug.log (if WP_DEBUG_LOG enabled)
- **Log Level**: Warning for missing LED codes, Error for fatal issues
- **Content**: Timestamp, source system, specific error, context data

---

## 6. Admin Interface Requirements

### REQ-056: Dashboard Widget
The system shall display a dashboard widget with queue status.

**Supporting Information:**
- **Visual Reference**: [Dashboard Widget Mockup](docs/screenshots/dev/qip-dashboard-widget.png) (placeholder)
- **Location**: WordPress admin dashboard
- **Content**:
  - Jobs currently in progress (count)
  - Arrays pending engraving (count)
  - Serial number capacity remaining (count and percentage)
- **Refresh**: Auto-refresh every 60 seconds or manual refresh button

### REQ-057: Job List View
The system shall display a list of engraving jobs.

**Supporting Information:**
- **Visual Reference**: [Job List Mockup](docs/screenshots/dev/qip-job-list.png) (placeholder)
- **Columns**: Job ID, Name, Batch ID, Arrays, Modules, Status, Created, Actions
- **Sorting**: Default by created date descending
- **Filtering**: By status (All, Pending, Ready, In Progress, Completed, Failed, Cancelled)
- **Pagination**: 20 items per page

### REQ-058: Create Job View
The system shall provide an interface for creating new engraving jobs.

**Supporting Information:**
- **Visual Reference**: [Create Job Mockup](docs/screenshots/dev/qip-create-job.png) (placeholder)
- **Steps**:
  1. Select production batch from dropdown (pending batches only)
  2. System displays preview: module count, module types, array count
  3. User confirms to generate job
  4. Progress indicator during SVG generation
  5. Redirect to job detail on completion

### REQ-059: Job Detail View
The system shall display detailed job information and array list.

**Supporting Information:**
- **Visual Reference**: [Job Detail Mockup](docs/screenshots/dev/qip-job-detail.png) (placeholder)
- **Sections**:
  - Job summary (name, batch, status, timestamps)
  - Array list with status indicators
  - Action buttons (Cancel Job, if applicable)

### REQ-060: Process Array View
The system shall provide an interface for processing individual arrays.

**Supporting Information:**
- **Visual Reference**: [Process Array Mockup](docs/screenshots/dev/qip-process-array.png) (placeholder)
- **Content**:
  - Job context (job name, progress "Array 2 of 5")
  - SVG preview (rendered in browser)
  - Module list for this array (serial numbers, module IDs)
  - Download SVG button
  - Mark as Engraved button
  - Next/Previous array navigation

### REQ-061: SVG Preview
The system shall render SVG preview in the admin interface.

**Supporting Information:**
- **Method**: Inline SVG in HTML
- **Scaling**: Fit to container width, maintain aspect ratio
- **Interactivity**: Pan/zoom optional enhancement
- **Fallback**: Download link if preview fails

### REQ-062: Capacity Warning Display
The system shall display prominent warnings when serial capacity is low.

**Supporting Information:**
- **Warning Level** (< 10,000 remaining):
  - Yellow banner on Engraving Module pages
  - Warning icon in dashboard widget
- **Critical Level** (< 1,000 remaining):
  - Red banner on all QIP pages
  - Admin notification
  - Block new job creation with explanation

---

## 7. Security Requirements

### REQ-063: Capability Checks
The system shall verify user capabilities before all actions.

**Supporting Information:**
- **Required Capability**: `manage_woocommerce`
- **Check Points**: Page load, AJAX handlers, REST endpoints
- **Failure Response**: wp_die() with appropriate message

### REQ-064: Nonce Verification
The system shall verify nonces for all form submissions and AJAX requests.

**Supporting Information:**
- **Nonce Action**: `qip_{action_name}`
- **Nonce Field**: `qip_nonce`
- **Verification**: `wp_verify_nonce()` on all handlers
- **Failure Response**: wp_die() or WP_Error for AJAX

### REQ-065: Input Validation
The system shall validate all user input before processing.

**Supporting Information:**
- **Batch ID**: Positive integer, must exist in database
- **Job ID**: Positive integer, must exist in database
- **Serial Numbers**: Match pattern `^[0-9]{8}$`
- **Status Values**: Must be valid enum value

### REQ-066: SQL Injection Prevention
The system shall use prepared statements for all database queries.

**Supporting Information:**
- **Method**: `$wpdb->prepare()` for all queries with user data
- **Direct Queries**: Only for static queries with no user input
- **Validation**: Additional type checking before query execution

### REQ-067: File Security
The system shall protect exported SVG files from unauthorized access.

**Supporting Information:**
- **Storage Directory**: `wp-content/uploads/qip-svg/`
- **Directory Protection**: .htaccess deny direct access
- **Access Method**: Authenticated download handler only
- **Filename**: Non-guessable (include job ID and hash)

### REQ-068: Data Integrity
The system shall maintain data integrity across related tables.

**Supporting Information:**
- **Transactions**: Use database transactions for multi-table operations
- **Foreign Keys**: Application-level enforcement (WordPress limitation)
- **Cascade Rules**: Document and enforce in code

---

## 8. Testing Requirements

### REQ-069: Unit Test Coverage
The system shall include unit tests for critical functions.

**Supporting Information:**
- **Location**: `tests/unit/` directory
- **Framework**: PHPUnit via Composer
- **Coverage Areas**:
  - Serial number generation and validation
  - Micro-ID encoding algorithm
  - Micro-ID parity calculation
  - Data Matrix generation
  - Text path rendering
  - SVG assembly

### REQ-070: Micro-ID Encoding Tests
The system shall include specific test cases for Micro-ID encoding.

**Supporting Information:**
- **Test Cases**:

| Serial Number | Binary (20-bit) | Parity | Expected Dots |
|---------------|-----------------|--------|---------------|
| 00000001 | 00000000000000000001 | 1 | Verify pattern |
| 00333333 | 01010001010100110101 | 0 | Verify pattern |
| 01048575 | 11111111111111111111 | 0 | All data dots ON |

### REQ-071: Integration Test Coverage
The system shall include integration tests for data retrieval.

**Supporting Information:**
- **Test Areas**:
  - Legacy OM data retrieval
  - QPM BOM data retrieval
  - WooCommerce LED code retrieval
  - End-to-end job creation workflow

### REQ-072: Manual Acceptance Tests
The system shall document manual test procedures.

**Supporting Information:**
- **Test Procedures**:

| Test | Steps | Expected Result |
|------|-------|-----------------|
| Lightburn Import | Open generated SVG in Lightburn | All elements visible, correctly positioned |
| Micro-ID Verification | Decode engraved Micro-ID | Serial number matches database |
| Data Matrix Scan | Scan engraved barcode | Opens correct quadi.ca URL |
| Visual Inspection | Review engraved module | All text legible, proper sizing |

### REQ-073: Test Data Requirements
The system shall document required test data.

**Supporting Information:**
- **Required Data**:
  - Test batch in legacy OM with known new-style modules
  - Test BOMs in QPM with known LED configurations
  - Test LED products with known LED codes
  - Expected output documentation for verification

---

## 9. Configuration

### REQ-074: Plugin Settings
The system shall provide configurable settings via WordPress options.

**Supporting Information:**
- **Settings Location**: Settings > QIP or Quadica submenu
- **Options**:

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `qip_serial_warning_threshold` | Integer | 10000 | Remaining serials before warning |
| `qip_serial_critical_threshold` | Integer | 1000 | Remaining serials before critical |
| `qip_svg_export_path` | String | '' | Optional filesystem export directory |
| `qip_default_text_size_module_id` | Float | 1.5 | Module ID text height (mm) |
| `qip_default_text_size_serial` | Float | 1.2 | Serial URL text height (mm) |
| `qip_default_text_size_led_code` | Float | 1.0 | LED code text height (mm) |
| `qip_datamatrix_size` | Float | 3.0 | Data Matrix size (mm) |

### REQ-075: Position Configuration
The system should allow position coordinates to be configured.

**Supporting Information:**
- **Storage**: WordPress options as serialized array
- **UI**: Optional admin page for coordinate entry
- **Default**: Hardcoded values until CAD finalized
- **Override**: Filter hook for programmatic override

---

## 10. Future Considerations

### 10.1 Planned Future Modules

| Module | Purpose | Priority |
|--------|---------|----------|
| Production Batching | Create and manage production batches (replace legacy OM) | High |
| Component Reservation | Soft/hard lock component allocation | Medium |
| Order Tracking | Track order completion across batches | Medium |
| Shipping Integration | Shipping batch management | Medium |
| Reporting | Production reports and analytics | Low |
| Inventory | Component inventory management | Low |

### 10.2 Serial Number Capacity Planning

When serial numbers approach 80% utilization (~838,000 used), consider:
- Expanding to a second encoding range (new Micro-ID format)
- Transitioning to larger Micro-ID grid (6x6 = 32 data bits)
- Retiring old serials from scrapped modules for reuse (with safeguards)

### 10.3 Extensibility Hooks

Document hooks for future module integration:
- `qip_module_registered` - After module registration
- `qip_serial_generated` - After serial number creation
- `qip_svg_generated` - After SVG file creation
- `qip_array_engraved` - After array marked complete

---

## Appendix A: Reference Documents

| Document | Location | Purpose |
|----------|----------|---------|
| SVG Engraver Plan | [quadica-svg-engraver-plan.md](docs/reference/quadica-svg-engraver-plan.md) | Technical implementation details |
| Standard Array Drawing | [quadica-standard-array.jpg](docs/reference/quadica-standard-array.jpg) | Position coordinates reference |
| Legacy OM Documentation | [legacy-om-system.md](docs/reference/legacy-om-system.md) | Database schema, integration points |
| QPM Discovery Document | [qpm-discovery.md](docs/reference/qpm-discovery.md) | BOM structure, field mappings |

## Appendix B: Database Schema

### Table: {prefix}_quad_serial_numbers
```sql
CREATE TABLE {prefix}_quad_serial_numbers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    serial_number CHAR(8) NOT NULL,
    serial_integer INT UNSIGNED NOT NULL,
    module_id VARCHAR(20) NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    array_position TINYINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    array_id BIGINT UNSIGNED NOT NULL,
    status ENUM('generated','engraved','shipped','scrapped') NOT NULL DEFAULT 'generated',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    engraved_at DATETIME DEFAULT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY serial_integer (serial_integer),
    UNIQUE KEY serial_number (serial_number),
    KEY batch_id (batch_id),
    KEY job_id (job_id),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: {prefix}_quad_engraving_jobs
```sql
CREATE TABLE {prefix}_quad_engraving_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    job_name VARCHAR(100) NOT NULL,
    array_count INT UNSIGNED NOT NULL DEFAULT 0,
    module_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('pending','ready','in_progress','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY batch_id (batch_id),
    KEY status (status),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: {prefix}_quad_engraving_arrays
```sql
CREATE TABLE {prefix}_quad_engraving_arrays (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    sequence INT UNSIGNED NOT NULL,
    svg_filename VARCHAR(100) NOT NULL,
    svg_content LONGTEXT NOT NULL,
    module_count TINYINT UNSIGNED NOT NULL,
    status ENUM('pending','engraved','failed') NOT NULL DEFAULT 'pending',
    engraved_at DATETIME DEFAULT NULL,
    engraved_by BIGINT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY job_id (job_id),
    KEY status (status),
    UNIQUE KEY job_sequence (job_id, sequence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Appendix C: Open Items

Items requiring decision before implementation:

| Item | Status | Owner | Notes |
|------|--------|-------|-------|
| Standard Array position coordinates | Pending | Engineering | From CAD drawings |
| Element offsets within positions | Pending | Engineering | From CAD drawings |
| Text sizes for each element type | Pending | Engineering | Verify with test engravings |
| Lightburn auto-import directory structure | Pending | Operations | Production workflow decision |
| LED code field name confirmation | Pending | Development | Verify `led_shortcode` is correct |

---

**Document Status:** Draft - Pending Review

*Document generated: December 11, 2025*

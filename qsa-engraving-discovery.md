# Quadica Standard Array Engraving- Discovery
 
**Last Update:** Dec 29, 2025
**Author:** Ron Warris  

This is a project startup document that contains a 'brain dump' from all project stakeholders. It includes thoughts, ideas, specific wants, project considerations, concerns, identified problems, etc. These are just captured thoughts about the project in no particular order that are indented to help define exactly what are we trying to build. This captured information is then used to generate the first draft of a formal requirements document.

## 1. Project Overview
The Quadica Standard Array (QSA) is our new standard array that will be used for all newly created LED modules. This array will replace the 30+ different array designs we have been using with the objective of making it easier for us to create and deploy new module designs into our production process without the need to generate custom engraving files for every new module array.

## 2. Project Goals
Create a WordPress/WooCommerce plugin that:

1. Determine which modules need to be built
2. Provide an interface that allows the laser operator to select the modules that will be included in the engraving batch
3. Extracts module engraving details from order BOMs and product details
4. Use the extracted data to generate an SVG file ready for engraving using LightBurn
5. Provides an interface that allows the laser operator to engrave the arrays using the SVG file

## 3. Definitions
- **QSA**: Quadica Standard Array - A MCPCB board with 8 LED module PCBs
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

## 4. Required Functionality
### Module Selection
The process will only process LED modules that use the QSA.
- [QSA design reference](docs/reference/quadica-standard-array.jpg)
-Q SA compatible modules can be identified using the first 5 characters of the module SKU which will always be 4 upper case alpha characters followed by a dash. E.g., `CORE-`.
- When the module engraving batch app is opened it will create a list of modules to be built from currently active production batches.
- The operator will be able to refresh the list of modules by clicking on an refresh icon.
- Needed modules can be determined by querying and comparing the values from the build_qty field with the value in the qty_recieved field in the oms_batch_items table. If the value in the qty_received field is less than the build_qty field, then the difference is what needs to be built and is included in the modules to build list.
- The list of modules that need to be engraved will be presented to the operator using the Module Engraving Batch Creator webpage
- A fully functional React mockup of this webpage is here https://claude.ai/public/artifacts/ec02119d-ab5b-44cd-918d-c598b2d2dd94

### SVG Engraving
- The operator selects the modules that are included in the batch using the [Module Selection page](https://claude.ai/public/artifacts/ec02119d-ab5b-44cd-918d-c598b2d2dd94)
- Each QSA accommodates up to 8 LED modules
- **Every QSA requires its own unique SVG file** because each module has a unique serial number embedded in the engraving (Micro-ID, Data Matrix, URL text). Even if the module type is identical across multiple QSAs, the serial numbers are different, so each QSA needs a fresh SVG.
- When the operator starts engraving a row, the system pre-generates all SVG files for that row so they are ready for instant loading.
- If there are fewer than 8 modules of the same unique ID, the system will combine different module types onto arrays. E.g., if the batch contains:

  | Module | Quantity |
  |--------|----------|
  | CORE-91247 | 2 |
  | CORE-38455 | 1 |
  | CORE-98546 | 3 |
  | CORE-23405 | 4 |
  | CORE-45946 | 3 |
  | **Total** | **13** |

  Then it will create two QSAs (and two unique SVG files):

  **QSA 1** (8 modules - full array)
  | Module | Quantity |
  |--------|----------|
  | CORE-91247 | 2 |
  | CORE-38455 | 1 |
  | CORE-98546 | 3 |
  | CORE-23405 | 2 |

  **QSA 2** (5 modules - partial array)
  | Module | Quantity |
  |--------|----------|
  | CORE-23405 | 2 |
  | CORE-45946 | 3 |

### Engraving Queue
After creating an engraving batch, the operator sees an Engraving Queue interface listing all QSAs to be engraved.

#### QSA Grouping
QSAs are grouped into rows to organize the work for the operator:

| Group Type | Description |
|------------|-------------|
| **Same ID × Full** | 8 identical modules per QSA |
| **Same ID × Partial** | <8 identical modules (final QSA) |
| **Mixed ID × Full** | 8 different module types per QSA |
| **Mixed ID × Partial** | <8 different module types (final QSA) |

Each row displays: module count, array count, and an "Engrave" button. Each QSA in the row will have its own unique SVG file (generated with unique serial numbers).

#### QSA Starting Offset
QSAs may have unused positions from previous batches. The operator can specify a **Starting Position** (1-8) for the first QSA in a run.

| Field | Description |
|-------|-------------|
| **Starting Position** | First position to engrave on the initial QSA (default: 1) |
| **Valid Range** | 1-8 (position 1 = first/top-left; position 8 = single module remaining) |

**Behavior:**
- The offset applies **only to the first QSA** in a multi-array run
- Subsequent QSAs always start at position 1
- If Starting Position = 5, the first QSA uses positions 5-8 (4 modules max)
- Offset is set **per row** — operator engraves one row at a time
- No persistence — operator manually provides starting position each time

**Example:** 26 identical modules, Starting Position = 5
1. **QSA 1** — positions 5-8 (4 modules, 4 unique serials)
2. **QSA 2** — positions 1-8 (8 modules, 8 unique serials)
3. **QSA 3** — positions 1-8 (8 modules, 8 unique serials)
4. **QSA 4** — positions 1-6 (6 modules, 6 unique serials)

Total: 4 + 8 + 8 + 6 = 26 modules across 4 QSAs, each with its own SVG file.

A fully functional React mockup of this webpage is here:
- https://claude.ai/public/artifacts/8319e841-26b2-4ae9-a7d6-8df243b19cf8

#### Array-by-Array Workflow

**Constraint:** LightBurn can only load one SVG file at a time, and each QSA needs unique serial numbers. The operator steps through each QSA sequentially.

**SVG Count = QSA Count:** Every QSA in a row requires its own SVG file. The system pre-generates all SVGs when the operator starts the row.

**Example:** 26 modules, Starting Position = 5

| QSA | Positions | Modules | Serials |
|-----|-----------|---------|---------|
| QSA 1 | 5-8 | 4 | 00000001-00000004 |
| QSA 2 | 1-8 | 8 | 00000005-00000012 |
| QSA 3 | 1-8 | 8 | 00000013-00000020 |
| QSA 4 | 1-6 | 6 | 00000021-00000026 |

**Total:** 4 QSAs, 4 SVG files, 26 modules with unique serials

#### Engraving Workflow

The operator steps through each QSA one at a time. The workflow is the same regardless of how many QSAs are in the row.

**Workflow Steps:**
1. Operator clicks **Engrave** → System pre-generates all SVGs for the row, loads first SVG into LightBurn
2. Interface shows: `Array 1 of N` with current positions and module details
3. Operator uses **footswitch** to engrave the QSA
4. Operator swaps in the next blank QSA
5. Operator presses **Spacebar** (or clicks **Next Array**) → System loads next SVG into LightBurn
6. Repeat steps 3-5 until all QSAs are engraved
7. After final QSA, operator clicks **Complete** → Row marked done, serial numbers committed

**Keyboard Shortcut:** The **Spacebar** advances to the next array. This allows the operator to keep hands near the work area without reaching for the mouse. The UI must clearly display this shortcut (e.g., "Press SPACEBAR or click Next Array").

**Interface Elements During Engraving:**

| Element | Description |
|---------|-------------|
| **Progress Indicator** | Shows "Array X of Y" with visual progress |
| **Positions** | Current QSA's position range (e.g., "5-8" or "1-8") |
| **Module Details** | Module IDs and serial numbers for current QSA |
| **Next Array Button** | Advances to next QSA (Spacebar shortcut) |
| **Complete Button** | Finalizes row (appears only on last QSA) |

Operator repeats workflow for all rows in the batch.

#### Error Recovery During Engraving

Things can go wrong during the engraving process. A module may not be positioned correctly, the laser may skip a step, or the operator may notice a quality issue that requires re-engraving. The interface needs to provide controls to handle these situations without starting the entire batch over.

**Why Error Recovery Is Needed:**
- Laser communication issues may require resending the file
- QSA shifted during engraving — need to scrap and use a fresh QSA
- Quality inspection reveals a defective engraving
- Operator accidentally advanced before completing an array
- A completed row needs to be re-done due to discovered defects

**Recovery Controls:**

| Control | When Available | What It Does | Serial Number Impact |
|---------|----------------|--------------|---------------------|
| **Resend** | During engraving | Sends the current SVG to LightBurn again. Use when the file didn't transfer properly but the QSA is still usable. | No change — same serials |
| **Retry** | During engraving | Scraps the current QSA and generates a fresh SVG with new serial numbers. Use when the QSA is ruined and you need a fresh substrate. | Current serials returned to pool, new serials reserved |
| **Back** | After first array | Returns to the previous array. The previous array's serials remain committed; you'll re-engrave on a new QSA with new serials. | Previous stays committed, new serials reserved for re-do |
| **Rerun** | After row complete | Resets selected arrays (or entire row) back to pending. Allows adjusting starting position. | Selected serials returned to pool, new serials reserved |

**Resend vs Retry — Key Distinction:**
- **Resend** = Same QSA, same serials, just re-transmit the file (communication issue)
- **Retry** = New QSA, new serials, the old QSA is scrapped (physical failure)

**Important Behaviors:**
- The "Back" button only appears after the first array
- The "Resend" and "Retry" buttons are available during an in-progress row
- Using "Rerun" allows selecting which arrays to redo, or redoing the entire row
- None of these actions affect other rows in the queue
- Voided and scrapped serial numbers return to the available pool for future use

### SVG Generation
The SVG file sent to LightBurn is generated on demand when the operator clicks the Engrave button on the Engraving Queue screen.

Referencing the [QSA design reference](docs/reference/quadica-standard-array.jpg) configuration graphic. The SVG file will contain the following elements for each module position to be engraved:
- Module Serial Number Micro-ID Code
- LED Code(s)
- Module ID
- Module Serial Number URL
- Module Serial Number ECC 200 Data matrix

These elements are created using the following data:
- Unique serial number
- 3 digit LED code(s)
- Base ID
- Base Configuration Code

#### Unique Serial Number Generation
- A unique serial number is generated for each LED module to be engraved at the time that the SVG file is created
- Serial numbers are generated using the following rules:
  - **Minimum Value**: 00000001 (1)
  - **Maximum Value**: 01048575 (2^20 - 1)
  - **Total Capacity**: 1,048,575 unique serial numbers
  - **Format**: 8-character zero-padded string
  - **Constraining Source**: Micro-ID 20-bit encoding limit
  - **Sequentially Generated**: Serial numbers are sequentially created from the pool of available numbers
  - **No Duplicates in Production**: Only one physical module in production can have a given serial number
  - **Voided/Scrapped Serials Return to Pool**: Serials that were never engraved (voided) or were engraved on scrapped modules return to the available pool for future use

#### Serial Number Lifecycle
Serial numbers move through the following states:

| Status | Meaning |
|--------|---------|
| **Reserved** | Serial allocated and embedded in SVG, engraving not yet confirmed |
| **Engraved** | Physically engraved on a module, confirmed by operator |
| **Shipped** | Module shipped to customer (future functionality) |
| **Available** | Serial returned to pool (was voided or scrapped) |

**State Transitions:**
- Reserved → Engraved (operator confirms successful engraving)
- Reserved → Available (operator uses Retry before engraving, or row cancelled — serial was never physically used)
- Engraved → Shipped (future: module ships to customer)
- Engraved → Available (module scrapped/destroyed — serial can be reused since physical module is gone)

#### Serial Number Assignment
Serial numbers are assigned using a "reserve then commit" approach:

1. **When operator clicks Engrave:** System pre-generates all SVGs for the row. Serial numbers are reserved and embedded in the SVG files.

2. **When operator presses Spacebar/Next Array:** The current array's serials are committed (Reserved → Engraved), and the next SVG is loaded.

3. **When operator clicks Complete:** The final array's serials are committed. The row is done.

4. **If operator clicks Retry:** Current array's reserved serials are returned to the available pool, new serials are reserved, and a new SVG is created.

5. **If operator clicks Rerun (after complete):** Selected engraved serials are returned to the available pool (physical modules scrapped), new serials are reserved, and new SVGs are created.

#### Serial Number Data Storage
The system will store serial number data in a database table named `lw_quad_serial_numbers`:
  - **Serial Number**: Zero-padded string (e.g., "00123456")
  - **Module ID**: Associated module identifier (e.g., "STAR-34924") — cleared when returned to available
  - **Batch ID**: Reference to source production batch — cleared when returned to available
  - **Order ID**: Reference to customer order
  - **QSA ID**: The array that the LED module is part of — cleared when returned to available
  - **Array Position**: Position number (1-8) on the QSA — cleared when returned to available
  - **Status**: Current lifecycle state (available, reserved, engraved, shipped)
  - **Reserved Timestamp**: When serial was last reserved (nullable)
  - **Engraved Timestamp**: When engraving was confirmed (nullable)

#### Micro-ID Code Generation
- Micro-ID codes are generated using the [Quadica 5x5 Micro ID specification](docs/reference/quadica-micro-id-specs.md)
- **QSA Coordinate Origin**: Top-left corner
- **Micro ID Insertion Point**: Top-Left corner
- **Units**: Millimeters
- **Position Coordinates**: Micro ID codes are engraved in each LED module position using the following coordinates:
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

---
**!!! CONTENT BELOW THIS POINT IS STILL BEING WORKED ON !!!**

### Data Matrix Barcode

#### Data Matrix Format
The system will generate Data Matrix ECC 200 barcodes.

**Supporting Information:**
- **Format**: ECC 200 (error correction capable)
- **Library**: `tecnickcom/tc-lib-barcode` via Composer
- **Error Correction**: Built-in to ECC 200 standard

#### Data Matrix Content
The system will encode module URLs in Data Matrix barcodes.

**Supporting Information:**
- **URL Format**: `https://quadi.ca/{serial_number}`
- **Example**: `https://quadi.ca/00123456`
- **Validation**: Serial number must be valid 8-digit format

#### Data Matrix Size
The system will render Data Matrix at configurable size.

**Supporting Information:**
- **Default Size**: 3.0mm x 3.0mm
- **Configurable**: Size specified per element in job data
- **Scaling**: Library output scaled to target dimensions
- **Aspect Ratio**: Always 1:1 (square)

#### Data Matrix SVG Output
The system will output Data Matrix as SVG elements.

**Supporting Information:**
- **Element Type**: `<rect>` elements for modules
- **Grouping**: Wrapped in `<g>` element with ID
- **Fill**: `black` for filled modules
- **Positioning**: `transform="translate(x,y)"` on group

### Text Rendering

#### Character Set Support
The system will support a restricted character set for engraving.

**Supporting Information:**
- **Uppercase Letters**: A-Z (26 characters)
- **Lowercase Letters**: a (revision suffix only)
- **Digits**: 0-9 (10 characters)
- **Punctuation**: . - / : (4 characters)
- **Total**: 41 characters

#### LED Code Character Set
The system will validate LED codes against the restricted character set.

**Supporting Information:**
- **Valid Characters**: `1234789CEFHJKLPRT` (17 characters)
- **Code Length**: Exactly 3 characters
- **Validation**: Reject invalid characters with error message
- **Source**: `led_shortcode` product meta field

#### Text Sizing
The system will support configurable text sizes.

**Supporting Information:**
- **Size Unit**: Millimeters
- **Default Sizes**:
  - Module ID: 1.5mm height
  - Serial URL: 1.2mm height
  - LED Code: 1.0mm height
- **Scaling**: Character paths scaled proportionally

#### Text Anchor Positions
The system will support text anchor positioning.

**Supporting Information:**
- **Options**: start, middle, end
- **Default**: start (left-aligned)
- **Calculation**: Adjust X position based on text width and anchor

### SVG Generation

#### SVG Document Structure
The system will generate valid SVG documents with millimeter units.

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

#### Element Grouping
The system will logically group SVG elements by module position.

**Supporting Information:**
- **Module Group**: `<g id="module-{position}">`
- **Element Groups**: Nested groups for micro-id, datamatrix, text
- **Purpose**: Organize output, enable selective editing in Lightburn

#### Element Positioning
The system will position elements using transform attributes.

**Supporting Information:**
- **Method**: `transform="translate(x,y)"` on group elements
- **Coordinate System**: Origin at top-left of array
- **Units**: Millimeters matching viewBox

#### SVG Storage
The system will store generated SVG content in the database.

**Supporting Information:**
- **Storage Location**: `svg_content` column in `qip_engraving_arrays` table
- **Format**: Complete SVG document as TEXT
- **Compression**: None (human-readable for debugging)
- **Export**: Optional filesystem export for Lightburn watched directory

#### SVG Filename Generation
The system will generate descriptive filenames for exported SVG files.

**Supporting Information:**
- **Format**: `{job_id}-{sequence}-{batch_id}.svg`
- **Example**: `42-003-1234.svg` (job 42, array 3, batch 1234)
- **Sanitization**: Remove/replace invalid filesystem characters
- **Uniqueness**: Combination of job_id and sequence ensures uniqueness



## 4. Out of Band Functions
None of the the following needs to be considered as part of the plugin development as they are handled using separate business processes:

1. TBC

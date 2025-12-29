# Quadica Standard Array Engraving- Discovery1

**Last Update:** Dec 28, 2025  
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
- Each module type selected by the operator will need to have its own SVG file generated
- Each QSA accommodates up to 8 LED modules
- The process will generate as many SVG files as need to engrave all of the modules for a specific module type in the batch.
- If there are more than 8 modules in the batch that have the same unique ID e.g., CORE-91247, then the process will only generate one SVG file for the modules as the same file can be used to engrave all of the QSAs.For example, if there are 26 CORE-91247 in the batch, then the process will generate a single SVG file that will be used for 3 QSAs, and then one additional SVG file for a QSA for the remaining 2 LED modules.
- If there are fewer than 8 modules of the same unique ID, then the process will combine different module types onto arrays and create as many SVG files as needed. E.g., if the batch contains the following CORE type modules:

  | Module | Quantity |
  |--------|----------|
  | CORE-91247 | 2 |
  | CORE-38455 | 1 |
  | CORE-98546 | 3 |
  | CORE-23405 | 4 |
  | CORE-45946 | 3 |
  | **Total** | **13** |

  Then it will create two SVG files:

  **SVG 1** (8 modules - full array)
  | Module | Quantity |
  |--------|----------|
  | CORE-91247 | 2 |
  | CORE-38455 | 1 |
  | CORE-98546 | 3 |
  | CORE-23405 | 2 |

  **SVG 2** (5 modules - partial array)
  | Module | Quantity |
  |--------|----------|
  | CORE-23405 | 2 |
  | CORE-45946 | 3 |

### Engraving Queue
After creating an engraving batch, the operator sees an Engraving Queue interface listing all QSAs to be engraved.

#### QSA Grouping
QSAs are grouped into rows based on SVG reusability:

| Group Type | Description | SVG Behavior |
|------------|-------------|--------------|
| **Same ID × Full** | 8 identical modules | One SVG, reused for multiple arrays |
| **Same ID × Partial** | <8 identical modules | One SVG, single array |
| **Mixed ID × Full** | 8 different modules | Unique SVG per array |
| **Mixed ID × Partial** | <8 different modules | Unique SVG, single array |

Each row displays: module count, array count, and an "Engrave" button.

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
1. **SVG 1** — First QSA, positions 5-8 (4 modules)
2. **SVG 2** — Full QSAs, positions 1-8 (reused for 2 arrays = 16 modules)
3. **SVG 3** — Final QSA, positions 1-6 (6 modules)

Total: 4 + 16 + 6 = 26 modules across 4 QSAs

A fully functional React mockup of this webpage is here:
- https://claude.ai/public/artifacts/114216a3-b9e4-4121-8e51-6681388a084a

#### Multi-SVG Workflow

**Constraint:** LightBurn can only load one SVG file at a time. When a row requires multiple unique SVG files (due to starting offset or partial final arrays), the operator must step through each SVG sequentially.

**SVG Breakdown Calculation:**

The system calculates how many unique SVG files are needed for a row based on:
- Total modules in the row
- Starting position (1-8)

| Scenario | SVG Files Generated |
|----------|---------------------|
| Start = 1, modules ≤ 8 | 1 SVG (single array) |
| Start = 1, modules = multiple of 8 | 1 SVG (reused for N arrays) |
| Start = 1, modules not divisible by 8 | 2 SVGs (full + partial) |
| Start > 1 | Up to 3 SVGs (offset + full + partial) |

**Example:** 26 modules, Starting Position = 5

| SVG | Positions | Modules | Arrays | Reusable? |
|-----|-----------|---------|--------|-----------|
| SVG 1 | 5-8 | 4 | 1 | No (offset start) |
| SVG 2 | 1-8 | 8 | 2 | Yes (identical) |
| SVG 3 | 1-6 | 6 | 1 | No (partial end) |

**Total:** 3 unique SVG files, 4 QSAs, 26 modules

#### Engraving Workflow

**Single-SVG Row** (most common case):
1. Operator clicks **Engrave** → System generates SVG and sends to LightBurn
2. Operator engraves all arrays using that SVG (repeating for array count)
3. Operator clicks **Complete** → Row marked done

**Multi-SVG Row** (when starting offset > 1 or partial arrays needed):
1. Operator clicks **Engrave** → System generates first SVG and sends to LightBurn
2. Interface shows: `SVG 1 of N` with positions and array count for current step
3. Operator engraves the array(s) for current SVG
4. Operator clicks **Next SVG** → System generates next SVG and sends to LightBurn
5. Repeat steps 3-4 until all SVG steps complete
6. After final SVG step, operator clicks **Complete** → Row marked done

**Interface Elements During Multi-SVG Processing:**

| Element | Description |
|---------|-------------|
| **SVG Step Indicator** | Shows "SVG X of Y" with progress dots |
| **Positions** | Current SVG's position range (e.g., "5-8") |
| **Modules** | Module count for current SVG step |
| **Arrays** | How many arrays to engrave with current SVG |
| **Next SVG Button** | Advances to next SVG (appears between steps) |
| **Complete Button** | Finalizes row (appears only after last SVG step) |

Operator repeats workflow for all rows in the batch.

#### Error Recovery During Engraving

Things can go wrong during the engraving process. A module may not be positioned correctly, the laser may skip a step, or the operator may notice a quality issue that requires re-engraving. The interface needs to provide controls to handle these situations without starting the entire batch over.

**Why Error Recovery Is Needed:**
- Modules may shift during engraving, causing misalignment
- Laser communication issues may require resending the file
- Quality inspection may reveal a defective engraving
- Operator may accidentally advance before completing an array
- A completed row may need to be re-done due to discovered defects

**Recovery Controls:**

| Control | When Available | What It Does |
|---------|----------------|--------------|
| **Resend** | During engraving (in progress) | Sends the current SVG file to LightBurn again without changing steps. Use when the laser didn't receive the file properly or communication was interrupted. |
| **Back** | During engraving (after first SVG step) | Returns to the previous SVG step and resends that file to LightBurn. Use when the operator needs to re-engrave the previous array. |
| **Rerun** | After row is completed | Resets the entire row back to pending status, allowing the operator to adjust the starting position if needed and begin the row again from the first SVG. Use when a completed row needs to be re-engraved entirely. |

**Important Behaviors:**
- The "Back" button only appears after the first SVG step (cannot go back before the beginning)
- The "Resend" button is always available during an in-progress row
- Using "Rerun" clears the completed status and resets progress, allowing the operator to also change the starting position before re-engraving
- None of these actions affect other rows in the queue

### SVG Generation
The SVG file sent to LightBurn is generated on demand when the operator clicks the Engrave button on the Engraving Queue screen.

Referencing the [QSA design reference](docs/reference/quadica-standard-array.jpg) configuration graphic. The SVG file will contain the following elements for each LED module:
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
- A unique serial number is generated for each LED module to be engraved at the time that the SVG file is created and sent to LightBurn
- Serial numbers are generated using the following rules:
  - **Minimum Value**: 00000001 (1)
  - **Maximum Value**: 01048575 (2^20 - 1)
  - **Total Capacity**: 1,048,575 unique serial numbers
  - **Format**: 8-character zero-padded string
  - **Constraining Source**: Micro-ID 20-bit encoding limit
  - **Sequentially Generated**: Serial numbers are sequentially created
  - **No Duplicates**: Ensure that duplicate serial numbers are never generated
- The system will store the following data for each generated serial number in a database table named `lw_quad_serial_numbers`:
  - **Serial Number**: Zero-padded string (e.g., "00123456")
  - **Module ID**: Associated module identifier (e.g., "STAR-34924")
  - **Batch ID**: Reference to source production batch
  - **Order ID**: Reference to customer order
  - **Array Position**: Position number (1-8) on the QSA
  - **Created Timestamp**: Creation Date/Time

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

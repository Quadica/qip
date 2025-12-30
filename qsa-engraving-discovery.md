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
- **LED Code**: 3-character code identifying each unique LED that we use to build modules. This is also referred to as a `Production Code` or `Shortcode` Note that we have been using 2 digit LED codes, however this new system will start to use 3 digit codes to accomodate additional LEDs.
- **Lightburn**: Laser engraving software that consumes SVG files
- **Micro-ID**: Quadica's proprietary 5x5 dot matrix serial encoding (20-bit capacity)
- **Module ID**: Base ID + revision + Config Code (e.g., "STAR-34924")
- **New-Style Module**: Module with SKU matching pattern: 4 uppercase letters + lowercase revision + hyphen + 5 digits
- **Serial Number**: 8-digit unique identifier for each manufactured module (e.g., "00123456")
- **Order BOM**: This is an existing CPT in WordPress that provides details about LED modules in each order

## 4. Required Functionality
### Module Selection
The process will only process LED modules that use the QSA.
- [QSA design reference](docs/reference/quadica-standard-array.jpg)
-QSA compatible modules can be identified using the first 5 characters of the module SKU which will always be 4 upper case alpha characters followed by a dash. E.g., `CORE-`.
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
| **Available** | Serial returned to pool (was voided or scrapped) |

**State Transitions:**
- Reserved → Engraved (operator confirms successful engraving)
- Reserved → Available (operator uses Retry before engraving, or row cancelled — serial was never physically used)
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

#### QSA Engraving Configuration
Each QSA design (CORE, SOLO, EDGE, STAR, etc.) has different physical layouts, so the coordinates for each engraved element vary by design. This configuration data will be stored in a custom database table.

**Custom Database Table**
- Store engraving coordinates in a dedicated database table
- Each row defines the position of one element type at one module position for one QSA design
- Supports revision-specific coordinates (if COREb has different layout than COREa)
- Can fall back to shared coordinates if revisions use the same layout

**Data to Store Per Element:**

| Field | Description |
|-------|-------------|
| QSA Design | Module type name (e.g., "CORE", "SOLO") |
| Revision | Revision letter (e.g., "a") or NULL for all revisions |
| Position | Module position on QSA (1-8) |
| Element Type | What is being engraved (see below) |
| Origin X/Y | Coordinates in mm from top-left of QSA to the center point of the element |
| Rotation | Degrees of rotation (default 0) |
| Text Height | Height in mm for text elements |

**Element Types:**

| Element | Description |
|---------|-------------|
| micro_id | Micro-ID 5x5 dot matrix |
| datamatrix | ECC 200 barcode |
| module_id | Module ID text (e.g., "CORE-91247") |
| serial_url | Serial URL text (e.g., "quadi.ca/00123456") |
| led_code_1 | First LED code position |
| led_code_2 | Second LED code position (if needed) |
| led_code_x | Additional LED code positions as needed |

**Relationship to WooCommerce Products:**
- QSA designs correspond to WooCommerce product SKUs (COREa, SOLOa, etc.)
- The configuration table uses the design name (CORE, SOLO) which matches the SKU prefix
- This keeps manufacturing configuration separate from product catalog data

**Admin Management:**
- Dedicated admin page under the plugin menu for managing engraving configurations
- Grid-based editor showing all 8 positions × element types for a design
- Copy function to duplicate one design's configuration as a starting point for another

#### Micro-ID Code Generation
- Micro-ID codes are generated using the [Quadica 5x5 Micro ID specification](docs/reference/quadica-micro-id-specs.md)
- The module serial number is encoded into the Micro-ID code
- Coordinates for each position are retrieved from the QSA Engraving Configuration table

#### Datamatrix Generation
- Generated using ECC 200
- The serial number URL for each module is encoded into the Data Matrix barcode
  - **URL Format**: `https://quadi.ca/{serial_number}`
  - **Example**: `https://quadi.ca/00123456`
- **Size**: 14 mm x 6.5 mm (It has been confirmed that the ECC 200 standard does support rectangular formats)
- **Generation Library**: `tecnickcom/tc-lib-barcode` via Composer
- Coordinates for each position are retrieved from the QSA Engraving Configuration table

#### Module ID
- The full Module ID is engraved (e.g., `CORE-39435`)
- This value comes from the assembly_sku field in the oms_batch_items table (The same table used to determine what modules need to be built as described in the Module Selection section)

#### LED Code(s)
- Each LED mounted on the module has a 3-character code engraved on the PCB.
- The number of mounted LEDs for each LED module is provided in the Order BOM CPT for each module in the order

**Data Source:**
1. Query the `order_no` from `oms_batch_items` for the module being engraved
2. Using the Order ID, retrieve the LED SKU(s) and their PCB position numbers from the Order BOM CPT
3. For each LED SKU, retrieve the 3-character LED code from the `led_shortcode_3` field on the LED's WooCommerce product

**Example:**
A module with two LEDs might have:
- Position 1: LED SKU `LXML-PWC2` → LED code `K7P`
- Position 2: LED SKU `LXZ1-4070` → LED code `C4R`

**Engraving Placement:**
- Each LED code is engraved at the coordinates defined in the QSA Engraving Configuration table (`led_code_1`, `led_code_2`, etc.)
- The number of LED codes varies by module type

---
**!!! STOP! CONTENT BELOW THIS POINT IS REFERENCE MATERIAL ONLY. DO NOT RELY ON ANY INFORMATION BELOW THIS POINT !!!**

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
## 4. Out of Band Functions
None of the the following needs to be considered as part of the plugin development as they are handled using separate business processes:

1. TBC

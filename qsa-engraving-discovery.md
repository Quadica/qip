# Quadica Standard Array Engraving- Discovery
 
**Last Update:** Dec 30, 2025
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

### User Permissions
Access to the QSA Engraving functionality is restricted to the following WordPress roles:
- Administrator
- Manager
- Shop Manager

### Error Handling
The system must validate all required data before allowing engraving to proceed. If any of the following conditions are detected, display an error message to the operator so they can resolve the issue:
- Module SKU has no engraving configuration defined
- LED product is missing the `led_shortcode_3` field
- Order BOM CPT is missing LED information for a module
- Any other data validation failure

The operator must fix the underlying data issue before the system will allow engraving to continue.

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
- When the Module Engraving Batch Creator screen is opened it will display a list of modules to be built from currently active production batches.
- The operator will be able to refresh the list of modules by clicking on an refresh icon.
- Needed modules can be determined by querying and comparing the values from the build_qty field with the value in the qty_recieved field in the oms_batch_items table. If the value in the qty_received field is less than the build_qty field, then the difference is what needs to be built and is included in the modules to build list.

**Module Engraving Batch Creator Page Mockup**
- [Functional React mockup page](https://claude.ai/public/artifacts/bc2959bd-0c6d-402b-ba37-2ada39964eda)
- [JSX React Source Code](docs/reference/module-engraving-batch-creator-mockup.jsx)

#### Batch History Access
The Module Engraving Batch Creator screen includes a link to view previously completed engraving batches. This supports re-engraving scenarios (e.g., QA rejects modules due to engraving defects).

**Workflow for Re-engraving:**
1. Operator clicks "View Batch History" link on the Batch Creator screen
2. System displays a list of previously completed engraving batches
3. Operator selects a batch to view its details
4. Batch details are displayed on the Batch Creator screen showing all modules from that batch
5. Operator can select specific modules to re-engrave
6. Selected modules are added to a new engraving batch with new serial numbers

This allows QA-rejected modules to be re-engraved, or additional modules to be added to an existing order, without affecting other modules in the original batch. Original serial numbers remain in "engraved" status (not recycled).

**Engraving Batch History Page Mockup**
- [Functional React mockup page](https://claude.ai/public/artifacts/10900dfa-9d47-4a73-99fd-60960f169cb5)
- [JSX React Source Code](docs/reference/engraving-batch-history-mockup.jsx)

A mockup of the Engraving Batch History page is here:
- https://claude.ai/public/artifacts/10900dfa-9d47-4a73-99fd-60960f169cb5

### SVG Engraving
- The operator selects the modules that are included in the batch using the [Module Selection page](https://claude.ai/public/artifacts/ec02119d-ab5b-44cd-918d-c598b2d2dd94)
- Each QSA accommodates up to 8 LED modules
- Modules are assigned to positions 1-8 based on their serial number order
- **Every QSA requires its own unique SVG file** because each module has a unique serial number embedded in the engraving (Micro-ID, Data Matrix, URL text). Even if the module type is identical across multiple QSAs, the serial numbers are different, so each QSA needs a fresh SVG.

#### Module Sorting for LED Pick-and-Place Optimization

Modules in a batch must be sorted to minimize LED type transitions during manual pick-and-place assembly.

**Context: Manual Pick-and-Place Process**
- Quadica uses manual (human) LED placement, not pick-and-place machines
- Workers can only have ONE LED type open on the bench at a time (LEDs are unmarked due to small size — this prevents mix-ups)
- Worker retrieves an LED type from storage, places it on all applicable modules, then returns it before retrieving the next type
- **Goal:** Minimize the number of LED retrievals/switches per batch

**Sorting Algorithm Requirements:**
1. Group modules by their LED code(s) to minimize LED type transitions
2. Modules with identical LED codes should be adjacent in the batch
3. For multi-LED modules, group those sharing common LEDs together
4. Sequence the groups so that modules with overlapping LED codes are adjacent (reducing transitions)

**Example:**
A batch contains:
- 3 modules with LED code "AF3" only
- 2 modules with LED codes "AF3" + "K7P" (2 LEDs each)
- 4 modules with LED code "K7P" only
- 2 modules with LED code "34T" only

**Optimal sort order:**
1. 3× AF3-only modules
2. 2× AF3+K7P modules
3. 4× K7P-only modules
4. 2× 34T-only modules

**Result:** Worker opens 3 LED types total:
- Open AF3 → place on 5 modules (positions 1-5)
- Open K7P → place on 6 modules (positions 3-8, overlapping with AF3+K7P modules)
- Open 34T → place on 2 modules (positions 9-10)

This is an optimization problem similar to minimizing transitions in a traveling salesman problem. The coding AI should implement an algorithm that minimizes total LED type switches across the batch.
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

**Engraving Queue Page Mockup**
- [Functional React mockup page](https://claude.ai/public/artifacts/8319e841-26b2-4ae9-a7d6-8df243b19cf8)
- [JSX React Source Code](docs/reference/engraving-queue-mockup.jsx)

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
| **Retry** | During engraving | Scraps the current QSA and generates a fresh SVG with new serial numbers. Use when the QSA is ruined and you need a fresh substrate. | Original serials stay reserved, new serials assigned |
| **Back** | After first array | Returns to the previous array. The previous array's serials remain committed; you'll re-engrave on a new QSA with new serials. | Previous stays committed, new serials assigned for re-do |
| **Rerun** | After row complete | Resets selected arrays (or entire row) back to pending. Allows adjusting starting position. | Original serials stay engraved, new serials assigned |

**Resend vs Retry — Key Distinction:**
- **Resend** = Same QSA, same serials, just re-transmit the file (communication issue)
- **Retry** = New QSA, new serials, the old QSA is scrapped (physical failure)

**Important Behaviors:**
- The "Back" button only appears after the first array
- The "Resend" and "Retry" buttons are available during an in-progress row
- Using "Rerun" allows selecting which arrays to redo, or redoing the entire row
- None of these actions affect other rows in the queue
- Serial numbers are never recycled — new serials are always assigned for re-engraving

#### LightBurn Integration
The system communicates with LightBurn to load SVG files for engraving. All technical details for LightBurn integration (UDP commands, file loading, batch processing workflow) are documented in the `lightburn-svg` skill. Refer to that skill for implementation specifics.

### SVG Generation
The SVG file sent to LightBurn is generated on demand when the operator clicks the Engrave button on the Engraving Queue screen. SVG files are ephemeral — they are generated immediately before sending to LightBurn and deleted after use.

**SVG Canvas Specifications:**
- **Dimensions**: 148mm × 113.7mm (matches physical QSA size)
- **ViewBox**: `viewBox="0 0 148 113.7"`
- **Coordinate Origin**: Top-left (standard SVG)
- **Source Coordinates**: Bottom-left (CAD format) — transformation required: `svg_y = 113.7 - cad_y`

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
  - **Sequentially Generated**: Serial numbers are sequentially assigned in order
  - **No Recycling**: Serial numbers are never returned to the pool — once assigned, they remain used
  - **Capacity Note**: At current production volumes, the 1M+ serial capacity provides 10+ years of runway

#### Serial Number Lifecycle
Serial numbers move through the following states:

| Status | Meaning |
|--------|---------|
| **Reserved** | Serial allocated and embedded in SVG, engraving not yet confirmed |
| **Engraved** | Physically engraved on a module, confirmed by operator |
| **Voided** | Serial was reserved but never physically engraved (e.g., Retry used, row cancelled) |

**State Transitions:**
- Reserved → Engraved (operator confirms successful engraving)
- Reserved → Voided (operator uses Retry before engraving, or row cancelled)

**Note:** There is no recycling of serial numbers. Voided serials remain in "voided" status for audit purposes. New modules always receive new serial numbers.

#### Serial Number Assignment
Serial numbers are assigned using a "reserve then commit" approach:
1. **When operator clicks Engrave:** System pre-generates all SVGs for the row. Serial numbers are reserved and embedded in the SVG files.
2. **When operator presses Spacebar/Next Array:** The current array's serials are committed (Reserved → Engraved), and the next SVG is loaded.
3. **When operator clicks Complete:** The final array's serials are committed. The row is done.
4. **If operator clicks Retry:** Current array's reserved serials are marked as voided, new serials are assigned, and a new SVG is created.
5. **If operator clicks Rerun (after complete):** Original serials remain as engraved (not recycled), new serials are assigned for the re-engraved modules.

#### Serial Number Data Storage
The system will store serial number data in a database table named `lw_quad_serial_numbers`:
  - **Serial Number**: Zero-padded string (e.g., "00123456")
  - **Module ID**: Associated module identifier (e.g., "STAR-34924")
  - **Batch ID**: Reference to source production batch
  - **Order ID**: Reference to customer order
  - **QSA ID**: The array that the LED module is part of
  - **Array Position**: Position number (1-8) on the QSA
  - **Status**: Current lifecycle state (reserved, engraved, voided)
  - **Reserved Timestamp**: When serial was reserved (nullable)
  - **Engraved Timestamp**: When engraving was confirmed (nullable)
  - **Voided Timestamp**: When serial was voided (nullable)

#### Engraving Batch Tracking
The system needs to track which modules have been engraved to prevent duplicate engraving and support batch history.

**Engraving Batch Table** (`lw_quad_engraving_batches`):
  - **Batch ID**: Auto-increment primary key
  - **Created At**: Timestamp when batch was created
  - **Created By**: User ID who created the batch
  - **Status**: Batch status (in_progress, completed)
  - **Completed At**: Timestamp when batch was completed (nullable)

**Engraved Modules Table** (`lw_quad_engraved_modules`):
  - **ID**: Auto-increment primary key
  - **Engraving Batch ID**: Reference to the engraving batch
  - **Production Batch ID**: Reference to `oms_batch_items.batch_id`
  - **Module SKU**: The assembly_sku from oms_batch_items
  - **Order ID**: Reference to customer order
  - **Serial Number**: The assigned serial number
  - **QSA Sequence**: Which QSA in the batch (1, 2, 3...)
  - **Array Position**: Position 1-8 on the QSA
  - **Row Status**: Status of the engraving row (pending, done)
  - **Engraved At**: Timestamp when row marked done (nullable)

**Integration with Module Selection:**
When determining modules that need to be built, the system must check the engraved modules table:
- A module from `oms_batch_items` should NOT appear in the "Modules Awaiting Engraving" list if it already exists in `lw_quad_engraved_modules` with `row_status = 'done'`
- This prevents duplicate engraving of the same module

**Batch Completion:**
A batch is considered complete when all rows have been marked "done". There is no explicit batch cancellation — incomplete rows simply remain in "pending" status.

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
| Origin X/Y | Coordinates are in mm from the bottom-left of the QSA to the center point of the element |
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
- Current module designs support up to 9 LED positions; future designs may require more

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

#### Sample Data and Reference SVG

Sample files are available for development and testing:

**Sample Coordinate Data:**
- [stara-qsa-sample-svg-data.csv](docs/sample-data/stara-qsa-sample-svg-data.csv) - Complete coordinate and engraving data for a STARa QSA with 8 modules
- Includes X/Y positions for all element types (micro_id, datamatrix, module_id, serial_url, led_code_1)
- Coordinates use bottom-left origin (CAD format); convert to SVG with: `svg_y = 113.7 - csv_y`
- Contains metadata header documenting rendering parameters (text heights, font, sizes)

**Sample SVG Output:**
- [stara-qsa-sample.svg](docs/sample-data/stara-qsa-sample.svg) - Generated SVG showing all 8 module positions
- Demonstrates correct element positioning and coordinate transformation
- Includes working Micro-ID dot patterns encoded from sample serial numbers
- Data Matrix shown as placeholder rectangles (14mm x 6.5mm) - actual barcodes require library generation
- Text rendered using Roboto Thin font with hair-space character spacing

**Key Rendering Parameters (from sample data):**

| Element | Size/Height | Notes |
|---------|-------------|-------|
| micro_id | 1.0mm x 1.0mm | 0.10mm dots, 0.225mm pitch |
| datamatrix | 14mm x 6.5mm | ECC 200 rectangular format |
| module_id | 1.5mm text height | Roboto Thin, text-anchor middle |
| serial_url | 1.2mm text height | Roboto Thin, text-anchor middle |
| led_code_1 | 1.0mm text height | Roboto Thin, text-anchor middle |

## 4. Out of Band Functions
None of the the following needs to be considered as part of the plugin development as they are handled using separate business processes:

1. None

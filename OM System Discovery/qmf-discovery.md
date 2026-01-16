# Quadica Manufacturing (QMF) - Discovery

**Last Update:** Nov 30, 2025  
**Author:** Claude Code + Ron Warris  
**Status:** Initial Discovery & Exploration  

**Important Information About This Document**
- This is a **discovery** document. NOT a PRD or planning document
- The primary purpose of this document is the start to compile information about **what** we need to build, not **how** we are going to build it
- Its intent is to explore ideas and capture information that will be used to generate a formal Product Requirements Document
- Information in this document is unstructured and free form
- This is not a technical document. It is a document that managers and users can review and provide feedback on.

**Overview**
This document captures the exploration and discovery process for modernizing the LED module production system. It documents the thinking process, questions asked, decisions made, and the emerging architecture for the Quadica Manufacturing (QMF) system that will provide full integration into WooCommerce, continuous visibility into the entire production pipeline, production batch generation, production documentation, etc.

This system will include updated functionality for the following existing OM production related processes (Not an exhaustive list):
- prod-batch-list.php - Batch List
- prod-generate.php - Generated Production Batch and all related functions
- prod-batch.php - Production Batch Details
- prod-report.php - Production Batch Report
- prod-batch-receive.php - Receiving Production Batch Items
- prod-batch-labels.php - Module Label Printing
- The Production CSV File generation process
- prod-binning-report.php - Binning Report
- report-order-status.php - Order Status Report

<div class="page"/>

## Definitions
- **SKU**: The part number assigned to a product or a component
- **LED**: A single Light Emitting Diode used to build LED modules
- **LED Module**: A self-contained lighting unit that includes one or more light-emitting diodes (LEDs) along with the necessary supporting components
- **Base** (Also called a Substrate or PCB): The component that LEDs and other supporting components are mounted to.
- **Base Type** (Also called a Base Design): The unique classification of a base design identified by its SKU (e.g., ATOMa, NORDa). Each base type represents a unique combination of PCB geometry and LED footprint pattern. Base type determines solder mask compatibility - modules with different base types cannot be produced together without equipment changeover.
- **Footprint**: The solder pad geometry of an LED or other component reflow soldered to the base
- **Footprint Pattern**: The specific arrangement and dimensions of solder pads on a base that determines which LED SKUs are physically compatible for placement. LEDs with the same footprint pattern can be interchanged on the same base type without changing the solder mask. Different base types may share the same footprint pattern, but still require different masks due to geometry differences.
- **Reflow**: The process of soldering component parts to a base
- **Solder Paste Mask**: A screening template used to apply solder paste to a base for reflow soldering. Each base type requires its own unique mask. Changing masks during production creates significant time delays and equipment changeover overhead.
- **Tray**: The container used to hold completed LED modules that are ready to be shipped for a specific order
- **Binned LED**: An LED that has been further classified and assigned a bin code by the manufacturer. The bin code would typically define Color (Chromaticity), Flux (Brightness / Luminous Output) and Voltage. Binned LEDs are identified by the manufacturers part number followed by the bin code. E.g., L1C2-DRD1000000000-C20F, where L1C2-DRD1000000000 is the manufacturers part number and C20F is the bin code
- **Component**: Parts that are used to build an LED module. These typically includes:
  - LEDs
  - Base
  - 0R Resistors
  - Connectors
- **Production Batch**: A collection of LED modules grouped together for manufacturing. All modules in a batch are built during the same production run using the same equipment setup.
- **Soft Reservation**: A preliminary allocation of components to an order that can be reallocated to higher-priority orders if needed. Components are soft-reserved when an order enters the production queue but before a batch is created.
- **Hard Lock**: A firm allocation of components to an active production batch that cannot be reallocated. Components become hard-locked when a PM creates a batch and production begins.
- **Array** (Productions slang term is "Fret"): A single printed circuit board (PCB) panel containing multiple identical bases that are v-scored for separation. Also called a "panel" in standard PCB terminology. Bases are delivered from the PCB manufacturer in arrays and used during production. Each base type has its own specific array configuration.
- **Array Frame** The outside permitter of the array that the LED modules are connected to.
- **Array Size (X-up)**: The number of individual bases contained in a single array. For example, "15-up" means one array contains 15 bases. Array size varies by base type (e.g., ATOMa = 15-up array, NORDa = 8-up array).
- **Complete Array**: An array where all bases are built and separated during production. Using complete arrays optimizes manufacturing efficiency by minimizing waste and simplifying production workflow.
- **Partial Array**: An array where only some bases are built, leaving unused bases that must be discarded or saved for future use. Partial arrays are acceptable when necessary to meet high-priority orders or when insufficient modules exist to complete the array.
- **PM** (Production Manager): The person currently managing the process of generating production batches.
- **FoldPack**: ESD safe folding cardboard package that the LED array is fastened to for protection during shipping and customer warehousing
- **Serial Number**: Numeric value used to identify each LED module that we manufacture

## Base Designs
- We currently offer 30+ different base designs (types) for customers to choose from
- We are regularly adding new base designs to our catalog
- Each base design has its own unique geometry. E.g., 20mm Stars (hex), 10mm Squares, 20m Quads, etc.
- A single base will contain one or more LEDs mounted to the base
- Each base will be assigned its own unique SKU to identify the base. The SKU will typically consist of a 4 letter word followed by a revision letter. E.g., ATOMa, NORDa, etc.
- Bases with multiple LEDs can be configured to be powered in series with optional 0R resistors that are added during production
- LED bases with multiple LEDs can contain different types of LEDs. E.g., different colors, manufacturers, footprints, etc.
- The customer selects what LEDs are mounted to the base when they place their order
- The Base SKU and details about LEDs and other components (e.g., 0R resistors) mounted to the base are saved to our BOM system for each order

<div class="page"/>

## The Quadica Standard Array
- Our standard LED module array design used for all module types
- This standardized array simplifies the production and handling process and lends itself to future automation

### Standard Array Physical Specifications
- **Standard dimensions**: 148 mm × 113.7 mm must be maintained for production compatibility 
- **Fiducial markers**
  - 3X Array Fiducials in the corners for array alignment
  - 2X Module Fiducials per module for pick-and-place positioning
- **Array Fiducial geometry**
  - Positioned in the top left, top right and bottom right corners of the array
  - Positioned 5 mm from the edges of the array
  - Target is a 1 mm copper circle inside a 2 mm circular mask opening
- **Module Fiducial geometry**: As needed by each specific Base ID design
- **Module capacity**: Varies by base design

### Standard Array Identification Codes
- Reference the [Quadica Standard Array Configuration drawing](../reference/quadica-standard-array.jpg) for markings and  locations

- Each LED module has identification codes assigned at two levels:
  1. **Product Level** - Identifies the module design (base type + LED configuration)
  2. **Unit Level** - Identifies each individual manufactured module for traceability

<div class="page"/>

#### Product-Level Identifiers

**Base ID**
- A 4-character dictionary word assigned to each base design
- Each base design accommodates specific LED footprints and customer requirements
- Examples:
  - **STAR** - 20 mm Star base for 1 Rebel LED
  - **NORD** - 20 mm Tri-Star base for Stingray customer
  - **APEX** - 10 mm Square base for 1 Rebel LED
  - **BAND** - 5 mm Square base for 1 Cree XLamp XQ-E LED
  - **CORE** - 20 mm Star base for 1 Cree XLamp XP-G4 LED
  - **QUAD** - 20 mm Square base for 4 LUXEON C-ES LEDs

**Base ID Version Code**
- A single alpha character that represents the current revision of the base
- First version of the base is always assigned `a`
- Subsequent versions are assigned alphabetically
- A new version code is assigned each time the base design is modified to correct an error or for minor adjustments
- The version code is always displayed in lower case
- Always immediately follows the Base ID with no space or dash

**Config Code**
- A 5-digit numeric value identifying a specific LED configuration on a base
- The same Config Code always represents the same combination of:
    - **Specific LED part numbers** (including color/CCT variants)
    - Quantity of each LED
    - Position of each LED on the base
- Different LED colors or CCT values on the same base require different Config Codes
- Config Codes are assigned when a new configuration is first created, either:
  - Automatically by the LED Module Builder app when a customer designs a new module
  - Manually when creating special modules for specific customers
- Config Codes may optionally carry meaning (e.g., customer reference, project code) or be randomly assigned
- Once assigned, a Config Code is permanent and never reused, even if the configuration is discontinued
- The assignment process verifies the code is not already in use before confirming
- Examples:
  - **34924** - Single XPGEWT-01-0000-00000BQDT 7000K Cree XLamp XP-G4 LED
  - **17051** - Three L1C2-RYL1000000000 Royal Blue Lumileds C-ES LEDs (Stingray tri-star)
  - **17052** - Three L1C2-GRN1000000000 Green Lumileds C-ES LEDs (Stingray tri-star)
  - **29654** - Single LXML-PR02-A900 Royal Blue Lumileds C-ES LED
  - **20035** - Single LXML-PWC2 White Lumileds Rebel LED
  - **39856** - 4 LEDs mounted in the following positions:
    1. L1C2-DRD1000000000 Red Lumileds C-ES LED
    2. L1C2-LME1000000000 Lime Lumileds C-ES LED
    3. L1C2-DRD1000000000 Red Lumileds C-ES LED
    4. L1C2-BLU1000000000 Blue Lumileds C-ES LED

**Module ID**
- The combination of Base ID and Config Code that uniquely identifies a module design
- Format: `BASE-#####` (4-character Base ID + hyphen + 5-digit Config Code)
- The Module ID is the product SKU used for ordering and inventory
- Examples:
  - **CORE-34924** - 20 mm Star base with single 7000K XP-G4 LED
  - **STAR-29654** - 20 mm Star base with single Royal Blue C-ES LED
  - **NORD-17051** - 20 mm Tri-Star base with three Royal Blue C-ES LEDs
  - **NORD-17052** - 20 mm Tri-Star base with three Green C-ES LEDs
  - **APEX-20035** - 10 mm Square base with single White Rebel LED
  - **BAND-44930** - 5 mm Square base with single PC Lime XQ-E LED
  - **QUAD-39856** - 20 mm Square base with 4 LUXEON C-ES LEDs

**Standard Array ID**
- Each standard array will only contain a single Base ID type. E.g, all STAR, or all NORD
- Each standard array is assigned the 4 character Base ID followed by the version letter. E.g., `STARa`
- Each revision of the Base ID will be assigned a version letter with the first version assigned `a`
- See "Base ID Version Code" above for version assignment rules
- Subsequent revisions will be assigned the next unused letter. E.g., `b`, `c`, etc.
- The Array ID and revision letter will be engraved on the bottom rail of the array

<div class="page"/>

#### Unit-Level Identifier

**Module Serial Number**
- An 8-digit numeric value assigned to every individual LED module we manufacture
- Provides full traceability of each physical unit throughout its lifetime
- Numeric-only format required due to the extreme space constraints of the [Quadica 5x5 Micro-ID code](../reference/quadica-micro-id-specs.md) engraved directly on each module
- Range: 00000001 to 01048575 (~1 million unique values)
- The range is constrained by the 20-bit capacity of the Quadica 5x5 Micro-ID encoding
- Generation rules:
  - Values are always padded with zeros so the code is always 8 digits long
  - Assigned when the production batch is created and confirmed
  - Permanently stored in the WordPress database
  - Never reused, even if a module is scrapped during production
  - Abandoned codes are simply marked as unused in the database
- The Serial Number links to complete module information:
  - Module ID (base type and LED configuration)
  - Production batch ID and build date
  - Order number
  - LED positions and orientations
  - Any other production or quality data

**Serial Number Markings**
- **Micro-ID code on the module**: Encoded into Quadica's proprietary 5x5 Micro-ID code (1mm² footprint)
- **URL on the carrier tab**: A URL linking to the module's information page (e.g., `https://quadi.ca/00123456`)
- **Data Matrix on the carrier tab**: URL linking to the module's information page encoded into a Rectangular Data Matrix (ECC 200) for scanning

<div class="page"/>

#### LED Production Code

**Purpose**
- A 3-character code assigned to each LED type in our catalog
- Used to minimize engraved text width when marking LED identification on modules

**Format**
- Generated from a restricted character set: `1234789CEFHJKLPRT` (17 characters)
- Characters selected to:
  - Minimize engraved width
  - Avoid visually similar characters (no O/0, I/1, S/5, etc.)
- Provides 4,913 possible codes (17³)

**Assignment Rules**
- Assigned when an LED is first added to our catalog
- Permanently assigned and never reused, even after LED discontinuation
- Stored in the LED database alongside the manufacturer part number

#### Identifier Summary

| Identifier | Format | Scope | Example | Purpose |
|------------|--------|-------|---------|---------|
| Base ID | 4 letters | Per base design | `STAR` | Identifies PCB geometry |
| Config Code | 5 digits | Per LED configuration | `34924` | Identifies LED arrangement |
| Module ID | BASE-##### | Per product design | `STAR-34924` | Product SKU for ordering |
| Module Serial Number | 8 digits | Per manufactured unit | `00123456` | Individual unit traceability |
| LED Code | 3 chars | Per LED type | `K7P` | Compact LED identification |

<div class="page"/>

### Standard Array Packaging Markings
Markings to be printed or engraved on the following items during the production & shipping process

**Outside Of The Sealed ESD Package**
- Thermally printed 4" x 6" self adhesive label with the following markings/information:
  - Our company and product branding marks
  - Module ID. E.g., `STAR-34924`
  - Full part description E.g.,
    ```
    20 mm Square LED Module with 4 LUXEON C-ES LEDs in the following positions:

      - Position 1: L1C2-DRD1000000000 Red Lumileds C-ES LED
      - Position 2: L1C2-LME1000000000 Lime Lumileds C-ES LED
      - Position 3: L1C2-DRD1000000000 Red Lumileds C-ES LED
      - Position 4: L1C2-BLU1000000000 Blue Lumileds C-ES LED
    ```
  - Quantity of LED modules in the package
  - Our Order ID
  - Customer PO
  - Package Date
  - Standard Code 128 bar code encoded with the Module ID
  - QR code encoded with the Module Serial Number linked to a webpage with complete details about the module. E.g., `QUADI.ca/00123456`
  - **Made In Canada** statement

**Foldpack External Label**
- This is a pre-printed, generic self adhesive label that includes:
  - All standard branding marks
  - Made In Canada statement
  - Contact details
  - General application information
- No additional information is added to this label during shipping

**Foldpack Internal Label**
- This is the exact same label as the one used on the outside of the ESD packaging
- Included in case the Foldpack is separated from the ESD packaging

**Standard Array Frame**
- Branding marks
- Base ID with version letter of the base. E.g., `STARa`

<div class="page"/>

**LED Module Carrier Tab**
- **LED Code(s)**: 3 digit production code engraved in the same relative positions as the module LED(s)
- **Module ID**: Base ID + Config code. E.g., `STAR-34924`
- **Module Serial Number URL**: URL that links to a web page that contains information for that specific LED module. E.g., `QUADI.ca/23546764`
- **Module Serial Number Data Matrix**: Scannable bar code encoded with an URL that links to a web page that contains information for that specific LED module. E.g., `QUADI.ca/23546764`

**LED Module**
- **Our module domain**: The domain that contains information for every LED module we produce. E.g., `QUADI.ca`
- **Base ID**: Base name. E.g., `STAR`
- **Module Serial Number Micro-ID code**: Quadica 5x5 Micro-ID code that contains the modules Serial Number. A magnified image of this code can be provided to a web page app that will decode the Micro-ID code and direct the user to the web page that contains details about that specific LED module.

<div class="page"/>

## Module-Focused Production Batch Creation Rules

**Architecture:** Module-focused cross-order batching

**Core Principle:**
Production batches are optimized for manufacturing efficiency by grouping modules of the **same base type** across multiple orders, while respecting order priority and completion preferences.

---

### Category 1: Batch Composition Rules

#### Rule 1: Single Base Type Per Batch
**Statement:** A production batch shall contain modules of only one base type (base SKU).

**Details:**
- All modules in a batch must share the same base SKU (e.g., ATOMa, NORDa, POLYa)
- Modules from multiple customer orders may be included in the same batch
- No mixing of base types within a single batch under any circumstances
- Base type is defined by the base SKU, which represents a unique geometry + footprint pattern combination

**Example:**
```
Valid Batch 47:
  - 25× ATOMa modules from Order 12345
  - 50× ATOMa modules from Order 12346
  - 10× ATOMa modules from Order 12347
  Total: 85 ATOMa modules

Invalid Batch (would be rejected):
  - 25× ATOMa modules from Order 12345
  - 30× NORDa modules from Order 12346
  Reason: Cannot mix ATOMa and NORDa base types
```

**Rationale:**
Each base SKU requires a specific solder paste mask. Mixing base types would require equipment changeover (mask replacement) during production, creating significant time delays and inefficiency. Maintaining single-base-type batches enables continuous production flow without interruption.

**System Impact:**
- QMF filters eligible modules by base type when creating batches
- Batch creation UI groups modules by base type for PM selection
- System prevents adding modules with different base types to existing batches

**PM Actions:**
- PM selects which base type to batch next based on priority and capacity
- PM cannot override this rule - system enforces single base type per batch

---

#### Rule 2: LED Variation Allowed Within Base Type
**Statement:** A batch may contain modules with different LED SKUs, colors, manufacturers, or bin codes, provided all LEDs are compatible with the base footprint pattern.

**Details:**
- LED color variations are permitted (e.g., white, red, blue in same batch)
- LED manufacturer variations are permitted (e.g., Cree, Nichia, Osram in same batch)
- LED bin code variations are permitted
- All LEDs must be physically compatible with the base footprint pattern
- LEDs are identified and picked individually during production, so variation does not impact manufacturing process

**Example:**
```
Valid Batch 48 (ATOMa base type):
  - Order 12345: 20× ATOMa with LED SKU L1C2-DRD1000000000 (Cree, Red)
  - Order 12346: 30× ATOMa with LED SKU XHP35B-00-0000-0DPUD240H (Cree, White)
  - Order 12347: 15× ATOMa with LED SKU NVSW719AT (Nichia, White, different bin)
  All LEDs use footprint pattern compatible with ATOMa base
  Total: 65 ATOMa modules with 3 different LED types
```

**Rationale:**
LED placement is a manual pick-and-place operation during reflow. The specific LED (color, manufacturer, bin) does not affect the solder mask or equipment setup. Production staff simply pick the correct LED for each module according to the batch production sheet. This flexibility maximizes batch size and manufacturing efficiency.

**System Impact:**
- QMF groups modules by base type only, not by LED SKU
- Batch production sheets list LED SKU for each module position
- Component reservation system tracks LED quantities by SKU within batch

**PM Actions:**
- PM does not need to consider LED variations when creating batches
- PM reviews batch production sheet to ensure LED stock availability

---

#### Rule 3: Cross-Order Batching Enabled
**Statement:** A single production batch may contain modules from different customer orders, provided they share the same base type and meet priority/availability criteria.

**Details:**
- No limit on number of orders that can contribute modules to a single batch
- Each module retains its source order association for tracking and fulfillment
- Batch naming convention: "Batch [Number]" (e.g., "Batch 47") not tied to specific order
- Order completion is tracked independently - an order is complete when ALL its modules across all batches are built

**Example:**
```
Batch 49 (NORDa base type):
  Source Orders:
    - Order 12345 (Priority: High): 100 modules
    - Order 12346 (Priority: High): 50 modules
    - Order 12347 (Priority: Medium): 25 modules
    - Order 12348 (Priority: Medium): 30 modules
  Total: 205 NORDa modules from 4 different orders

Tracking:
  - Order 12345 completes when all 100 modules built (may span multiple batches)
  - Order 12346 completes when all 50 modules built
  - Etc.
```

**Rationale:**
Cross-order batching maximizes manufacturing efficiency by creating larger batches of the same base type, reducing total number of tool changes during production. This approach optimizes production throughput while maintaining order priority through strategic module selection (see Rule 6).

**System Impact:**
- Batch data structure stores array of {order_id, module_count, module_details} entries
- Order completion requires aggregating module counts across all batches
- Tray/storage system must support multi-order batch organization (see Rule 20)

**PM Actions:**
- PM sees which orders contribute to each batch in batch creation UI
- PM can include/exclude specific orders from batch composition
- PM reviews order completion impact before finalizing batch

---

#### Rule 4: Batch Size Guidelines & Array Optimization
**Statement:** Production batches have no enforced minimum or maximum size constraints, but PM should follow operational best practices for batch sizing and array optimization.

**Details:**
- **No hard limits:** System allows batches from 1 module to unlimited modules
- **Recommended minimum:** Configurable by PM (default: 10-20 modules per batch to reduce batch overhead)
- **Recommended maximum:** Configurable by PM (default: 150 modules for single-day build capacity)
- **Array optimization:** Batch sizes should ideally use complete arrays when possible
- **Very large orders:** Consider splitting into multiple batches (see Rule 14)
- **Very small urgent orders:** Single-module batches are acceptable for rush/expedite situations

**Array Optimization Principles:**
- Each base type is delivered in arrays (panels) with specific array sizes
- **Complete arrays are preferred** when possible to minimize waste and simplify production
- **Partial arrays are acceptable** when necessary for high-priority orders or when insufficient modules exist
- When filling batch capacity, prefer complete-array increments for lower-priority orders (see Rule 7)

**Array Size Examples:**
```
Base Type: ATOMa → 15-up array (15 bases per array)
Base Type: NORDa → 8-up array (8 bases per array)
Base Type: POLYa → 12-up array (12 bases per array)
```

**Batch Sizing with Array Considerations:**
```
Example 1: High-Priority Order with Partial Array (ACCEPTABLE)
  Order 12345 (High Priority, Due Tomorrow): 145× ATOMa modules
  ATOMa Array Size: 15-up
  Arrays Required: 9 complete arrays (135 modules) + 1 partial array (10 modules)
  Decision: Create batch with all 145 modules
  Rationale: High priority requires full order build, partial array is acceptable

Example 2: Mixed Priority with Array Optimization (PREFERRED)
  Order 12346 (High Priority): 48× NORDa modules
  Order 12347 (Medium Priority): 24× NORDa modules
  Order 12348 (Low Priority, Waiting on non-LED items): 11× NORDa modules
  Total Available: 83 NORDa modules
  NORDa Array Size: 8-up

  Option A: Build all 83 modules
    → 10 complete arrays (80 modules) + 1 partial array (3 modules)
    → Partial array waste

  Option B: Build 80 modules (array-optimized)
    → Include: Order 12346 (48) + Order 12347 (24) + 8 from Order 12348
    → Result: 10 complete arrays (80 modules), no partial array
    → Remaining 3 modules from Order 12348 build in future batch
    → Order 12348 is low priority and waiting for non-LED items anyway

  Decision: Choose Option B (array-optimized)
  Rationale: High/medium priority orders complete fully, low-priority order
            partially built but not urgent, complete array usage achieved
```

**Best Practices:**
```
Optimal Batch Sizes:
  Small Batch: 10-30 modules (1-2 hour build time)
    → Prefer multiples of array size when possible
    → Example: 30 ATOMa (2 complete 15-up arrays)

  Medium Batch: 30-100 modules (half-day build time)
    → Target complete array counts
    → Example: 96 NORDa (12 complete 8-up arrays)

  Large Batch: 100-200 modules (full-day build time)
    → Balance array optimization with capacity limits
    → Example: 180 ATOMa (12 complete 15-up arrays)

Avoid:
  Tiny Batches: 1-5 modules (unless urgent/expedite)
  Massive Batches: 300+ modules (exceeds single-day production capacity)
  Unnecessary Partial Arrays: When lower-priority modules can be deferred
```

**When Partial Arrays Are Acceptable:**
- High-priority or critical orders require specific quantity
- Order promised date at risk if modules deferred to next batch
- No lower-priority modules available to defer for array optimization
- PM makes strategic decision that order completion outweighs array optimization

**When to Optimize for Complete Arrays:**
- Lower-priority modules can be deferred without impacting promised dates
- Batch contains mix of priorities and capacity allows flexibility
- Small quantity reduction (1-7 modules) achieves complete array usage
- Non-urgent orders or orders waiting for other components

**Rationale:**
Batch size affects production scheduling and build time estimation. Array optimization reduces waste and simplifies production workflow by using complete panels. However, array optimization is secondary to order priority - high-priority orders are built even if requiring partial arrays. Production will use as many trays as needed to hold completed modules, so tray count is not a constraint on batch size.

**System Impact:**
- QMF displays array size for selected base type
- QMF calculates complete vs. partial array usage for batch
- System shows array optimization suggestions (e.g., "Reduce by 3 modules for 10 complete arrays")
- QMF displays: "Using 9 complete arrays + 1 partial array (10/15 modules)" or "Using 10 complete arrays"
- QMF displays estimated build time based on module count

**PM Actions:**
- PM reviews batch size and array optimization during creation
- PM decides whether to defer lower-priority modules for array optimization
- PM can split large module groups into multiple batches manually
- PM overrides array optimization for time-critical orders
- PM considers array efficiency alongside priority and production capacity

---

### Category 2: Module Selection & Priority Rules

#### Rule 5: Order Priority Calculation
**Statement:** Order priority is calculated using a hierarchical scoring system that determines the sequence in which modules are selected for batching.

**Details:**
Priority factors are evaluated in hierarchical order (higher factors have greater weight in priority calculation):

1. **PM Manual Expedite** (Highest Priority)
   - WooCommerce ACF field: `order_expedite` (integer value)
   - PM sets numeric value to elevate order importance
   - Higher number = more important
   - Semi-permanent (can be adjusted anytime by PM)
   - Used for VIP customers, urgent business needs, strategic priorities, customer escalations

2. **Customer Paid Expedite**
   - WooCommerce ACF field: `order_paid_expedite` (dollar amount: $0, $50, $100, $200)
   - Customer paid a fee for faster order processing
   - Higher fee = higher priority
   - Reflects customer willingness to pay for expedited fulfillment
   - Revenue-generating priority factor

3. **Days Past Promised Date**
   - Automated priority boost for orders past their promised delivery date
   - Each day past due increases priority score
   - Prevents late orders from being overlooked
   - High-weight factor in priority calculation

4. **Almost Due Boost**
   - Automated priority boost for orders within 2 days of promised date
   - Prevents orders from becoming past due
   - Proactive late-order prevention

5. **Order Age** (Lowest Priority Factor)
   - Older orders receive slight priority over newer orders (FIFO principle)
   - Prevents order starvation
   - Fairness factor for similar-priority orders

**Example Priority Scenarios:**
```
Order 12345:
  - PM Expedite: 5 (PM set for VIP customer)
  - Paid Expedite: $100
  - Promised Date: Tomorrow (1 day until due)
  - Age: 10 days
  - Result: HIGH PRIORITY (PM expedite + paid expedite + almost due)

Order 12346:
  - PM Expedite: 10 (PM set to "Critical" for CEO escalation)
  - Paid Expedite: $0
  - Promised Date: Next week (7 days until due)
  - Age: 5 days
  - Result: HIGHEST PRIORITY (PM expedite value 10 overrides all others)

Order 12347:
  - PM Expedite: 0 (no PM override)
  - Paid Expedite: $0
  - Promised Date: 3 days ago (past due)
  - Age: 20 days
  - Result: HIGH PRIORITY (past due factor dominates)

Order 12348:
  - PM Expedite: 3 (moderate PM expedite)
  - Paid Expedite: $200 (customer paid highest expedite fee)
  - Promised Date: Next week
  - Age: 2 days
  - Result: HIGH PRIORITY (combined PM + paid expedite)
```

**Priority Calculation Notes:**
- Exact mathematical formula will be defined in PRD
- System weights PM expedite and paid expedite factors heavily
- Past-due orders automatically elevated to prevent customer service issues
- Multiple factors combine to create final priority score
- PM can see calculated priority score for each order in QMF dashboard

**Rationale:**
Hierarchical priority ensures business-critical decisions (PM expedites, paid expedites) take precedence over automated factors, while automated factors (past due, almost due, age) prevent orders from becoming late or forgotten. System balances:
- **Strategic priorities** (PM manual expedite)
- **Revenue** (customer paid expedites)
- **Customer satisfaction** (prevent late orders)
- **Operational fairness** (FIFO for similar-priority orders)

**System Impact:**
- Priority scores recalculated daily (or when order details change)
- QMF dashboard displays orders sorted by priority score (highest first)
- Component reservation follows priority sequence (see Rule 9)
- PM can filter/sort orders by priority factors (e.g., "show all PM expedite > 5")

**PM Actions:**
- PM sets `order_expedite` value in WooCommerce order details when needed
- PM reviews priority scores before creating batches
- PM can adjust `order_expedite` value anytime to change order priority
- System shows "Priority changed by PM on [date]" in order history

---

#### Rule 6: Module Selection Sequence (Complete High-Priority Orders First, Array Optimization Secondary)
**Statement:** When creating a batch for a specific base type, the system shall prioritize completing high-priority orders fully before including modules from lower-priority orders, with array optimization applied to lower-priority modules when possible.

**Details:**
Selection algorithm for Batch creation:

**Step 1:** Sort all orders needing the base type by priority score (highest first)

**Step 2:** For each high-priority order (Critical, High) in priority sequence:
  - If order has sufficient stock to build ALL modules for this base type:
    - Include ALL modules for this base type from this order in batch
    - **No array optimization applied** - high-priority orders built in full even if creating partial arrays
  - If order has insufficient stock for complete build:
    - **Build what you can now** - include all buildable modules in batch
    - Excluded modules (missing components) will be batched later when components arrive
    - Only skip order entirely if very few modules are buildable (see Rule 8 for component shortage handling)

**Step 3:** After all high-priority buildable modules are included:
  - Fill remaining batch capacity with medium-priority and low-priority orders
  - **Apply array optimization** when selecting lower-priority modules (see details below)
  - Goal: maximize batch size while prioritizing high-priority orders and optimizing array usage

**Step 3a - Array Optimization for Lower-Priority Modules:**
When adding medium/low-priority orders to fill batch capacity:
  - Calculate total modules if all lower-priority orders included
  - Check if array optimization possible by reducing/excluding lowest-priority modules
  - If small reduction (1-7 modules) achieves complete arrays AND affected order is:
    - Low priority, OR
    - Waiting for other components (not urgent), OR
    - Has acceptable promised date buffer
  - Then: Reduce/exclude those modules for array optimization
  - Else: Include all modules (accept partial array)

**Step 4:** PM reviews and approves final batch composition, including array optimization decisions

**Example Without Array Optimization:**
```
Available modules needing ATOMa base (sorted by priority):
ATOMa Array Size: 15-up

Priority 1 (Critical):
  Order 12345: Needs 50 ATOMa → Stock sufficient → INCLUDE all 50

Priority 2 (High):
  Order 12346: Needs 30 ATOMa → Stock sufficient → INCLUDE all 30
  Order 12347: Needs 100 ATOMa → Stock insufficient (missing 2 LED SKUs) → INCLUDE 98 buildable modules (2 excluded)

Priority 3 (Medium):
  Order 12348: Needs 20 ATOMa → Stock sufficient → INCLUDE all 20
  Order 12349: Needs 40 ATOMa → Stock sufficient → INCLUDE all 40

Current batch: 50 + 30 + 98 + 20 + 40 = 238 ATOMa modules
Array Analysis: 238 ÷ 15 = 15 complete arrays (225) + 1 partial array (13/15)

Result:
  - Orders 12345, 12346, 12348, 12349 will complete (all ATOMa modules built)
  - Order 12347: Partial build (98 of 100 modules, remaining 2 will batch when components arrive)
  - Partial array acceptable (contains mix of priorities)
```

**Example With Array Optimization:**
```
Available modules needing NORDa base (sorted by priority):
NORDa Array Size: 8-up

Priority 1 (High):
  Order 12350: Needs 48 NORDa → Stock sufficient → INCLUDE all 48
  Order 12351: Needs 24 NORDa → Stock sufficient → INCLUDE all 24

Priority 2 (Medium):
  Order 12352: Needs 16 NORDa → Stock sufficient

Priority 3 (Low):
  Order 12353: Needs 11 NORDa → Stock sufficient
    → Order waiting for non-LED components (not urgent)

Step 2 Result: 48 + 24 = 72 NORDa (high-priority orders)

Step 3 - Fill with lower priority:
  Option A: Include all lower-priority modules
    → 72 + 16 + 11 = 99 NORDa
    → 12 complete arrays (96) + 1 partial array (3/8)

  Option B: Array optimization
    → 72 + 16 + 8 = 96 NORDa
    → 12 complete arrays, NO partial array
    → Only build 8 of Order 12353's 11 modules
    → Remaining 3 modules built in future batch

Array Optimization Decision:
  Order 12353 is Low Priority AND waiting for other components
  → Deferring 3 modules does not impact promised date
  → Choose Option B (array-optimized)

Final Batch: 96 NORDa modules (12 complete 8-up arrays)
  - Order 12350: Complete (48 modules)
  - Order 12351: Complete (24 modules)
  - Order 12352: Complete (16 modules)
  - Order 12353: Partial (8 of 11 modules, remaining 3 in next batch)

System Display:
  "Array optimization applied: Reduced Order 12353 by 3 modules
   to achieve 12 complete arrays (96 modules). Order 12353 is low
   priority and waiting for other components."
```

**Rationale:**
High-priority orders should progress as quickly as possible. When stock is available for most modules (e.g., 98 of 100), batching buildable modules immediately prevents urgent orders from sitting idle while waiting for a few missing components. Remaining modules batch when components arrive. "Build what you can now" maximizes throughput for critical orders. Array optimization is applied only to lower-priority modules where small reductions achieve complete array usage without impacting promised dates. High-priority orders are NEVER reduced for array optimization - only component availability limits high-priority batching.

**System Impact:**
- Batch creation wizard sorts and groups orders by priority
- QMF calculates array usage (complete vs. partial arrays)
- System highlights array optimization opportunities for lower-priority modules
- QMF shows "Order Completion Impact" and "Array Optimization" analysis before batch creation
- System displays: "Using X complete arrays" or "Using X complete + 1 partial array (Y/Z modules)"

**PM Actions:**
- PM reviews recommended batch composition including array optimization
- PM can override array optimization to include all modules
- PM can manually exclude specific orders from batch
- PM can adjust priorities to trigger component reallocation (Rules 9-11, 16)
- PM sees clear indication when lower-priority modules reduced for array optimization

---

#### Rule 7: Batch Capacity Fill Strategy with Array Optimization
**Statement:** After including all high-priority complete-buildable orders, the system shall fill remaining batch capacity with lower-priority orders to optimize manufacturing efficiency, preferring array-size increments when possible.

**Details:**

**Fill Strategy Algorithm:**

1. **Primary Selection:** Include all high-priority orders where ALL modules for the base type can be built
2. **Capacity Check:** Calculate remaining capacity before reaching recommended batch size (Rule 4)
3. **Secondary Selection:** If capacity remains, add lower-priority orders using same "complete-buildable" criteria
4. **Array Optimization:** When filling with lower-priority orders, prefer quantities that complete arrays
5. **Optimization:** Continue adding orders until batch reaches optimal size, complete arrays achieved, or no more buildable orders exist

**Capacity Guidelines:**
- Recommended maximum: Configurable by PM (default: 150 modules for single-day build capacity)
- Large batch threshold: Configurable by PM (default: 200+ modules triggers split consideration)
- PM can adjust these thresholds based on production capacity, staffing, and operational needs
- System uses configured values for batch size warnings and automatic split suggestions

**Fill Priority Sequence:**
```
Fill Order:
  1. Critical manual override orders (complete builds only, no array optimization)
  2. High-priority orders (complete builds only, no array optimization)
  3. Medium-priority orders (complete builds, array optimization considered)
  4. Low-priority orders (complete or partial builds, array optimization prioritized)
  5. Stop when optimal batch size reached, complete arrays achieved, or no more eligible orders
```

**Array-Optimized Fill Logic:**
When adding medium/low-priority orders:
- Calculate modules needed to reach next complete array boundary
- Prioritize orders/quantities that achieve complete arrays
- If choice between:
  - Option A: Include all modules (creates partial array)
  - Option B: Reduce/exclude modules (achieves complete arrays)
- Choose Option B if affected orders are low priority or waiting for other components
- Choose Option A if all orders are time-sensitive or array waste is minimal (1-2 modules)

**Example With Array Optimization:**
```
NORDa Batch Creation:
NORDa Array Size: 8-up

High-Priority Orders (all included):
  Order 12345: 48 NORDa → Included
  Order 12346: 24 NORDa → Included
  Subtotal: 72 NORDa (9 complete arrays)

Batch Capacity: 150 modules (recommended max)
Remaining Capacity: 78 modules

Medium-Priority Orders (sorted by priority within tier):
  Order 12347: 32 NORDa → Would bring total to 104 (13 complete arrays) → INCLUDE
  Subtotal now: 104 NORDa (13 complete arrays)
  Remaining capacity: 46 modules

  Order 12348: 40 NORDa → Would bring total to 144 (18 complete arrays) → INCLUDE
  Subtotal now: 144 NORDa (18 complete arrays)
  Remaining capacity: 6 modules

Low-Priority Orders:
  Order 12349: 11 NORDa (Low priority, waiting for other components)
    → Would bring total to 155 (exceeds capacity 150) → SKIP entire order
    OR
    → Could include 8 modules to reach 152 (19 complete arrays)
    → But exceeds soft capacity limit

  Order 12350: 6 NORDa → Would bring total to 150 (18 complete + 6 partial)
    → Fits capacity but creates partial array
    → Order is low priority
    → SKIP for array optimization

Array Optimization Decision:
  Stop at 144 modules (18 complete 8-up arrays)
  Orders 12349 and 12350 deferred to next batch
  No partial arrays, maximum efficiency

Final Batch: 144 NORDa modules (18 complete 8-up arrays)
  - Order 12345: Complete (48 modules)
  - Order 12346: Complete (24 modules)
  - Order 12347: Complete (32 modules)
  - Order 12348: Complete (40 modules)

System Display:
  "Batch uses 18 complete arrays (144 modules). Orders 12349 and 12350
   excluded for array optimization (low priority, waiting for components)."
```

**Example Without Array Optimization Opportunity:**
```
ATOMa Batch Creation:
ATOMa Array Size: 15-up

High-Priority Orders (all included):
  Order 12345: 50 ATOMa → Included
  Order 12346: 30 ATOMa → Included
  Subtotal: 80 modules

Remaining Capacity: 70 modules

Medium-Priority Orders:
  Order 12347: 60 modules → Fits → INCLUDE
  Subtotal: 140 modules (9 complete arrays + 5 module partial array)

  Order 12348: 10 modules → Fits → INCLUDE
  Subtotal: 150 modules (10 complete arrays, no partial!)

Array Optimization Result:
  Including Order 12348 achieves 10 complete arrays
  System recommends including it even though creates larger batch

Final Batch: 150 modules (10 complete 15-up arrays)
```

**Rationale:**
Filling batch capacity with lower-priority complete-buildable orders maximizes manufacturing efficiency (larger batches reduce total mask changeovers) without delaying high-priority work. Array optimization further reduces waste by preferring complete-array quantities when selecting lower-priority modules. This three-way balance (priority, capacity, array efficiency) ensures optimal production scheduling.

**System Impact:**
- Batch creation algorithm automatically suggests array-optimized fill
- QMF shows "Batch Efficiency" metric (actual size vs. optimal size)
- QMF displays array analysis: "Using X complete arrays" or "Using X complete + 1 partial (Y/Z)"
- System highlights when lower-priority modules excluded for array optimization
- QMF displays estimated build time for batch size

**PM Actions:**
- PM reviews suggested batch composition including array optimization
- PM can manually adjust which lower-priority orders to include
- PM can override array optimization to include all available modules
- PM considers production capacity when creating very large batches

---

#### Rule 8: Component Availability Requirements (Strict Enforcement)
**Statement:** Modules can ONLY be included in batches if 100% of required components are in stock. This is a strict physical constraint - modules cannot be built without all components due to the single reflow soldering cycle.

**Details:**

**Physical Constraint - Single Reflow Cycle:**
- LED modules use a single solder paste application and reflow soldering cycle
- ALL components (base, LEDs, resistors, connectors) must be placed before reflow
- Once reflow is complete, additional components CANNOT be added
- Therefore: ALL components must be available before module production can begin
- This is a non-negotiable physical constraint, NOT a business rule

**Component Availability Check:**
- System checks component stock for each module before batch inclusion
- Components required: Base, all LEDs (by SKU and quantity), 0R resistors, connectors
- If ANY component is out of stock or insufficient quantity: module is marked "Not Buildable"
- Not buildable modules CANNOT be included in batch under any circumstances

**Component Check Example:**
```
Module: ATOMa with 4× LED SKU L1C2-DRD1000000000
Required components:
  - 1× ATOMa base → Stock: 50 available ✅
  - 4× LED L1C2-DRD1000000000 → Stock: 200 available (need 4) ✅
  - 4× 0R resistor → Stock: 5000 available ✅
  - 1× Connector XYZ → Stock: 0 available ❌

Result: Module marked "Not Buildable" (missing connector)
Action: Module CANNOT be included in batch until connector arrives
```

**PM Actions to Resolve Component Shortages:**
While PM CANNOT override the physical constraint, PM CAN take actions to make components available:

**Option 1: Manual Priority Adjustment**
```
Scenario:
  Order 12345 (High Priority): Needs 520× LED SKU ABC, but only 500 in stock
  Order 12346 (Medium Priority): Has soft-reserved 50× LED SKU ABC

PM Action:
  1. Manually increase Order 12345 priority to "Critical"
  2. System automatically reallocates 20 LEDs from Order 12346 to Order 12345
  3. Order 12345: Now has all 520 LEDs → Becomes "Fully Buildable"
  4. Order 12346: Loses 20 LEDs → Becomes "Partially Buildable" (30 modules still OK)
```

**Option 2: Wait for Component Arrival**
```
Order 12345 needs 520 ATOMa modules:
  - 518 modules: All components available ✅
  - 2 modules: Missing LED SKU (arrives in 3 days) ❌

PM Options:
  A. Create batch NOW with 518 buildable modules only
     → 2 modules excluded (will batch later when components arrive)

  B. Wait 3 days for LED arrival
     → Create batch with all 520 modules

PM Decision Factors:
  - Build time for 518 modules: 5 days
  - If wait 3 days, only 2 days remain before promised date
  - Choose Option A: Batch 518 now, batch 2 later
  - This is NOT an override - simply excluding unbuildable modules
```

**Option 3: Expedite Component Delivery**
- PM coordinates with purchasing to expedite delivery of missing components
- Once components arrive, modules become buildable
- Modules added to next batch for that base type

**Rationale:**
The single reflow cycle is a non-negotiable physical constraint in LED module manufacturing. ALL components must be available before production can begin. PM cannot override physics, but PM can adjust priorities to trigger component reallocation or can choose to batch available modules while waiting for components to arrive for remaining modules.

**System Impact:**
- Component availability check runs before batch creation
- System displays clear "Buildable" vs "Not Buildable" indicators for each module
- System shows which specific components are missing for unbuildable modules
- QMF displays reallocation options when PM adjusts priorities
- Batch creation ONLY includes modules with 100% component availability

**PM Actions:**
- PM reviews component availability report before batch creation
- PM can manually adjust order priorities to trigger component reallocation
- PM can choose to batch available modules now, defer unbuildable modules to later
- PM CANNOT include modules in batch without all required components

---

### Category 3: Component Reservation System

#### Rule 9: Module-Level Soft Reservation
**Statement:** When an order enters the production queue, components for its modules are soft-reserved, indicating provisional allocation that can be reallocated to higher-priority orders.

**Details:**

**When Soft Reservation Occurs:**
- Triggered when order status changes to `wc-process` (Released for processing)
- This is the only production-eligible status that triggers soft reservation
- Components remain soft-reserved until batch is created (transitions to hard lock) or order cancelled

**Why Only wc-process Status:**
- `wc-on-hold`: Orders awaiting payment or on hold should NOT reserve components (creates stock starvation)
- `wc-pending`: Unpaid orders should NOT reserve components
- `wc-processing`: Orders already in shipping/fulfillment (not relevant to production)
- Only `wc-process` indicates order is released and ready for production planning

**Soft Reservation Characteristics:**
- Components are "earmarked" for the order but not locked
- Higher-priority orders can reallocate soft-reserved components
- System displays "Reserved For Order #12345" in inventory view
- Component quantities decrease from "Available" pool
- Can be manually released by PM if order priority changes

**Reallocation Rules:**
- Higher priority order can "steal" soft-reserved components from lower priority order
- System shows warning: "Reallocating 50 LEDs from Order 12346 (Low Priority) to Order 12345 (High Priority)"
- PM must confirm reallocation understanding the impact
- Lower-priority order returns to "Not Buildable" status after reallocation

**Example:**
```
Initial State:
  LED Stock: 100× LED SKU ABC

  Order 12345 (High Priority): Needs 60× LED ABC
    → Soft reserves 60 LEDs

  Order 12346 (Medium Priority): Needs 50× LED ABC
    → Soft reserves 50 LEDs

  Remaining Available: 100 - 60 - 50 = -10 (CONFLICT)

Resolution:
  Order 12345 (Higher Priority): Keeps 60 LEDs (soft reserved)
  Order 12346 (Lower Priority): Gets 40 LEDs (soft reserved)
  Order 12346: Shows "Partially Buildable - Insufficient LED ABC stock"

  Later: If Order 12347 (Critical Priority) needs 70× LED ABC:
    → Reallocates 60 from Order 12345
    → Reallocates 10 from Order 12346
    → Order 12347: Soft reserves 70 LEDs
    → Orders 12345 and 12346: Become "Not Buildable"
```

**Rationale:**
Soft reservations provide visibility into component allocation without locking components prematurely. This allows dynamic reallocation based on changing priorities while preventing accidental double-allocation of limited stock. System flexibility ensures high-priority orders always get components first.

**System Impact:**
- Component inventory table includes "soft_reserved_qty" column
- QMF dashboard shows soft-reserved quantities by order
- Reallocation triggers require PM confirmation

**PM Actions:**
- PM can manually release soft reservations if order cancelled or delayed
- PM can force reallocation if business priorities change
- PM reviews soft reservation conflicts before batch creation

---

#### Rule 10: Batch-Level Hard Lock
**Statement:** When a PM creates a production batch, all components allocated to that batch transition from soft reservation to hard lock, preventing reallocation until batch completion.

**Details:**

**When Hard Lock Occurs:**
- Triggered when PM finalizes batch creation
- Applies to ALL components allocated to modules in the batch:
  - Bases
  - All LEDs (by SKU and quantity)
  - 0R resistors
  - Connectors
  - Any other module-specific components

**Hard Lock Characteristics:**
- Components are physically allocated to active production
- **Cannot be reallocated** to other orders, even higher-priority orders
- System displays "Hard Locked to Batch #47" in inventory view
- Components remain locked until batch marked complete or cancelled
- Protects in-progress work from interruption

**Hard Lock Duration:**
```
Timeline:
  Day 1: PM creates Batch 47 → Components hard locked
  Day 1-3: Production builds modules from Batch 47
  Day 3: Batch 47 marked complete → Components transition to "consumed"
  Day 3: Hard lock released (components no longer in inventory)
```

**Priority Conflict Handling:**
```
Scenario:
  Batch 47 (in progress): Uses 50× LED ABC (hard locked)

  New Order 12999 (Critical Priority): Needs 100× LED ABC
  LED ABC Stock: 50 available, 50 hard locked in Batch 47

  System Response:
    → Cannot reallocate from Batch 47 (hard locked)
    → Shows warning: "Insufficient stock. 50 LEDs hard locked in active Batch 47."
    → Order 12999 remains "Not Buildable" until:
       a) Batch 47 completes, OR
       b) New LED stock arrives, OR
       c) PM cancels Batch 47 (releases hard lock)
```

**Exception - Batch Cancellation:**
PM can cancel an active batch, which:
- Releases hard lock on all components
- Returns components to soft reservation pool for original orders
- Requires PM confirmation (work in progress will be lost)
- Used only in emergency situations (order cancelled, component defect discovered, etc.)

**Rationale:**
Hard locks protect active production work from disruption. Once production begins, reallocating components would waste already-completed work and confuse production staff. Hard locks ensure batch integrity while providing clear visibility into why components are unavailable for other orders.

**System Impact:**
- Component inventory includes "hard_locked_qty" and "batch_id" columns
- QMF prevents component reallocation from active batches
- Batch cancellation requires elevated PM permissions and confirmation

**PM Actions:**
- PM sees hard-locked quantities when reviewing component availability
- PM can cancel batches in emergency situations (releases hard locks)
- PM cannot reallocate hard-locked components (enforced by system)

---

#### Rule 11: Component Reallocation Rules
**Statement:** Components with soft reservations can be reallocated from lower-priority orders to higher-priority orders, with system warnings and PM confirmation.

**Details:**

**Reallocation Eligibility:**
- Only soft-reserved components can be reallocated (not hard-locked)
- Reallocation flows from lower-priority orders to higher-priority orders
- System prevents reallocation from equal or higher-priority orders

**Reallocation Algorithm:**
```
When higher-priority order needs components:
  1. Check available unreserved stock first
  2. If insufficient, identify soft-reserved components from lower-priority orders
  3. Calculate reallocation impact (which orders become unbuildable)
  4. Present reallocation plan to PM with warnings
  5. PM confirms or cancels reallocation
  6. If confirmed: Update reservations, notify affected orders
```

**Reallocation Example:**
```
Component: LED SKU XYZ
Total Stock: 200

Current Reservations:
  Order 12345 (Priority: High): Soft-reserved 80 LEDs
  Order 12346 (Priority: Medium): Soft-reserved 70 LEDs
  Order 12347 (Priority: Low): Soft-reserved 50 LEDs
  Available: 0

New Order 12999 (Priority: Critical) needs 100 LEDs

System Reallocation Plan:
  "Reallocating 100× LED XYZ to Order 12999 (Critical Priority)

   Impact:
   ⚠️ Order 12347 (Low): Loses 50 LEDs → Becomes Not Buildable
   ⚠️ Order 12346 (Medium): Loses 50 LEDs → Becomes Partially Buildable (20 modules affected)
   ✅ Order 12345 (High): Keeps 80 LEDs (higher priority than 12346)

   Confirm reallocation? [Yes] [No]"

If PM confirms:
  Order 12999: Soft-reserves 100 LEDs (50 from 12347, 50 from 12346)
  Order 12347: Soft reservation cleared → Not Buildable
  Order 12346: Soft reservation reduced to 20 → Partially Buildable
  Order 12345: Unchanged
```

**Notification Strategy:**
- System logs all reallocations with timestamp, PM user, and justification
- Affected orders flagged with note: "Components reallocated to higher-priority Order 12999 on [date]"
- PM can review reallocation history in order notes

**Rationale:**
Dynamic reallocation ensures limited components always flow to highest-priority orders. System warnings prevent accidental disruption of buildable orders, while PM confirmation ensures human oversight of business-critical decisions.

**System Impact:**
- Reallocation engine calculates impact across all soft-reserved orders
- QMF displays clear before/after state for PM review
- All reallocations logged for audit trail

**PM Actions:**
- PM reviews reallocation impact warnings before confirming
- PM can cancel reallocation if impact is unacceptable
- PM can manually adjust reservations if needed

---

#### Rule 12: Stalled Batch Detection & Component Release
**Statement:** QMF shall automatically detect batches that have been active for an abnormally long time and notify the PM to prevent components from being stranded indefinitely in hard locks.

**Details:**

**Stalled Batch Criteria:**
- Batch is active (not yet completed or cancelled)
- No progress/updates for configurable threshold (default: 5 business days)
- Components remain hard-locked but production appears stalled

**Automatic Flagging System:**
```
Daily Check (runs at 8:00 AM):
  → Scan all active batches for last activity timestamp
  → Flag batches exceeding threshold
  → Send Slack notification to PM

Flagged Batch 47:
  Created: Nov 1, 2025
  Last Activity: Nov 3, 2025 (10 days ago)
  Hard-Locked Components:
    - 85× ATOMa bases
    - 200× LED L1C2-DRD
    - 140× LED XHP35B
    - 340× 0R resistors
```

**Slack Notification Format:**
```
⚠️ STALLED BATCH ALERT - Batch #47

Batch has been active for 10 days with no progress updates.
Hard-locked components may be blocking other orders.

Batch Details:
  Created: Nov 1, 2025 by PM John Smith
  Last Activity: Nov 3, 2025 (production staff scanned 15/85 modules)
  Current Progress: 15/85 modules complete (18%)
  Orders Included: 12345 (25 modules), 12346 (50 modules), 12347 (10 modules)

Hard-Locked Components:
  - 85× ATOMa bases
  - 200× LED L1C2-DRD
  - 140× LED XHP35B
  - 340× 0R resistors

PM Actions Required:
  1. Check production status (machine down? batch abandoned?)
  2. If continuing: Update batch status/notes (resets timer)
  3. If stalled: Cancel batch → releases hard locks
  4. If complete: Close batch → releases components, updates orders

[View Batch in QMF] [Dismiss Alert for 2 Days]
```

**PM Resolution Options:**

**Option 1: Batch Still Active (False Alarm)**
```
PM Action: Click "Update Batch Status" and add note
  → Note: "Waiting for LED restock, production resuming Nov 15"
  → Resets stalled timer
  → Dismisses alert
```

**Option 2: Batch Abandoned/Cancelled**
```
PM Action: Cancel batch in QMF
  1. PM clicks "Cancel Batch 47"
  2. System confirms: "Cancel Batch 47? This will release all hard locks and return components to inventory."
  3. PM confirms
  4. System Response:
     → Batch status: Cancelled
     → Hard locks released
     → Components return to available/soft-reserved pool
     → Affected orders return to buildable state (if components available)
     → Slack notification: "✅ Batch 47 cancelled. Hard locks released."
```

**Option 3: Batch Complete (Forgot to Close)**
```
PM Action: Mark batch complete in QMF
  1. PM verifies all modules built
  2. PM clicks "Complete Batch 47"
  3. System Response:
     → Batch status: Complete
     → Hard locks released
     → Orders updated with completed module counts
     → Components no longer reserved
```

**Escalation for Ignored Alerts:**
```
If stalled batch remains unresolved:
  - Day 5: Initial Slack alert
  - Day 7: Second Slack alert (escalated to production manager)
  - Day 10: Third Slack alert + email to PM and production manager
  - Day 14: Batch flagged "CRITICAL - MANUAL REVIEW REQUIRED" in QMF dashboard
```

**System Impact:**
- Batch table includes "last_activity_timestamp" column
- Daily automated job checks for stalled batches
- Slack integration sends alerts to #production channel
- QMF dashboard displays "Stalled Batch" warnings prominently
- Hard lock release only via PM action (no automatic unlock)

**PM Actions:**
- PM reviews stalled batch alerts daily
- PM investigates cause (production delay, machine failure, forgotten batch)
- PM takes appropriate action (update, cancel, or complete batch)
- PM can adjust stalled threshold if needed (e.g., 3 days for urgent periods, 7 days for normal periods)

**Rationale:**
Hard locks protect active production but can strand components if batches are abandoned or forgotten. Automatic detection ensures PM awareness without requiring manual monitoring. PM retains full control over resolution - system never auto-releases hard locks (could disrupt legitimate production). Escalating alerts ensure critical component availability issues don't go unnoticed.

---

### Category 4: Order Completion & Tracking

#### Rule 13: Partial Order Completion Tracking
**Statement:** The system shall track order completion status across multiple batches, clearly indicating which modules have been built and which remain pending.

**Details:**

**Order Completion States:**
- **Not Started:** No modules built yet
- **Partially Complete:** Some modules built, some pending
- **Fully Complete:** All modules built across all batches

**Tracking Mechanism:**
```
Order-Level Tracking:
  Order 12345 Total: 200 modules across 3 base types
    - ATOMa: 100 modules
    - NORDa: 75 modules
    - POLYa: 25 modules

Batch Allocation:
  Batch 47 (ATOMa): 100 modules for Order 12345 → Built on Day 1
  Batch 52 (NORDa): 75 modules for Order 12345 → Built on Day 3
  Batch 58 (POLYa): 25 modules for Order 12345 → Built on Day 5

Completion Status by Day:
  Day 1: Order 12345 = 50% complete (100/200 modules built)
  Day 3: Order 12345 = 87.5% complete (175/200 modules built)
  Day 5: Order 12345 = 100% complete (200/200 modules built) ✅
```

**QMF Display:**
```
Order #12345 Status:
  ✅ ATOMa (100 modules) - Complete [Batch 47]
  ✅ NORDa (75 modules) - Complete [Batch 52]
  🔄 POLYa (25 modules) - In Progress [Batch 58] - 15/25 built

  Overall: 87.5% Complete (175/200 modules)
```

**Multi-Order Batch Tracking:**
Since batches contain modules from multiple orders:
- Batch completion does NOT mean order completion
- System aggregates module counts across all batches for each order
- Order status updates only when ALL its modules (across all batches) are complete

**Rationale:**
Module-focused batching means orders are fulfilled across multiple batches over time. Clear tracking prevents shipping incomplete orders and provides visibility into production progress. PM can make informed decisions about scheduling and customer communication.

**System Impact:**
- Order table includes "modules_built" and "modules_total" columns
- Batch-module association table tracks which modules from which orders in each batch
- Completion percentage calculated dynamically: `(modules_built / modules_total) × 100`

**PM Actions:**
- PM reviews order completion status before creating shipping batches
- PM can filter orders by completion percentage (e.g., ">90% complete")
- PM can prioritize batches to complete specific near-complete orders

---

#### Rule 14: WooCommerce Order Status Integration
**Statement:** QMF shall integrate with WooCommerce order statuses to reflect production state, with automatic status transitions and protection against unauthorized manual changes.

**Details:**

**QMF-Managed Order Statuses:**
- `wc-process`: Order released for processing, components soft-reserved
- `wc-in-production`: At least one batch created for this order, modules being built

**Status Transition Flow:**
```
Order Created → wc-on-hold
  ↓ (Admin releases order)
Order Released → wc-process (QMF soft-reserves components)
  ↓ (PM creates first batch for order)
Batch Created → wc-in-production (QMF sets automatically)
  ↓ (All batches for order complete)
Production Complete → wc-process (QMF returns order to processing)
  ↓ (Shipping batch system processes order)
Shipping Ready → wc-processing (Shipping batch system sets)
  ↓ (Shipping creates label)
Order Shipped → wc-completed
```

**QMF Sets These Statuses:**
- `wc-process` → `wc-in-production`: When first batch created for order
- `wc-in-production` → `wc-process`: When all batches for order complete

**QMF Does NOT Set:**
- `wc-processing` ("Ready to Ship") - Shipping batch system owns this
- Order may contain non-module items that QMF doesn't track
- Shipping batch system has visibility into complete order readiness

**Status Protection Mechanisms:**

**Edge Case A: Manual Status Changes AWAY from "In Production"**
```
Problem: Admin accidentally changes order status while batches active
Detection: Monitor woocommerce_order_status_changed hook
Action: Auto-revert to "In Production" + Slack notification

Example:
  Order 12345 status manually changed: In Production → Shipped
  System Response:
    → Auto-revert to In Production
    → Slack #production: "⚠️ Order 12345 status manually changed from 'In Production' to 'Shipped'. Auto-reverted. Active batches: Batch 47 (15/20 complete)"
```

**Edge Case B: Manual Status Changes TO "In Production" Without Batches**
```
Problem: Admin manually sets order to "In Production" without creating batches
Detection: Status changed TO "In Production" but no active batches exist
Action: Auto-revert to "Process" + Slack notification

Example:
  Order 12346 manually set to In Production (no batches exist)
  System Response:
    → Auto-revert to Process
    → Slack #production: "⚠️ Order 12346 manually set to 'In Production' but has no active batches. Auto-reverted to 'Process'. Use QMF to create batches."
```

**Edge Case C: "In Production" → "Hold" Transitions**
```
Problem: Order placed on hold during production
Detection: Status changed from "In Production" to "Hold"
Action: Allow transition, Slack notification, flag batches as "On Hold"

Component Lock Behavior: Components remain locked (not released)
Batch State: Flagged "On Hold" but not cancelled

Example:
  Order 12347: In Production → Hold
  System Response:
    → Allow transition
    → Flag all active batches for this order as "On Hold"
    → Slack #production: "📋 Order 12347 placed on HOLD during production. Batches flagged. Components remain locked. Batch 48: 15/20 complete (ON HOLD)"
```

**Edge Case D: Order Cancellation During Production**
```
Problem: Order cancelled while batches in progress
Detection: Status changed to "Cancelled" from "In Production"
Action: Slack notification only - NO automation, manual PM intervention required

PM Must:
  1. Complete or abandon in-progress batches
  2. Decide component disposition (return to inventory or complete for stock)
  3. Manually close batches in QMF
  4. Clean up order trays

Example:
  Order 12348: In Production → Cancelled
  System Response:
    → Allow transition (no auto-revert)
    → Slack #production: "🚨 Order 12348 CANCELLED during production. Manual PM action required. Batch 49: 15/20 complete. Batch 50: 0/50 started."
```

**Rationale:**
Order status protection prevents data integrity issues from unauthorized manual changes. Auto-reversion ensures "In Production" status accurately reflects active batches. "Hold" transitions preserve component locks for easy resumption. Cancellations require manual PM judgment for component disposition decisions.

**System Impact:**
- Hook: `woocommerce_order_status_changed` monitors all transitions
- Status reversion logic executes immediately on invalid transitions
- Batch records include "on_hold" boolean flag
- Slack integration posts to #production channel

**PM Actions:**
- PM sees status protection notifications in Slack #production
- PM manually manages cancelled orders (batch completion, component disposition)
- PM can resume held orders (system auto-updates batch flags)

---

#### Rule 15: Multi-Batch Order Management
**Statement:** Large orders may be split into multiple batches for the same base type, with each batch tracked independently and order completion requiring all batches to finish.

**Details:**

**When to Split Orders into Multiple Batches:**
- Order requires more modules than PM-configured recommended batch size (configurable threshold)
- Production capacity constraints (build over multiple days)
- Component availability (some stock available now, more arriving later)
- Strategic reasons (build urgent portion first, rest later)

**Multi-Batch Example:**
```
Order 12345: 500× ATOMa modules

PM Strategy: Split into 3 batches
  Batch 47: 200× ATOMa (Day 1-2)
  Batch 52: 200× ATOMa (Day 3-4)
  Batch 58: 100× ATOMa (Day 5)

All batches linked to Order 12345

Order Completion Status:
  Day 2: Batch 47 complete → Order 12345 = 40% complete (200/500)
  Day 4: Batch 52 complete → Order 12345 = 80% complete (400/500)
  Day 5: Batch 58 complete → Order 12345 = 100% complete ✅
```

**Same Base Type, Multiple Batches:**
```
Even though all modules are ATOMa (same base type), PM splits into multiple batches because:
  - 500 modules exceeds single-day build capacity
  - Production scheduling (spread work across week)
  - Manageable batch sizes for production workflow
```

**Batch Independence:**
- Each batch has its own production sheet
- Each batch tracked separately in QMF
- Production staff builds one batch at a time
- Batch completion updates order completion percentage

**Rationale:**
Large orders exceeding practical batch size limits need to be split for manageable production runs. Multi-batch support provides flexibility while maintaining clear tracking and order completion logic.

**System Impact:**
- Batch table includes `order_id` and `batch_sequence` fields (e.g., Order 12345 Batch 1, 2, 3)
- Order completion logic: `SUM(modules_built across all batches) = order total modules`
- QMF displays all batches for an order in order detail view

**PM Actions:**
- PM decides how to split large orders into batches
- PM can create batches sequentially (build Batch 1, then create Batch 2, etc.)
- PM can create all batches upfront if all components available

---

#### Rule 16: Completion Notification Strategy
**Statement:** When an order's module production completes (all batches finished), QMF shall notify relevant stakeholders via Slack and update order status.

**Details:**

**Notification Trigger:**
- Last batch for an order is marked complete
- System calculates: All modules for order now built
- Notification sent immediately

**Notification Channel:**
- **Slack #production**: All production-related notifications
- No email notifications (avoids notification overload)

**Notification Format:**
```
✅ Order 12345 Production COMPLETE

Modules Built: 500/500 ATOMa modules
Batches Completed:
  - Batch 47: 200 modules (completed Nov 10)
  - Batch 52: 200 modules (completed Nov 12)
  - Batch 58: 100 modules (completed Nov 14)

Order Status: Process (waiting for shipping batch)
Order Trays: Labeled "Order 12345" in shipping area Order Tray rack

Next Step: Order ready for shipping batch creation
Priority: High | Promised Date: Nov 18
```

**Order Status Update:**
- QMF automatically transitions order: `wc-in-production` → `wc-process`
- Order now waits for shipping batch system to process
- Shipping batch system will set `wc-process` → `wc-processing` (Ready to Ship) when all order items (modules + non-module items) are ready

**Rationale:**
Clear completion notifications ensure production team, shipping team, and PM know when orders are ready for next steps. Centralized Slack notifications provide single communication stream. Automatic status transitions keep WooCommerce order status accurate. Order trays are labeled with order number and stored in the shipping area Order Tray rack for easy retrieval.

**System Impact:**
- Order completion check runs when any batch is marked complete
- Slack integration posts to #production channel
- WooCommerce order status updated via `wc_update_order()` function

**PM Actions:**
- PM monitors #production channel for completion notifications
- PM can use notifications to prioritize shipping batch creation
- Shipping staff locates order trays by order number label in Order Tray rack

---

### Category 5: PM Override & Control

#### Rule 17: PM Can Adjust Priorities to Include Specific Modules
**Statement:** The PM may manually adjust order priorities to trigger component reallocation, allowing specific modules to become buildable even when initial automated priority calculations would exclude them.

**Details:**

**When PM Might Adjust Priorities:**
- Strategic business decision (important customer relationship)
- VIP customer with flexible stock allocation
- Urgent order that needs to jump the queue
- Business value justifies reallocating components from lower-priority orders

**Priority Adjustment Mechanism:**
```
Batch Creation UI:
  Default Recommended Modules: [List based on Rules 5-8]

  Additional Available Modules: [List of lower-priority or unbuildable modules]

  PM Action for Unbuildable Module:
    → Click module to see "Why Not Buildable" details
    → System shows: "Missing 50× LED SKU XYZ (reserved for Order 12346 - Low Priority)"
    → PM clicks "Reallocate Components"
    → System shows reallocation impact warning
    → PM confirms reallocation
    → Components reallocated, module becomes buildable
    → Module added to batch
```

**Reallocation Warning Example:**
```
⚠️ Component Reallocation Required

Module: 50× ATOMa for Order 12347 (High Priority)
Missing Components:
  - LED SKU XYZ: Need 200, Available 150, Reserved 50 (for Order 12346 - Low Priority)

To make Order 12347 buildable, reallocate components from:
  Order 12346 (Low Priority): Loses 50× LED SKU XYZ
  Impact: Order 12346 becomes "Partially Buildable" (can only build 30 of 80 modules)

Options:
  1. [Reallocate] - Take components from Order 12346, build Order 12347
  2. [Cancel] - Leave allocations as-is, skip Order 12347 for this batch

Justification Required: [Text field for PM notes]
Confirm Reallocation? [Reallocate] [Cancel]
```

**Reallocation vs Physical Override:**
```
What PM CAN Do (Reallocation):
  ✅ Adjust order priorities to trigger automatic reallocation
  ✅ Manually reallocate soft-reserved components between orders
  ✅ Release soft reservations from cancelled/delayed orders
  ✅ Expedite component delivery to make modules buildable

What PM CANNOT Do (Physical Override):
  ❌ Include modules in batch without 100% component availability
  ❌ Build modules with missing components
  ❌ Override the reflow cycle constraint
  ❌ Reallocate hard-locked components (already in active batches)
```

**Tracking:**
- All manual priority adjustments logged with PM user, timestamp, justification
- All component reallocations logged with before/after state
- Affected orders flagged with note: "Components reallocated to Order 12347 on [date]"

**Rationale:**
Automated priority calculations handle most cases correctly, but business realities sometimes require manual intervention. PM priority adjustment triggers component reallocation (within soft-reserved pool only), allowing high-value orders to access components even when automated priority wouldn't allocate them. This is strategic component allocation management.

**System Impact:**
- Reallocation engine shows PM which orders would lose components
- QMF logs all manual priority adjustments and reallocations
- System prevents reallocation of hard-locked components (active batches)
- Batch can only include modules that have 100% component availability after reallocation

**PM Actions:**
- PM reviews component availability and reservation state
- PM adjusts priorities or manually triggers reallocation when business justification exists
- PM documents justification in notes field
- PM coordinates with affected order stakeholders about component reallocation

---

#### Rule 18: PM Can Exclude Modules from Batching
**Statement:** The PM may manually exclude specific modules from batch creation, even if they meet all default selection criteria, to manage strategic priorities or production scheduling.

**Details:**

**When PM Might Exclude Modules:**
- Need to limit batch size for production capacity
- Want to keep order together (all modules in same batch)
- Prefer to batch this order next week due to scheduling
- Customer requested delayed shipment
- Component quality issue discovered, waiting for replacement

**Exclusion Mechanism:**
```
Batch Creation UI:
  Recommended Modules: [List based on Rules 5-8]

  PM Action:
    → Click "Exclude" next to specific module/order
    → System removes from current batch
    → Module remains available for future batches
    → No impact on component reservations (stays soft-reserved)
```

**Exclusion Example:**
```
Batch 47 (ATOMa) Recommended Composition:
  ✅ Order 12345: 50 modules (High Priority)
  ✅ Order 12346: 30 modules (High Priority)
  ✅ Order 12347: 100 modules (Medium Priority)
  ❌ Order 12348: 20 modules (Medium Priority) [PM EXCLUDED]

PM Exclusion Reason: "Order 12348 customer requested delayed shipment to Nov 20.
                       Exclude from batches until Nov 18."

Result: Batch 47 contains 180 modules (Orders 12345, 12346, 12347 only)
        Order 12348 remains in queue for next batch
```

**Temporary vs Permanent Exclusion:**
- **Temporary:** Exclude from current batch only (remains eligible for next batch)
- **Permanent:** Mark order "Do Not Batch Until [date]" - system skips in all batch creation

**Rationale:**
PM needs control over which orders are batched when due to customer requests, production scheduling, or strategic business priorities. Exclusion capability provides granular control without affecting component reservations or order priority.

**System Impact:**
- Order table includes "exclude_until_date" field for permanent exclusions
- Batch creation UI respects exclusion flags
- Excluded orders remain visible in QMF (not hidden)

**PM Actions:**
- PM can exclude individual modules or entire orders
- PM sets exclusion duration (current batch only, or until specific date)
- PM documents exclusion reason in order notes

---

#### Rule 19: PM Can Adjust Batch Composition Manually
**Statement:** The PM may manually add, remove, or rearrange modules within a batch composition before finalizing, overriding automated selection logic.

**Details:**

**Manual Composition Adjustments:**
- Add modules not in recommended list
- Remove modules from recommended list
- Change module quantities (if order has multiples of same base type)
- Reorder batch sequence for production efficiency

**Composition Editor UI:**
```
Batch 47 Composition Editor:

Current Batch Contents: [Drag and drop interface]
  Order 12345: 50× ATOMa [Remove] [Edit Qty]
  Order 12346: 30× ATOMa [Remove] [Edit Qty]
  Total: 80 modules

Available to Add: [Filtered by base type: ATOMa]
  Order 12347: 100× ATOMa [Add All] [Add Partial: __ modules]
  Order 12348: 20× ATOMa [Add All]
  Order 12349: 40× ATOMa [Add All]

Batch Stats:
  Total Modules: 80
  Estimated Build Time: 3 hours
  Tray Capacity: 150 modules remaining
  Component Check: ✅ All components available
```

**Partial Quantity Addition:**
```
Example: Order 12347 needs 100 ATOMa modules
  PM Decision: Add only 50 to this batch, reserve 50 for next batch

  Result:
    Batch 47: Contains 50 of Order 12347's modules
    Order 12347: 50% complete after Batch 47
    Remaining 50: Included in next ATOMa batch
```

**Validation:**
- System validates component availability for manual composition
- System warns if batch exceeds recommended size
- System prevents mixing base types (Rule 1 enforcement)

**Rationale:**
Automated selection provides good defaults, but PM may have specific reasons for custom composition (production scheduling, tray organization, customer coordination). Manual composition capability provides ultimate flexibility while enforcing critical constraints (single base type).

**System Impact:**
- Batch composition stored as array of {order_id, module_count} records
- Validation rules enforced on save (base type check, component check)
- Manual changes logged for audit trail

**PM Actions:**
- PM reviews automated composition suggestion
- PM makes adjustments based on business priorities
- PM validates component availability before finalizing
- PM saves and creates batch

---

### Category 6: Storage & Organization

#### Rule 20: Order Tray Storage & Module Sorting
**Statement:** As modules are completed during production, staff shall scan module Data Matrix codes and sort completed modules into order-specific trays. Each order uses as many trays as needed, with simple Data Matrix-coded labels for order identification.

**Details:**

**Production Workflow:**
```
1. Production batch is generated (e.g., Batch 47: 85 modules from 3 different orders)

2. Production staff builds modules from batch

3. As each module is completed:
   → Staff scans module Data Matrix code
   → Data Matrix code identifies which order the module belongs to
   → Staff places module in appropriate order tray

4. Multiple orders being built simultaneously:
   → Order 12345 tray(s) receive completed modules for Order 12345
   → Order 12346 tray(s) receive completed modules for Order 12346
   → Order 12347 tray(s) receive completed modules for Order 12347

5. When all modules complete:
   → All order trays stored in Order Tray rack (shipping area)
   → Ready for shipping when order fully complete
```

**Module Data Matrix Codes:**
- Each completed module has a Data Matrix code engraved on the module during production
- Data Matrix code contains module identification and order number
- Staff scans Data Matrix code to determine which order tray to use
- Same Data Matrix code used during final packaging/labeling for verification

**Order Tray Organization:**
- **No organization within trays** - modules simply placed in tray as completed
- **No dividers, slots, or internal labels needed**
- **Use as many trays as needed per order** - no tray capacity limits
- **Modules identified by Data Matrix codes** - easy to verify during packaging

**Order Tray Labeling:**
```
Each order tray requires simple printed label:

┌─────────────────────────────────┐
│  [Data Matrix CODE]                      │
│                                 │
│  Order #12345                   │
│                                 │
└─────────────────────────────────┘

Label Contents:
  - Data Matrix code (identifies order when scanned)
  - Order number (human readable)
  - No module details needed (modules have own Data Matrix codes)
```

**Tray Label Printing Process:**
- QMF provides "Print Order Tray Label" function
- PM or production staff prints labels as needed
- Label includes order-identifying Data Matrix code + order number
- Staff affixes label to tray(s) for that order
- Multiple trays for same order get identical labels

**Multi-Batch Order Example:**
```
Order 12345 needs 200 ATOMa modules built across 3 batches:

Batch 47 builds 50 modules for Order 12345:
  → Staff scans each completed module Data Matrix code
  → Identifies "Order 12345"
  → Places modules in Order 12345 tray(s)
  → Uses 1 tray (50 modules fit)

Batch 52 builds 100 modules for Order 12345:
  → Staff scans each completed module Data Matrix code
  → Identifies "Order 12345"
  → Places modules in Order 12345 tray(s)
  → Uses 2 more trays (100 modules)

Batch 58 builds 50 modules for Order 12345:
  → Staff scans each completed module Data Matrix code
  → Identifies "Order 12345"
  → Places modules in Order 12345 tray(s)
  → Uses 1 more tray (50 modules)

Result: Order 12345 has 4 trays total (200 modules)
        All trays labeled "Order #12345" with Data Matrix code
        All trays stored in Order Tray rack
```

**Tray Storage:**
- All order trays stored in single Order Tray rack (shipping area)
- Trays organized by order number (staff sorts by label)
- Typically only a few dozen orders in progress at any time
- Shipping staff locates order trays by scanning Data Matrix code or reading order number

**Rationale:**
Module QR codes enable automatic sorting during production - staff simply scan and place in correct order tray. Simple tray labeling with QR codes enables quick order identification without complex tracking systems. Using as many trays as needed provides flexibility for orders of any size.

**System Impact:**
- QMF generates order tray labels with Data Matrix codes and order numbers
- QMF tracks order completion status (not physical tray count or locations)
- Module Data Matrix code system determines which tray modules go into
- System transitions orders to "Process" (wc-process) when all modules complete

**PM Actions:**
- PM prints order tray labels when production starts on new order
- Production staff uses labels to mark order trays
- No manual tray organization or tracking required

---

#### Rule 21: Order Completion Tracking & Shipping System Integration
**Statement:** QMF shall track module completion status for each order and transition orders to "Process" (wc-process) when all modules are complete, integrating with the shipping batch system for order fulfillment.

**Details:**

**Module Completion Tracking:**
- QMF tracks which modules have been built for each order
- Tracks completion across multiple production batches
- Aggregates module counts: "150 of 200 modules complete"
- Automatically transitions order to "Process" (wc-process) when all modules complete

**Order Status Example:**
```
Order 12345 Module Production:
  ATOMa modules: 175/175 complete ✅
  NORDa modules: 25/25 complete ✅
  Total: 200/200 modules complete

Status: Process (wc-process) - waiting for shipping batch
```

**Shipping Batch System Integration:**
- Shipping batch system queries QMF via API for module completion status
- QMF confirms "all modules complete" before shipping processes order
- Shipping system handles order retrieval and packaging workflow
- Module Data Matrix codes used for validation during packaging

**Physical Workflow:**
```
1. QMF transitions Order 12345: wc-in-production → wc-process
   ↓
2. Shipping batch system queries QMF: "Are all modules complete for Order 12345?"
   ↓
3. QMF responds: "Yes - 200/200 modules complete"
   ↓
4. Shipping batch system transitions order: wc-process → wc-processing (Ready to Ship)
   ↓
5. Shipping staff retrieves Order 12345 tray(s) from Order Tray rack
   (Trays labeled with Data Matrix code + order number - easy to find)
   ↓
6. Staff scans module QR codes during packaging for verification
   ↓
7. Order ships when all items packaged
```

**Rationale:**
QMF's role is tracking module production completion status and providing this information to the shipping system. Physical tray retrieval is trivial (few dozen orders, QR-coded trays) and handled by shipping staff. Module QR codes handle all verification during packaging.

**System Impact:**
- QMF tracks module completion counts by order
- QMF provides API endpoint for shipping system to query completion status
- QMF transitions orders to "Process" (wc-process) when all modules complete
- Shipping batch system transitions orders to "Processing" (wc-processing/Ready to Ship) when ready
- No tray location tracking needed (QR codes + small order volume)

**PM Actions:**
- PM monitors order completion status in QMF dashboard
- System automatically notifies shipping when orders complete (via status change)

---

#### Rule 22: Batch Labeling Conventions
**Statement:** All production batches shall use standardized labeling conventions to ensure consistent identification across production, storage, and fulfillment processes.

**Details:**

**Batch Number Format:**
```
Sequential numbering: Batch 1, Batch 2, Batch 3, ... Batch 9999
- Simple, unambiguous
- No date encoding (date tracked separately)
- No base type encoding (base type displayed on label)
```

**Production Batch Sheet Label:**
```
╔═══════════════════════════════════════╗
║      PRODUCTION BATCH #47             ║
║      ATOMa Base Type                  ║
║      Created: Nov 14, 2025            ║
║      PM: John Smith                   ║
╠═══════════════════════════════════════╣
║ Orders Included:                      ║
║   Order 12345: 25 modules             ║
║   Order 12346: 50 modules             ║
║   Order 12347: 10 modules             ║
║                                       ║
║ Total Modules: 85                     ║
║ Est. Build Time: 3 hours              ║
╠═══════════════════════════════════════╣
║ Component Stock Reserved:             ║
║   ✅ ATOMa Base: 85                   ║
║   ✅ LED L1C2-DRD: 200                ║
║   ✅ LED XHP35B: 140                  ║
║   ✅ 0R Resistor: 340                 ║
╚═══════════════════════════════════════╝
```

**Storage Tray Label:**
```
╔═══════════════════════════════════════╗
║      BATCH #47 - ATOMa                ║
║      Completed: Nov 14, 2025          ║
╠═══════════════════════════════════════╣
║ Storage: Rack B, Shelf 2              ║
║                                       ║
║ Modules by Order:                     ║
║   Slots 1-25:   Order 12345 (25)      ║
║   Slots 26-75:  Order 12346 (50)      ║
║   Slots 76-85:  Order 12347 (10)      ║
║                                       ║
║ Total: 85 modules                     ║
╚═══════════════════════════════════════╝
```

**Module-Level Labels (individual modules):**
```
Individual module labels (printed after build):
  Order #12345
  SKU: ATOMa-L1C2-DRD-001
  Batch #47 | Module 3 of 25
  Built: Nov 14, 2025
```

**QR Code Integration:**
- Batch number QR Code on all labels
- Order number QR Code on storage tray sections
- Module SKU QR Code on individual modules (if required)

**Rationale:**
Consistent labeling prevents confusion, enables QR Code scanning, supports accurate inventory tracking, and ensures modules can be located quickly during shipping.

**System Impact:**
- QMF generates printable labels in standard formats
- QR Code generation for scanning integration
- Label templates stored in database for easy updates

**PM Actions:**
- PM prints batch labels when creating batch
- PM prints storage tray labels when batch completes
- PM ensures labels legible and securely attached

---

### Category 7: Edge Cases & Conflicts

#### Rule 23: Handling Component Shortages During Batching
**Statement:** When component stock becomes insufficient during batch creation (e.g., another order created, stock adjusted, counting error), the system shall alert PM and provide resolution options.

**Details:**

**Shortage Detection:**
```
Scenario: PM creating Batch 47 (ATOMa, 100 modules)

Step 1: PM reviews recommended modules
  - Component check: ✅ All components available (100 bases, 400 LEDs)

Step 2: PM adds modules to batch composition

Step 3: Meanwhile, another order placed needing ATOMa bases
  - Stock decreases: 100 bases → 90 bases available

Step 4: PM clicks "Create Batch"
  - Component recheck before final creation
  - System detects: ❌ Only 90 bases available, need 100

System Response:
  ⚠️ Component Shortage Detected

  LED Base ATOMa:
    Required: 100
    Available: 90
    Shortage: 10

  Resolution Options:
    1. Reduce batch size to 90 modules [Auto-adjust]
    2. Wait for stock replenishment (ETA: [date])
    3. Proceed anyway (mark 10 modules as back-ordered)
    4. Cancel batch creation
```

**Resolution Option Details:**

**Option 1: Auto-Adjust Batch Size**
- System removes lowest-priority modules to fit available stock
- Recalculates batch composition
- PM reviews and approves adjusted batch

**Option 2: Wait for Stock**
- Save batch composition as "draft"
- Monitor component arrivals
- Alert PM when stock sufficient

**Option 3: Proceed with Back-Order**
- Create batch with 90 buildable modules
- Mark 10 modules as "pending stock"
- Production builds 90 now, holds batch open for remaining 10
- When stock arrives, complete final 10 modules

**Option 4: Cancel**
- Discard batch creation
- Release any soft-locked components
- PM can retry later

**Shortage During Production:**
```
Scenario: Batch 48 created, production started, then component quality issue discovered

PM Actions:
  1. Identify defective components
  2. Remove from batch (reduce quantity)
  3. Order replacement components
  4. Options:
     a) Complete partial batch, create new batch for remaining modules
     b) Hold batch open until replacements arrive
     c) Substitute compatible components if available
```

**Rationale:**
Component shortages can occur between batch planning and creation due to timing, other orders, or stock counting errors. System must detect shortages before production begins and provide PM with flexible resolution options.

**System Impact:**
- Component availability rechecked immediately before batch creation (final validation)
- Batch creation supports "draft" state for saved-but-not-created batches
- Shortage alerts include ETA for stock replenishment if known

**PM Actions:**
- PM reviews shortage alert and selects resolution option
- PM coordinates with inventory team on stock arrivals
- PM decides whether to wait or proceed with partial batch

---

#### Rule 24: Large Order Splitting Strategy
**Statement:** When an order requires significantly more modules than recommended batch size, the PM shall split the order into multiple batches using a strategic approach that balances production efficiency and order completion timing.

**Details:**

**Large Order Threshold:**
- Large order threshold: Configurable by PM (default: >200 modules)
- Very large order threshold: Configurable by PM (default: >500 modules)
- PM evaluates splitting strategy based on order size, promised date, and current production load
- System uses configured thresholds to flag orders for split consideration

**Splitting Strategies:**

**Strategy A: Time-Based Splitting**
```
Order 12345: 1000× ATOMa modules
Promised Date: 30 days from now
Build Time: 1000 modules = ~8 days

PM Strategy: Split into 5 batches of 200 modules each
  Week 1: Batch 47 (200 modules)
  Week 2: Batch 52 (200 modules)
  Week 3: Batch 58 (200 modules)
  Week 4: Batch 63 (200 modules)
  Week 5: Batch 68 (200 modules)

Rationale: Spreads work across 5 weeks, avoids overloading production capacity
```

**Strategy B: Completion-Focused Splitting**
```
Order 12346: 500× ATOMa modules
Promised Date: 10 days from now
Other orders: Low priority, no urgent dates

PM Strategy: Build all 500 modules ASAP, split into 3 batches for capacity
  Day 1-2: Batch 49 (200 modules)
  Day 3-4: Batch 50 (200 modules)
  Day 5-6: Batch 51 (100 modules)

Rationale: Completes order quickly by dedicating consecutive batches
```

**Strategy C: Mixed Priority Splitting**
```
Order 12347: 800× ATOMa modules (Medium Priority)
Other high-priority orders: Need ATOMa modules

PM Strategy: Interleave large order with high-priority orders
  Batch 54: 200 modules for Order 12347 + 50 modules for Order 12348 (High Priority)
  Batch 56: 150 modules for Order 12349 (Critical Priority)
  Batch 58: 200 modules for Order 12347 + 30 modules for Order 12350 (High Priority)
  Batch 60: 200 modules for Order 12347
  Batch 62: 200 modules for Order 12347

Rationale: Prevents large order from blocking high-priority orders
```

**Component Reservation for Split Orders:**
- All components for large order soft-reserved when order enters queue
- Components transition to hard-lock batch-by-batch
- PM can release portions of reservation if order delayed or cancelled

**Example:**
```
Order 12347: 800× ATOMa modules

Component Reservation:
  Total Required: 800 bases, 3200 LEDs, 3200 resistors

  Batch 54 created: Hard-lock 200 bases, 800 LEDs, 800 resistors
  Remaining soft-reserved: 600 bases, 2400 LEDs, 2400 resistors

  Batch 58 created: Hard-lock another 200 bases, 800 LEDs, 800 resistors
  Remaining soft-reserved: 400 bases, 1600 LEDs, 1600 resistors

  (Pattern continues until all modules batched)
```

**Rationale:**
Large orders must be split to avoid overwhelming production capacity and preventing other orders from processing. Strategic splitting balances order completion timing with manufacturing efficiency and priority management.

**System Impact:**
- Large order detection triggers PM notification: "Order 12345 requires 1000 modules. Recommend splitting into multiple batches."
- Component reservation supports partial hard-locks (batch-by-batch)
- Order completion tracking aggregates across all batches

**PM Actions:**
- PM evaluates large order splitting strategy based on business priorities
- PM decides batch sizes and scheduling for split orders
- PM monitors completion progress across multiple batches
- PM adjusts strategy if priorities change during production

---

### Production Batch Creation Rules Summary

These 24 rules provide a comprehensive framework for module-focused production batch creation that:

✅ **Solves the solder mask changeover problem** (Rule 1: Single base type per batch)
✅ **Maintains manufacturing efficiency** (Rules 2-4: Cross-order batching, optimal sizing, array optimization)
✅ **Respects order priorities** (Rules 5-8: Priority calculation using existing ACF fields, selection sequence, component availability)
✅ **Protects component allocation** (Rules 9-12: Soft reservation, hard locks, reallocation, stalled batch detection)
✅ **Tracks order completion** (Rules 13-16: Partial completion tracking, status integration, multi-batch management, notifications)
✅ **Provides PM control** (Rules 17-19: Priority adjustment, module exclusion, manual composition)
✅ **Enables efficient fulfillment** (Rules 20-22: Tray organization, shipping integration, labeling)
✅ **Handles edge cases** (Rules 23-24: Component shortages, large orders)

The system balances **manufacturing efficiency** (batching by base type) with **order fulfillment priorities** (high-priority orders first) while providing **PM flexibility** (priority adjustments, component reallocation) and **clear tracking** (multi-batch order completion).

---

## Module Serial Number, CSV File Generation & Base Engraving Process

### Module Serial Number

- Every LED module will be assigned a permanent, unique 8-digit numeric Serial Number that is laser engraved onto each LED module
- Module GUIDs must be perpetually unique - we should never ship two modules with the same Serial Number
- Assigned GUIDs will be permanently saved in a WordPress DB table
- GUIDs can never be reused - if a module is not completed or damaged during production, the Serial Number is simply abandoned
- A QR or Data Matrix Code encoded with our LED module domain followed by the Serial Number (e.g., `https://quadi.ca/00123456`) will be engraved onto the carrier tab

**Module Serial Number Format**
- 8-digit numeric string (e.g., `00123456`)
- Range: 00000001 to 01048575 (~1 million unique values)
- Numeric-only format required for compatibility with the [Quadica 5x5 Micro-ID code](../reference/quadica-micro-id-specs.md) engraved directly on each module
- The Serial Number conveys no additional information beyond being a unique identifier

**Module Serial Number Purpose**
- This unique code will be used to identify complete details about the LED module, including (but not limited to):
    - The Module ID (base type and LED configuration)
    - The LEDs mounted to the base
    - The location and orientation of each LED mounted to the base
    - The production batch ID
    - When the module was built (uses the date of the production batch)
    - The order number
    - LED orientation drawing (created by another process) used by production staff for positioning the LEDs onto the base
    - Other useful information to be defined when the PRD document is created
- Production staff will use the Serial Number/Data Matrix code to find and view details about each module using the WordPress admin, tablet or smartphone
- LED placement information for each module will be accessed by the Laser Projection system to display the SKU, mounting position and orientation directly onto the base at the time that the LEDs are positioned on the base by production staff
- Additional details about the Laser Projection system to be defined in the PRD
- A public facing landing page will be provided that will display complete details for an LED module when the Serial Number URL entered. Additional details about the public landing page will be defined in the PRD
- Details about each LED module are permanently stored so information can be retrieved at any time by anyone with a valid Serial Number or module URL

**Module Serial Number Generation**
- The Serial Number is generated for each LED module when the production batch has been created and confirmed correct
- Generation ensures uniqueness by checking against all previously assigned GUIDs in the database

### CSV Engraving File

- The QMF will generate a CSV file that is used to laser engrave the Module Serial Number and QR code onto the module
- The CSV file will contain one row for each LED module in the batch
- The order of the rows in the CSV file should be optimized so that unique LED SKUs are grouped together with the objective of minimizing LED retrieval and handling during the production process
- Each row in the CSV file will include the following fields for each module:
  - Production Batch ID
  - Module ID (e.g., `STAR-34924`)
  - Order Number
  - Module Serial Number (8-digit numeric)
- Any number of rows can be included in the CSV file
- The generated CSV file is saved to the `Quadica\Production\production list.csv` file on our Google shared drive, over-writing the existing file if one exists
- The QMF will include functionality that allows production staff to re-generate a CSV file for a previous production batch at any time

### Base Engraving Process

- The generated CSV file will be used by custom Python software to engrave the Module Serial Number and Data Matrix code onto each base using our Cloudray UV Laser Engraver
- Other than producing the CSV file from the production batch, the QMF will have no interaction with the process that engraves the Serial Number or Data Matrix code onto the base
- The UV Laser engraving software will manage the process of generating the Data Matrix Code that is engraved onto the carrier tab

---

## The Production Process
TBC

## Open Questions & Still To Explore

1. **Product Labels System**
   - What information must be on labels?
   - Individual modules or shipping boxes?
   - Label size and printer type?
   - Barcode/QR code requirements?
   - Integration with shipping system?

2. **Batch Report Format**
   - Digital tablet view details
   - What information is essential vs nice-to-have?
   - Print option still needed for backup?
   - How much detail in component lists?

3. **Component Availability Alerts**
   - Just visual indicators in QMF?
   - Or also email/notification when components arrive?
   - Threshold alerts for low stock?

4. **Batch Completion Workflow**
   - How does "mark complete" update order status?
   - Integration with shipping workflow?
   - What happens to partial orders?
   - Hold area management details?

5. **Historical Batch Data**
   - How much history to show?
   - Reporting requirements?
   - Export/analytics needed?

6. **Mobile/Tablet Optimization**
   - Screen sizes to support?
   - Touch interface details?
   - Offline capability needed?

7. **Legacy Data Handling**
   - Should we keep historical batch data from old system?
   - How to handle transition from old system to new?
   - What data needs to be migrated vs archived?

8. **User Interface Mockups**
   - QMF dashboard layout
   - Tablet batch view
   - Component status widgets
   - Priority management interface

9. **Integration Points**
    - How does LMB plugin feed data to QMF?
    - Quadica Purchasing Management (BOM Module) integration
    - WooCommerce order updates
    - Stock level synchronization

---

## Order Processing Flow
The customer defines the LED module they want built by selected a base design and LED(s) that will be mounted to the base  
  ↓  
The customers order is received via our website or created from a customer submitted PO  
  ↓  
Order is reviewed and released for processing  
  ↓  
Non-LED products are ordered if mot available in inventory  
  ↓  
LED Modules are added to one or more production batches as components become available using a set of defined priority rules  
  ↓  
LED Modules are built, tested, inspected and stored in the order's holding tray  
  ↓  
Received Non-LED products are received into inventory  
  ↓  
When all products for an order are available the order is added to a shipping batch  
  ↓  
Order products are packaged, the order packed, shipping label generated and the order shipped  
  ↓  
The order is marked complete

## Conclusion

This discovery session has successfully defined a comprehensive framework for module-focused production batch creation. Through iterative refinement and deep exploration of actual production workflows, we've arrived at 24 well-defined rules that balance manufacturing efficiency with order fulfillment priorities.

**The Vision:** A unified Quadica Manufacturing system that handles both production planning (queue management, component reservation, priority optimization) and production execution (batch management, digital instructions, progress tracking) in a single integrated interface.

**Key Architectural Decisions:**

1. **Module-Focused Batching** - Group modules by base type across multiple orders, solving the solder mask changeover bottleneck
2. **Array/Fret Optimization** - Prefer complete arrays (15-up ATOMa, 8-up NORDa) but allow partial arrays for high-priority orders
3. **Two-Tier Component Reservation** - Soft reservation (PM can reallocate) vs Hard Lock (in active batch, cannot reallocate)
4. **Physical Constraints First** - Single reflow cycle means ALL components must be available before production (non-negotiable physics)
5. **Existing ACF Field Integration** - Leverage `order_expedite` (PM manual) + new `order_paid_expedite` (customer paid) for priority calculation
6. **QR-Code-Driven Workflows** - Modules and trays use QR codes for automatic identification and sorting
7. **Production Capacity Focus** - No artificial tray limits; constrained by single-day build capacity
8. **Simple Batch Priority** - Production builds batches by number (lower batch# first); no module prioritization within batches
9. **PM Strategic Control** - Full visibility with ability to adjust priorities, reallocate components, and manually compose batches
10. **Shipping Integration** - QMF tracks completion status and provides API; shipping system handles order fulfillment

**The Path Forward:**

This discovery document provides the foundation for formal Product Requirements Document (PRD) development. The 24 rules define WHAT the system must do; the PRD will define HOW to build it, including:
- User interface design and workflows
- Database schema for batch tracking
- API endpoints for component reservation and batch management
- Integration points with WooCommerce order system
- Production reporting and analytics

**The Approach:** Gradual migration alongside the legacy system, allowing validation and confidence-building before full cutover, with zero risk to ongoing operations.

---

**Document Status:** Discovery Complete - Batch creation rules defined and refined

**Next Action:** Proceed to formal PRD development based on these 24 rules

---

# Appendix: Historical Reference Material

# Quadica Manufacturing - Reference

# REFERENCE MATERIAL ONLY FROM PREVIOUS EXPLORATIONS

**Note:** The content below represents earlier explorations of order-focused batching approaches. This material is preserved for reference and context but has been superseded by the module-focused rules defined above.

---

## 1. The Problems We Are Trying To Solve

### Current State Issues

The existing Order Management (OM) system (`/om` directory) has deep legacy problems:
- Originally built for 3dCart e-commerce platform 10+ years ago
- Minimally adapted when we migrated to WooCommerce several years ago
- Never properly integrated with WC
- Numerous undocumented changes over the years
- Serious security issues with the code
- Batch generation process is complex and flawed in many ways

**Key Flaw: One-Time Calculation vs Continuous Visibility**

The current system generates a **snapshot** when PM clicks "Generate Batch":
- System calculates what can be built based on rules
- Shows only buildable items
- PM selects from this list and creates batch
- **Then visibility is gone** - PM doesn't see the full picture

**What PM Doesn't See:**
- Why other modules aren't buildable
- Complete component inventory status
- What's blocking specific orders
- Overall production pipeline status
- Impact of priority changes on buildability

### The Vision

The **Quadica Manufacturing** that provides continuous visibility:
- Shows EVERYTHING that needs to be built (not just buildable items)
- Real-time component availability
- Active batch status
- Why modules are blocked
- Enables strategic decision-making vs just executing system calculations
- Changes from a modules that can be built system to an orders that can be built system

**Fundamental Shift:**
- FROM: "System tells me what to build" → generates one-time snapshot
- TO: "Show me everything" → continuous visibility, PM decides
- FROM: "Show my all modules that can be built for all orders" → generates a batch that contains modules from multiple orders
- TO: "Show my orders where all modules can be built for the order" → generates a batch that contains modules that can be built for a single order

---

## 2. Order-Based Batch Creation Strategy

### Core Strategy Shift

**FUNDAMENTAL CHANGE:** The new QMF system moves from a **module-focused** batch generation approach to an **order-focused** approach. This represents a complete architectural change from the legacy system.

**Legacy (Module-Based):**
- "Show me all modules that can be built across all orders"
- Generate batch containing modules from multiple different orders
- Modules grouped by type for manufacturing efficiency
- Orders fulfilled piecemeal as their modules are built

**New (Order-Based):**
- "Show me orders where all (or most) modules can be built"
- Generate batch containing modules from a SINGLE order only
- Build complete orders together
- Orders fulfilled as complete units

**Why This Matters:**
- Simplifies order fulfillment (complete orders ship together)
- Reduces module tracking complexity (all modules for one customer)
- Eliminates cross-order allocation problems
- Better aligns with customer expectations (complete order delivery)

---

### Batch Creation Rules

#### Rule 1: Single Order Per Batch
**Statement:** A production batch will only include modules from a single WooCommerce order.

**Details:**
- Each batch is associated with exactly one order ID
- Batch naming: "Order 12345 Batch 1" (if multiple batches needed)
- Components reserved are allocated to that order
- Production staff know all modules in batch go to one customer

**Rationale:** Simplifies fulfillment, reduces tracking complexity, eliminates cross-order component conflicts.

---

#### Rule 2: Complete-Order Batching (Default)
**Statement:** By default, orders are only considered for batch creation if there is enough component stock to build ALL modules in the order.

**Details:**
- System calculates component requirements for entire order
- Order marked "Fully Buildable" only if ALL components available for ALL module types
- PM queue defaults to showing only fully buildable orders
- PM can filter to see partially buildable or blocked orders

**Buildability Display:**
```
Order Status Indicators:
✅ Fully Buildable - All components available for all modules
⚠️ Partially Buildable (X of Y module types) - Some modules can be built
   → Expandable detail shows which modules buildable/blocked
   → Shows which specific components are blocking
❌ Not Buildable - Missing components for all modules
   → Shows blocking components for each module type
```

**Rationale:** Ensures orders ship complete, prevents partial fulfillment complexity, maintains customer satisfaction.

---

#### Rule 3: Partial Order Batching (PM Override)
**Statement:** The PM may override the default and create a batch for an order even when only some modules can be built due to component shortages.

**Business Justification - Example Scenario:**
```
Customer Order 12345:
  - 20× SP-08-E6W3 (buildable - components available)
  - 500× SP-08-394D (buildable - components available)
  - 2× SP-07-3483 (NOT buildable - components on order, 13-day lead time)

Promise Date: 15 days from now
Build Time: 522 modules = ~5 days
Component Arrival: Day 13

Decision Logic:
- If we wait for SP-07-3483 components, we have only 2 days to build 522 modules
- Building 522 modules in 2 days is nearly impossible
- Building 520 modules now (days 1-5) then 2 modules later (day 14) = on-time delivery
- Waiting = guaranteed late shipment

PM Decision: Create partial batch now for 520 buildable modules
```

**When PM Should Use This:**
- Large order with small blocking component
- Long lead time for missing components would delay entire order
- Build time for buildable modules exceeds remaining time after component arrival
- Customer promised date at risk if we wait

**When PM Should NOT Use This:**
- Small order (not worth the complexity of multiple batches)
- Short lead time for missing components
- Plenty of time to build after components arrive
- Missing components are high proportion of order

---

#### Rule 3a: Multiple Batches Per Order
**Statement:** One order can have multiple batches. Each batch is marked complete when all modules IN THAT BATCH are built. Order completion requires ALL batches for that order to be complete.

**Details:**
- Batch naming convention: "Order 12345 Batch 1", "Order 12345 Batch 2", etc.
- Each batch tracks its own completion status independently
- Order not marked complete until all batches complete
- System displays: "Batch 1: 520/520 complete, Batch 2: 0/2 pending"

**Example Scenarios:**

**Scenario A: Partial Order Build**
```
Day 1: Create Batch 1 for Order 12345 (520 modules - buildable now)
Day 5: Batch 1 complete, modules in Order 12345 tray(s)
Day 13: Components arrive for remaining 2 modules
Day 13: Create Batch 2 for Order 12345 (2 modules)
Day 14: Batch 2 complete
Day 14: Order 12345 fully complete, ready for shipping
```

**Scenario B: Large Order Split**
```
Order 12345: 5000× Module A (estimated 4 weeks build time)
Batch 1: 1000 modules (Week 1)
Batch 2: 1000 modules (Week 2)
Batch 3: 1000 modules (Week 3)
Batch 4: 1000 modules (Week 4)
Batch 5: 1000 modules (Week 4)
All batches linked to Order 12345
Order complete when all 5 batches complete
```

---

#### Rule 4: Order Tray Storage (Simple)
**Statement:** When production completes modules in a batch, they are stored in one or more trays labeled with the order number. All order trays are kept in a single rack for shipping to reference.

**Details:**
- **No bin assignment system needed** - low volume operation (5-30 orders in progress, usually <5)
- **Production staff labels trays** with order number when placing completed modules
- **Single storage rack** contains all order trays
- **Shipping references order number** on tray labels to find modules

**Physical Process:**
```
Batch Complete
  ↓
Production places modules in tray(s)
  ↓
Production writes "Order 12345" on tray label
  ↓
Tray(s) placed in storage rack
  ↓
When all batches complete → Order ready for shipping
  ↓
Shipping finds "Order 12345" trays in rack
  ↓
Ship order, remove/reuse trays
```

**Tray Labeling:**
```
Simple handwritten or printed labels:
┌─────────────────────┐
│   Order 12345       │
│   Customer: Acme    │
│   Modules: 520      │
└─────────────────────┘
```

**Multiple Trays for Large Orders:**
- Production uses as many trays as needed
- Each tray labeled with same order number
- Example: Order 12345 might use 3 trays, all labeled "Order 12345"

**System Tracking:**
- System tracks: "Order 12345 - modules completed and in storage"
- System does NOT track: specific tray IDs, tray locations, tray numbers
- System flags: "Order 12345 ready for shipping" when all batches complete

**Why This Works:**
- Low volume: 5-30 active orders (usually <5)
- Small facility: Single rack visible to everyone
- Order numbers are unique and easy to find
- No complex warehouse management needed

---

#### Rule 5: Order Storage Lifecycle
**Statement:** Completed modules for an order remain in labeled trays in the storage rack until shipping confirms the order has been shipped.

**Storage Lifecycle:**
```
First Batch Completes
  ↓
Production places modules in tray(s), labels with order number
  ↓
Tray(s) in storage rack (status: "Partial" if more batches needed)
  ↓
Additional batches complete → Add to existing trays or new trays (same order label)
  ↓
All batches complete → System marks "Order 12345 Ready for Shipping"
  ↓
Shipping pulls tray(s) labeled "Order 12345"
  ↓
After shipping confirms → Trays reused for other orders
```

**WooCommerce Order Statuses (QMF Perspective):**
- **"New" or "Process"** - Order in queue, waiting for PM to create batch
- **"In Production"** (wc-in-production) - One or more batches in progress or complete, order not fully done
- **"Process"** (wc-process) - All production batches complete, modules ready for shipping batch system
- **"Ready to Ship"** (wc-processing) - Set by shipping batch system (NOT QMF) when entire order ready
- **"Shipped"** (wc-completed) - Order shipped, trays available for reuse

**Note:** QMF does not create intermediate statuses for partial completion. Orders remain in "In Production" until all production batches are complete, then automatically return to "Process" status.

**Special Cases:**

**Order Cancelled:**
- Completed modules remain in trays labeled with order number
- PM decides disposition (reallocate, scrap, hold)
- Trays reused after PM decision

**Order Quantity Reduced:**
- Excess modules remain in same trays
- PM decides what to do with excess (see Rule 9)
- No need to move trays

**Order Quantity Increased:**
- Additional batches create more modules
- Add to existing trays or use additional trays with same order label
- All trays stay together in rack

---

#### Rule 6: Component Reservation System
**Statement:** The component reservation system (soft reserve / hard lock) works at the order level. Components are automatically soft-reserved when orders enter the production queue, then hard-locked when batches are created.

**Automatic Soft-Reservation Trigger:**
- When a WooCommerce order reaches "New" (wc-on-hold), "Process" (wc-process), or "Hold" (wc-hold) status
- System automatically calculates component requirements from order line items
- System soft-reserves components for that order
- See "WooCommerce Order Status Integration" section below for complete workflow

**Reservation Tiers:**

**Tier 1: Soft Reservation (Order Level - Automatic)**
```
Status: Components reserved for an order but no batch created yet
Trigger: Automatic when order enters production queue
Purpose: Prevents component poaching by higher priority orders
PM Can: Reallocate components to higher priority order (with warning)
Used For: Full orders waiting to be batched, partial order unbuildable modules
```

**Tier 2: Hard Lock (Batch Level)**
```
Status: Components allocated to an active production batch
Purpose: Protects in-progress work from reallocation
PM Cannot: Steal components from active batch
Production Staff Can: Adjust batch quantity (releases components)
Used For: Batches with status "In Progress"
```

**Example - Full Order Reservation:**
```
Order 12345 placed by customer
System automatically soft-reserves all components for Order 12345
Order sits in queue, PM hasn't created batch yet
Another order arrives with higher priority
PM can reallocate soft-reserved components from Order 12345 (with warning)
```

**Example - Partial Order Reservation:**
```
Order 12345: 20× A, 500× B, 2× C
Components available for A and B only (C on backorder)
PM creates Batch 1 for A + B modules
System:
  - Hard locks: Components for A + B (batch in progress)
  - Soft reserves: Components for C (waiting on stock)
When C components arrive:
  - System flags: "Order 12345 can now be completed"
  - PM creates Batch 2 for C modules
  - Soft reservation becomes hard lock
```

---

#### Rule 6a: Component Reservation for Partial Order Batches
**Statement:** When PM creates a partial batch for an order, components for unbuildable modules are soft-reserved to prevent other orders from consuming them when they arrive.

**Details:**
- Buildable modules → Hard lock (in batch)
- Unbuildable modules (waiting on stock) → Soft reserve
- PM can explicitly release soft reservations for higher priority orders
- System warns PM if releasing will delay order completion

**Example:**
```
Order 12345 (promise date: 15 days)
  - 520 modules buildable → Batch 1 created → Hard lock
  - 2 modules waiting on components → Soft reserve for future

Day 10: Higher priority order arrives needing same components
PM attempts to create batch
System warns:
  ⚠️ COMPONENT REALLOCATION WARNING

  Order #45678 (new, high priority) needs 10× Component X

  Current Allocation:
  • Order 12345: 2× soft-reserved (waiting on stock arrival)

  If you proceed:
  • Order 12345 will not have reserved components
  • When components arrive, Order #45678 has priority
  • Order 12345 may be delayed

  [Cancel] [Proceed and Reallocate]

PM Decision: Proceed (new order is more urgent)
```

---

#### Rule 7: Order Priority Management
**Statement:** Like the module-based system, the PM has the ability to reprioritize orders so that components are reallocated from lower priority orders to allow higher priority orders to be built.

**Priority Scope:**
- Priority calculated at **order level** (not individual module level)
- All modules in an order inherit the order's priority
- PM can manually override order priority
- Component allocation follows order priority sequence

**Priority Factors (same as before):**
1. PM Manual Override (highest)
2. Order Expedite Value
3. Days Past Promised Date
4. Almost Due Boost (within 2 days of promise date)
5. Order Age

**Reallocation Rules:**
- Higher priority orders can reallocate **soft-reserved** components from lower priority orders
- Higher priority orders **cannot** reallocate **hard-locked** components (in active batches)
- System shows impact warnings when reallocating
- PM confirms reallocation understanding the consequences

---

#### Rule 8: Module Reallocation Between Orders
**Statement:** Completed modules allocated to an order can be reallocated to another order by the PM, subject to eligibility constraints.

**Why This Is Needed:**
- Order cancelled after modules built
- Customer reduces quantity
- Higher priority order needs same module configuration
- Excess inventory from overbuilds

**Eligibility Constraints (Rule 8a):**
- ✅ Module not yet shipped
- ✅ Module SKU and configuration match target order exactly
- ✅ No custom build notes specific to original customer
- ❌ Cannot reallocate custom-configured modules
- ❌ Cannot reallocate after shipping process started

**Reallocation Process (Rule 8b):**
```
PM Action: Reallocate 50× Module A from Order 12345 to Order #67890

System Creates Audit Record:
  - from_order: 12345
  - to_order: #67890
  - module_sku: Module A
  - quantity: 50
  - reason: "Order 12345 quantity reduced, #67890 expedited"
  - timestamp: 2025-11-13 14:30:00
  - pm_user: Ron Warris

System Updates:
  - BOM order_id: Remains 12345 (preserves build history)
  - Module tray location: Moved from Order 12345 tray(s) to Order #67890 tray(s)
  - Order 12345 completion: Recalculated (50 fewer modules needed)
  - Order #67890 completion: Recalculated (50 modules closer to complete)
  - Component reservations: Remain against Order 12345 (accounting simplicity)
```

**PM Interface:**
```
Select modules to reallocate: [✓] 50× Module A
Source Order: 12345
Target Order: [ Select Order ▼ ] → Shows eligible orders (same SKU needed)
Reason: [___________________________________]
[Cancel] [Reallocate Modules]
```

**Restrictions (Rule 8c):**
- PM must have "reallocate_modules" capability
- Reallocation must have documented reason
- Cannot reallocate modules with custom assembly notes
- Cannot reallocate after shipping started

---

### Order Change Management

#### Rule 9: Order Quantity Reduction
**Statement:** When a customer reduces the order quantity after batch creation, the system flags the order for PM action rather than making automatic changes.

**System Response:**
```
⚠️ ORDER QUANTITY REDUCED - REQUIRES PM ACTION

Order 12345 quantity changed:
  - Original: 500× Module A
  - New: 300× Module A
  - Reduction: 200× Module A

Current Batch Status:
  - Batch 1: 350 complete, 150 in progress (total 500)

PM Options:
  [A] Stop batch at 300, mark 200 excess for reallocation
  [B] Complete batch anyway (350 already done), 200 available for future orders
  [C] Reduce batch to 300 (stop building remaining 150 in progress)

Decision: [___]
Notes: [___________________________________]
```

**PM Considerations:**
- How far along is production?
- Cost of stopping vs completing
- Likelihood of reallocating excess to other orders
- Customer relationship factors

---

#### Rule 10: Order Quantity Increase
**Statement:** When a customer increases order quantity after the original order is placed, the increase is treated as a new line item on the existing order.

**System Response:**
```
✅ ORDER QUANTITY INCREASED

Order 12345 quantity changed:
  - Original: 500× Module A
  - New: 750× Module A
  - Addition: 250× Module A

Current Status:
  - Batch 1: 500× Module A (in progress)

System Action:
  - Flagged for additional batch creation
  - PM can create Batch 2 for 250× Module A when ready
  - Both batches linked to Order 12345
  - Order not complete until both batches complete
```

---

#### Rule 11: Order Cancellation
**Statement:** When an order is cancelled after batch creation, the system flags the batch and built modules for PM action rather than making automatic changes.

**System Response:**
```
❌ ORDER CANCELLED - REQUIRES PM ACTION

Order 12345 has been cancelled
Customer: Acme Corp
Reason: Project cancelled

Current Status:
  - Batch 1: 300 modules complete (in tray)
  - Batch 1: 200 modules in progress

PM Decisions Required:

1. Completed modules (300):
   [A] Move to "Available for Reallocation" pool
   [B] Scrap (if customer-specific configuration)
   [C] Hold pending customer negotiation

2. In-progress modules (200):
   [A] Stop production, release components
   [B] Complete production (if nearly done)

3. Component reservations:
   [A] Release all soft-reserved components immediately
   [B] Release hard-locked components if production stopped

4. Batch status:
   [A] Mark batch as "Cancelled - Partially Complete"
   [B] Archive batch with cancellation notes

PM Notes: [___________________________________]
```

---

#### Rule 12: Order Splitting (Backorders)
**Statement:** When an order is split into multiple WooCommerce orders (e.g., for partial shipment), the system flags for PM action to allocate batches and modules between the split orders.

**Scenario:**
```
Original: Order 12345 for 1000× Module A (promise date: 14 days)
Day 5: Customer requests split shipment
  - Ship 500 now (urgent project)
  - Ship 500 in 30 days (next phase)
WooCommerce creates:
  - Order 12345 (original - now 500 modules)
  - Order #12346 (backorder - 500 modules)

Current Status:
  - Batch 1: 400 modules complete
  - Batch 2: 600 modules in progress
```

**System Response:**
```
⚠️ ORDER SPLIT DETECTED - REQUIRES PM ACTION

Original Order 12345 split into:
  - Order 12345: 500× Module A (urgent)
  - Order #12346: 500× Module A (backorder)

Current Batches:
  - Batch 1: 400 complete
  - Batch 2: 600 in progress
  - Total: 1000 modules

PM Allocation Decision:
Order 12345 (urgent - 500 needed):
  Batch 1: [400] modules
  Batch 2: [100] modules
  Total: 500 modules

Order #12346 (backorder - 500 needed):
  Batch 2: [500] modules
  Total: 500 modules

[Cancel] [Apply Allocation]
```

**PM Actions:**
- Decides which batches belong to which order
- System updates batch-order associations
- Component reservations recalculated for both orders
- Trays labeled separately for each order

---

### Buildability & Display Rules

#### Rule 13: Order Buildability Calculation & Display
**Statement:** The system calculates and displays buildability at the order level, showing whether an entire order can be built or only portions of it.

**Calculation Logic:**
```
For each order:
  For each module type in order:
    For each component in module BOM:
      Check available component stock

  If ALL components available for ALL module types:
    Status = "Fully Buildable" ✅
  Else if SOME module types have all components:
    Status = "Partially Buildable (X of Y module types)" ⚠️
  Else:
    Status = "Not Buildable" ❌
```

**Display Format:**
```
PRODUCTION QUEUE - ORDERS NEEDING PRODUCTION

Filters: [All] [Fully Buildable] [Partially Buildable] [Not Buildable]
Sort By: [Priority ▼]

┌─────────────────────────────────────────────────────────────────────┐
│ ✅ Order 12345 │ Acme Corp │ ⚡HIGH Priority │ 5 days old         │
│ FULLY BUILDABLE - All components available                          │
│ • 20× SP-08-E6W3                                                    │
│ • 500× SP-08-394D                                                   │
│ • 2× SP-07-3483                                                     │
│ [Create Batch] [View Details] [Adjust Priority]                    │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ ⚠️ Order #12346 │ Tech Inc │ NORMAL │ 3 days old                   │
│ PARTIALLY BUILDABLE (2 of 3 module types)                          │
│ ✅ 100× SP-08 base modules (components available)                   │
│ ✅ 50× SP-03 base modules (components available)                    │
│ ❌ 10× ATOM base modules (missing: LED-XYZ)                         │
│    Blocking: Need 30× LED-XYZ, have 15 (short 15)                  │
│    Expected: PO #543 arriving in 7 days                            │
│ [Create Partial Batch] [View Details] [Adjust Priority]            │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ ❌ Order #12347 │ Labs LLC │ ⚡RUSH │ 1 day old                     │
│ NOT BUILDABLE - Missing components for all modules                 │
│ ❌ 200× Module X                                                    │
│    Blocking: LED-ABC (need 600, have 0), PCB-X (need 200, have 50) │
│    Expected: LED-ABC PO #544 arriving in 14 days                   │
│              PCB-X PO #545 arriving in 21 days                      │
│ [View Details] [Adjust Priority] [Soft Reserve Components]         │
└─────────────────────────────────────────────────────────────────────┘
```

**Expandable Details:**
- Click order to expand full component breakdown
- Shows component availability for each module type
- Displays blocking components with quantities
- Links to purchase orders for incoming components

---

### Large Order Management

#### Rule 14: Large Order Batch Sizing
**Statement:** Orders exceeding defined thresholds can be split into multiple batches for better progress tracking and production management.

**Thresholds:**
- Module count > 1000 units, OR
- Estimated build time > 5 days

**PM Options:**
```
Order 12345: 5000× Module A (estimated build time: 4 weeks)

PM Batch Strategy:
[A] Single batch (5000 modules)
    Pros: Simple tracking
    Cons: No progress visibility for 4 weeks

[B] Split by week (4-5 batches)
    Batch 1: 1000 modules (Week 1)
    Batch 2: 1000 modules (Week 2)
    Batch 3: 1000 modules (Week 3)
    Batch 4: 1000 modules (Week 4)
    Batch 5: 1000 modules (Week 4)
    Pros: Progress tracking, flexibility
    Cons: More batch management

[C] Custom split
    PM defines batch sizes manually
```

**System Behavior:**
- All batches linked to same order
- Order not complete until all batches complete
- Components can be reserved for all batches or allocated per batch
- PM decides based on component availability and production capacity

---

### Manufacturing Efficiency

#### Rule 15: Sub-Batch Grouping for Manufacturing Efficiency
**Statement:** When an order contains modules using different base PCBs, the PM can split the order into multiple batches grouped by base type to optimize manufacturing setup time.

**Scenario:**
```
Order 12345:
  - 100× SP-08 base modules (various LED configurations)
  - 5× SP-03 base modules (various LED configurations)

Manufacturing Reality:
  - SP-08 and SP-03 require different PCB handling
  - Switching between bases mid-batch is inefficient
```

**PM Strategy:**
```
Option A: Single Batch (100 + 5 = 105 modules)
  - Production switches between base types
  - Setup time wasted

Option B: Sub-Batches by Base Type
  - Batch 1: All SP-08 modules (100 modules)
  - Batch 2: All SP-03 modules (5 modules)
  - Both linked to Order 12345
  - Production optimized (no base switching)
```

**Implementation:**
- PM creates multiple batches for one order
- Each batch grouped by base PCB type
- Order complete when all sub-batches complete
- Modules still stored in same order tray(s)

---

### Component Arrival During Production

#### Rule 16: New Batches for Newly Buildable Modules
**Statement:** When components arrive making previously unbuildable modules buildable, the PM must create a NEW batch for those modules. The PM cannot add new module SKUs/types to existing batches.

**Important Distinction:**
- **Production Staff CAN:** Adjust quantities of existing modules in a batch (see Section 20)
- **PM CANNOT:** Add new module SKUs/types to an existing batch

**Scenario:**
```
Order 12345:
  - 20× SP-08-E6W3 (buildable now)
  - 500× SP-08-394D (buildable now)
  - 2× SP-07-3483 (NOT buildable - waiting on Component X)

Day 1: PM creates Batch 1 for buildable modules
  Batch 1: 20× SP-08-E6W3 + 500× SP-08-394D (Status: In Progress)

Day 3: Component X arrives for SP-07-3483
  2× SP-07-3483 now buildable

PM Action Required:
  CREATE NEW Batch 2: 2× SP-07-3483 ← Correct

  ❌ CANNOT add SP-07-3483 to Batch 1 (different module type)
```

**Why This Rule Exists:**
- Each batch has a specific module list with quantities
- Production staff are building from that list
- CSV engraving files are generated per batch
- Adding new module types mid-batch disrupts production workflow
- Cleaner tracking: One batch = one module list

**Process:**
1. Components arrive for blocked modules
2. System flags: "Order 12345 - Additional modules now buildable"
3. PM creates new batch for newly buildable modules
4. Both batches linked to same order
5. Order complete when all batches complete

---

### Batch-Order Relationship Tracking

#### Rule 17: Priority Management at Order Level
**Statement:** Priority is calculated and managed at the order level. All modules in an order inherit the order's priority.

**Priority Calculation:**
```
Order Priority Score =
  IF pm_manual_override THEN pm_override_value
  ELSE IF days_past_promised_date > 0 THEN 2000 + days_past_promised_date
  ELSE IF days_until_promised_date <= 2 THEN 1500 + (2 - days_until_promised_date)
  ELSE IF order_expedite_value > 0 THEN 1000 + order_expedite_value
  ELSE order_age_days * 10
```

**Component Allocation:**
- Orders sorted by priority score (highest first)
- Components allocated in priority order
- Higher priority orders can reallocate soft-reserved components from lower priority

**PM Actions:**
- Drag-and-drop to reorder orders (sets manual override)
- Manually enter priority value
- System recalculates component allocation immediately
- Shows what becomes buildable/unbuildable as priorities change

---

#### Rule 18: Batch-Order Relationship Tracking
**Statement:** The system maintains clear relationships between batches and orders, supporting one-to-many relationships (one order, multiple batches).

**Data Structure:**
```
Batch Record:
  - batch_id: Unique identifier
  - batch_number: Sequence within order (1, 2, 3...)
  - order_id: WooCommerce order ID
  - batch_type: "full" | "partial" | "sub-batch" | "additional"
  - status: "in_progress" | "complete" | "cancelled"
  - created_date
  - completed_date
  - module_count_planned
  - module_count_actual
```

**Tracking:**
- Every batch has exactly one associated order
- One order can have multiple batches
- System displays: "Order 12345 (3 batches: 2 complete, 1 in progress)"
- Order completion = all batches for that order marked complete

---

#### Rule 19: Production Completion & Order Status Update
**Statement:** When all production batches for an order are complete, QMF automatically sets the order status back to "Process" to indicate module production is complete and the order is ready for the shipping batch system.

**Workflow:**
```
All batches for Order 12345 complete
  ↓
QMF automatically:
  - Releases hard-locked components
  - Sets order status to "Process" (wc-process)
  - Flags order as "Module production complete"
  ↓
Order now visible to shipping batch system
  ↓
Shipping batch system (separate from QMF):
  - Assembles complete order (modules + accessories + other items)
  - Creates shipping batch
  - Sets order to "Ready to Ship" (wc-processing)
  - Triggers payment capture
  ↓
Shipping creates label and ships order
```

**QMF Production Complete Indicator:**
```
✅ PRODUCTION COMPLETE

Order 12345 - Acme Corp
All production batches complete:
  - Batch 1: 520 modules (complete 11/08)
  - Batch 2: 2 modules (complete 11/13)
Total: 522 modules built

Storage: Trays in rack (labeled "Order 12345")
Module Types: 3 different configurations

Order Status: Automatically set to "Process" (ready for shipping batch)

[View Order Details] [View Batch History]
```

**IMPORTANT:** QMF does not manage the transition to "Ready to Ship" - that is handled by the shipping batch system which knows when ALL order items (modules + non-module items) are ready for shipment.

---

### WooCommerce Order Status Integration

#### Repurposing Existing "In Production" Status

**Status:** `wc-in-production` (Custom Status)

**Current State:**
- Status is defined in WooCommerce via YITH Custom Order Status plugin
- Status is included in legacy OM query filters
- **Status is NOT actively set by legacy OM** (confirmed via code review)
- Safe to repurpose for QMF without breaking existing system

**QMF Will Use This Status:**
- Set when PM creates first batch for an order
- Indicates order has entered active production
- Remains set until all batches for order are complete

---

#### Order Status Workflow for QMF

**Orders That Enter QMF Production Queue:**

The following WooCommerce statuses trigger soft-reservation and appear in QMF:

1. **"New"** (`wc-on-hold`)
   - Order received from website, waiting for admin review
   - Stock allocated (decreased)
   - **QMF Action:** Soft-reserve components automatically
   - **Appears in queue:** Yes, with "New" flag

2. **"Process"** (`wc-process`)
   - Order released for production and shipping
   - Stock allocated (decreased)
   - **QMF Action:** Soft-reserve components automatically (if not already)
   - **Appears in queue:** Yes, ready for batching

3. **"Hold"** (`wc-hold`)
   - Order on hold (waiting for customer confirmation, etc.)
   - Stock allocated (decreased)
   - **QMF Action:** Soft-reserve components but flag as "On Hold"
   - **Appears in queue:** Yes, but visually flagged as "Hold"

**Orders That QMF Should Ignore:**

The following statuses do NOT appear in QMF queue:

- ❌ **"Awaiting Payment"** (`wc-pending`) - Stock deallocated, waiting for wire/check
- ❌ **"Quote"** (`wc-quote`) - Quote stage only
- ❌ **"Scheduled"** (`wc-scheduled`) - Future processing date
- ❌ **"Failed"** (`wc-failed`) - Payment failed
- ❌ **"Declined"** (`wc-declined`) - Credit card declined
- ❌ **"Cancelled"** (`wc-cancelled`) - Order cancelled
- ❌ **"Pending"** (PayPal internal) - PayPal processing
- ❌ **"Draft"** - Phone Orders plugin draft state
- ❌ **"Ready to Ship"** (`wc-processing`) - Already complete, waiting for shipping label
- ❌ **"Shipped"** (`wc-completed`) - Order shipped

---

#### QMF Status Update Rules

**When QMF Creates First Batch for Order:**
```
Current Status: "New" or "Process"
QMF Action: Set order to "In Production" (wc-in-production)
Component Reservation: Soft-reserve → Hard lock (for batched modules)
```

**While Batches Are In Progress or Partially Complete:**
```
Current Status: "In Production" (wc-in-production)
QMF Action: NO status change
Order Remains: "In Production" until ALL batches complete

Example: Order has 3 batches
  - Batch 1: Complete (modules in tray)
  - Batch 2: In Progress
  - Batch 3: Not started yet
  → Order stays "In Production"
```

**When ALL Production Batches for Order Complete:**
```
Current Status: "In Production" (wc-in-production)
QMF Action: Set order back to "Process" (wc-process)
Rationale: Module production complete, order ready for shipping batch system
Component Reservation: Hard lock released (modules built)
PM Action: None required - automatic when last batch marked complete

IMPORTANT: QMF does NOT set "Ready to Ship" status!
- Orders may contain non-module items (accessories, power supplies, etc.)
- Shipping batch system (separate from QMF) handles final order assembly
- "Ready to Ship" triggers payment capture - only shipping system should set this
```

**When Order is Cancelled:**
```
Current Status: Any production status
WooCommerce Action: Admin sets to "Cancelled" (wc-cancelled)
QMF Action:
  - Release all soft-reserved components
  - Flag batches as "Order Cancelled - Requires PM Action"
  - PM decides disposition of any completed modules
Component Reservation: All released
```

---

#### Status Transition Diagram

```
Customer Places Order
  ↓
"New" (wc-on-hold)
  ↓ [Admin reviews, approves]
"Process" (wc-process)
  ↓ [PM creates batch in QMF]
"In Production" (wc-in-production) ← QMF sets this
  ↓ [All production batches complete]
"Process" (wc-process) ← QMF sets this (module production complete)
  ↓ [Shipping batch system creates shipping batch]
"Ready to Ship" (wc-processing) ← Shipping system sets this (⚡ captures payment!)
  ↓ [Shipping creates label in Ordoro]
  ↓ [ShipStation ships order]
"Shipped" (wc-completed)
```

**Alternative Paths:**
- "Hold" (wc-hold) - Can enter/exit production queue as needed
- "Cancelled" (wc-cancelled) - Exit queue, release components
- "Awaiting Payment" (wc-pending) - Exit queue, deallocate stock

---

#### Critical Status Behaviors

**"Ready to Ship" (wc-processing) - PAYMENT CAPTURE**
- ⚡ **CRITICAL:** Automatically captures pre-authorized credit card and PayPal payments
- Must ONLY be set when order is truly ready to ship (all items, not just modules)
- Stock is guaranteed decreased (failsafe for shipping)
- **Set by shipping batch system** (NOT QMF) - shipping system knows when entire order ready

**Stock Allocation:**
- "New", "Process", "In Production", "Hold" = Stock DECREASED (allocated)
- "Awaiting Payment", "Cancelled" = Stock INCREASED (deallocated)
- "Ready to Ship", "Shipped" = Stock DECREASED (guaranteed)
- Failed/Declined = NO CHANGE

---

#### Edge Cases & Status Protection

**Manual Status Changes Away From "In Production"**

**Problem:** Admin accidentally changes order status while production batches are active.

**QMF Protection:**
```
Monitor: WooCommerce order status changes
Detect: Order status changed FROM "In Production" TO any status (except "Hold")
Action:
  1. Automatically revert status back to "In Production"
  2. Post to Slack #production channel

Slack Message:
  🚫 ORDER STATUS CHANGE BLOCKED

  Order #12345 (Customer: Acme Corp)
  Status change attempt: "In Production" → "[NEW_STATUS]"
  Action: Automatically reverted back to "In Production"

  WHY THIS HAPPENED:
  Order #12345 has active production batches in QMF.
  Manually changing the status away from "In Production" while
  batches are active will cause production tracking issues.

  TO PROPERLY REMOVE ORDER FROM PRODUCTION:
  1. Go to QMF dashboard
  2. Mark all production batches as complete (or cancel batches)
  3. QMF will automatically update order status to "Process"

  EXCEPTION: Orders can be manually set to "Hold" status if
  production needs to be suspended.
```

**Exception: "Hold" Status Is Allowed**
- Admins CAN manually change "In Production" → "Hold"
- This is intentional (suspend production on order)
- QMF responds differently (see below)

---

**Manual Status Changes TO "In Production" Without Batches**

**Problem:** Admin manually sets order to "In Production" but order has no QMF batches.

**QMF Protection:**
```
Monitor: WooCommerce order status changes
Detect: Order status changed TO "In Production" but order has zero QMF batches
Action:
  1. Automatically revert status to previous status (usually "Process" or "New")
  2. Post to Slack #production channel

Slack Message:
  🚫 ORDER STATUS CHANGE BLOCKED

  Order #12345 (Customer: Acme Corp)
  Status change attempt: "[OLD_STATUS]" → "In Production"
  Action: Automatically reverted (no production batches exist)

  WHY THIS HAPPENED:
  The "In Production" status is managed exclusively by the QMF
  system and should only be set when production batches are
  created.

  TO START PRODUCTION FOR THIS ORDER:
  1. Go to QMF dashboard
  2. Create a production batch for this order
  3. QMF will automatically set the order status to "In Production"

  DO NOT manually set orders to "In Production" status.
```

**Rationale:**
- "In Production" status should only be set by QMF when batches exist
- Prevents confusion about what's actually being built
- Maintains data integrity between WooCommerce and QMF system

---

**Orders Changed to "Hold" During Production**

**Scenario:** Order is "In Production" with active batches, admin sets to "Hold" status.

**Business Need:** Customer needs to pause order, payment issue, specification change, etc.

**QMF Response:**
```
Detect: Order status changed FROM "In Production" TO "Hold"
Actions:
  1. Keep batches in system (do NOT cancel or remove)
  2. Flag batches as "Order On Hold - Suspend Work"
  3. Post to Slack #production channel

Slack Message:
  ⚠️ PRODUCTION HOLD

  Order #12345 (Customer: Acme Corp) has been placed on HOLD.

  Active Batches:
  • Batch #46: 200/500 modules complete
  • Batch #47: 0/100 modules (not started)

  ACTION REQUIRED: Suspend all production work on this order.

  The order will remain in QMF. Wait for order to return to
  "Process" or "In Production" status before resuming work.
```

**When Order Resumes (Hold → Process or In Production):**
```
Detect: Order status changed FROM "Hold" TO "Process" or "In Production"
Actions:
  1. Remove "Order On Hold" flag from batches
  2. Batches return to normal "In Progress" status
  3. Post to Slack #production channel

Slack Message:
  ✅ PRODUCTION RESUMED

  Order #12345 (Customer: Acme Corp) is no longer on hold.

  Active Batches:
  • Batch #46: 200/500 modules complete - RESUME WORK
  • Batch #47: 0/100 modules - CAN START

  Production can resume work on this order.
```

**Component Reservations During Hold:**
- Hard-locked components remain locked (protect in-progress work)
- Components are NOT released during hold
- Other orders cannot steal components from held batches

---

**Order Cancellation During Production**

**Scenario:** Order is "In Production" (or "Hold") with active batches, admin sets to "Cancelled" status.

**Business Reality:** This would be highly unusual but can happen (including Hold → Cancelled transitions).

**QMF Response:**
```
Manual Management Only - No Automation

When order is cancelled:
  ✓ QMF flags batches as "Order Cancelled"
  ✓ Batches remain in system for PM to review
  ✓ Components remain locked until PM makes decision

PM Manual Actions Required:
  1. Review what's already built
  2. Decide what to do with completed modules (reallocate, scrap, hold)
  3. Decide what to do with in-progress modules (finish, stop, scrap)
  4. Manually release component reservations as appropriate
  5. Update batch status accordingly
```

**Rationale for Manual Approach:**
- Cancellations during production are rare
- Too many variables to automate:
  - How much work is complete?
  - Can modules be reallocated?
  - Are they custom/customer-specific?
  - Should we finish building or stop immediately?
  - Component cost considerations
- PM needs to make strategic decision based on circumstances
- Attempting to automate would create complexity for rare edge case

---

### Critical Questions & Issues Requiring Resolution

The following questions were identified during the rule development process and need to be addressed before creating the formal PRD:

#### 1. Component Reservation Default Behavior
**Question:** When an order is placed, should components be automatically soft-reserved for that order, or only when the PM explicitly selects the order for batching?

**ANSWER: Option A - Automatic Soft-Reservation**

**Decision:** Components are automatically soft-reserved when an order is placed.

**How It Works:**
- Customer places order → WooCommerce order created
- System automatically calculates component requirements from order line items
- System soft-reserves components for that order (if available)
- Order appears in PM queue with soft-reserved components
- PM can create batch immediately or let it wait in queue
- Higher priority orders can reallocate soft-reserved components (with PM confirmation)

**Impact:**
- ✅ Prevents automatic poaching by later orders
- ✅ Simple: No manual "selection" step needed
- ✅ PM always sees accurate component availability
- ✅ Soft-reserves can be reallocated when priorities change

**WooCommerce Status Integration:**
- Soft-reservation triggers when orders reach "New" (wc-on-hold) or "Process" (wc-process) status
- These are the standard statuses for orders entering the production queue
- See Order Status Integration section below for complete workflow

---

#### 2. Custom Module Reallocation Policy
**Question:** For custom LED modules built with customer-specific configurations, can they ever be reallocated to other orders?

**Considerations:**
- Some "custom" modules may be functionally identical
- Custom build notes may be customer preference, not requirement
- Waste vs customer specificity tradeoff

**Needs Decision:** Clear policy on what makes a module "non-reallocatable"

---

#### 3. Large Order Progress Tracking Granularity
**Question:** For very large orders (5000+ modules), what's the optimal batch size for progress tracking without creating management overhead?

**Options:**
- **A:** Fixed size (e.g., 1000 modules per batch)
- **B:** Time-based (e.g., 1 week of production per batch)
- **C:** PM discretion case-by-case

**Impact:** Affects system design for batch management UI and reporting.

---

#### 5. WooCommerce Order Status Integration
**Question:** Should batch completion trigger automatic WooCommerce order status changes, or remain completely decoupled?

**ANSWER: QMF Updates Status Automatically (Limited Scope)**

**Decision:** QMF will automatically update WooCommerce order statuses for module production milestones only.

**Status Changes QMF Makes:**
1. **First batch created** → Set order to "In Production" (wc-in-production)
2. **All production batches complete** → Set order back to "Process" (wc-process)

**Status Changes QMF Does NOT Make:**
- ❌ **"Ready to Ship"** (wc-processing) - This is managed by the shipping batch system
- ❌ **"Shipped"** (wc-completed) - This is managed by Ordoro

**Why This Separation:**
- **Orders may contain non-module items** - accessories, power supplies, cables, etc.
- **Shipping batch system has complete visibility** - knows when ALL items ready (modules + other items)
- **Payment capture is critical** - "Ready to Ship" captures pre-authorized payments, should only happen when entire order ready
- **QMF scope is module production only** - shipping/fulfillment is separate system

**QMF's Responsibility:**
- Track module production progress
- Update status when module production starts ("In Production")
- Update status when module production completes (back to "Process")
- Hand off to shipping batch system for final order assembly

**Implementation:**
- QMF sets "In Production" automatically when first batch created
- QMF sets back to "Process" automatically when last batch marked complete
- No PM confirmation required (automatic state transitions)
- Shipping batch system takes over from "Process" status

See "WooCommerce Order Status Integration" section above for complete workflow.

---

#### 6. Historical Batch Data Migration
**Question:** When transitioning from legacy OM to new QMF system, how do we handle active batches in the old system?

**Answer:** There will be no active batches when we make the transition.


---

## 3. Understanding the BOM Management System

### Current BOM Implementation

The BOM (Bill of Materials) system is integrated into the **Quadica Purchasing Management** plugin and is fully operational on the testing site. The system uses WordPress Custom Post Types with Advanced Custom Fields (ACF) for structured data storage.

**Architecture:**
- Plugin: `quadica-purchasing-management` (active on testing site)
- Module: BOM Module (`includes/Modules/BOM/`)
- Storage: WordPress Custom Post Types with ACF fields (no dedicated database tables)
- Integration: WooCommerce orders, Stock Monitor, template copying

**Two-Tier BOM Structure:**

The system uses two distinct Custom Post Types to separate templates from customer-specific orders:

#### 1. BOM Templates (`quad_bom_template`)
**Purpose:** Pre-configured BOM templates for standard LED module configurations

**Custom Post Type Details:**
- Post Type: `quad_bom_template`
- Label: "BOMs"
- Description: "Pre-configured BOM templates for different LED configurations"
- Status: 1 template currently in system (more can be added)

**ACF Field Structure:**
- **SKU** (text, required) - Module SKU identifier
- **SKU Description** (text) - Human-readable module description
- **Non-LED Components** (repeater field):
  - Component SKU (text, required)
  - Qty (number, required)
  - Description (text)
- **LEDs and Positions** (repeater field):
  - LED SKU (text, required)
  - Position (text, required) - Position number on the PCB
  - Description (text)
- **Build Notes** (textarea) - Assembly instructions
- **Source Info** (text) - Origin or source of this template

**Example BOM Template (SZ-05-W9):**
```
SKU: SZ-05-W9
Non-LED Components:
  - SZ-05b (qty: 1) - Saber Z5 – Rev B
LEDs and Positions:
  - LXZ2-5770 (position: 1) - 5700K White LUXEON Z LED
  - LXZ2-5770 (position: 2) - 5700K White LUXEON Z LED
  - LXZ2-5770 (position: 3) - 5700K White LUXEON Z LED
  [continues for all LED positions]
```

#### 2. Order BOMs (`quad_order_bom`)
**Purpose:** Customer-specific BOM variations for specific WooCommerce orders

**Custom Post Type Details:**
- Post Type: `quad_order_bom`
- Label: "Order BOMs"
- Description: "Customer-specific BOM variations derived from templates for specific orders"
- Status: 0 currently in system (generated automatically when orders are placed)

**ACF Field Structure:**
- **Order ID** (number, required) - WooCommerce order number
- **Customer** (text) - Customer name (first + last)
- **SKU** (text, required) - Module SKU
- **SKU Description** (text) - Product name from order item
- **Requires Review** (true/false) - Incomplete BOM flag
- **Last Modified** (date/time) - Manual modification timestamp
- **Non-LED Components** (repeater) - Same structure as template
- **LEDs and Positions** (repeater) - Same structure as template
- **Build Notes** (textarea) - Assembly instructions
- **Optional Components List** (text) - Additional components

**Internal Meta Keys (not ACF):**
- `_qpm_order_id` - Internal order ID reference for reliable lookups
- `_qpm_item_id` - Internal line item ID reference
- `_previous_*` - Baseline comparison data for diff tracking

**Key Services & Features:**

1. **BOMGenerator Service**
   - Automatically creates Order BOMs when WooCommerce orders are placed
   - Uses template copying strategy when matching template exists
   - Uses placeholder strategy for custom/unknown configurations
   - Tracks incomplete BOMs for review

2. **BOMRepository Service**
   - `find_template_by_sku()` - Lookup templates by SKU
   - `get_order_bom()` - Find BOM for specific order + line item
   - `get_order_boms()` - Get all BOMs for an order
   - `ensure_order_bom()` - Create or retrieve Order BOM
   - `copy_template_fields()` - Copy template data to Order BOM

3. **Template Copying**
   - When order contains standard module, system copies from template
   - Maps template fields to Order BOM field keys
   - Handles repeater fields (components, LEDs) correctly
   - Preserves build notes and assembly instructions

4. **Modification Tracking**
   - DiffTracker service monitors BOM changes
   - Stores "previous" state for comparison
   - Email notifications for manual modifications
   - Audit trail for production changes

5. **Integration Points**
   - **WooCommerce Orders:** Automatic BOM generation on order placement
   - **Stock Monitor:** Component availability tracking
   - **Required SKUs:** Syncs BOM components to purchasing requirements
   - **Email Notifications:** Alerts for incomplete or modified BOMs
   - **Admin UI:** Dedicated admin interface for BOM management

---

## 4. The Priority System

### Multi-Factor Priority Scoring

**Question:** What determines module priority?

**Hierarchy (highest to lowest):**
1. **PM Manual Override** - trumps everything
2. **Module Expedite Value** - numeric, per-module
3. **Order Expedite Value** - numeric, applies to all modules in order
4. **Days Past Promised Date** - overdue orders get highest priority
5. **Almost Due Boost** - within 2 days of promised date
6. **Order Age** - days since order placed

### Priority Calculation Logic

The system will automatically calculate priority scores based on the hierarchy above, with higher scores indicating higher priority. Modules are then sorted by priority score to determine build sequence.

### Promised Lead Time Integration

**Key Discovery:** Lead times are critical priority factor!

When customer orders a module, they receive a projected build time. This promised date must factor into priority.

**Lead Time Logic:**
```
promised_date = order_date + lead_time_days
days_past_due = max(0, today - promised_date)

if days_past_due > 0:
    // Past due gets highest priority (except manual override)
    priority_score = 2000 + days_past_due
```

**Questions Resolved:**
- Lead times stored in order meta (by order)
- Order lead time = module with longest lead time in order
- PM can override lead times (sets order priority value)
- "Almost due" modules (within 2 days) get priority boost

**Scenario Example:**
```
Module A: Order age 10 days, promised in 14 days (4 days left)
Module B: Order age 5 days, promised in 7 days (2 days left)
Module C: Order age 3 days, promised in 5 days (2 days LATE!)

Priority: C > B > A (late orders first, then almost due, then by age)
```

### Priority Scope & Persistence

**Order-level priority:**
- Applies to all modules in that order
- Example: Expedite order → all modules get boost

**Module-level priority:**
- Overrides order priority
- PM can prioritize specific modules within an order
- Example: Waiting for specific component → bump that module

**Persistence:**
- All PM priority changes are saved
- Affects future report calculations
- Updates order/module metadata
- Not temporary - carries forward

---

## 5. Component Stock Management

### Stock Accounting Model

**Three Stock Values:**
```
Physical Stock = Actual inventory count (WC stock)
Reserved Stock = Components allocated to active batches
Available Stock = Physical Stock - Reserved Stock
```

### Reservation Timing

**Decision: Reserve on Batch Creation**

When PM creates batch:
- ✅ Components reserved immediately
- ✅ Available stock reduced
- ✅ Other modules can't use those components

When PM removes module or adjusts quantity:
- ✅ Components released back to available
- ✅ Available stock increases
- ✅ Other modules can now use them

**Rationale:** Immediate reservation provides accurate inventory picture and prevents over-allocation.

### Stock Discrepancy Handling

**Scenario:** Physical count ≠ system count

**Workflow:**
1. Production discovers stock issue
2. Notifies PM
3. PM makes manual stock adjustment in WC
4. System flags affected batches:
   ```
   ⚠️ Batch #45: Module X needs 10× LED-A (now short 20 total)
   ⚠️ Batch #46: Module Y needs 15× LED-A (now short 20 total)
   ```
5. PM manually adjusts batches (remove modules, change quantities)

**Question:** Should system prevent stock adjustment if it breaks active batches?

**Decision: Allow with Warning**
- Warn: "This will affect 2 batches"
- PM proceeds
- System flags affected batches
- PM handles consequences

**Rationale:** PM needs flexibility. Reality doesn't match theory - system shouldn't block fixing reality.

---

## 6. Batch Lifecycle Management

### Initial Thinking

**Questions Asked:**
- Do we need batch status at all?
- What's the real reason for distinguishing open vs closed?
- Any problem allowing changes to any batch anytime?
- Do we need to note if module quantity has been built? Why?

### Batch Status Decision

**Chosen Approach: Middle Ground**

**Status Flow:**
```
Create Batch → "In Progress"
    ↓
All modules qty_completed >= qty_requested
    ↓
Auto-mark "Completed"
    ↓
PM can reopen if needed (with warning)
```

**Batch Rules:**
- ✅ Batch status = "In Progress" or "Completed"
- ✅ Auto-mark completed when all modules received
- ✅ "Completed" is flag, not lock
- ✅ PM can reopen if needed
- ✅ System warns: "This batch was marked complete on [date]"
- ❌ No audit trail at this time

**Why have status at all?**
- Prevents accidental changes to historical data
- "Locking" mechanism for completed work
- Reporting: "What did we build last month?"
- But with warning, PM can override when needed

**What happens when batch partially done?**
- Module status updated in QMF
- When qty_completed >= qty_required → module marked complete
- Batch remains "In Progress" until ALL modules complete
- PM can change qty_completed anytime while batch open
- Once batch marked complete, no further changes (unless reopened)

**Order Status:**
- ❌ No WC order status changes when module batched
- ❌ No order status changes when module completed
- Order fulfillment handled separately

**Batch Operations:**
- ❌ No pause/resume functionality
- ✅ If long-term problem → PM removes module from batch
- ✅ PM can edit any batch anytime (with warning if completed)

---

## 7. Order Change Management

### Scenarios to Handle

**Order Changes After Batching:**
1. Order cancelled
2. Order quantity changed (reduced or increased)
3. Order split into multiple orders (backorder scenario)
4. Module specifications changed

### System Response: Flagging, Not Automation

**Decision: No Automatic Actions**

System flags these conditions:
```
⚠️ Order Cancelled - Module in Batch #45
⚠️ Order Quantity Reduced (was 15, now 10) - Batch #45 has 15
⚠️ Order Split - Original 12345 now 12345 + #12346
⚠️ Stock Adjusted - Batch #45 now short 20× LED-A
⚠️ Component Unavailable - LED-X out of stock
```

**PM handles all flagged issues manually:**
- Remove excess modules from batch
- Adjust quantities in batch
- Re-assign split order modules
- Address stock shortages

**Rationale:** Too many nuances to automate. PM needs to make strategic decisions based on customer relationships, urgency, business factors.

**Order Split Scenario:**
Common when accommodating backorders. Customer wants part of order now, rest later.
- Original order 12345 → split into 12345 + #12346
- Modules may be in different batches
- System flags the split
- PM decides how to handle

---

## 8. Out of Scope (For Now)

These items identified but deferred:

### QA Issues & Rework
- QA problems managed outside production batch process
- If QA issue causes delay → PM removes/adjusts module in batch
- No separate rework tracking
- No component handling for rework

**Rationale:** QA is separate workflow. Keep production system focused.

### Component Receiving Workflow
- Component stock arrives (PO received)
- Stock levels update
- Dashboard reflects new buildability
- ❌ No special notifications
- ❌ No "what's now buildable" alerts

**Rationale:** PM checks dashboard when needed. Given order volumes, notifications unnecessary.

### Buildable But Not Building
- Module is buildable
- PM decides not to build yet (strategic reasons)
- ❌ No "snooze" functionality
- Module continues to appear in report based on priority

**Could Add:** Flag for modules where:
- Component stock available
- X days past promised date
- Still not built

**Decision:** Defer - normal priority system should surface these.

### Historical Analytics
- "What did we build last week?"
- "Why did we build Module X before Module Y?"
- Batch history reporting
- Production metrics

**Decision:** Future enhancement. Focus on live operational tool first.

---

## 9. Emerging Architecture

### Plugin Structure

**New Plugin: "Quadica Manufacturing"**

Separate plugin (not extension of BOM or LMB) because:
- LMB = customer-facing module configuration
- Quadica Purchasing Management (BOM Module) = component definition
- Production Management = manufacturing workflow
- Clean separation of concerns

### Core Components

**1. Buildability Calculator**
- Checks what can be built based on available components and BOMs
- Shows buildable quantities for each module
- Identifies which components are blocking production
- Updates automatically when stock levels or priorities change

**2. Priority Manager**
- Calculates priority scores based on multiple factors
- Allows PM to drag-and-drop to reorder modules
- Saves PM priority overrides
- Recalculates buildability in real-time as priorities change

**3. Batch Manager**
- Create and edit production batches
- Reserve components for batches
- Track batch status (In Progress, Completed)
- Track module completion within batches

**4. Dashboard UI**
- Shows summary information with ability to expand for details
- Updates in real-time as things change
- Interactive drag-and-drop controls
- Component availability status display
- Active batch progress display

### Data Architecture

**Database Strategy: Hybrid Approach**

The system will store production-specific data (production queue, batches, component reservations, priority settings) separately for performance and organization, while reading from existing WooCommerce and Quadica Purchasing Management BOM data.

**Data Sources (Read Only):**
- Quadica Purchasing Management BOM posts (`quad_bom_template`, `quad_order_bom`)
- WooCommerce Orders (status, dates, customer)
- WooCommerce Products (component stock)
- Order Meta (expedite, lead times)
- Module Meta (module expedite)

**Data Writes:**
- Priority overrides
- Batch records
- Component reservations
- Module completion tracking

**Does NOT Write To:**
- Order status (no WC status changes)
- Component stock directly (only through batch operations)
- BOM posts (read only)

---

## 10. Open Questions & Next Steps

### Questions Still to Explore

**1. User Interface Details**
- Exact layout and information hierarchy
- Color coding scheme for status indicators
- Mobile/tablet experience (even though primarily desktop)
- Accessibility considerations

**2. Notification System**
- Does PM need alerts for critical situations?
- Email notifications for late orders?
- In-dashboard alerts vs external notifications?

**3. Reporting Requirements**
- What reports does PM need from batch history?
- Export capabilities (CSV, PDF)?
- Integration with business intelligence tools?

**4. Component Procurement Integration**
- How does this tie into purchase order system?
- Should QMF suggest what to order?
- Minimum stock level alerting?

**5. Performance Optimization**
- Caching strategy for buildability calculations
- Database query optimization
- How often to recalculate vs use cached data?

**6. Integration Points**
- How does LMB plugin feed data to this system?
- Quadica Purchasing Management BOM Module integration
- API for external manufacturing systems?
- Webhook notifications for status changes?

## 11. Alternative Approaches Considered

For completeness, documenting approaches we discussed and why we didn't choose them:

### Option A: Extend BOM Plugin
Add batch generation directly to Quadica Purchasing Management plugin's BOM Module.

**Pros:**
- Single system for BOMs and batches
- All data in one place
- Simpler architecture

**Cons:**
- QMF plugin becomes complex/bloated
- Mixing concerns (component definition vs production workflow)
- Harder to evolve independently

**Why Not Chosen:** Separation of concerns. BOM Module stays focused on component definition and template management.

### Option B: Modernize Legacy OM
Refactor existing /om system to use BOM plugin data.

**Pros:**
- Keep proven batch UI/workflow
- Less new development
- Familiar to production team

**Cons:**
- Still dealing with technical debt
- Harder to add modern features (real-time updates, drag-drop)
- Eventually need full rewrite anyway
- Can't achieve "live report" vision with legacy architecture

**Why Not Chosen:** Technical debt too deep. Can't achieve vision within legacy constraints.

### Option C: Metadata-Only (No BOM Posts)
Store everything as order metadata, skip BOM post system.

**Pros:**
- Single source of truth
- Simpler architecture
- Less duplication

**Cons:**
- Breaks Stock Monitor integration
- Requires complete BOM Manager refactor
- Doesn't match existing Color Mixing pattern
- No modification history
- No separation between customer config and production reality

**Why Not Chosen:** BOM system already works well. Metadata serves different purpose (UI reconstruction).

---

## 12. Business Context

Understanding the business realities that shape these decisions:

### Order Volume
- ~20 orders per day
- Not high-volume manufacturing
- Quality and accuracy more important than speed
- Allows for PM strategic decision-making

### Team Size
- 1 Production Manager creates batches
- Concurrency not a major concern
- System can be optimized for single-user experience
- Training requirements are minimal

### Product Complexity
- LED modules with custom configurations
- Variable component requirements
- Can't fully automate batch optimization
- PM expertise is valuable and necessary

### Customer Relationships
- B2B sales, long-term relationships
- Flexibility in fulfillment important
- Partial shipments common
- Lead time commitments matter

### Manufacturing Reality
- QA issues happen
- Stock counts drift from system
- Orders change after placement
- System must be flexible, not rigid

**Key Insight:** This is not Amazon fulfillment. It's custom manufacturing with strategic decision-making. System should enable PM expertise, not replace it.

---

## 13. Why This Approach Makes Sense

Bringing it all together - why the Quadica Manufacturing concept is the right direction:

### Solves Real Problems
- PM currently lacks complete visibility
- Current system is snapshot-based, not continuous
- "Why not buildable" information is hidden
- Component constraints not visible
- Strategic decision-making is difficult

### Builds on What Works
- Quadica Purchasing Management (BOM Module) is operational
- WooCommerce provides component inventory
- Manufacturing workflow is established
- Don't reinvent what's working

### Enables Growth
- Handles current 20 orders/day
- Scales to 100+ orders/day
- Can add features incrementally
- Modern architecture allows evolution

### Respects Business Reality
- PM expertise is valuable
- Can't fully automate manufacturing decisions
- Flexibility is required
- Customer relationships matter

### Low-Risk Migration
- Gradual parallel operation
- Legacy system remains as backup
- Historical data preserved
- No "big bang" cutover

### Delivers Strategic Value
- Transforms PM from reactive to strategic
- Enables proactive problem-solving
- Improves on-time delivery
- Better inventory management
- Higher customer satisfaction

---

## 14. Build-to-Order vs Build-to-Stock Decision

### Fundamental Business Model Shift

**Question Explored:** Should production batch functionality be a separate system like current OM, or integrated into QMF?

This led to discovering a fundamental shift in manufacturing approach:

### Legacy OM System: Build-to-Stock
```
Build modules → Put in warehouse bins → Ship when orders come in
```

**Requires:**
- Finished goods inventory management
- Warehouse bin system with location tracking
- Complex receiving (update inventory, assign bins, allocate to orders)
- Stock rotation and tracking

### New System: Build-to-Order
```
Orders come in → Build specific modules for those orders → Ship directly
```

**Simplified Requirements:**
- No finished goods inventory to manage
- Simple tray storage (labeled with order numbers, single rack)
- Simpler completion process
- Direct order fulfillment

**This changes everything about batch management.**

---

## 15. The Poaching Problem

### Business Reality: Component Allocation Challenge

**Critical Discovery:** Build-to-order doesn't eliminate all inventory challenges. It introduces the "poaching" problem.

### What is Poaching?

**Scenario:**
```
Day 1: Order A arrives
  - Module X, Y, Z (need 3 modules)
  - Can build X, Y (have components)
  - Cannot build Z (waiting for component delivery)

Decision: Wait for all components before building

Day 3: Order B arrives (higher priority)
  - Module X only
  - Can build X (components available)

Build Order B Module X → Uses components needed for Order A

Result: Order A STILL can't complete (components "poached")

This can repeat indefinitely → Order A never gets built
```

### Real-World Example

**Large customer order scenario:**
```
Order 12345: 500× Module A + 10× Module B
  - Module A: Can build all 500 (3-4 days build time)
  - Module B: Waiting for 1 component
  - Customer expects complete shipment in 5 days

Without component reservation:
  - Can't start 500-unit build (waiting for Module B component)
  - Components sit idle for days
  - When B component arrives, still need 3-4 days to build A
  - Result: Late shipment, unhappy customer

With component reservation:
  - Reserve components for Module A, start building immediately
  - Reserve (but don't build) Module B
  - 500 units built over days 1-4
  - Module B component arrives day 3
  - Build small batch for Module B on day 4
  - Complete order ships day 5 on time
```

### Solution: Component Reservation System

**Two-Tier Reservation:**

1. **Soft Reservation** (Planning/Queue)
   - Status: Reserved but not in production
   - Location: In QMF queue, no batch created yet
   - PM Can: Reallocate with impact warning
   - Purpose: Prevent accidental poaching, planning

2. **Hard Lock** (In Production)
   - Status: In active batch
   - Location: Components "in the pipe" (manufacturing process)
   - PM Cannot: Steal or reallocate
   - Only Production Staff Can: Adjust batch
   - Purpose: Protect in-process work

---

## 16. Integrated QMF Architecture Decision

### Option A vs Option B Analysis

**Option A: Separate Batch Management**
```
QMF System (Queue)  +  Separate Batch System (Execution)
```
- Two systems to navigate
- Context switching
- Duplicate data display

**Option B: Integrated QMF** ✅ CHOSEN
```
Quadica Manufacturing (One System)
├─ Production Queue Tab
├─ Active Batches Tab
└─ Completed Batches Tab
```

### Why Integrated Approach

**Advantages:**
- ✅ Single system, no context switching
- ✅ PM sees entire pipeline in one place
- ✅ Natural flow: Queue → Create Batch → Monitor → Complete
- ✅ Batch progress visible alongside queue
- ✅ Build-to-order fits naturally (no complex receiving)

**Team Reality:**
- 3 people total (1 PM + 2 production staff)
- Small shop, close collaboration
- Face-to-face communication
- No need for complex workflows

**Design Principle:** Build system to prevent mistakes, not add bureaucracy

### Role-Based Interface Views

**Production Manager View (Desktop):**
- Full QMF dashboard
- Production queue + active batches + completed history
- Create batches, manage priorities, monitor overall production
- Component reservation management

**Production Staff View (Tablet/Phone):**
- Focus on active batches
- Digital batch instructions (replacing printed reports)
- Mark modules complete
- Simple, focused interface

**Same database, same batch records - just different UI optimized for role**

---

## 17. Simplified Batch Workflow (Build-to-Order)

### Old Way (Mixture of Build-to-Stock and Build-to-Order)
```
Create Batch
  ↓
Print Multi-Page Report
  ↓
Gather Components from Warehouse Bins
  ↓
Assemble Modules
  ↓
Receive Items (Complex)
  - Update inventory
  - Assign warehouse bins
  - Allocate to orders by priority
  ↓
Put in Warehouse Bins for Storage
```

### New Way (Build-to-Order)
```
Create Batch from QMF Queue
  ↓
View Digital Instructions (Tablet)
  ↓
Export CSV (if needed for testing equipment)
  ↓
Assemble Modules
  ↓
Mark Complete (Simple)
  - Orders automatically updated
  ↓
Generate Labels for Shipping
```

**Eliminated Steps:**
- ❌ Printed reports (replaced with digital instructions)
- ❌ Component gathering from bins (pulled as needed)
- ❌ Inventory receiving (no finished goods stock)
- ❌ Warehouse bin assignment (nowhere to store)
- ❌ QC quantity tracking (not used)

---

## 18. Partial Order Builds & Holding Strategy

### The Partial Build Question

**Scenario:**
```
Order A needs: Module X, Y, Z
Stock Status: Can build X, Y (not Z - waiting for components)
```

**Decision Point:** Build partial and hold? Or wait for all components?

### Option Chosen: Hybrid - PM Decides Per Order

**PM sees in QMF:**
```
Order A: Can build 2/3 modules (missing components for Z)
```

**PM Options:**
1. **"Build partial now, hold for order"**
   - Creates batch for X, Y
   - Completed modules go to "Order Hold" area
   - Marks order as "partial in hold"
   - When Z components arrive, build Z and complete order

2. **"Reserve and wait"**
   - Reserves all components (X, Y, Z)
   - Doesn't create batch yet
   - Order stays in queue with "reserved" status
   - When Z components arrive, build all together

**Strategic Decision:** Depends on order size, urgency, customer expectations

### Simplified Storage for Partial Orders

**Not complex warehouse management. Just:**
- **Order-Based Tray Storage:** Tray(s) labeled "Order 12345"
- Physical location: Single rack in production area
- System tracks: "2 modules completed, in storage for Order 12345"
- No tray IDs, no location tracking, no formal bin management

**Database Tracking:**
```
Batch Item Status:
  - 'building' - In production now
  - 'completed_hold' - Built, holding for rest of order
  - 'completed_shipped' - Built and order complete
```

---

## 19. Component Reservation: Soft vs Hard Lock

### The Critical Rule

**Once components are in a production batch, they are LOCKED.**

**Rationale:** Components "in the pipe" cannot be easily removed without understanding physical state of production.

### Two-Tier System

#### Tier 1: Soft Reservation (Queue/Planning)
```
Status: Reserved but not in production
Location: In QMF queue, no batch created yet
PM Can: Steal/reallocate with impact warning
Purpose: Prevent poaching, planning for future builds
```

**Component Availability Calculation:**
```
Physical Stock: 520
Soft Reserved: 100 (Order 12345, no batch yet)
Hard Locked: 0
Available: 420 (PM can use these)
```

**PM Can Reallocate Soft Reserved Components:**
```
⚠️ COMPONENT ALLOCATION CONFLICT

To build Module C (100 qty), need to use reserved components.

This will take from:
  • 100× LED-X soft reserved for Order 12345
    Status: Reserved but NOT in batch yet

Impact:
  Order 12345 will have reduced reservation

[Cancel] [Proceed and Reallocate] ✅ PM can do this
```

#### Tier 2: Hard Lock (In Production)
```
Status: In active batch
Location: Components pulled, in manufacturing process
PM Cannot: Steal or reallocate
Only Production Staff Can: Adjust batch (remove modules, change quantities)
Purpose: Protect in-process work
```

**Component Availability with Hard Lock:**
```
Physical Stock: 520
Soft Reserved: 0
Hard Locked: 500 (Batch #46, IN PRODUCTION)
Available: 20 (only these can be used)
```

**PM CANNOT Reallocate Hard Locked Components:**
```
❌ INSUFFICIENT COMPONENTS

Module C needs 100× LED-X

Current Status:
  Available: 20
  🔒 Hard Locked: 500 (Batch #46 - In Production)

Cannot use locked components.
Production staff must adjust batch to release components.

Options:
  1. [Wait] - Reserve when available
  2. [Build Partial] - Build 20 now, 80 later

[No "steal" option available]
```

### Why This Makes Sense

**Physical Reality:**
- Components may be partially assembled
- Work in progress that can't be easily reversed
- Production staff know physical state, PM doesn't

**Team Reality:**
- Production staff (2 people) are at the work bench
- They know what's started, what's safe to remove
- PM (at desk) doesn't have that visibility

**Process:**
- PM needs components from hard-locked batch
- PM walks over, talks to production staff (20 feet away)
- Production staff confirms what's safe to remove
- Production staff adjusts batch on tablet
- Components released, PM creates new batch

**No formal request system needed.** Just face-to-face communication.

---

## 20. Production Staff Batch Adjustment (Simple)

### What Production Staff Can and Cannot Do

**Production Staff CAN:**
- ✅ Adjust **quantities** of existing modules already in the batch
- ✅ Increase quantity (we have extra components, build more)
- ✅ Decrease quantity (component miscount, build fewer)

**Production Staff CANNOT:**
- ❌ Add new module SKUs/types to the batch
- ❌ Remove module SKUs/types from the batch
- ❌ Change which modules are in the batch

**Critical Rule:** Production staff can only adjust **how many** of each module to build, not **what modules** are in the batch.

---

### Common Adjustment Scenarios

**Scenario 1: Build Extra (Increase Quantity)**
```
Batch has: 500× Module A
Production discovers extra components in bin
Decision: Build 505 instead of 500 (use up inventory)
Action: Production staff increases quantity to 505
```

**Scenario 2: Component Shortage (Decrease Quantity)**
```
Batch has: 500× Module A
Production finds component miscount (only enough for 495)
Decision: Build 495 instead of 500
Action: Production staff decreases quantity to 495
Result: 5 modules' worth of components released back to available stock
```

**Scenario 3: Production Issue (Decrease Quantity)**
```
Batch has: 100× Module B
Built: 80 complete
Issue: Equipment failure, can't continue
Decision: Stop at 80, release components for remaining 20
Action: Production staff decreases quantity to 80
```

---

### How Quantity Adjustment Works

**Production Staff Tablet View:**
```
Batch #46 - Order 12345
Module: SP-08-E6W3
Status: In Progress
Built: 200/500 modules

[Adjust Quantity]
```

**Click Adjust Quantity → Simple Form:**
```
Adjust Module Quantity

Module: SP-08-E6W3
Current Planned Quantity: 500
Already Built: 200

New Quantity: [____]
  (minimum: 200 - cannot reduce below already built)
  (maximum: based on available components)

If reducing quantity, components will be released:
  (Auto-calculated as user types)

Reason: [___Component miscount___]

[Cancel] [Save Changes]
```

**After Saving (Decrease Example):**
```
✅ Batch #46 Updated
   Module: SP-08-E6W3
   Quantity: 500 → 450

Components Released (50 modules' worth):
   50× LED-XYZ → returned to available stock
   50× PCB-SP08 → returned to available stock

System automatically:
  ✓ Updated component availability
  ✓ Recalculated buildability for other orders
  ✓ Updated batch progress (200/450 instead of 200/500)
```

**After Saving (Increase Example):**
```
✅ Batch #46 Updated
   Module: SP-08-E6W3
   Quantity: 500 → 505

Components Reserved (5 additional modules):
   5× LED-XYZ → locked for this batch
   5× PCB-SP08 → locked for this batch

System automatically:
  ✓ Updated component reservations
  ✓ Reduced available stock
  ✓ Updated batch progress (200/505 instead of 200/500)
```

**That's it.** No PM approval needed, no complex workflows. Production staff handle practical adjustments in real-time.

### Component Release Happens Automatically

**QMF Updates Immediately:**
```
LED-X Component Status:
  Physical Stock: 520
  Hard Locked: 200 (was 500)
  Available: 320 (was 20)

Production Queue Updates:
  Orders now showing as buildable with newly available components
```

PM sees availability changed and can create new batches.

---

## 21. Small Team Design Philosophy

### Business Context

**Team Size:**
- 1 Production Manager (PM)
- 2 Production Staff
- All working in one small shop

**Communication:**
- Face-to-face (20 feet apart)
- Informal, collaborative
- Quick decisions
- High trust

### What This Means for System Design

**Don't Need:**
- ❌ Formal request/approval workflows
- ❌ Notification systems (email, Slack, etc.)
- ❌ Complex permission hierarchies
- ❌ Inter-department communication tools
- ❌ Justification/reason tracking

**Do Need:**
- ✅ Prevent accidental mistakes (hard lock enforcement)
- ✅ Clear visibility (component status, batch progress)
- ✅ Simple controls (edit batch, mark complete)
- ✅ Fast operations (minimal clicks)
- ✅ Audit trail (what changed, when)

**Design Principle:**
> "Build systems to prevent mistakes for a few people building highly specialized products in small quantities."

**Not:** Enterprise resource planning for sprawling factory
**Instead:** Smart guardrails for small, expert team

### Trust Model

**Production Staff:**
- Trusted to adjust batches safely
- Know physical state of work
- Can release components when appropriate

**Production Manager:**
- Trusted to make priority decisions
- Can override soft reservations with warnings
- Cannot override hard locks (physical safety)

**System Role:**
- Enforce physical constraints (hard lock)
- Show accurate status
- Track changes for audit
- Get out of the way

---

## 22. Digital-First Production Documentation

### Shift from Printed to Digital

**Current (Legacy OM):**
- Multi-page printed batch reports
- Distributed to assembly stations
- Static, can't update during production
- Paper management required

**New (QMF System):**
- Digital batch instructions on tablets
- Always current, updates in real-time
- Progressive disclosure (expand for details)
- No paper to manage

### Production Staff Tablet Interface

**Batch List View:**
```
My Active Batches

┌─────────────────────────────────────┐
│ Batch #46                           │
│ Order 12345 - 500× Module A        │
│ Progress: 200/500 built             │
│                                     │
│ [View Instructions] [Mark Complete] │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Batch #47                           │
│ Order #12350 - 100× Module C        │
│ Progress: 0/100                     │
│                                     │
│ [View Instructions] [Mark Complete] │
└─────────────────────────────────────┘
```

**Batch Instructions View:**
```
Batch #46 - Module A
Build Quantity: 500

Components Needed:
  1× SP-08a (Base PCB)
  1× LED-A (Position 1) [Blue]
  1× LED-B (Position 2) [Red]

[ + Expand for Assembly Notes ]

[ + Expand for Customer Instructions ]

Progress:
  [200] modules completed

[Save Progress] [Mark Batch Complete]
```

**Progressive Disclosure:**
- Start with just essentials (SKU, qty, component list)
- Expand sections as needed
- Touch-friendly interface
- Quick access to order details

### Benefits

**For Production Staff:**
- ✅ No walking to get printed reports
- ✅ Always see current status
- ✅ Update progress immediately
- ✅ Access from anywhere in shop

**For System:**
- ✅ Real-time progress tracking
- ✅ No paper document version control
- ✅ Can update instructions if needed
- ✅ Automatic integration with QMF

---

## 23. Component Availability Indicators

### Visual Status System

**Production Queue Display:**
```
Order #     Module      Status        Components
──────────────────────────────────────────────────
12345      Module A    🟢 Ready      All available ← NEW!
#12346      Module B    🔴 Blocked    Missing: LED-X
#12347      Module C    🟡 Partial    50/100 buildable
#12348      Module D    ⏳ Building   Batch #46
#12349      Module E    🔵 Reserved   Not building yet
```

**Status Legend:**
- 🔴 **Blocked** - Missing components, cannot build any
- 🟢 **Ready** - All components available (especially if status just changed)
- 🟡 **Partial** - Can build some quantity
- ⏳ **Building** - In active batch
- 🔵 **Reserved** - Components reserved, not building yet

### When Components Arrive

**Event:** PO #543 received, 100× LED-X added to stock

**QMF Response:**
```
Automatic Update:
  Module B: 🔴 Blocked → 🟢 Ready

Visual Feedback:
  - Row highlights briefly (flash green animation)
  - Icon changes from 🔴 to 🟢
  - Status text updates

No email, no notification - just live update on PM's screen
```

**PM Sees:**
```
Component LED-X:
  Physical: 620 (was 520)
  Reserved: 100
  Available: 520 (was 420)

Orders Now Buildable:
  🟢 Order #12346 - Module B (was blocked)
  🟢 Order #12351 - Module F (was blocked)
```

PM can immediately create batches with newly available components.

---

## 24. CSV Engraving File Generation & Base Engraving Process

### Current UV Laser Engraver CSV Engraving File

The purpose of the current CSV engraving file is to provide engraving details for our UV laser engraver. These details currently include:
- Production Batch ID
- SKU ID
- Order Number
- Quantity modules to be engraved
- Component SKU(s)
- 2 Digit LED Production Code(s)

The CSV file is saved to the Quadica\Production\production list.csv file in our Google share drive.

**Current UV Engraving Process**
- The CSV file generated by the current OM process is used by custom Python software that runs our Cloudray UV Laser Engraver to engrave the production code of each LED onto the base of each LED module
- These production codes are used to identify which LED is mounted into each position on the base
- We currently have about 40 different MCPCB array designs
- An array will consist of 4 our more bases (Metal Core Printed Circuit Boards) on a single panel
- Arrays are not standardized, which means that the custom Python software includes very complex routines to identify the type of base being engraved and where the 2 digit production code is engraved on each base in the array
- The engraving target area on the bases for the 2 digit production codes is very small, requiring the process of engraving codes to be extremely precise
- Each time we add a new array design we need to add positioning details for engraving production codes to the current process, which can be very challenging to set up

### QMF UV Laser Engraver CSV Export File

**Proposed Revised Engraving Process**
The new QMF production process will work differently than the existing system.

**Standardized Array Design** 
- We will standardize the arrays so that every array is physically the same size with 6 bases in each array
- The QR Code and unique module ID code will always be engraved in the exact same positions regardless of the type of base that the array contains

**Single Un1que Production Code Only**
- Instead of 2 digit LED production codes being laser engraved in each LED position on the base, a single 8 character module ID Code will be engraved onto each module base
- In addition to the 8 character ID code, the UV engraving process will also engrave a QR code onto the base
- The QR code will include our module domain (https://quadi.ca) followed by the 8 character module ID code. E.g., https://quadi.ca/A834BC23
- Instead of a 2 digit LED code engraved on the base, a laser projector will project the LED SKU, mounting position and orientation directly onto the base at the time that the LEDs are being placed on the base by production staff. See `33. Production Report and Laser Projector Integration`

**Revised CSV Export File**
- The CSV file is saved to the Quadica\Production\production list.csv file on our Google shared drive over-writing the existing file of it exists
- Functionality is provided that will allow production staff to re-create a CSV file from a previous batch at any time
- The CSV file will contain one row for each LED module in the batch
- The order of the rows in the CSV file should be optimized so that modules that use the exact same LEDs are grouped together.
- All other rows should be optimized to keep common LED types and colors together with the objective of minimizing LED retrieval during the production process.
- Each row will include the following fields for each module:
  - Production Batch ID
  - SKU ID
  - Order Number
  - 8 Character module ID Code
- Any number of rows can be included in the CSV file. The process that manages the UV engraver will manage partial array engraving


**UV Engraving Process**
- Other than producing the CSV file from the production batch, the QMF will have no interaction with the process that engraves the module ID code or QR Code onto the base
- The UV Laser engraver will manage the process of separating generating the QR Code that will be engraved on the base


**Module ID Code**
- Used to uniquely identify every LED module that we build.
- Module ID codes need to be perpetually unique. We should never ship two modules with the same code
- Generated for each LED module when the production batch is created
- The very first step in the production process is to use the generated CSV file to engrave the module ID code on the base using our UV laser
- The code is created using the following rules:
  **Grouping Code** - The first two characters are a 'grouping' code. A grouping code allows production staff to identify bases in a production batch that have the exact same LEDs mounted to the base.
    - Each grouping code in the batch is randomly generated generated from any combination of the following numbers and upper case characters
      - 19 uppercase letters: A, C, D, E, F, H, J, K, L, M, N, P, R, T, U, V, W, X, Y
      - 10 digits: 0, 1, 2, 3, 4, 5, 6, 7, 8, 9
    - Modules with the same base and the same LEDs mounted in the same positions on the base are automatically assigned the same 2 digit grouping code
    - Example: All modules in the batch that use the ATOM base with "3× LED-A in positions 1, 3 & 4 + 2× LED-B in positions 2 & 5" will share grouping code "A7"
    - The grouping code is used by production staff to identify and separate modules into sub-batches to optimize the assembly process. E.g., all modules with a grouping code of A7 use the exact same LEDs in the exact same positions on the base
    - More details about how production will use the grouping code are in section `33. Production Report and Laser Projector Integration` 
  **Unique Module ID** - The last six characters of the 8 character module ID code are a unique value for every LED module. The code is generated from any combination of the following numbers and upper case characters
    - 19 uppercase letters: A, C, D, E, F, H, J, K, L, M, N, P, R, T, U, V, W, X, Y
    - 10 digits: 0, 1, 2, 3, 4, 5, 6, 7, 8, 9
  - This unique code will be used to identify complete details about the LED module, including:
    - The base type
    - The LEDs mounted to the base
    - The position location of each LED mounted to the base
    - The production batch ID
    - When the module was built (uses the data of the production batch)
    - The order number
    - LED orientation drawing
    - etc.
  - Details about each LED module are permanently stored so information can be retrieved at any time by anyone with a valid code or module ID code URL

**Module ID Code Information**
- Managers and staff will be able to use the module ID code to find and view details about each LED module using the WordPress admin
- Information about each LED module will also be accesed by production staff using a tablet
- Placement information for each module will be accessed by the Laser Projector system
- A public facing landing page will be provided that will be used to display all of the details for an LED module when the QR code is scanned
- A public facing inquiry page will be provided that will display all of the details for an LED module when a valid module ID code is entered

---

## 25. Production Process

## 26. Open Questions & Still To Explore

1. **Product Labels System**
   - What information must be on labels?
   - Individual modules or shipping boxes?
   - Label size and printer type?
   - Barcode/QR code requirements?
   - Integration with shipping system?

2. **Batch Report Format**
   - Digital tablet view details
   - What information is essential vs nice-to-have?
   - Print option still needed for backup?
   - How much detail in component lists?

3. **Component Availability Alerts**
   - Just visual indicators in QMF?
   - Or also email/notification when components arrive?
   - Threshold alerts for low stock?

4. **Batch Completion Workflow**
   - How does "mark complete" update order status?
   - Integration with shipping workflow?
   - What happens to partial orders?
   - Hold area management details?

5. **Historical Batch Data**
   - How much history to show?
   - Reporting requirements?
   - Export/analytics needed?

6. **Mobile/Tablet Optimization**
   - Screen sizes to support?
   - Touch interface details?
   - Offline capability needed?

7. **Legacy Data Handling**
   - Should we keep historical batch data from old system?
   - How to handle transition from old system to new?
   - What data needs to be migrated vs archived?

8. **User Interface Mockups**
   - QMF dashboard layout
   - Tablet batch view
   - Component status widgets
   - Priority management interface

9. **Integration Points**
    - How does LMB plugin feed data to QMF?
    - Quadica Purchasing Management (BOM Module) integration
    - WooCommerce order updates
    - Stock level synchronization

### Next Areas to Explore

**Priority 1 (Critical for MVP):**
- CSV export requirements and format
- Label system requirements
- Batch completion workflow details

**Priority 2 (Important):**
- UI/UX mockups
- Data storage design
- Migration strategy

**Priority 3 (Can Defer):**
- Advanced reporting
- Mobile optimization details
- Historical analytics

---

## 27. Conclusion

This discovery session has explored the modernization of LED module production batch generation. Through systematic analysis of the legacy system, understanding of the BOM infrastructure, deep exploration of the Quadica Manufacturing concept, and now integration of production batch functionality, we've arrived at a clear architectural direction.

**The Vision:** A unified Quadica Manufacturing system that handles both production planning (queue management, component reservation, priority optimization) and production execution (batch management, digital instructions, progress tracking) in a single integrated interface.

**Key Architectural Decisions:**

1. **Integrated System** - QMF handles both queue and batch management
2. **Build-to-Order Model** - Minimal finished goods inventory, simplified workflow
3. **Two-Tier Component Reservation** - Soft (PM can reallocate) vs Hard Lock (in production)
4. **Digital-First** - Tablet-based batch instructions, not printed reports
5. **Small Team Optimized** - Simple workflows, face-to-face communication, no bureaucracy
6. **Component Poaching Protection** - Automatic reservation prevents accidental reallocation
7. **PM Strategic Control** - Full visibility, can override soft reservations with warnings
8. **Production Staff Autonomy** - Can safely adjust batches based on physical reality

**The Approach:** Gradual migration alongside the legacy system, allowing validation and confidence-building before full cutover, with zero risk to ongoing operations.

**The Path Forward:** Continue exploring remaining open questions (CSV format, labels, completion workflow), then proceed to formal PRD development and UI/UX design.

---

**Document Status:** Active Discovery - Production batch integration being explored

**Next Action:** Continue exploring production batch functionality, CSV export, labels, and integration architecture

**The Path Forward:** Gradual migration alongside the legacy system, allowing validation and confidence-building before full cutover, with zero risk to ongoing operations.

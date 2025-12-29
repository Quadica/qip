# Quadica Production Manager (QPM) - Discovery

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
This document captures the exploration and discovery process for modernizing the LED module production system. It documents the thinking process, questions asked, decisions made, and the emerging architecture for the Quadica Production Manager (QPM) system that will provide full integration into WooCommerce, continuous visibility into the entire production pipeline, production batch generation, production documentation, etc.

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
- QPM filters eligible modules by base type when creating batches
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
- QPM groups modules by base type only, not by LED SKU
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
- QPM displays array size for selected base type
- QPM calculates complete vs. partial array usage for batch
- System shows array optimization suggestions (e.g., "Reduce by 3 modules for 10 complete arrays")
- QPM displays: "Using 9 complete arrays + 1 partial array (10/15 modules)" or "Using 10 complete arrays"
- QPM displays estimated build time based on module count

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
- PM can see calculated priority score for each order in QPM dashboard

**Rationale:**
Hierarchical priority ensures business-critical decisions (PM expedites, paid expedites) take precedence over automated factors, while automated factors (past due, almost due, age) prevent orders from becoming late or forgotten. System balances:
- **Strategic priorities** (PM manual expedite)
- **Revenue** (customer paid expedites)
- **Customer satisfaction** (prevent late orders)
- **Operational fairness** (FIFO for similar-priority orders)

**System Impact:**
- Priority scores recalculated daily (or when order details change)
- QPM dashboard displays orders sorted by priority score (highest first)
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
- QPM calculates array usage (complete vs. partial arrays)
- System highlights array optimization opportunities for lower-priority modules
- QPM shows "Order Completion Impact" and "Array Optimization" analysis before batch creation
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
- QPM shows "Batch Efficiency" metric (actual size vs. optimal size)
- QPM displays array analysis: "Using X complete arrays" or "Using X complete + 1 partial (Y/Z)"
- System highlights when lower-priority modules excluded for array optimization
- QPM displays estimated build time for batch size

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
- QPM displays reallocation options when PM adjusts priorities
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
- QPM dashboard shows soft-reserved quantities by order
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
- QPM prevents component reallocation from active batches
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
- QPM displays clear before/after state for PM review
- All reallocations logged for audit trail

**PM Actions:**
- PM reviews reallocation impact warnings before confirming
- PM can cancel reallocation if impact is unacceptable
- PM can manually adjust reservations if needed

---

#### Rule 12: Stalled Batch Detection & Component Release
**Statement:** QPM shall automatically detect batches that have been active for an abnormally long time and notify the PM to prevent components from being stranded indefinitely in hard locks.

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

[View Batch in QPM] [Dismiss Alert for 2 Days]
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
PM Action: Cancel batch in QPM
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
PM Action: Mark batch complete in QPM
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
  - Day 14: Batch flagged "CRITICAL - MANUAL REVIEW REQUIRED" in QPM dashboard
```

**System Impact:**
- Batch table includes "last_activity_timestamp" column
- Daily automated job checks for stalled batches
- Slack integration sends alerts to #production channel
- QPM dashboard displays "Stalled Batch" warnings prominently
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

**QPM Display:**
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
**Statement:** QPM shall integrate with WooCommerce order statuses to reflect production state, with automatic status transitions and protection against unauthorized manual changes.

**Details:**

**QPM-Managed Order Statuses:**
- `wc-process`: Order released for processing, components soft-reserved
- `wc-in-production`: At least one batch created for this order, modules being built

**Status Transition Flow:**
```
Order Created → wc-on-hold
  ↓ (Admin releases order)
Order Released → wc-process (QPM soft-reserves components)
  ↓ (PM creates first batch for order)
Batch Created → wc-in-production (QPM sets automatically)
  ↓ (All batches for order complete)
Production Complete → wc-process (QPM returns order to processing)
  ↓ (Shipping batch system processes order)
Shipping Ready → wc-processing (Shipping batch system sets)
  ↓ (Shipping creates label)
Order Shipped → wc-completed
```

**QPM Sets These Statuses:**
- `wc-process` → `wc-in-production`: When first batch created for order
- `wc-in-production` → `wc-process`: When all batches for order complete

**QPM Does NOT Set:**
- `wc-processing` ("Ready to Ship") - Shipping batch system owns this
- Order may contain non-module items that QPM doesn't track
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
    → Slack #production: "⚠️ Order 12346 manually set to 'In Production' but has no active batches. Auto-reverted to 'Process'. Use QPM to create batches."
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
  3. Manually close batches in QPM
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
- Each batch tracked separately in QPM
- Production staff builds one batch at a time
- Batch completion updates order completion percentage

**Rationale:**
Large orders exceeding practical batch size limits need to be split for manageable production runs. Multi-batch support provides flexibility while maintaining clear tracking and order completion logic.

**System Impact:**
- Batch table includes `order_id` and `batch_sequence` fields (e.g., Order 12345 Batch 1, 2, 3)
- Order completion logic: `SUM(modules_built across all batches) = order total modules`
- QPM displays all batches for an order in order detail view

**PM Actions:**
- PM decides how to split large orders into batches
- PM can create batches sequentially (build Batch 1, then create Batch 2, etc.)
- PM can create all batches upfront if all components available

---

#### Rule 16: Completion Notification Strategy
**Statement:** When an order's module production completes (all batches finished), QPM shall notify relevant stakeholders via Slack and update order status.

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
- QPM automatically transitions order: `wc-in-production` → `wc-process`
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
- QPM logs all manual priority adjustments and reallocations
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
- Excluded orders remain visible in QPM (not hidden)

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
- QPM provides "Print Order Tray Label" function
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
- QPM generates order tray labels with Data Matrix codes and order numbers
- QPM tracks order completion status (not physical tray count or locations)
- Module Data Matrix code system determines which tray modules go into
- System transitions orders to "Process" (wc-process) when all modules complete

**PM Actions:**
- PM prints order tray labels when production starts on new order
- Production staff uses labels to mark order trays
- No manual tray organization or tracking required

---

#### Rule 21: Order Completion Tracking & Shipping System Integration
**Statement:** QPM shall track module completion status for each order and transition orders to "Process" (wc-process) when all modules are complete, integrating with the shipping batch system for order fulfillment.

**Details:**

**Module Completion Tracking:**
- QPM tracks which modules have been built for each order
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
- Shipping batch system queries QPM via API for module completion status
- QPM confirms "all modules complete" before shipping processes order
- Shipping system handles order retrieval and packaging workflow
- Module Data Matrix codes used for validation during packaging

**Physical Workflow:**
```
1. QPM transitions Order 12345: wc-in-production → wc-process
   ↓
2. Shipping batch system queries QPM: "Are all modules complete for Order 12345?"
   ↓
3. QPM responds: "Yes - 200/200 modules complete"
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
QPM's role is tracking module production completion status and providing this information to the shipping system. Physical tray retrieval is trivial (few dozen orders, QR-coded trays) and handled by shipping staff. Module QR codes handle all verification during packaging.

**System Impact:**
- QPM tracks module completion counts by order
- QPM provides API endpoint for shipping system to query completion status
- QPM transitions orders to "Process" (wc-process) when all modules complete
- Shipping batch system transitions orders to "Processing" (wc-processing/Ready to Ship) when ready
- No tray location tracking needed (QR codes + small order volume)

**PM Actions:**
- PM monitors order completion status in QPM dashboard
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
- QPM generates printable labels in standard formats
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

- The QPM will generate a CSV file that is used to laser engrave the Module Serial Number and QR code onto the module
- The CSV file will contain one row for each LED module in the batch
- The order of the rows in the CSV file should be optimized so that unique LED SKUs are grouped together with the objective of minimizing LED retrieval and handling during the production process
- Each row in the CSV file will include the following fields for each module:
  - Production Batch ID
  - Module ID (e.g., `STAR-34924`)
  - Order Number
  - Module Serial Number (8-digit numeric)
- Any number of rows can be included in the CSV file
- The generated CSV file is saved to the `Quadica\Production\production list.csv` file on our Google shared drive, over-writing the existing file if one exists
- The QPM will include functionality that allows production staff to re-generate a CSV file for a previous production batch at any time

### Base Engraving Process

- The generated CSV file will be used by custom Python software to engrave the Module Serial Number and Data Matrix code onto each base using our Cloudray UV Laser Engraver
- Other than producing the CSV file from the production batch, the QPM will have no interaction with the process that engraves the Serial Number or Data Matrix code onto the base
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
   - Just visual indicators in QPM?
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
   - QPM dashboard layout
   - Tablet batch view
   - Component status widgets
   - Priority management interface

9. **Integration Points**
    - How does LMB plugin feed data to QPM?
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

**The Vision:** A unified Quadica Production Manager system that handles both production planning (queue management, component reservation, priority optimization) and production execution (batch management, digital instructions, progress tracking) in a single integrated interface.

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
10. **Shipping Integration** - QPM tracks completion status and provides API; shipping system handles order fulfillment

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

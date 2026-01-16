# Quadica Production Manager - Reference

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

The **Quadica Production Manager** that provides continuous visibility:
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

**New Plugin: "Quadica Production Manager"**

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

Bringing it all together - why the Quadica Production Manager concept is the right direction:

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
Quadica Production Manager (One System)
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

This discovery session has explored the modernization of LED module production batch generation. Through systematic analysis of the legacy system, understanding of the BOM infrastructure, deep exploration of the Quadica Production Manager concept, and now integration of production batch functionality, we've arrived at a clear architectural direction.

**The Vision:** A unified Quadica Production Manager system that handles both production planning (queue management, component reservation, priority optimization) and production execution (batch management, digital instructions, progress tracking) in a single integrated interface.

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

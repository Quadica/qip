# QAM - Quadica Assembly Management - Discovery

**Module Code:** QAM
**Status:** Discovery
**Created:** January 2026
**Author:** Claude Code + Chris Warris

---

## Purpose

This discovery document captures the exploration and analysis of production batch and assembly management functionality in the legacy OM system. It documents the current implementation, identifies requirements, and informs the design of the QAM module.

---

## Legacy System Analysis

### Source Files Analyzed

| File | Purpose |
|------|---------|
| `prod-generate.php` | Main production batch generation UI |
| `gen_asmlist.php` | Determines SKUs to include in batch |
| `gen_batch.php` | Creates the production batch records |
| `prod-batch.php` | View/manage individual batch |
| `prod-batches.php` | List all production batches |
| `prod-assemblies.php` | Assembly definition management |
| `prod-assemblies-new.php` | Create new assembly definitions |

### Database Tables

**`oms_prod_batch`** - Production batch headers
```sql
batch_id INT PRIMARY KEY AUTO_INCREMENT
batch_date DATE
status VARCHAR(50)          -- 'Pending', 'In Progress', 'Complete'
created_by INT
created_at DATETIME
completed_at DATETIME
notes TEXT
```

**`oms_batch_items`** - Production batch line items
```sql
id INT PRIMARY KEY AUTO_INCREMENT
batch_id INT (FK)
assembly_sku VARCHAR(100)   -- Module SKU to build
order_no INT                -- Associated WooCommerce order
build_qty INT               -- Quantity to build
priority INT                -- Build priority
customer_comments TEXT
internal_comments TEXT
customer_instructions TEXT
instructions TEXT           -- Special assembly instructions
connector_option VARCHAR(50)
status VARCHAR(50)          -- 'Pending', 'Built', 'Cancelled'
```

**`oms_assemblies`** - Assembly/BOM definitions
```sql
assembly_id INT PRIMARY KEY AUTO_INCREMENT
assembly_sku VARCHAR(100)
assembly_name VARCHAR(255)
description TEXT
is_active TINYINT
created_at DATETIME
```

**`oms_canbuild`** - Temporary table for build calculations
```sql
-- Rebuilt fresh during each batch generation
assembly_sku VARCHAR(100)
can_build INT               -- Quantity that can be built
limiting_component VARCHAR(100)
```

**`oms_candidates`** - Batch candidate staging
```sql
-- Items being considered for inclusion in a batch
order_no INT
assembly_sku VARCHAR(100)
build_qty INT
priority INT
order_date DATE
order_time TIME
-- ... other order details
```

---

## Current Workflows

### 1. Generate Production Batch

**Entry Point:** `prod-generate.php`

**Process:**
1. User selects batch mode:
   - **Orders** - Build only what's needed for customer orders
   - **Inventory** - Build to maintain stock levels
2. System calls `gen_asmlist.php` via AJAX
3. `gen_asmlist.php` performs:
   a. Truncates working tables (`oms_canbuild`, `oms_currentstock`, `oms_candidates`)
   b. Loads all WooCommerce product stock into `oms_currentstock`
   c. For "Orders" mode:
      - Queries `wc-process` status orders
      - Extracts assembly SKUs from order line items
      - Calculates build quantities needed
   d. For "Inventory" mode:
      - Compares current stock to preferred stock levels
      - Identifies assemblies below reorder point
4. System displays candidate list with:
   - Assembly SKU (with connector option if applicable)
   - Order number (linked to WP admin)
   - Order date/time
   - Priority
   - Build quantity
   - Customer comments
   - Internal comments
   - Customer instructions
   - Special assembly instructions
   - Include checkbox (pre-checked)
5. User unchecks items to exclude
6. "Regenerate" recalculates with exclusions
7. "Create Batch" calls `gen_batch.php` to create batch

**Key Calculation in `gen_asmlist.php`:**
```php
// For each order in wc-process status:
// 1. Get line items with assembly SKUs
// 2. Check component availability via oms_currentstock
// 3. Calculate how many can be built
// 4. Subtract from available components for next calculation
```

### 2. View Production Batch

**Entry Point:** `prod-batch.php`

**Features:**
- Batch header (date, status, notes)
- Line items with build status
- Print batch report
- Mark items as built
- Update batch status

### 3. Assembly Management

**Entry Point:** `prod-assemblies.php`

**Purpose:** Define which components make up each assembly (BOM)

**Note:** The legacy system has limited BOM functionality. The QPM plugin's BOM module provides more comprehensive order-specific BOM management.

---

## Batch Generation Logic

### Orders Mode

```
1. Get all orders with status 'wc-process'
2. For each order:
   a. Extract line items that are assemblies
   b. Get order priority (expedite flag)
   c. Get customer/internal comments
   d. Get special instructions
3. Sort by priority DESC, order_date ASC
4. For each assembly:
   a. Check component availability
   b. If can build >= needed, add to candidates
   c. Deduct components from available pool
5. Display candidate list for user selection
```

### Inventory Mode

```
1. Get all assembly products
2. For each assembly:
   a. Get current stock level
   b. Get preferred stock level
   c. If current < preferred, add shortfall to candidates
3. Check component availability for each candidate
4. Display candidates that can be built
```

### "Can Build" Calculation

The system determines buildable quantity by:
1. Looking up assembly's BOM (component list)
2. Checking available quantity of each component
3. Finding the limiting component (lowest available)
4. Can Build = MIN(available / required) across all components

---

## Integration Points

### WooCommerce Integration

**Data Read:**
- Orders with `wc-process` status
- Order line items (assembly SKUs, quantities)
- Order meta (priority, comments, instructions)
- Product stock levels

**Data Written:**
- None directly (receiving updates stock via QIM)

### Inventory System Integration

**Data Read:**
- Component stock levels for "can build" calculation
- Deducts from available pool during batch generation

**Data Written:**
- Component reservations (hard locks) when batch created

### QSA (Engraving) Integration

**Data Read:**
- QSA reads `oms_batch_items` for modules awaiting engraving
- Filters for new-style modules (4-letter + digit pattern)

### Fulfillment System Integration

**Trigger:**
- Completed batches make modules available for "can ship"

---

## Data Flow

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   WooCommerce    │────▶│   gen_asmlist    │────▶│  oms_candidates  │
│    Orders        │     │   (calculate)    │     │   (staging)      │
└──────────────────┘     └──────────────────┘     └──────────────────┘
                                                           │
                                                           ▼
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│     QSA          │◀────│  oms_batch_items │◀────│    gen_batch     │
│  (engraving)     │     │   (line items)   │     │    (create)      │
└──────────────────┘     └──────────────────┘     └──────────────────┘
                                  │
                                  ▼
                         ┌──────────────────┐
                         │   oms_prod_batch │
                         │    (header)      │
                         └──────────────────┘
```

---

## Pain Points in Legacy System

1. **Temporary Tables** - `oms_canbuild`, `oms_candidates` rebuilt each time
2. **No Component Locking** - Components not formally reserved until built
3. **Limited BOM Management** - Basic assembly definitions only
4. **Manual Receiving** - No workflow for receiving completed modules
5. **No Production Scheduling** - No workload balancing or scheduling
6. **Limited Tracking** - No serial number assignment (handled by QSA now)
7. **No Work Instructions** - Assembly steps not documented in system

---

## QAM Module Requirements

### Must Have (P0)

1. Production batch creation from orders and inventory needs
2. "Can build" calculation based on component availability
3. Component hard-lock when batch created (via QIM)
4. Batch status workflow (Pending → In Progress → Complete)
5. Integration with QSA for engraving queue
6. Receiving workflow for completed modules
7. Update WooCommerce stock when modules received
8. Batch list and detail views

### Should Have (P1)

1. Production scheduling and prioritization
2. Workload estimation
3. Production reports (daily/weekly output)
4. Partial batch completion
5. Build instruction management
6. Component shortage alerts

### Nice to Have (P2)

1. Production floor display/kiosk mode
2. Time tracking per batch/item
3. Quality inspection workflow
4. Rework tracking
5. Production cost estimation

---

## Data Migration Considerations

### Tables to Migrate

| Source | Records | Notes |
|--------|---------|-------|
| `oms_prod_batch` | ~2,000 | Batch headers |
| `oms_batch_items` | ~15,000 | Batch line items |
| `oms_assemblies` | ~100 | Assembly definitions (may be replaced by QPM BOMs) |

### Migration Notes

1. Map batch statuses to new workflow states
2. Link batch items to WooCommerce order IDs
3. Consider archive vs. active batch threshold
4. Coordinate with QSA for modules already engraved
5. BOM data may migrate to QPM's Order BOM system

---

## Relationship to QSA

QSA (Quadica Standard Array Engraving) reads from `oms_batch_items` to identify modules ready for engraving. The workflow is:

1. **QAM** creates production batch with modules to build
2. **QSA** creates engraving batches from QAM batch items
3. **QSA** generates SVGs and tracks serial numbers
4. **Physical assembly** happens after engraving
5. **QAM** receives completed modules into inventory

QAM should maintain the batch structure that QSA depends on, or coordinate migration.

---

## Questions for Clarification

1. Should BOM management stay in QPM or move to QAM?
2. What batch statuses are needed beyond Pending/Complete?
3. Should receiving update stock directly or go through QIM?
4. How should partial builds be handled?
5. Is production scheduling a priority requirement?
6. Should QAM track assembly time for labor costing?

---

*Document created by Claude Code + Chris Warris - January 2026*

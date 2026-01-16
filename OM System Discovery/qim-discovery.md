# QIM - Quadica Inventory Management - Discovery

**Module Code:** QIM
**Status:** Discovery
**Created:** January 2026
**Author:** Claude Code + Chris Warris

---

## Purpose

This discovery document captures the exploration and analysis of inventory and bin management functionality in the legacy OM system. It documents the current implementation, identifies requirements, and informs the design of the QIM module.

---

## Legacy System Analysis

### Source Files Analyzed

| File | Purpose |
|------|---------|
| `report-inventory.php` | SKU inventory counts and bin assignments |
| `report-bin-history.php` | Bin transaction history |
| `get_invrep_data.php` | AJAX handler for inventory data retrieval |
| `post_product_data.php` | Save inventory adjustments |

### Database Tables

**`oms_bins`** - Bin location master data
```sql
bin_id INT PRIMARY KEY
location VARCHAR(50)         -- Warehouse location (e.g., "MAIN", "ANNEX")
aisle VARCHAR(10)           -- Aisle identifier
bin_number VARCHAR(20)      -- Specific bin number
sku VARCHAR(100)            -- Assigned SKU (one SKU per bin)
bin_count INT               -- Current quantity in bin
bin_type ENUM               -- 'static', 'dynamic'
last_updated DATETIME
```

**`oms_bin_log`** - Bin transaction history
```sql
log_id INT PRIMARY KEY AUTO_INCREMENT
bin_id INT (FK)
sku VARCHAR(100)
previous_count INT
new_count INT
change_qty INT
change_type VARCHAR(50)     -- 'PO_RECEIVE', 'ADJUSTMENT', 'PRODUCTION', 'SHIPMENT'
reference_id INT            -- PO number, batch ID, order ID
created_at DATETIME
created_by INT              -- User ID
notes TEXT
```

**`oms_currentstock`** - Working table for stock calculations
```sql
component VARCHAR(100) PRIMARY KEY
open_stock INT              -- Stock at start of calculation
current_stock INT           -- WooCommerce stock level
on_hand INT                 -- Calculated available stock
```

---

## Current Workflows

### 1. Inventory Report

**Entry Point:** `report-inventory.php`

**Process:**
1. User enters SKU search pattern (partial match supported)
2. AJAX call to `get_invrep_data.php` retrieves matching SKUs
3. System displays for each SKU:
   - SKU identifier
   - Actual bin count (editable)
   - In active orders (allocated)
   - In inactive orders (on hold)
   - Allocated to production
   - WooCommerce current stock
   - Currently on purchase order
   - Assigned bin location (editable)
4. User can adjust bin counts or change bin assignments
5. "Update SKU Details" saves changes

**Business Rules:**
- Actual bin count must be >= 0
- WooCommerce stock CAN be negative (due to order deductions)
- Changing bin assignment must use unassigned static bin
- All changes update WooCommerce stock automatically

### 2. Stock Calculation

The legacy system calculates stock status using this formula:

```
Available Stock = WC Current Stock
                  - In Active Orders
                  - Allocated to Production
                  + Currently on PO (expected)
```

**Key Tables:**
- `oms_currentstock` - Rebuilt fresh for each batch/ship calculation
- Populated by iterating all WooCommerce products via `wc_get_products()`

### 3. Bin Assignment

**Bin Types:**
- **Static Bins:** Permanent assignment to a single SKU
- **Dynamic Bins:** Can be reassigned as needed

**Rules:**
- During PO receiving, items go to assigned bin
- If no bin assigned, user must specify during receiving
- Bin changes logged to `oms_bin_log` for audit

---

## Integration Points

### WooCommerce Integration

**Data Read:**
- Product SKU
- Current stock quantity (`_stock` meta)
- Stock alert level (reorder point)

**Data Written:**
- Stock quantity during receiving and adjustments
- Synced from bin counts

### Production System Integration

**Data Read:**
- Components allocated to production batches
- Used in "Allocated to Production" column

### Purchasing System Integration

**Data Read:**
- Items currently on purchase orders (outstanding balance)
- Used in "Currently on PO" column

**Data Written:**
- Bin assignments during PO receiving
- Stock updates when items received

### Fulfillment System Integration

**Data Read:**
- Bin locations for picklist generation
- Stock availability for "can ship" calculation

---

## Data Flow

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   PO Receiving   │────▶│     oms_bins     │────▶│  WC Product      │
│                  │     │   (bin counts)   │     │  Stock (_stock)  │
└──────────────────┘     └──────────────────┘     └──────────────────┘
                                  │
                                  ▼
                         ┌──────────────────┐
                         │   oms_bin_log    │
                         │  (audit trail)   │
                         └──────────────────┘
```

---

## Pain Points in Legacy System

1. **Manual Stock Reconciliation** - No automatic sync between bins and WooCommerce
2. **Limited Bin Visibility** - No visual bin map or location hierarchy
3. **No Reservation System** - Orders don't formally reserve stock
4. **Stale Data** - `oms_currentstock` rebuilt from scratch each calculation
5. **Single Bin Per SKU** - Can't split SKU across multiple bins
6. **No Cycle Count Support** - No workflow for inventory audits
7. **No Low Stock Alerts** - Reorder points checked only during PO generation

---

## QIM Module Requirements

### Must Have (P0)

1. Bin location CRUD with location hierarchy
2. SKU-to-bin assignment management
3. Stock level tracking synchronized with WooCommerce
4. Bin count adjustments with audit logging
5. Bin history report
6. Component reservation tracking (soft and hard locks)
7. Integration with QPM for PO receiving
8. Integration with QAM for production allocation
9. Integration with QFM for picklist bin locations

### Should Have (P1)

1. Visual bin map/warehouse layout
2. Barcode scanning support for bin operations
3. Cycle count workflow
4. Low stock alerts and notifications
5. Stock movement report
6. Multi-bin support per SKU

### Nice to Have (P2)

1. FIFO/LIFO stock rotation tracking
2. Lot/batch tracking
3. Expiration date tracking
4. Stock valuation reporting
5. ABC inventory classification

---

## Data Migration Considerations

### Tables to Migrate

| Source | Records | Notes |
|--------|---------|-------|
| `oms_bins` | ~500 | Bin locations and assignments |
| `oms_bin_log` | ~50,000 | Historical transactions |

### Migration Notes

1. Validate bin assignments against current WooCommerce products
2. Reconcile bin counts with WooCommerce stock levels
3. Archive old bin log entries (keep recent 2 years active)
4. Map legacy location codes to new hierarchy

---

## Reservation System Design

### Proposed States

| State | Description | Trigger |
|-------|-------------|---------|
| Available | No claim on stock | Default state |
| Soft Reserved | Claimed for order, can be reallocated | Order placed |
| Hard Locked | Committed to production batch | Batch created |
| In Transit | Moving between locations | Shipment created |

### Rules

1. Soft reserves auto-expire after configurable period
2. Hard locks prevent any reallocation
3. Production can only lock available or soft-reserved stock
4. Shipping can only pick from available or soft-reserved

---

## Questions for Clarification

1. Should QIM support multi-warehouse (multiple locations)?
2. What bin naming convention should be enforced?
3. Should stock alerts be email, Slack, or dashboard-based?
4. Is FIFO tracking needed for any SKU types?
5. Should cycle counts integrate with physical scanners?

---

*Document created by Claude Code + Chris Warris - January 2026*

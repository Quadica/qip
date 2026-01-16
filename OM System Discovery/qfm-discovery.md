# QFM - Quadica Fulfillment Management - Discovery

**Module Code:** QFM
**Status:** Discovery
**Created:** January 2026
**Author:** Claude Code + Chris Warris

---

## Purpose

This discovery document captures the exploration and analysis of shipping batch and fulfillment functionality in the legacy OM system. It documents the current implementation, identifies requirements, and informs the design of the QFM module.

---

## Legacy System Analysis

### Source Files Analyzed

| File | Purpose |
|------|---------|
| `shipbatch-generate.php` | Create new shipping batch UI |
| `gen_canship.php` | Determines orders ready to ship |
| `gen_shipbatch.php` | Creates shipping batch records |
| `shipbatch.php` | View/manage individual ship batch |
| `shipbatches.php` | List all shipping batches |
| `gen_picklist.php` | Generate picklist for batch |
| `classes/shipstation.php` | ShipStation API integration |

### Database Tables

**`oms_shipbatches`** - Shipping batch headers
```sql
shipbatch_id INT PRIMARY KEY AUTO_INCREMENT
batch_date DATE
status VARCHAR(50)          -- 'Pending', 'Shipped', 'Complete'
created_by INT
created_at DATETIME
shipped_at DATETIME
notes TEXT
```

**`oms_shipbatch_items`** - Shipping batch line items
```sql
id INT PRIMARY KEY AUTO_INCREMENT
shipbatch_id INT (FK)
order_id INT                -- WooCommerce order ID
order_no INT                -- Order number
sku VARCHAR(100)
qty INT
status VARCHAR(50)
```

**`oms_canship`** - Temporary table for ship calculations
```sql
-- Rebuilt fresh during each batch generation
order_id INT
order_no INT
priority INT
order_date DATE
order_time TIME
status VARCHAR(50)
customer_org VARCHAR(255)
firstname VARCHAR(100)
lastname VARCHAR(100)
carrier VARCHAR(100)
shipmethod VARCHAR(100)
internal_comments TEXT
customer_comments TEXT
customer_instructions TEXT
text_priority VARCHAR(50)
order_value DECIMAL(10,2)
order_num_items INT
```

**`oms_ship_candidates`** - Order candidates for shipping
```sql
order_id INT
order_no INT
priority INT
order_date DATE
order_time TIME
sku VARCHAR(100)
custom INT                  -- Is custom order
order_status VARCHAR(50)
order_qty INT
status VARCHAR(50)
firstname VARCHAR(100)
lastname VARCHAR(100)
customer_org VARCHAR(255)
shipmethod VARCHAR(100)
carrier VARCHAR(100)
carrier_rep VARCHAR(100)
internal_comments TEXT
customer_comments TEXT
text_priority VARCHAR(50)
```

---

## Current Workflows

### 1. Generate Shipping Batch

**Entry Point:** `shipbatch-generate.php`

**Process:**
1. System calls `gen_canship.php` on page load
2. `gen_canship.php` performs:
   a. Truncates `oms_canship` and `oms_ship_candidates`
   b. Queries orders with `wc-process` status
   c. For each order:
      - Checks "do not ship until" date
      - Gets order priority (expedite flag)
      - Gets shipping method and carrier from order items
      - Gets line items with SKUs and quantities
   d. Inserts into `oms_ship_candidates`
   e. Calculates if all items can be fulfilled from stock
   f. Orders that can fully ship go to `oms_canship`
3. System displays candidate list with:
   - Order number (linked to WP admin)
   - Priority
   - Order date
   - Item count
   - Order value
   - Order status
   - Customer organization
   - Carrier
   - Internal comments
   - Customer comments
   - Customer instructions
   - Include checkbox (pre-checked)
4. User unchecks orders to exclude
5. "Regenerate" recalculates with exclusions
6. "Create Shipping Batch" creates the batch

**Key Calculation in `gen_canship.php`:**
```php
// For each order in wc-process status:
// 1. Check if "do not ship until" date has passed
// 2. For each line item:
//    a. Check if SKU is available in stock
//    b. Track custom vs regular items
// 3. If ALL items can be fulfilled, add to canship
// 4. Sort by priority DESC, order_date ASC
```

### 2. Can Ship Determination

An order is considered "can ship" when:
1. Order status is `wc-process`
2. "Do not ship until" date is in the past or not set
3. ALL line items have sufficient stock to fulfill
4. Custom items have been built (if applicable)

**Exclusion Reasons (documented in KB):**
- Order on hold
- Missing products
- Insufficient stock
- Future ship date
- Custom items not ready
- Payment pending

### 3. Picklist Generation

**Entry Point:** `gen_picklist.php`

**Features:**
- Groups items by bin location for efficient picking
- Shows SKU, description, quantity, bin location
- Sorted by warehouse aisle/bin for walking path
- Print-friendly format

### 4. ShipStation Integration

**Entry Point:** `classes/shipstation.php`

**Features:**
- Push orders to ShipStation
- Retrieve tracking numbers
- Update order status to shipped
- Sync shipping labels

---

## Can Ship Logic Detail

```php
// Pseudo-code from gen_canship.php

foreach ($orders as $order) {
    // Skip if ship date in future
    if ($order->do_not_ship_until > today()) {
        continue;
    }

    $can_ship = true;
    foreach ($order->line_items as $item) {
        $sku = $item->sku;
        $qty_needed = $item->quantity;
        $qty_available = get_stock($sku);

        if ($qty_available < $qty_needed) {
            $can_ship = false;
            break;
        }
    }

    if ($can_ship) {
        insert_into_canship($order);
    }
}
```

---

## Integration Points

### WooCommerce Integration

**Data Read:**
- Orders with `wc-process` status
- Order line items (SKUs, quantities)
- Order meta (priority, do_not_ship_until, comments)
- Shipping method and carrier from order items
- Customer billing/shipping addresses

**Data Written:**
- Order status updates (→ shipped/completed)
- Tracking number meta
- Shipment date meta

### Inventory System Integration

**Data Read:**
- Stock levels for "can ship" calculation
- Bin locations for picklist generation

**Data Written:**
- Stock deductions when batch shipped (via WooCommerce)

### Production System Integration

**Dependency:**
- Production batches must complete before modules are available to ship

### ShipStation Integration

**Operations:**
- Push order data to ShipStation
- Retrieve shipping labels
- Get tracking numbers
- Update shipped status

---

## Data Flow

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   WooCommerce    │────▶│   gen_canship    │────▶│   oms_canship    │
│    Orders        │     │   (calculate)    │     │    (ready)       │
└──────────────────┘     └──────────────────┘     └──────────────────┘
                                                           │
                                                           ▼
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│    ShipStation   │◀────│ oms_shipbatch_   │◀────│  gen_shipbatch   │
│                  │     │     items        │     │    (create)      │
└──────────────────┘     └──────────────────┘     └──────────────────┘
                                  │
                                  ▼
                         ┌──────────────────┐
                         │  oms_shipbatches │
                         │    (header)      │
                         └──────────────────┘
                                  │
                                  ▼
                         ┌──────────────────┐
                         │   gen_picklist   │
                         │   (warehouse)    │
                         └──────────────────┘
```

---

## Pain Points in Legacy System

1. **Temporary Tables** - `oms_canship`, `oms_ship_candidates` rebuilt each time
2. **No Partial Shipments** - Must ship entire order or nothing
3. **Manual ShipStation Sync** - Not fully automated
4. **Limited Tracking** - Basic tracking number storage only
5. **No Pack Verification** - No scan-to-verify packing workflow
6. **No Carrier Rate Shopping** - Manual carrier selection
7. **No Delivery Promises** - No estimated delivery date management

---

## QFM Module Requirements

### Must Have (P0)

1. "Can ship" calculation for order fulfillment
2. Shipping batch creation from ready orders
3. Picklist generation sorted by bin location
4. Order status tracking through fulfillment
5. ShipStation integration for labels and tracking
6. Integration with QIM for stock and bin data
7. Integration with QAM for production completion
8. Batch list and detail views

### Should Have (P1)

1. Partial shipment support (split orders)
2. Pack verification workflow (scan-to-verify)
3. Automated ShipStation sync
4. Delivery date estimation
5. Shipment notifications to customers
6. Return/RMA management

### Nice to Have (P2)

1. Carrier rate shopping
2. Address validation
3. International customs documentation
4. Shipping cost tracking and reporting
5. Delivery confirmation integration
6. Customer self-service tracking portal

---

## Data Migration Considerations

### Tables to Migrate

| Source | Records | Notes |
|--------|---------|-------|
| `oms_shipbatches` | ~3,000 | Batch headers |
| `oms_shipbatch_items` | ~20,000 | Batch line items |

### Migration Notes

1. Map batch statuses to new workflow states
2. Link to WooCommerce order IDs
3. Preserve ShipStation order IDs for reference
4. Archive old batches (keep recent 2 years active)
5. Migrate tracking numbers to WooCommerce order meta

---

## ShipStation Integration Details

### Current Implementation

The legacy system uses `classes/shipstation.php` for:
- Creating orders in ShipStation
- Retrieving shipment details
- Updating WooCommerce with tracking

### API Operations

| Operation | Purpose |
|-----------|---------|
| Create Order | Push order details to ShipStation |
| Get Shipments | Retrieve tracking and label info |
| Mark Shipped | Update order status in both systems |

### Integration Points

```
LuxeonStar OM ──┬── Push Order ────▶ ShipStation
               │
               └── Get Tracking ◀── ShipStation
                        │
                        ▼
               WooCommerce Order
               (tracking meta)
```

---

## Picklist Optimization

### Current Approach

Items sorted by:
1. Warehouse location (MAIN, ANNEX)
2. Aisle number
3. Bin number

### Walking Path

```
MAIN Warehouse
├── Aisle A
│   ├── Bin A-01 → Pick items
│   ├── Bin A-02 → Pick items
│   └── Bin A-03 → Pick items
├── Aisle B
│   ├── Bin B-01 → Pick items
│   └── ...
└── ...
```

### Proposed Enhancements

1. Wave picking (multiple orders, single pass)
2. Zone picking (picker per area)
3. Batch picking with put-to-light

---

## Questions for Clarification

1. Should QFM support partial shipments (backorders)?
2. What ShipStation API version is currently used?
3. Is carrier rate shopping a priority?
4. Should packing verification be required or optional?
5. How should international orders be handled differently?
6. Is real-time ShipStation sync needed vs. batch sync?

---

*Document created by Claude Code + Chris Warris - January 2026*

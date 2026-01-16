# QFM - Quadica Fulfillment Management

**Module Code:** QFM
**Status:** Planned
**Created:** January 2026

---

## Purpose

QFM manages order fulfillment from production completion through shipping. It handles shipping batch creation, picklist generation, "can ship" calculations, and order completion tracking. QFM is the final step in the order lifecycle before handoff to shipping carriers.

---

## Scope

### In Scope
- Shipping batch creation and management
- "Can ship" calculations based on stock availability
- Picklist generation for warehouse picking
- Order completion tracking
- Shipping documentation
- Tray/staging area management
- Order status updates

### Out of Scope
- Carrier label generation (ShipStation handles this)
- Carrier rate shopping (ShipStation handles this)
- Purchasing (see QPM)
- Production (see QAM)
- Inventory management (see QIM)

---

## Legacy OM Replacement

| Legacy File | Functionality | QFM Replacement |
|-------------|---------------|-----------------|
| `shipbatch-generate.php` | Generate shipping candidates | Fulfillment queue |
| `shipbatches.php` | List shipping batches | Batch list dashboard |
| `shipbatch.php` | View/manage batch | Batch detail screen |
| `shipbatch-picklist.php` | Print picking list | Picklist generation |
| `gen_canship.php` | Calculate shippable orders | "Can ship" engine |
| `gen_shipbatch.php` | Create shipping batch | Batch creation |
| `gen-shipbatch-csv.php` | Export to ShipStation | ShipStation sync |
| `report-order-status.php` | Order status dashboard | Order visibility |

**Legacy Tables:**
- `oms_shipbatches` (6,143 records) - Shipping batches
- `oms_shipbatch_items` - Batch line items
- `oms_canship` - Shippable orders (regenerated)
- `oms_ship_candidates` - Candidate staging

---

## Core Concepts

### Shipping Batch
- Collection of orders grouped for fulfillment
- All items picked, verified, and prepared together
- Typically represents one picking session

### Can Ship
- Order where ALL line items have available stock
- Stock must not be reserved for other orders/batches
- Considers both finished modules and other products

### Order Completion
- Order is complete when all items are picked and packed
- May span multiple production batches (for modules)
- Triggers shipping label generation in ShipStation

### Tray Staging
- Completed modules stored in labeled trays by order
- Trays held in staging area until order ships
- Low volume operation (typically 5-30 orders in progress)

---

## Core Workflows

### 1. View Fulfillment Queue
1. Display all orders awaiting fulfillment
2. Show fulfillment status:
   - Ready to Ship (all items available)
   - Partial (some items available)
   - Blocked (waiting on production or stock)
3. Indicate what's blocking partial/blocked orders
4. Filter by priority, date, status, shipping method

### 2. Create Shipping Batch
1. Select orders to include in batch
2. System validates stock availability
3. Reserve stock for selected orders
4. Generate batch with unique identifier
5. Batch ready for picking

### 3. Generate Picklist
1. Print picklist for warehouse staff
2. List includes:
   - Order numbers and customer names
   - Items to pick with bin locations
   - Quantities per item
   - Special instructions
3. Sort by bin location for efficient picking path

### 4. Pick and Verify
1. Warehouse picks items per picklist
2. Verify quantities against order
3. Mark items as picked in system
4. Handle exceptions (short picks, damage)

### 5. Complete Shipping Batch
1. All orders in batch picked and verified
2. Update order status in WooCommerce
3. Trigger ShipStation sync (if not automatic)
4. Release to shipping for label generation
5. Mark batch complete

### 6. Order Status Tracking
1. View order progress through fulfillment
2. See which batches contain order items
3. Track estimated ship date
4. Handle customer inquiries

---

## Key Features

- **Can Ship Engine:** Real-time availability calculations
- **Priority Management:** Rush orders surface to top
- **Efficient Picking:** Picklists sorted by bin location
- **ShipStation Integration:** Seamless handoff to shipping
- **Order Visibility:** Track orders through entire lifecycle
- **Batch Flexibility:** Group orders strategically
- **Exception Handling:** Manage shorts, holds, cancellations

---

## Data Model (Conceptual)

```
qfm_shipping_batches
  - batch_id (PK)
  - batch_number (display)
  - status (pending/picking/complete/cancelled)
  - created_by
  - created_at
  - picking_started_at
  - completed_at
  - notes

qfm_batch_orders
  - id (PK)
  - batch_id (FK)
  - order_id (WC order ID)
  - order_number (WC order number)
  - priority
  - status (pending/picked/complete/exception)
  - picked_at
  - notes

qfm_batch_items
  - item_id (PK)
  - batch_id (FK)
  - order_id
  - sku
  - product_name
  - quantity_ordered
  - quantity_picked
  - bin_id (FK to QIM)
  - status (pending/picked/short/exception)
  - picked_by
  - picked_at

qfm_order_holds
  - hold_id (PK)
  - order_id
  - hold_type (payment/customer_request/address/other)
  - hold_reason
  - hold_until (date, nullable)
  - created_by
  - created_at
  - released_at
  - released_by
```

---

## Integration Points

| Module | Integration |
|--------|-------------|
| **QIM** | Stock availability for "can ship"; bin locations for picklists |
| **QAM** | Production completion triggers fulfillment eligibility |
| **WooCommerce** | Order data; status updates; customer information |
| **ShipStation** | Order export for label generation; tracking import |

---

## ShipStation Integration

### Current State (Legacy OM)
- Custom integration via `classes/shipstation.php`
- Manual CSV export and API calls
- Currency conversion for customs

### Recommended Approach
- Use WooCommerce ShipStation plugin for core sync
- QFM triggers "ready to ship" status that ShipStation picks up
- Verify no custom logic in legacy integration is required
- If custom logic needed, build minimal integration layer

### Data Synced to ShipStation
- Order details and line items
- Customer shipping address
- Product weights and dimensions
- Customs information (HTS codes, country of origin)

---

## Success Criteria

1. Fulfillment queue provides clear visibility into all orders
2. "Can ship" calculations are accurate and real-time
3. Picklists are efficient and reduce picking errors
4. ShipStation integration is seamless
5. Order status is always current and accurate
6. Handling exceptions doesn't break workflow

---

## Migration Considerations

- Migrate `oms_shipbatches` for historical reference
- Verify ShipStation integration approach before migration
- Historical shipping data may be read-only archive
- Coordinate with WooCommerce order status mappings

---

## Dependencies

- **QIM** required for stock availability and bin locations
- **QAM** completion feeds fulfillment queue
- **WooCommerce** for order data
- **ShipStation** for carrier integration

---

## Open Questions

1. Continue custom ShipStation integration or use WC plugin?
2. How to handle orders with mixed items (modules + non-modules)?
3. Integration with existing shipping label printers?
4. Order hold/release workflow requirements?

---

*Document created by Claude Code + Chris Warris - January 2026*

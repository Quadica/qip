# QIM - Quadica Inventory Management

**Module Code:** QIM
**Status:** Planned
**Created:** January 2026

---

## Purpose

QIM provides centralized inventory and warehouse bin management, serving as the foundation for purchasing, production, and fulfillment operations. It bridges WooCommerce stock data with physical warehouse locations and component allocation tracking.

---

## Scope

### In Scope
- Bin location management (physical warehouse locations)
- Stock level visibility (tied to WooCommerce)
- Component allocation/reservation tracking
- Bin assignment workflows
- Stock movement history
- Inventory reporting
- Reorder point management

### Out of Scope
- Purchase order management (see QPM)
- Production batching (see QAM)
- Order fulfillment (see QFM)
- Product catalog management (WooCommerce)

---

## Legacy OM Replacement

| Legacy File | Functionality | QIM Replacement |
|-------------|---------------|-----------------|
| `report-inventory.php` | Inventory report with bin editing | Inventory dashboard |
| `report-bin-history.php` | Bin change history | Movement history report |
| `get_invrep_data.php` | Inventory data queries | API endpoints |
| `get_bin_data.php` | Bin data queries | API endpoints |
| `post_invrep_data.php` | Save inventory changes | Bin management UI |

**Legacy Tables:**
- `oms_bins` (988 records) - Warehouse bin locations
- `oms_bin_log` - Bin change history
- `oms_currentstock` - Stock snapshot (regenerated)

---

## Core Concepts

### Bins
- Physical warehouse locations identified by bin number
- Each bin can be designated as "dynamic" (contents change) or "static" (dedicated to specific SKU)
- Bins have picking priority for fulfillment optimization
- Bins track current SKU assignment

### Stock Levels
- Primary source of truth: WooCommerce product stock quantities
- QIM provides additional visibility: which bins contain which SKUs
- Stock movements recorded for audit trail

### Component Reservation
- **Soft Reservation:** Preliminary allocation that can be reallocated to higher-priority orders
- **Hard Lock:** Firm allocation to active production batch, cannot be reallocated
- Reservation system prevents over-commitment of components

---

## Core Workflows

### 1. Bin Management
1. View all bins with current contents and status
2. Create new bin locations as warehouse expands
3. Edit bin properties (dynamic/static, priority, current SKU)
4. Reassign bin contents when reorganizing warehouse
5. Deactivate bins no longer in use

### 2. Stock Visibility
1. View stock levels by SKU across all bins
2. See which bins contain a specific SKU
3. View total available vs. reserved quantities
4. Identify items below reorder point
5. Export inventory reports

### 3. Bin Assignment (Called by Other Modules)
- QPM calls during PO receiving: "Assign bin for received items"
- QAM calls during production receiving: "Assign bin for completed assemblies"
- QIM provides bin selection UI and records assignment

### 4. Stock Movement Recording
1. Record stock adjustments (cycle counts, damage, etc.)
2. Track movements between bins
3. Maintain audit trail of all changes
4. Link movements to source (PO receipt, production, adjustment)

---

## Key Features

- **WooCommerce Sync:** Stock quantities synchronized with WC product data
- **Multi-Bin Support:** Same SKU can exist in multiple bins
- **Reservation Tracking:** See available vs. reserved quantities
- **Movement History:** Full audit trail of stock changes
- **Bin Suggestions:** Intelligent bin assignment based on SKU history
- **Reorder Alerts:** Highlight items needing reorder
- **Barcode Support:** Bin lookup by scanning

---

## Data Model (Conceptual)

```
qim_bins
  - bin_id (PK)
  - bin_number (display identifier)
  - location_zone (optional grouping)
  - is_dynamic (boolean)
  - picking_priority (integer)
  - current_sku (nullable)
  - status (active/inactive)
  - notes
  - created_at
  - updated_at

qim_bin_contents
  - id (PK)
  - bin_id (FK)
  - sku
  - quantity
  - last_updated

qim_stock_movements
  - movement_id (PK)
  - sku
  - quantity (positive = in, negative = out)
  - movement_type (receipt/production/shipment/adjustment/transfer)
  - source_bin_id (nullable)
  - destination_bin_id (nullable)
  - reference_type (po/batch/order/manual)
  - reference_id
  - notes
  - created_by
  - created_at

qim_reservations
  - reservation_id (PK)
  - sku
  - quantity
  - reservation_type (soft/hard)
  - source_type (order/batch)
  - source_id
  - expires_at (for soft reservations)
  - created_at
```

---

## Integration Points

| Module | Integration |
|--------|-------------|
| **QPM** | Bin assignment during PO receiving; stock levels for reorder suggestions |
| **QAM** | Component reservation for batches; bin assignment for completed assemblies |
| **QFM** | Stock availability for "can ship" calculations; picking locations |
| **WooCommerce** | Bidirectional stock sync; product SKU linkage |

---

## Success Criteria

1. Accurate real-time stock visibility across all bins
2. Reservation system prevents over-allocation of components
3. All stock movements have audit trail
4. Bin assignment workflow is fast and intuitive
5. WooCommerce stock stays synchronized
6. Reorder alerts prevent stockouts

---

## Migration Considerations

- Migrate `oms_bins` to `qim_bins`
- Migrate `oms_bin_log` to `qim_stock_movements`
- Reconcile `oms_currentstock` with WooCommerce quantities
- Validate bin assignments are accurate before go-live

---

## Dependencies

- WooCommerce product catalog with accurate SKUs
- No hard dependencies on other Q modules (QIM is foundational)

---

## Open Questions

1. Should QIM manage WooCommerce stock quantities directly, or just provide visibility?
2. How to handle stock discrepancies between physical count and WC?
3. Reservation expiration policy for soft reservations?

---

*Document created by Claude Code + Chris Warris - January 2026*

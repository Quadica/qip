# QPM - Quadica Purchasing Management

**Module Code:** QPM
**Status:** Planned
**Created:** January 2026

---

## Purpose

QPM manages the procurement lifecycle for components used in LED module production. It replaces the legacy OM system's PO management functionality with a modern, WooCommerce-integrated solution.

---

## Scope

### In Scope
- Purchase order creation and management
- Vendor master data management
- PO receiving workflows
- Receiving against bin locations (integration with QIM)
- PO status tracking and reporting
- Email notifications to vendors

### Out of Scope
- Production batch management (see QAM)
- Inventory/bin management (see QIM)
- Shipping/fulfillment (see QFM)
- Component stock level management (see QIM)

---

## Legacy OM Replacement

| Legacy File | Functionality | QPM Replacement |
|-------------|---------------|-----------------|
| `po-gen.php` | Create new PO | PO creation screen |
| `po-all.php` | List all POs | PO list with filters |
| `po-edit.php` | Edit existing PO | PO edit screen |
| `po-receive.php` | Receive items | Receiving workflow |
| `po-receive-list.php` | POs awaiting receiving | Dashboard/home screen |
| `vendors-list.php` | List vendors | Vendor management |
| `vendors-edit.php` | Edit vendor | Vendor edit screen |
| `vendors-new.php` | Add vendor | Vendor creation |

**Legacy Tables:**
- `oms_po` (3,012 records) - Purchase orders
- `oms_po_items` - PO line items
- `oms_vendors` (32 records) - Vendor master

---

## Core Workflows

### 1. Create Purchase Order
1. Select vendor from master list
2. System displays vendor's products with current stock levels
3. Enter quantities to order (system suggests based on reorder points)
4. Add internal notes and expected delivery date
5. Save PO (status: Draft)
6. Submit PO (status: Submitted, triggers email to vendor)

### 2. Receive Against PO
1. View list of POs with items pending receipt
2. Select PO to receive
3. Enter quantities received per line item
4. Assign bin locations for received items (QIM integration)
5. Handle partial receipts (remaining quantities stay open)
6. Mark PO complete when fully received

### 3. Vendor Management
- CRUD operations for vendor records
- Contact information (primary + CC emails)
- Default shipping terms
- Typical lead times
- PO terms/payment terms

---

## Key Features

- **WooCommerce Integration:** Links to WC products via SKU for stock visibility
- **Bin Assignment:** Receiving workflow integrates with QIM for bin allocation
- **Email Automation:** Send PO to vendor via email with PDF attachment
- **Reorder Suggestions:** Highlight items below reorder point when creating PO
- **Partial Receiving:** Support for multiple partial receipts against single PO
- **Audit Trail:** Track PO status changes and receiving history

---

## Data Model (Conceptual)

```
qpm_vendors
  - vendor_id (PK)
  - vendor_name
  - contact_name
  - contact_email
  - cc_email
  - address
  - default_shipping
  - typical_lead_days
  - payment_terms
  - notes

qpm_purchase_orders
  - po_id (PK)
  - po_number (display number)
  - vendor_id (FK)
  - status (draft/submitted/partial/complete/cancelled)
  - po_date
  - expected_delivery_date
  - vendor_notes
  - internal_notes
  - created_by
  - created_at
  - updated_at

qpm_po_items
  - item_id (PK)
  - po_id (FK)
  - sku
  - vendor_sku
  - description
  - qty_ordered
  - qty_received
  - unit_price
  - received_date
  - bin_id (FK to QIM)
```

---

## Integration Points

| Module | Integration |
|--------|-------------|
| **QIM** | Receiving updates bin inventory; stock levels shown during PO creation |
| **WooCommerce** | Product SKUs linked; stock quantities synchronized |
| **Email** | PO submission triggers vendor notification |

---

## Success Criteria

1. Replace all legacy PO functionality without data loss
2. Receiving workflow updates inventory in real-time
3. PO creation takes less time than legacy system
4. Full audit trail of all PO activity
5. Vendor email notifications work reliably

---

## Migration Considerations

- Migrate `oms_vendors` to `qpm_vendors`
- Migrate `oms_po` and `oms_po_items` to new schema
- Historical POs should be viewable but may be read-only
- Validate vendor email addresses during migration

---

## Dependencies

- QIM must be available for bin assignment during receiving (or receiving works without bin assignment initially)
- WooCommerce product catalog must have accurate SKUs

---

*Document created by Claude Code + Chris Warris - January 2026*

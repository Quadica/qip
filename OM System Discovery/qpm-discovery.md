# QPM - Quadica Purchasing Management - Discovery

**Module Code:** QPM
**Status:** In Active Development
**Created:** January 2026
**Author:** Claude Code + Chris Warris

---

## Purpose

This discovery document captures the exploration and analysis of purchasing management functionality in the legacy OM system. It documents the current implementation, identifies requirements, and informs the design of the QPM module.

**Note:** A substantial QPM plugin already exists in active development at `/home/chris/Documents/Quadica Plugin Dev/QPM/`. See the "Existing QPM Plugin" section below for current implementation status.

---

## Existing QPM Plugin Status

### Repository Location
`/home/chris/Documents/Quadica Plugin Dev/QPM/wp-content/plugins/quadica-purchasing-management/`

### Plugin Version
1.0.0 (Active Development)

### Custom Post Types Implemented

| CPT | Purpose | Status |
|-----|---------|--------|
| `quad_vendor` | Vendor master records | ✅ Complete |
| `quad_vendor_sku` | Maps internal SKUs to vendor part numbers | ✅ Complete |
| `quad_required_sku` | SKUs needed for production | ✅ Complete |
| `quad_po_candidate` | Items staged for PO creation | ✅ Complete |
| `quad_purchase_order` | Formal purchase orders | ✅ Complete |
| `quad_po_generation` | Internal staging for PO creation | ✅ Complete |

### Modules Implemented

1. **Purchasing Module** (`PurchasingModule.php`)
   - Vendor management with full ACF field configuration
   - Vendor SKU mapping with validation
   - Required SKU tracking with automatic rebuilds
   - PO candidate management with reorder assessment
   - Purchase order workflow and status tracking
   - Receiving log and stock adjustment services
   - WP-CLI commands for automation

2. **BOM Module** (`BOMModule.php`)
   - Order BOM automation on status changes
   - Template copy and placeholder strategies
   - Email and Slack notifications for incomplete BOMs
   - Diff tracking for BOM changes

### Key Services

- `RequiredSkuManager` - Tracks components needed for production
- `PurchaseOrderManager` - Manages PO lifecycle
- `CandidateRepository` - PO candidate data access
- `ReorderAssessmentService` - Calculates reorder quantities
- `VendorSkuMappingService` - Maps SKUs to vendor part numbers
- `StockAdjustmentService` - Updates WooCommerce stock on receiving
- `BinCountSyncService` - Synchronizes bin counts

---

## Legacy System Analysis

### Source Files Analyzed

| File | Lines | Purpose |
|------|-------|---------|
| `po-gen.php` | 294 | Generate new purchase orders |
| `po-receive.php` | 140 | Receive items against PO |
| `po-receive-list.php` | 96 | List POs awaiting receiving |
| `po-all.php` | 155 | List all POs with filtering |
| `po-edit.php` | 247 | Edit existing PO |
| `po-report.php` | 244 | Print PO report for vendor |
| `post_po.php` | 380 | Save PO to database |
| `post_po_receive.php` | 150 | Save received quantities |
| `get_products.php` | 438 | Fetch vendor products for PO |
| `vendors-list.php` | 59 | List all vendors |
| `vendors-edit.php` | 152 | Edit vendor details |
| `vendors-new.php` | 125 | Add new vendor |
| `post_vendor.php` | 50 | Save vendor |
| `post_vendor_data.php` | 55 | Save vendor data |

### Database Tables

**`oms_vendors`** - Vendor master data
```sql
vendor_id INT PRIMARY KEY AUTO_INCREMENT
vendor_name VARCHAR(255)
contact VARCHAR(255)
contact_email VARCHAR(255)
cc_email VARCHAR(255)
address TEXT
default_ship VARCHAR(255)
typ_lead VARCHAR(25)        -- Typical lead time in days
po_terms VARCHAR(25)
```

**`oms_po`** - Purchase order headers
```sql
ponum INT PRIMARY KEY AUTO_INCREMENT
status TEXT                 -- Open, Received, Cancelled
vendor_id INT (FK)
po_date DATE
delivery_date DATE
vendor_note TEXT            -- Printed on PO
internal_note TEXT          -- Displayed during receiving
```

**`oms_po_items`** - Purchase order line items
```sql
id INT PRIMARY KEY AUTO_INCREMENT
ponum INT (FK to oms_po)
sku VARCHAR(255)            -- Our SKU
vendor_sku VARCHAR(255)     -- Vendor's SKU
item_description VARCHAR(255)
order_qty INT
item_price DECIMAL(11,2)
qty_received INT DEFAULT 0
order_date DATETIME
receive_date DATETIME
proj_receive_date DATE      -- Projected receive date
```

---

## Current Workflows

### 1. Create Purchase Order

**Entry Point:** `po-gen.php`

**Process:**
1. User selects vendor from dropdown
2. AJAX call to `get_products.php` fetches vendor's products
3. System displays products with:
   - Our SKU (linked to WooCommerce admin)
   - Vendor SKU
   - Product description (editable)
   - Suggested reorder quantity
   - Stock status
   - Reorder level
   - Preferred stock level
   - Min order & increment
   - Currently on order
   - Required for production
   - Last PO quantity and date
   - Vendor price (editable)
   - Line total (calculated)
4. User adjusts quantities (items with 0 qty excluded)
5. User enters:
   - Ship Via (pre-populated from vendor default)
   - Delivery date
   - Note to vendor (printed on PO)
   - Internal notes (shown during receiving)
6. "Generate PO" creates the purchase order
7. "Update Levels" saves reorder/stock level changes to WooCommerce

**Key Feature:** Reorder quantity calculation
- System compares current stock vs reorder level
- Factors in items currently on order
- Factors in items required for production
- Suggests quantity based on preferred stock level and increment

### 2. PO Receiving

**Entry Point:** `po-receive-list.php` → `po-receive.php`

**Process:**
1. List shows all open POs awaiting receiving
2. User clicks PO to open receiving screen
3. Screen shows:
   - Vendor name
   - PO number
   - PO date
   - Internal notes (highlighted in red)
   - Line items with:
     - SKU (linked to WooCommerce admin)
     - Quantity ordered
     - Quantity received to date
     - Quantity to receive (editable, defaults to remaining)
     - Assigned bin (if `PO_RECEIVE_SHOW_ASSIGNBIN = 1`)
4. User adjusts quantities to receive
5. "Receive Items" updates:
   - `oms_po_items.qty_received`
   - WooCommerce product stock
   - Bin assignments (if applicable)
   - Bin history log

**Configuration:**
- `PO_RECEIVE_SHOW_ASSIGNBIN` - Show/hide bin assignment column

### 3. Vendor Management

**Entry Point:** `vendors-list.php` → `vendors-edit.php` or `vendors-new.php`

**Vendor Fields:**
| Field | Purpose |
|-------|---------|
| Vendor Name | Display name for POs |
| Vendor Address | Multi-line address |
| Order Contact | Contact person name |
| Vendor Email | Primary email for PO sending |
| CC Email | Additional email recipient |
| Default Ship Via | Pre-populated on POs |
| Typical Lead Time | Days for delivery estimate |
| PO Terms | Payment/credit terms |

### 4. PO Reporting

**Entry Point:** `po-report.php`

**Features:**
- Generates printable PO document
- Includes vendor address
- Lists all line items with quantities and prices
- Shows vendor notes
- Formatted for printing/PDF

---

## Data Integration Points

### WooCommerce Integration

**Product Data Read:**
- Product SKU
- Current stock quantity
- Stock alert level (reorder point)
- Product description
- Vendor price (from product meta)
- Bin location

**Product Data Written:**
- Stock quantity (during receiving)
- Bin location (during receiving, if `UPDATE_WC_BIN = 1`)

### Production System Integration

**Data Read:**
- Components required for production batches
- Used in reorder quantity calculation

### Inventory System Integration

**Data Read:**
- Current bin assignments
- Stock levels by bin

**Data Written:**
- Bin assignments during receiving
- Bin history log entries

---

## Business Rules

### Reorder Calculation

1. **Stock Status** = Current WC Stock - (On Order + Required for Production)
2. **Suggested Qty** = MAX(Preferred Stock Level - Stock Status, 0)
3. **Rounded Qty** = Round up to Min Order & Increment

### Receiving Rules

1. Can receive partial quantities
2. Cannot receive more than ordered (system allows but warns)
3. Receiving updates WooCommerce stock immediately
4. Multiple receiving sessions allowed per PO
5. PO status changes to "Received" when all items fully received

### Vendor Rules

1. Vendor email required for PO generation
2. CC email optional
3. One vendor per PO (no multi-vendor POs)
4. Products linked to vendors via WooCommerce product meta

---

## Key UI Elements

### PO Generation Screen

```
[Vendor Dropdown] [Show Needed Items]     [Update Levels]

| Our SKU | Vendor SKU | Description | Reorder Qty | Stock Status | Reorder Level | Preferred | Min Order | On Order | Req Prod | Last PO | Last Date | Price | Total |
|---------|------------|-------------|-------------|--------------|---------------|-----------|-----------|----------|----------|---------|-----------|-------|-------|

Ship Via: [________________]
Delivery Date: [________]

Note to Vendor:
[___________________________]

Internal Notes:
[___________________________]

[Generate PO]
```

### PO Receiving Screen

```
RECEIVE PURCHASE ORDER
Vendor: [Vendor Name]
PO: [12345]
PO Date: [2025-01-15]
Internal Notes: [Highlighted text]

| SKU | Qty Ordered | Qty Received | Qty to Receive | Assigned Bin |
|-----|-------------|--------------|----------------|--------------|

[Receive Items]
```

---

## Pain Points in Legacy System

1. **No Authentication** - Anyone with URL can access
2. **Outdated JavaScript** - jQuery 1.8.0 (2012)
3. **Direct Database Access** - Bypasses WordPress `$wpdb`
4. **No AJAX Error Handling** - Silent failures possible
5. **Manual Stock Updates** - No automatic reorder suggestions
6. **Limited Audit Trail** - Basic bin log only
7. **No PO Approval Workflow** - Direct creation to vendor
8. **Email Not Integrated** - PO sending is manual process

---

## QPM Module Requirements

### Must Have (P0)

1. Vendor CRUD with all current fields
2. PO creation with vendor product selection
3. Reorder quantity calculation matching current logic
4. PO receiving with stock updates
5. Bin assignment during receiving
6. WooCommerce stock synchronization
7. Print-friendly PO report
8. WordPress authentication integration

### Should Have (P1)

1. PO email sending (with PDF attachment)
2. PO status workflow (Draft → Sent → Partial → Received)
3. Receiving history per line item
4. Reorder point alerts/notifications
5. Vendor product catalog management
6. PO search and filtering

### Nice to Have (P2)

1. Automatic reorder suggestions
2. PO approval workflow
3. Vendor performance metrics
4. Purchase history reports
5. Multi-currency support
6. Vendor portal access

---

## Data Migration Considerations

### Tables to Migrate

| Source | Records | Target |
|--------|---------|--------|
| `oms_vendors` | ~32 | QPM vendor table |
| `oms_po` | ~3,000 | QPM PO table |
| `oms_po_items` | ~15,000 | QPM PO items table |

### Migration Notes

1. Preserve PO numbers for historical reference
2. Link vendors to existing WooCommerce data
3. Archive old POs as read-only
4. Validate stock quantities during migration
5. Map bin assignments to QIM system

---

## Questions for Clarification

1. Should QPM have its own vendor table or use ACF fields on a CPT?
2. What PO statuses are needed beyond Open/Received/Cancelled?
3. Should email sending be integrated or remain manual?
4. What approval workflow (if any) is needed?
5. Should historical POs be migrated or archived separately?
6. How should vendor products be linked - per product or global catalog?

---

*Document created by Claude Code + Chris Warris - January 2026*

# OM System Discovery Document

> **Purpose:** Exhaustive summary of the legacy Order Management (OM) system to inform decisions about which functionality to recreate in QIP plugins and what has become redundant.

**System Age:** ~23 years (first created 2002, major rewrite 2013)
**Current Location:** `https://luxeonstar.com/om/`
**Server Path:** `/www/luxeonstarleds_546/public/om/`
**Discovery Date:** January 2026

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [System Architecture](#system-architecture)
3. [Core Functional Modules](#core-functional-modules)
4. [Database Schema](#database-schema)
5. [External Integrations](#external-integrations)
6. [Data Volumes](#data-volumes)
7. [File Inventory](#file-inventory)
8. [Historical Documentation](#historical-documentation)
9. [Technology Stack](#technology-stack)
10. [Functionality Assessment](#functionality-assessment)

---

## Executive Summary

The OM system is a standalone PHP application that operates alongside (but largely independent of) WordPress/WooCommerce. It was originally built for 3dCart and has been adapted to work with WooCommerce. The system handles:

- **Production Batch Management** - Assembly of LED products from components
- **Shipping Batch Management** - Order fulfillment and shipping workflows
- **Purchase Order Management** - Vendor ordering and receiving
- **Inventory & Bin Management** - Warehouse location tracking
- **Product Data Management** - Extended product specifications (LED data)
- **Label Printing** - Product and shipping labels with barcodes
- **Reporting** - Order status, inventory, and shipping reports
- **ShipStation Integration** - Bidirectional sync with ShipStation

### Key Observations

1. **Tightly Coupled to WooCommerce** - Uses `wp-load.php` to access WooCommerce data directly via database queries and WC functions
2. **Dual Data Model** - Maintains its own `oms_*` tables while reading from WooCommerce tables
3. **No Authentication** - The OM system has no login/authentication (relies on URL obscurity or server-level protection)
4. **Legacy JavaScript** - Uses jQuery 1.8.0 (2012) and jQuery UI 1.8.12 (2011)
5. **Mixed Code Quality** - Some files heavily commented, others minimally documented
6. **Extensive Logging** - Writes debug logs to `tmp/` directory for troubleshooting

---

## System Architecture

### Entry Points

| URL | File | Purpose |
|-----|------|---------|
| `/om/` | `index.php` | Redirects to `po-receive-list.php` (main dashboard) |
| `/om/po-receive-list.php` | Main entry | PO receiving list - the "home page" |

### File Organization

```
/om/
├── config.php                    # Database and API credentials
├── index.php                     # Redirect to main page
├── om.css                        # Main stylesheet
├── printed-reports.css           # Print-specific styles
├── header-*.php                  # Page header templates (1-4)
├── footer.php                    # Page footer
│
├── classes/                      # PHP classes
│   ├── dbi_class.php            # Database interface
│   ├── dbi_class-p.php          # Alternative DB class
│   └── shipstation.php          # ShipStation API wrapper
│
├── includes/                     # Shared includes
│   ├── functions.inc.php        # Utility functions
│   ├── woo.inc.php              # WooCommerce integration
│   └── woo_mappings.php         # Field mappings
│
├── javascript/                   # Frontend JS
│   ├── jquery-1.8.0.min.js      # jQuery (2012)
│   ├── jquery-ui-1.8.12.min.js  # jQuery UI (2011)
│   └── filterlist.js            # Table filtering
│
├── barcodes/                     # Generated barcode images
├── php-barcode-generator/        # Barcode generation library
├── images/                       # Static images
├── tmp/                          # Log files and temp data
├── archive/                      # Backup versions of files
└── docs/                         # PDF documentation (historical)
```

### Request Flow

1. User accesses OM page (e.g., `prod-batch.php`)
2. Page includes `wp-load.php` to bootstrap WordPress
3. Page includes `config.php` for credentials
4. Page includes `dbi_class.php` for database connection
5. AJAX calls to `get_*.php` files fetch data as JSON
6. `post_*.php` files handle form submissions

---

## Core Functional Modules

### 1. Purchase Order Management

**Purpose:** Create and track purchase orders to vendors for components/inventory.

| File | Function |
|------|----------|
| `po-gen.php` | Create new PO - select vendor, add line items |
| `po-all.php` | List all POs with filtering |
| `po-edit.php` | Edit existing PO |
| `po-receive.php` | Receive items against PO |
| `po-receive-list.php` | List POs awaiting receiving |
| `po-report.php` | Print PO report |
| `post_po.php` | Save PO to database |
| `post_po_receive.php` | Save received quantities |
| `get_products.php` | Fetch vendor products for PO line items |

**Workflow:**
1. Select vendor from dropdown
2. System shows vendor's products with reorder levels
3. Enter quantities to order
4. Generate PO (saves to `oms_po` and `oms_po_items`)
5. Send PO to vendor (email with PDF)
6. Receive items when they arrive (updates `qty_received`)
7. Assign bin locations during receiving

### 2. Production Batch Management

**Purpose:** Manage assembly of products from components (e.g., mounting LEDs to heatsinks).

| File | Function |
|------|----------|
| `prod-generate.php` | Start batch generation wizard |
| `gen_asmlist.php` | Generate assembly candidate list |
| `gen_batch.php` | Create production batch |
| `prod-batch.php` | View/manage batch details |
| `prod-batch-list.php` | List all production batches |
| `prod-batch-receive.php` | Receive completed assemblies |
| `prod-batch-labels.php` | Print batch labels |
| `prod-report.php` | Print production report |
| `prod-file.php` | Export batch to CSV |
| `prod-binning-report.php` | Bin assignment report |
| `get_batch_data.php` | AJAX: Fetch batch data |
| `get_batch_items.php` | AJAX: Fetch batch line items |
| `post_prod_batch.php` | Save batch changes |

**Key Concepts:**
- **Assembly SKU** - The finished product SKU
- **Component SKUs** - The parts that make up the assembly (defined in `oms_assemblies`)
- **Candidates** - Orders/inventory items that could be built
- **Can Build** - Items with sufficient component stock

**Workflow:**
1. Generate candidates from open orders (`wc-process` status) or inventory needs
2. System checks component availability against `oms_currentstock`
3. Select items to include in batch
4. Create batch (saves to `oms_prod_batch` and `oms_batch_items`)
5. Print production report for assembly team
6. Receive completed assemblies (updates WooCommerce stock)
7. Assign bin locations

### 3. Shipping Batch Management

**Purpose:** Group orders for fulfillment and shipping.

| File | Function |
|------|----------|
| `shipbatch-generate.php` | Generate shipping candidates |
| `gen_canship.php` | Determine which orders can ship |
| `gen_shipbatch.php` | Create shipping batch |
| `shipbatches.php` | List all shipping batches |
| `shipbatch.php` | View/manage batch details |
| `shipbatch-picklist.php` | Print picking list |
| `shipbatch-labels.php` | Print shipping labels |
| `gen-shipbatch-csv.php` | Export to CSV for ShipStation |
| `update_shipbatch.php` | Update batch status |
| `get_shipbatch_data.php` | AJAX: Fetch batch data |
| `post_shipbatch_data.php` | Save batch changes |

**Key Concepts:**
- **Can Ship** - Orders with all items in stock
- **Ship Candidates** - Potential orders for a batch
- **Priority** - Order urgency (affects sort order)
- **Do Not Ship Until** - Date-based shipping hold

**Workflow:**
1. Generate "can ship" list from `wc-process` orders
2. System checks inventory availability
3. Select orders to include in batch
4. Create batch (saves to `oms_shipbatches` and `oms_shipbatch_items`)
5. Print picklist for warehouse
6. Pick items and verify
7. Export to ShipStation or print labels
8. Mark batch as shipped (updates order status)

### 4. Inventory & Bin Management

**Purpose:** Track warehouse bin locations and stock levels.

| File | Function |
|------|----------|
| `report-inventory.php` | Inventory report with bin editing |
| `report-bin-history.php` | History of bin changes |
| `get_invrep_data.php` | AJAX: Fetch inventory data |
| `get_bin_data.php` | AJAX: Fetch bin data |
| `post_invrep_data.php` | Save inventory changes |

**Key Concepts:**
- **Bin** - Physical warehouse location (number)
- **Dynamic Bin** - Bin can change based on contents
- **Priority** - Picking priority for bin
- **Current SKU** - What's currently in the bin

**Bin Assignment Process:**
- During PO receiving, assign bin to received items
- During production batch receiving, assign bin to completed assemblies
- System can update WooCommerce product meta with bin location (`UPDATE_WC_BIN` config)

### 5. Product Data Management

**Purpose:** Extended product specifications beyond WooCommerce.

| File | Function |
|------|----------|
| `get_products_info.php` | Fetch extended product data |
| `post_product_data.php` | Save product data changes |
| `import-led-data.php` | Import LED specs from CSV |
| `import-product-data.php` | Import product specs from CSV |
| `copy_description.php` | Copy descriptions between products |
| `custom-sku-generate.php` | Generate custom SKUs |
| `woo_query_products.php` | Query WooCommerce products |

**Data Tables:**
- `oms_product_data` - Extended specs (dimensions, materials, compliance, datasheets)
- `oms_led_data` - LED-specific specs (color, wavelength, output, thermal)

### 6. Assembly Management

**Purpose:** Define product compositions (bill of materials).

| File | Function |
|------|----------|
| `prod-assemblies.php` | View/edit assembly definitions |
| `prod-assemblies-new.php` | Create new assembly |
| `update-assemblies.php` | Update assembly components |
| `post_assemblies_data.php` | Save assembly changes |
| `woo_get_component_data.php` | Fetch component info from WC |

**Assembly Structure:**
- Each assembly SKU maps to 1+ component SKUs
- LED Position (1, 2, 3...) indicates physical placement
- Component Quantity specifies how many of each component

### 7. Label Printing

**Purpose:** Generate and print product labels with barcodes.

| File | Function |
|------|----------|
| `label-printing.php` | Main label printing interface |
| `prod-batch-labels.php` | Labels for production batch |
| `prod-labels.php` | Single product labels |
| `shipbatch-labels.php` | Shipping labels |

**Features:**
- Search by order ID or product SKU
- Generate barcodes (Code 128)
- Print to label printer or PDF
- Customizable label layout

### 8. Order Status & Reporting

**Purpose:** Monitor order processing status.

| File | Function |
|------|----------|
| `report-order-status.php` | Order status dashboard |
| `report-unpaid-orders.php` | List unpaid orders |
| `get_order_status.php` | AJAX: Detailed order status |
| `get_order_data.php` | AJAX: Single order data |
| `get_ordreps_data.php` | AJAX: Order report data |
| `get_shipreps_data.php` | AJAX: Shipping report data |
| `woo_query_orders.php` | Query WooCommerce orders |
| `woo_get_order.php` | Get single WC order |

### 9. Vendor Management

**Purpose:** Maintain vendor contact and ordering information.

| File | Function |
|------|----------|
| `vendors-list.php` | List all vendors |
| `vendors-edit.php` | Edit vendor details |
| `vendors-new.php` | Add new vendor |
| `post_vendor.php` | Save vendor |
| `post_vendor_data.php` | Save vendor data |

### 10. ShipStation Integration

**Purpose:** Sync order and SKU data with ShipStation.

| File | Function |
|------|----------|
| `shipstation-update.php` | Update ShipStation with order/SKU data |
| `ss_query_1.php` | Query ShipStation API |
| `classes/shipstation.php` | ShipStation API wrapper |

**Features:**
- GET orders from ShipStation by order number
- POST updated SKU information (customs data, dimensions)
- Currency conversion (USD to CAD) using Bank of Canada rate
- Retry logic for API failures

---

## Database Schema

### OM-Specific Tables (17 tables)

#### Core Transaction Tables

**`oms_po`** - Purchase orders
```sql
ponum INT PRIMARY KEY AUTO_INCREMENT
status TEXT
vendor_id INT
po_date DATE
delivery_date DATE
vendor_note TEXT
internal_note TEXT
```

**`oms_po_items`** - PO line items
```sql
id INT PRIMARY KEY AUTO_INCREMENT
ponum INT (FK to oms_po)
sku VARCHAR(255)
vendor_sku VARCHAR(255)
item_description VARCHAR(255)
order_qty INT
item_price DECIMAL(11,2)
qty_received INT DEFAULT 0
order_date DATETIME
receive_date DATETIME
proj_receive_date DATE
```

**`oms_prod_batch`** - Production batches
```sql
batch_id INT PRIMARY KEY AUTO_INCREMENT
batch_date DATETIME
status VARCHAR(50) DEFAULT 'Pending'
```

**`oms_batch_items`** - Production batch line items
```sql
id INT PRIMARY KEY AUTO_INCREMENT
batch_id INT (FK to oms_prod_batch)
assembly_sku VARCHAR(50)
connector_option VARCHAR(4)
description VARCHAR(255)
custom INT DEFAULT 0
custom_manf INT DEFAULT 0
short_code TEXT
label_text TEXT
asm_instructions TEXT
order_no INT
priority INT DEFAULT 0
order_date DATE
order_time TIME
component_skus TEXT
build_qty INT DEFAULT 0
qty_received INT DEFAULT 0
order_id INT DEFAULT 0
bin INT
customer_instructions TEXT
customer_comments TEXT
internal_comments TEXT
```

**`oms_shipbatches`** - Shipping batches
```sql
shipbatch_id INT PRIMARY KEY AUTO_INCREMENT
shipbatch_date DATETIME
shipped INT DEFAULT 0
```

**`oms_shipbatch_items`** - Shipping batch line items
```sql
id INT PRIMARY KEY AUTO_INCREMENT
shipbatch_id INT (FK to oms_shipbatches)
order_id INT
order_no INT
priority INT
order_date DATE
order_time TIME
sku VARCHAR(150)
custom INT
order_status VARCHAR(32)
order_qty INT
toship_qty INT
status VARCHAR(50)
firstname VARCHAR(100)
lastname VARCHAR(100)
customer_org VARCHAR(200)
shipmethod VARCHAR(150)
carrier VARCHAR(150)
carrier_rep VARCHAR(150)
internal_comments TEXT
customer_comments TEXT
print_comments INT
shipment_id INT
customer_instructions VARCHAR(255)
```

#### Reference/Master Tables

**`oms_vendors`** - Vendor master
```sql
vendor_id INT PRIMARY KEY AUTO_INCREMENT
vendor_name VARCHAR(255)
contact VARCHAR(255)
contact_email VARCHAR(255)
cc_email VARCHAR(255)
address TEXT
default_ship VARCHAR(255)
typ_lead VARCHAR(25)
po_terms VARCHAR(25)
```

**`oms_assemblies`** - Bill of materials
```sql
assembly_sku VARCHAR(150) PRIMARY KEY
component_sku VARCHAR(50) PRIMARY KEY
led_position INT DEFAULT 0 PRIMARY KEY
component_quantity INT
```

**`oms_bins`** - Warehouse bin locations
```sql
bin_id INT PRIMARY KEY
location VARCHAR(10) PRIMARY KEY
dynamic VARCHAR(1)
priority INT
current_sku VARCHAR(150)
```

**`oms_bin_log`** - Bin change history
```sql
id INT PRIMARY KEY AUTO_INCREMENT
sku VARCHAR(150)
date DATETIME
previous_bin INT
next_bin INT
```

#### Product Data Tables

**`oms_product_data`** - Extended product specifications
```sql
sku TEXT UNIQUE
category TEXT
short_description_sku TEXT
long_description_sku TEXT
-- Dimensions (metric and imperial)
length_m, width_m, height_m, weight_m TEXT
length_i, width_i, height_i, weight_i TEXT
-- Product attributes
cooling_type, storage_temp, temp_range, product_type TEXT
-- Compliance
country_origin, hts, customs_description TEXT
prop_65_statement, reach_statement, rohs_statement TEXT
cmrt_statement, pfas_statement TEXT
-- Documentation links
datasheet_primary, datasheet_1, datasheet_2, datasheet_3 TEXT
app_note_1, app_note_2, app_note_3 TEXT
drawing_1, drawing_2, drawing_3 TEXT
-- And ~50 more attribute fields...
```

**`oms_led_data`** - LED specifications
```sql
sku TEXT UNIQUE
led_used, led_series, geometry TEXT
color_name, primary_color_name, techcolor TEXT
color_type, color_type_abbr TEXT
cri, viewing_angle TEXT
wavelength_min, wavelength_max TEXT
test_current, max_current, maxpulse_current TEXT
-- Output at various currents (20mA to 1000mA)
out_typ_20, out_typ_40, out_typ_100, ... TEXT
out_min_20, out_min_40, out_min_100, ... TEXT
out_max_20, out_max_40, out_max_100, ... TEXT
-- Forward voltage at various currents
vf_typ_20, vf_typ_40, vf_typ_100, ... TEXT
-- Thermal specs
max_junction_temp, led_thermal_resist TEXT
base_thermal_resist, total_thermal_resist TEXT
-- And ~50 more LED-specific fields...
```

#### Working/Temporary Tables

**`oms_canship`** - Orders ready to ship (regenerated each run)
```sql
order_id INT
order_no INT PRIMARY KEY
priority INT
order_date DATE
order_time TIME
sku VARCHAR(150) PRIMARY KEY
custom INT DEFAULT 0
order_status VARCHAR(32)
order_qty INT
toship_qty INT
status VARCHAR(50)
firstname, lastname, customer_org VARCHAR
shipmethod, carrier, carrier_rep VARCHAR
internal_comments, customer_comments TEXT
run_date TIMESTAMP
text_priority VARCHAR(20)
customer_instructions VARCHAR(255)
order_value DECIMAL(11,2)
order_num_items INT
```

**`oms_ship_candidates`** - Ship candidate staging
```sql
-- Similar structure to oms_canship
-- Used during batch generation
```

**`oms_canbuild`** - Items that can be built (regenerated)
```sql
order_no INT PRIMARY KEY
priority INT
order_date DATE
order_time TIME
assembly_sku VARCHAR(150) PRIMARY KEY
connector_option VARCHAR(4)
custom INT DEFAULT 0
custom_manf INT DEFAULT 0
component_skus TEXT
build_qty INT
order_id INT DEFAULT 0
instructions TEXT
run_date TIMESTAMP
customer_instructions VARCHAR(255)
customer_comments TEXT
internal_comments TEXT
```

**`oms_candidates`** - Build candidate staging
**`oms_stock_candidates`** - Stock-based build candidates
**`oms_currentstock`** - Current stock snapshot (regenerated)

---

## External Integrations

### 1. WooCommerce (Primary)

**Connection Method:** Bootstraps WordPress via `require_once("../wp-load.php")`

**Data Read:**
- Orders (`lw_posts`, `lw_postmeta`) - status, items, addresses, dates
- Products (`wc_get_products()`) - SKU, stock, prices
- Order items (`lw_woocommerce_order_items`, `lw_woocommerce_order_itemmeta`)
- Customer data

**Data Written:**
- Product stock quantities (via `update_post_meta`)
- Product bin locations (if `UPDATE_WC_BIN` enabled)
- Order notes/comments (limited)

**Key Functions Used:**
- `wc_get_order($id)` - Get order object
- `wc_get_products($args)` - Query products
- `get_post_meta()` / `update_post_meta()` - Meta operations
- `wc_format_decimal()` - Number formatting

### 2. ShipStation

**Connection:** REST API via cURL (`classes/shipstation.php`)

**Authentication:** Basic auth stored in `config.php` as `SHIPSTATION_AUTH`

**Endpoints Used:**
- `GET /orders?orderNumber={num}` - Fetch order data
- `POST /orders` - Create/update orders
- `PUT /orders` - Update order data

**Data Synced:**
- Order line items (SKU, description, customs info)
- Product dimensions and weights
- Customs declarations (HTS codes, country of origin)
- Currency conversion (USD to CAD)

### 3. Bank of Canada (Exchange Rates)

**Purpose:** Get current USD/CAD exchange rate for ShipStation sync

**Endpoint:** `https://www.bankofcanada.ca/valet/observations/FXUSDCAD/json?recent=1`

### 4. Email (SMTP)

**Configuration:**
```php
SMTP_SERVICE = "az1-ss18.a2hosting.com"
SMTP_PORT = "465"
SMTP_SECURE = "ssl"
SMTP_USERNAME = "service@quadica.com"
```

**Uses:**
- Order status change notifications
- PO emails to vendors
- Error notifications

### 5. Zapier (via Email)

**Trigger:** Orders sent to `luxeonorders.evby7@zapiermail.com`

**Purpose:** Likely triggers Zapier automations for order processing

### 6. Legacy Integrations (Commented Out)

- **3dCart** - Previous e-commerce platform (migrated from)
- **FreshBooks** - Invoicing system (referenced but may be deprecated)

---

## Data Volumes

| Table | Record Count | Notes |
|-------|-------------|-------|
| `oms_vendors` | 32 | Active vendors |
| `oms_po` | 3,012 | Historical POs |
| `oms_assemblies` | 5,875 | BOM definitions |
| `oms_bins` | 988 | Warehouse locations |
| `oms_prod_batch` | 3,225 | Production batches |
| `oms_shipbatches` | 6,143 | Shipping batches |
| `oms_product_data` | 942 | Extended product specs |
| `oms_led_data` | 1,005 | LED specifications |

---

## File Inventory

### Core Application Files (by function)

| Category | Files | Purpose |
|----------|-------|---------|
| **Configuration** | `config.php` | DB credentials, API keys, constants |
| **Database** | `classes/dbi_class.php` | MySQLi wrapper |
| **WooCommerce** | `includes/woo.inc.php`, `woo_mappings.php` | WC data access |
| **Utilities** | `includes/functions.inc.php` | Shared functions |
| **API Integration** | `classes/shipstation.php` | ShipStation wrapper |

### Page Files (user-facing)

| Category | Count | Key Files |
|----------|-------|-----------|
| **PO Management** | 8 | `po-gen.php`, `po-receive.php`, `po-all.php` |
| **Production** | 12 | `prod-batch.php`, `prod-generate.php`, `prod-report.php` |
| **Shipping** | 8 | `shipbatch.php`, `shipbatch-generate.php`, `shipbatch-picklist.php` |
| **Inventory** | 3 | `report-inventory.php`, `report-bin-history.php` |
| **Labels** | 4 | `label-printing.php`, `prod-batch-labels.php` |
| **Reports** | 4 | `report-order-status.php`, `report-unpaid-orders.php` |
| **Vendors** | 3 | `vendors-list.php`, `vendors-edit.php`, `vendors-new.php` |
| **Assemblies** | 3 | `prod-assemblies.php`, `prod-assemblies-new.php` |

### AJAX Handler Files

| File | Purpose |
|------|---------|
| `get_batch_data.php` | Production batch queries |
| `get_batch_items.php` | Batch line items |
| `get_bin_data.php` | Bin information |
| `get_canship.php` | Can-ship calculations |
| `get_invrep_data.php` | Inventory report data |
| `get_order_data.php` | Single order details |
| `get_order_status.php` | Order status queries |
| `get_ordreps_data.php` | Order report data |
| `get_products.php` | Product queries |
| `get_products_info.php` | Extended product info |
| `get_shipbatch_data.php` | Shipping batch data |
| `get_shipreps_data.php` | Shipping report data |

### POST Handler Files

| File | Purpose |
|------|---------|
| `post_assemblies_data.php` | Save assembly definitions |
| `post_invrep_data.php` | Save inventory changes |
| `post_po.php` | Save purchase order |
| `post_po_receive.php` | Save PO receiving |
| `post_prod_batch.php` | Save production batch |
| `post_product_data.php` | Save product data |
| `post_shipbatch_data.php` | Save shipping batch |
| `post_vendor.php` | Save vendor |
| `post_vendor_data.php` | Save vendor data |

### Generator Files

| File | Purpose |
|------|---------|
| `gen_asmlist.php` | Generate assembly candidates |
| `gen_batch.php` | Generate production batch |
| `gen_canship.php` | Generate can-ship list |
| `gen_shipbatch.php` | Generate shipping batch |
| `gen-shipbatch-csv.php` | Export ship batch to CSV |

---

## Historical Documentation

The `/om/docs/` directory contains PDF requirements documents from the system's development history. **Note:** The README warns these may not reflect current implementation.

### Phase Documents

| Document | Description |
|----------|-------------|
| Phase 1 - Order Management System Requirements.pdf | Initial system spec |
| Phase 2 - Order Management System Requirements.pdf | Extended features |
| Phase 3 - Order Management System Requirements.pdf | Production batching |
| Phase 4 - Order Management System Requirements.pdf | Shipping integration |
| Phase 5 - Order Management System Requirements.pdf | Reporting enhancements |
| Phase 6 - Autorespond Emails.pdf | Email automation |

### Feature Documents

| Document | Description |
|----------|-------------|
| Dynamic Bin Management.pdf | Bin assignment system |
| PO Receiving - Bin Assignment.pdf | Receiving workflow |
| Production Batch Receiving - Bin Assignment.pdf | Production receiving |
| Requirements - Production Batch Generation.pdf | Batch creation |
| Requirements - Shipping Batch Picklist Details.pdf | Pick list design |
| Requirements - Reflow Soldering Monitor_Alert System.pdf | Manufacturing monitoring |

---

## Technology Stack

### Server-Side

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | 8.1+ | Current server version |
| MySQL/MariaDB | 10.x | Kinsta hosted |
| WordPress | 6.8+ | For WC integration |
| WooCommerce | 9.9+ | Order/product data |

### Client-Side

| Component | Version | Notes |
|-----------|---------|-------|
| jQuery | 1.8.0 | **Released 2012** - severely outdated |
| jQuery UI | 1.8.12 | **Released 2011** - severely outdated |
| Font Awesome | (included) | Icons |
| html2canvas | (included) | Screenshot/print |

### External Services

| Service | Purpose |
|---------|---------|
| ShipStation | Shipping management |
| Bank of Canada | Exchange rates |
| A2 Hosting SMTP | Email delivery |
| Zapier | Workflow automation |

---

## Functionality Assessment

### Functionality by Category

#### Category A: Core Business Logic (Likely to Recreate)

| Function | Current State | Recommendation |
|----------|---------------|----------------|
| Production Batch Management | Active, essential | **Recreate** - Core manufacturing workflow |
| Shipping Batch Management | Active, essential | **Recreate** - Order fulfillment workflow |
| Assembly Definitions (BOM) | Active, essential | **Recreate** - Product composition data |
| Bin/Inventory Management | Active, used | **Recreate** - Warehouse operations |

#### Category B: Supporting Functions (Consider Recreating)

| Function | Current State | Recommendation |
|----------|---------------|----------------|
| Purchase Order Management | Active | **Consider** - May use existing WC solutions |
| Vendor Management | Active | **Consider** - Simple CRUD, may integrate with WC |
| Label Printing | Active | **Partially recreate** - QSA already handles some |
| Order Status Reports | Active | **Consider** - WC admin may suffice |

#### Category C: Data/Integration (Migrate or Deprecate)

| Function | Current State | Recommendation |
|----------|---------------|----------------|
| LED Product Data (`oms_led_data`) | Active | **Migrate** - Move to WC product meta or ACF |
| Product Data (`oms_product_data`) | Active | **Migrate** - Move to WC product meta or ACF |
| ShipStation Sync | Active | **Deprecate** - WC ShipStation plugin handles this |
| 3dCart Integration | Commented out | **Remove** - Legacy, no longer used |
| FreshBooks Integration | Referenced | **Verify** - May be deprecated |

#### Category D: Likely Redundant

| Function | Current State | Recommendation |
|----------|---------------|----------------|
| Custom SKU Generation | Present | **Review** - May duplicate WC functionality |
| Copy Description Tool | Present | **Review** - May be one-time migration tool |
| Legacy Header Files (1-4) | Present | **Remove** - Redundant templates |

### Key Questions for Decision Making

1. **Is QSA Engraving the replacement for production batching?**
   - QSA handles custom engraving batches
   - Legacy OM handles assembly batches (mounting LEDs)
   - Are these separate workflows or should they merge?

2. **What happens to `oms_assemblies` data?**
   - 5,875 BOM definitions exist
   - This is critical manufacturing data
   - Need migration strategy to WC-native solution

3. **ShipStation sync approach?**
   - Legacy OM has custom ShipStation integration
   - WooCommerce has official ShipStation plugin
   - Likely redundant - verify no custom logic needed

4. **Extended product data migration?**
   - `oms_product_data` and `oms_led_data` have extensive specs
   - Could migrate to ACF fields or custom product meta
   - Some data may already be duplicated in WC

5. **Vendor/PO management?**
   - Simple CRUD functionality
   - Could use existing WordPress/WC plugins
   - Or build lightweight custom solution

---

## Appendix A: Configuration Constants

From `config.php`:

```php
// Pagination
ROWSPERPAGE_20, ROWSPERPAGE_50, ... ROWSPERPAGE_2000

// Document layouts (SKUs per page)
SKU_PERPAGE_IN_PACKSLIP = 8
SKU_PERPAGE_IN_INVOICE = 10
SKU_PERPAGE_IN_COMMINVOICE = 6
SKU_PERPAGE_IN_PROFORMAINVOICE = 8

// Feature flags
UPDATE_WC_BIN = 1  // Update WC product bin field
PO_RECEIVE_SHOW_ASSIGNBIN = 1
PROD_BATCH_RECEIVE_SHOW_ASSIGNBIN = 1

// Timeouts
EMAIL_FOR_ORDER_DAYS = 120  // Process orders of last N days
FRESHBOOKS_IMPORT_DELAY = 1000  // ms between order imports

// Notifications
EMAIL_TO = "luxeonorders.evby7@zapiermail.com"
EMAIL_FROM = "service@luxeonstar.com"
INVOICE_ERROR_EMAIL = "office@quadica.com"
UNPAID_ORDERS_EMAIL = "office@quadica.com"

// Timezone
TZ_OFFSET = "-6:0"  // Arizona time
```

---

## Appendix B: Database Connection

From `classes/dbi_class.php`:

```php
class databasei {
    function opendb() {
        $con = new mysqli(OMS_DB_HOSTNAME, OMS_DB_USERNAME, OMS_DB_PASSWORD, OMS_DB_NAME);
        return $con;
    }
}
```

The system connects directly to the WordPress database using mysqli, bypassing WordPress's `$wpdb` abstraction.

---

## Appendix C: Related Tables (Non-OM)

These tables in the database may interact with OM but are not part of the OM schema:

| Table | Purpose |
|-------|---------|
| `quad_led_specs` | Additional LED specifications |
| `quad_leds` | LED master data |
| `quad_leds_footprints` | LED footprint definitions |
| `lw_quad_*` | QIP plugin tables (engraving, shiptrack, etc.) |

---

*Document generated by Claude Code - January 2026*

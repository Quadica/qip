# QSA Engraving Plugin

**Version:** 1.0.0
**Author:** Quadica Developments
**License:** Proprietary
**Requires:** WordPress 6.8+, WooCommerce 9.9+, PHP 8.1+

---

## Overview

The QSA Engraving plugin generates SVG files for UV laser engraving of Quadica Standard Array (QSA) LED modules. It manages serial number assignment, creates LightBurn-compatible SVG files with Micro-ID codes and QR codes, and provides a production workflow interface for operators.

### Key Capabilities

- **Serial Number Management** - Atomic generation and lifecycle tracking of unique 8-digit serial numbers
- **Micro-ID Encoding** - Proprietary 5x5 dot matrix encoding of serial numbers (20-bit capacity)
- **QR Code Generation** - ECC 200 barcodes linking to `quadi.ca/{qsa_id}` for module tracking
- **SVG Generation** - LightBurn-compatible SVG files with all engraving elements
- **Production Workflow** - Batch creation, engraving queue, and operator interface
- **LightBurn Integration** - UDP communication for automatic SVG file loading
- **Legacy SKU Support** - Map legacy module SKUs to QSA-compatible design codes

---

## Features

### Serial Number System

- **Capacity:** 1,048,575 unique serial numbers (20-bit limit from Micro-ID encoding)
- **Format:** 8-digit zero-padded strings (e.g., `00123456`)
- **Lifecycle:** `reserved` → `engraved` or `reserved` → `voided`
- **Capacity Warnings:** Configurable thresholds with admin notices

### Micro-ID Encoding

The Quadica 5x5 Micro-ID is a proprietary dot matrix encoding:

- **Grid Size:** 1.0mm x 1.0mm total area
- **Dot Diameter:** 0.10mm
- **Dot Pitch:** 0.225mm center-to-center
- **Structure:**
  - 4 corner anchor dots (always ON)
  - 20 data bits (encodes serial number)
  - 1 parity bit (even parity for error detection)
  - 1 orientation marker (outside grid)

### QR Code System

- Each QSA array receives a unique identifier (e.g., `CUBE00076`)
- QR codes encode `quadi.ca/{qsa_id}` for module tracking
- Sequential numbering per design (CUBE00001, CUBE00002, etc.)

**Design Code Format:**

The design code portion of the QSA ID has these constraints:

| Rule | Constraint |
|------|------------|
| Length | 1-10 characters |
| Allowed Characters | Uppercase letters (A-Z) and digits (0-9) |
| Case Handling | Input is normalized to uppercase automatically |

Examples of valid design codes:
- `CUBE` - 4-letter native QSA design
- `STAR` - 4-letter native QSA design
- `PICO` - 4-letter native QSA design
- `SP01` - 4-character legacy code (alphanumeric)
- `SP03` - 4-character legacy code (alphanumeric)
- `MR1S` - 4-character legacy code (alphanumeric)

The complete QSA ID is formed as: `{DESIGN_CODE}{5-digit sequence}`
Examples: `CUBE00001`, `SP0300042`, `STARa00015` (with revision letter)

### QR Code Landing Pages

When a customer scans a QR code on an engraved module, they are directed to a landing page that verifies authenticity and displays product information.

**How It Works:**

1. **QR Code Content** - Each engraved QR code contains a URL like `quadi.ca/CUBE00076`
2. **URL Routing** - The plugin registers WordPress rewrite rules to capture QSA ID patterns at the site root (e.g., `luxeonstar.com/CUBE00076`)
3. **Database Lookup** - The QSA ID is looked up in the `lw_quad_qsa_identifiers` table
4. **Landing Page Display** - A branded information page is rendered

**Landing Page Content:**

For **valid QSA IDs**, the page displays:
- Large QSA ID badge (green background)
- "Product Information" heading
- Authenticity verification message
- Design name (e.g., CUBE, STAR, PICO)
- Sequence number
- Batch ID
- Creation date

For **invalid/unknown QSA IDs**, the page displays:
- QSA ID badge (red background)
- "Product Not Found" message
- Instructions to verify the code or contact support
- Returns HTTP 404 status

**URL Pattern Support:**

The system accepts QSA IDs in the format:
- 4 uppercase letters + 5 digits (e.g., `CUBE00001`)
- 4 uppercase letters + lowercase revision + 5 digits (e.g., `STARa00042`)

**Note:** The `quadi.ca` domain redirects to your main site. Ensure DNS is configured to redirect `quadi.ca/{path}` to `luxeonstar.com/{path}` for the landing pages to work correctly.

### SVG Elements

Generated SVG files include:

- **Micro-ID** - Encoded serial number
- **QR Code** - Array identifier barcode
- **Module ID** - Text label (e.g., `STARa-34924`)
- **Serial URL** - Text label (e.g., `quadi.ca/00123456`)
- **LED Codes** - Up to 9 LED code positions per module

### LED Shortcodes

Each LED product has a 3-character shortcode used for engraving identification.

**LED Shortcode Format:**

| Rule | Constraint |
|------|------------|
| Length | Exactly 3 characters |
| Allowed Characters | `A-Z a-z 0-9` (36 alphanumeric characters) |
| Case Handling | Case-insensitive (stored as entered) |

**Allowed Characters:**
- Letters: `A B C D E F G H I J K L M N O P Q R S T U V W X Y Z` (and lowercase)
- Digits: `0 1 2 3 4 5 6 7 8 9`
- **Not allowed:** Spaces, hyphens, underscores, or any special characters

Examples of valid LED shortcodes: `K7P`, `W2A`, `R10`, `B3C`

**Where LED Shortcodes Are Stored:**

LED shortcodes are stored in the `led_shortcode_3` custom field on WooCommerce LED products. The system retrieves them via the Order BOM which links orders to their LED components.

**Setting Up LED Shortcodes:**

1. Edit the LED product in WooCommerce
2. Add a custom field named `led_shortcode_3`
3. Enter the 3-character shortcode (e.g., `K7P`)
4. Save the product

If an LED product is missing its shortcode, the engraving queue will display an error with instructions to fix it.

---

## Requirements

### Software Dependencies

| Component | Minimum Version |
|-----------|-----------------|
| WordPress | 6.8+ |
| WooCommerce | 9.9+ |
| PHP | 8.1+ |
| MariaDB | 11.4+ (for CHECK constraints) |

### PHP Dependencies (Composer)

- `tecnickcom/tc-lib-barcode ^2.1` - QR code generation

### JavaScript Dependencies (npm)

- `@wordpress/scripts ^27.9.0` - Build tooling
- `@wordpress/element` - React integration
- `@wordpress/components` - UI components
- `@wordpress/api-fetch` - AJAX utilities
- `@wordpress/i18n` - Internationalization

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `lw_quad_serial_numbers` | Serial number tracking and lifecycle |
| `lw_quad_engraving_batches` | Engraving batch metadata |
| `lw_quad_engraved_modules` | Module-to-batch linkage with positions |
| `lw_quad_qsa_config` | Per-position element coordinates |
| `lw_quad_qsa_identifiers` | QSA-level identifiers for QR codes |
| `lw_quad_qsa_design_sequences` | Per-design sequence counters |
| `lw_quad_sku_mappings` | Legacy SKU to canonical code mappings |

---

## Admin Pages

Access via **QSA Engraving** menu in WordPress admin. Requires `manage_woocommerce` capability.

### Dashboard

Overview page showing:

- Serial number capacity status
- Active batches summary
- Quick links to other pages

### Batch Creator

Select modules for engraving:

1. **Module Tree** - Hierarchical display grouped by design type and order
2. **Selection** - Checkbox selection with quantity editing
3. **LED Optimization** - Automatic sorting to minimize LED code transitions
4. **Preview** - Array breakdown before batch creation

### Engraving Queue

Step-through workflow for operators:

1. **Queue Display** - Rows grouped by QSA sequence
2. **Start Row** - Reserves serials and generates SVG
3. **Array Navigation** - Progress through multi-array rows
4. **Complete/Retry** - Mark done or regenerate with new serials
5. **Keyboard Shortcuts** - Spacebar advances through arrays

### Batch History

View completed batches:

- Search by batch ID, order ID, or module SKU
- Filter by design type
- Re-engraving capability (generates new serial numbers)

### SKU Mappings

Manage legacy SKU pattern mappings:

- Pattern types: exact, prefix, suffix, regex
- Priority ordering for overlapping patterns
- Test tool to verify SKU resolution

### Tweak Coords

Fine-tune engraving coordinates:

- Adjust element positions per design
- Calibration offsets
- Visual preview

### Settings

Configure plugin options:

- **Serial Capacity Thresholds** - Warning and critical levels
- **LightBurn Integration** - Host, ports, paths, auto-load toggle
- **SVG Settings** - Rotation, top offset
- **Text Heights** - Module ID, serial URL, LED codes

---

## Workflow Guide

### Standard Engraving Workflow

#### 1. Create Engraving Batch

1. Navigate to **QSA Engraving > Batch Creator**
2. Expand design types to see available modules
3. Select modules using checkboxes (adjust quantities if needed)
4. Review the sorted preview (LED optimization applied)
5. Click **Create Batch**

#### 2. Process Engraving Queue

1. Navigate to **QSA Engraving > Engraving Queue**
2. Click **Engrave** on a pending row to start
3. SVG is generated and sent to LightBurn (if enabled)
4. Physically engrave the module array
5. Click **Next Array** or **Complete** when done
6. Repeat for remaining rows

#### 3. Handle Errors

- **Resend** - Same SVG, same serials (communication issue)
- **Retry** - New SVG with new serials (physical failure)
- **Rerun** - Reset completed row to Pending

### Starting Position Support

If continuing a partially-used QSA array:

1. Set the **Starting Position** (1-8) before clicking Engrave
2. Modules will be assigned starting from that position
3. Additional arrays created if needed for overflow

---

## LightBurn Integration

### Configuration

1. Navigate to **QSA Engraving > Settings**
2. Enable **LightBurn Integration**
3. Configure:
   - **Host IP** - LightBurn workstation IP address
   - **Send Port** - Default 19840
   - **Receive Port** - Default 19841
   - **SVG Output Directory** - Local path for SVG files
   - **LightBurn Path Prefix** - Network path as seen by LightBurn
   - **Timeout** - UDP response timeout in milliseconds

### How It Works

1. When operator clicks "Engrave" or "Next Array":
   - SVG file is generated and saved to output directory
   - `LOADFILE:{path}` command sent via UDP to LightBurn
   - LightBurn automatically loads the SVG file

2. Status indicator shows connection state in the queue UI

### Network Requirements

- UDP ports 19840 (send) and 19841 (receive) must be accessible
- SVG output directory must be accessible to LightBurn workstation
- Network share paths may need mapping via `LightBurn Path Prefix`

---

## Legacy SKU Support

The plugin supports mapping legacy module SKUs to QSA-compatible design codes.

### Adding a Legacy Module Design

#### Step 1: Create SKU Mapping

Via Admin UI (**QSA Engraving > SKU Mappings**)

#### Step 2: Add Configuration Coordinates

Create config entries for the canonical code:

```sql
INSERT INTO lw_quad_qsa_config
  (qsa_design, revision, position, element_type, origin_x, origin_y, rotation, text_height, is_active, created_by)
VALUES
  ('SP01', NULL, 1, 'micro_id', 5.000, 8.000, 0, NULL, 1, 1),
  ('SP01', NULL, 1, 'module_id', 10.000, 12.000, 0, 1.30, 1, 1),
  ('SP01', NULL, 1, 'serial_url', 10.000, 10.000, 0, 1.20, 1, 1),
  ('SP01', NULL, 1, 'led_code_1', 15.000, 8.000, 0, 1.20, 1, 1),
  ('SP01', NULL, 0, 'qr_code', 139.117, 56.850, 0, NULL, 1, 1);
```

#### Step 3: Verify

1. Legacy SKU now appears in Batch Creator UI
2. Test SKU resolution via Admin UI test tool
3. Create test batch and verify SVG generation

### Pattern Match Types

| Type | Behavior | Example |
|------|----------|---------|
| `exact` | SKU must match exactly | `SP-01` matches only `SP-01` |
| `prefix` | SKU must start with pattern | `SP-` matches `SP-01`, `SP-02` |
| `suffix` | SKU must end with pattern | `-10S` matches `MR-001-10S` |
| `regex` | MySQL regular expression | `^MR-[0-9]+-10S$` |

---

## File Structure

```
qsa-engraving/
├── qsa-engraving.php           # Main plugin file (singleton bootstrap)
├── composer.json               # PHP dependencies
├── package.json                # Node dependencies
├── webpack.config.js           # Build configuration
│
├── includes/
│   ├── Autoloader.php          # PSR-4 autoloader
│   │
│   ├── Admin/
│   │   └── class-admin-menu.php        # Menu registration and page rendering
│   │
│   ├── Ajax/
│   │   ├── class-batch-ajax-handler.php    # Batch creation AJAX
│   │   ├── class-queue-ajax-handler.php    # Queue operations AJAX
│   │   ├── class-history-ajax-handler.php  # Batch history AJAX
│   │   ├── class-lightburn-ajax-handler.php # LightBurn integration AJAX
│   │   └── class-sku-mapping-ajax-handler.php # SKU mappings AJAX
│   │
│   ├── Database/
│   │   ├── class-serial-repository.php     # Serial number CRUD
│   │   ├── class-batch-repository.php      # Batch and module CRUD
│   │   ├── class-config-repository.php     # QSA configuration CRUD
│   │   ├── class-qsa-identifier-repository.php # QSA ID management
│   │   └── class-sku-mapping-repository.php # SKU mappings CRUD
│   │
│   ├── Services/
│   │   ├── class-module-selector.php       # Query modules awaiting engraving
│   │   ├── class-batch-sorter.php          # LED optimization sorting
│   │   ├── class-config-loader.php         # Load QSA configurations
│   │   ├── class-svg-generator.php         # High-level SVG generation
│   │   ├── class-svg-file-manager.php      # SVG file lifecycle
│   │   ├── class-led-code-resolver.php     # LED code lookups
│   │   ├── class-lightburn-client.php      # UDP communication
│   │   └── class-legacy-sku-resolver.php   # Legacy SKU resolution
│   │
│   ├── SVG/
│   │   ├── class-svg-document.php          # SVG document assembly
│   │   ├── class-micro-id-encoder.php      # Micro-ID encoding
│   │   ├── class-qr-code-renderer.php      # QR code generation
│   │   ├── class-text-renderer.php         # Text path rendering
│   │   └── class-coordinate-transformer.php # CAD to SVG transforms
│   │
│   └── Frontend/
│       └── class-qsa-landing-handler.php   # quadi.ca redirect handling
│
├── assets/
│   ├── css/
│   │   └── admin.css                       # Admin styles
│   │
│   └── js/
│       ├── src/                            # React source files
│       │   ├── batch-creator/              # Batch Creator UI
│       │   ├── engraving-queue/            # Queue UI
│       │   └── batch-history/              # History UI
│       │
│       └── build/                          # Compiled bundles
│
├── tests/
│   └── smoke/
│       └── wp-smoke.php                    # WP-CLI smoke tests
│
└── vendor/                                 # Composer dependencies (committed)
```

---

## Services Reference

### Module_Selector

Queries `oms_batch_items` for modules awaiting engraving:

- Filters for QSA-compatible SKUs
- Excludes already-engraved modules
- Groups by design type
- Supports legacy SKU resolution

### Batch_Sorter

Optimizes module ordering:

- Minimizes LED code transitions
- Calculates array breakdown
- Handles starting position offsets

### SVG_Generator

Orchestrates SVG generation:

- Loads configuration from database
- Delegates to component renderers
- Supports batch generation across multiple arrays

### Config_Loader

Manages QSA configuration:

- Retrieves element coordinates from database
- Handles design revisions
- Applies calibration offsets

### LightBurn_Client

UDP communication with LightBurn:

- `ping()` - Test connection
- `load_file()` - Send LOADFILE command
- Configurable timeouts and error handling

### Legacy_SKU_Resolver

Maps legacy SKUs to canonical codes:

- Pattern matching (exact, prefix, suffix, regex)
- Priority-based resolution
- Caching for performance

---

## Troubleshooting

### Plugin Won't Activate

1. Check PHP version (requires 8.1+)
2. Check WordPress version (requires 6.8+)
3. Verify WooCommerce is installed and active
4. Check error logs for specific messages

### SVG Not Loading in LightBurn

1. Verify LightBurn integration is enabled in Settings
2. Check network connectivity to LightBurn workstation
3. Verify SVG output directory is accessible
4. Check LightBurn path prefix configuration
5. Test connection using Settings page test button

### Modules Not Appearing in Batch Creator

1. Check if modules exist in `oms_batch_items`
2. Verify SKU matches QSA pattern or has legacy mapping
3. Confirm modules haven't already been engraved
4. Check for SKU mapping if using legacy format

### QR Code Landing Page Not Working

1. Verify WordPress permalink settings are not set to "Plain"
2. Flush rewrite rules: **Settings > Permalinks > Save Changes**
3. Check that the QSA ID exists in `lw_quad_qsa_identifiers` table
4. Verify `quadi.ca` DNS redirects to your site correctly
5. Test with a known valid QSA ID directly on your domain (e.g., `luxeonstar.com/CUBE00001`)

### Serial Capacity Warning

The system has a maximum capacity of 1,048,575 serial numbers. When low:

1. Check actual usage vs remaining capacity
2. Serial numbers that have been voided (`lw_quad_serial_numbers/voided_at`) can be recovered and used
2. Plan for serial number range expansion
3. Contact development for capacity planning

---

## Change Log

### Version 1.0.0

- Initial release
- Serial number management with lifecycle tracking
- Micro-ID 5x5 encoding
- QR code generation with QSA identifiers
- LightBurn UDP integration
- Legacy SKU mapping support
- React-based admin interface
- 178+ smoke tests

---

## Support

For issues or feature requests, contact Quadica Developments.

**Internal Plugin** - Not for external distribution.

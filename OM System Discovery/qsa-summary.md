# QSA - Quadica Standard Array Engraving

**Module Code:** QSA
**Status:** In Development
**Created:** December 2025

---

## Purpose

QSA manages the laser engraving process for LED module arrays. It generates SVG files containing serial numbers, barcodes, and identification marks that are sent to LightBurn laser software for physical engraving on Quadica Standard Array (QSA) PCBs.

---

## Scope

### In Scope
- Engraving batch creation from production batch modules
- Serial number generation and lifecycle tracking
- SVG file generation for laser engraving
- LightBurn integration via SFTP file delivery
- Micro-ID 5x5 dot matrix encoding
- ECC 200 Data Matrix barcode generation
- LED code engraving placement
- QSA position coordinate configuration
- Batch history and re-engraving support

### Out of Scope
- Production batch management (see QAM)
- Physical laser operation (LightBurn handles this)
- Module assembly (manual process after engraving)
- Inventory management (see QIM)

---

## Core Concepts

### Quadica Standard Array (QSA)
- Standardized MCPCB board holding up to 8 LED module PCBs
- Replaces 30+ legacy array designs with a single flexible format
- Each position can hold different module configurations
- Physical dimensions: 148mm × 113.7mm

### Serial Numbers
- 8-digit unique identifier per module (e.g., "00123456")
- 20-bit capacity (~1M serials) constrained by Micro-ID encoding
- Lifecycle: Reserved → Engraved or Voided
- Never recycled - voided serials remain for audit

### Engraving Elements
| Element | Description |
|---------|-------------|
| Micro-ID | 5x5 dot matrix encoding serial number |
| Data Matrix | ECC 200 barcode with serial URL |
| Module ID | Text identifier (e.g., "STAR-34924") |
| Serial URL | Text URL (e.g., "quadi.ca/00123456") |
| LED Codes | 3-character codes per LED position |

---

## Core Workflows

### 1. Create Engraving Batch
1. View modules awaiting engraving from active production batches
2. Select modules to include in engraving batch
3. System groups modules by base type + revision into rows
4. System sorts modules within rows to optimize LED pick-and-place
5. System calculates array count per row
6. Confirm batch creation

### 2. Engrave Arrays
1. Click "Engrave" to start a row
2. System reserves serial numbers and pre-generates all SVGs for the row
3. First SVG is sent to LightBurn via SFTP watcher
4. Operator uses foot switch to engrave the physical array
5. Press Spacebar to advance to next array (commits previous serials)
6. Repeat until row complete
7. Click "Complete" to finalize row

### 3. Error Recovery
| Control | Purpose |
|---------|---------|
| Resend | Re-transmit current SVG (same serials) |
| Retry | Scrap current array, generate new SVG with new serials |
| Rerun | Reset completed row for re-engraving (new serials) |

### 4. View Batch History
1. Access previously completed batches
2. Select modules for re-engraving (e.g., QA rejects)
3. Create new batch with new serial numbers

---

## Key Features

- **LED Optimization Sorting:** Modules sorted to minimize LED type transitions during manual pick-and-place
- **Starting Position Offset:** Support for partially-used arrays from previous batches
- **Multi-Array Rows:** Automatic distribution across multiple QSAs when needed
- **Keyboard Shortcuts:** Spacebar advances arrays for hands-free operation
- **Configuration Tweaker:** Fine-tune element coordinates per QSA design
- **Ephemeral SVGs:** Generated on demand, deleted after use

---

## Data Model (Implemented)

```
quad_serial_numbers
  - serial_number (8-char, unique)
  - serial_integer (for range queries)
  - module_sku
  - engraving_batch_id (FK)
  - production_batch_id
  - order_id
  - qsa_sequence
  - array_position (1-8)
  - status (reserved/engraved/voided)
  - reserved_at, engraved_at, voided_at

quad_engraving_batches
  - id (PK)
  - batch_name
  - module_count
  - qsa_count
  - status (in_progress/completed)
  - created_by, created_at, completed_at

quad_engraved_modules
  - engraving_batch_id (FK)
  - production_batch_id
  - module_sku
  - order_id
  - serial_number
  - qsa_sequence
  - array_position
  - row_status (pending/done)

quad_qsa_config
  - qsa_design (e.g., "STAR")
  - revision (e.g., "a")
  - position (1-8)
  - element_type
  - origin_x, origin_y (mm)
  - rotation, text_height
```

---

## Integration Points

| System | Integration |
|--------|-------------|
| **QAM** | Reads production batch modules from `oms_batch_items` |
| **Order BOM CPT** | Retrieves LED SKUs and positions per module |
| **WooCommerce** | Gets `led_shortcode_3` from LED product meta |
| **LightBurn** | SVG delivery via SFTP watcher on Windows machine |

---

## LightBurn Integration

### Architecture
- QSA plugin generates SVG files on WordPress server
- SFTP watcher script (`lightburn-watcher.js`) runs on production Windows PC
- Watcher polls staging server for new SVG files
- Downloads and places files in LightBurn's watched folder
- LightBurn automatically loads new SVG files

### Watcher Configuration
- Location: `C:\Users\Production\LightBurn\lightburn-watcher.js`
- Process manager: PM2
- Poll interval: 5 seconds (configurable)

---

## Success Criteria

1. Serial numbers are unique and never recycled
2. SVG files render correctly in LightBurn
3. Engraving coordinates match physical QSA positions
4. LED optimization reduces pick-and-place transitions
5. Error recovery allows re-engraving without data loss
6. Batch history supports QA rejection workflows

---

## Dependencies

- **QAM** completion provides modules for engraving
- **LightBurn** must be running on production PC
- **SFTP watcher** must be active for SVG delivery
- **Order BOM CPT** must have LED data populated
- **LED products** must have `led_shortcode_3` field set

---

*Document created by Claude Code + Chris Warris - January 2026*

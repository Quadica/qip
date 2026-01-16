# QAM - Quadica Assembly Management

**Module Code:** QAM
**Status:** Planned
**Created:** January 2026

---

## Purpose

QAM manages the production of LED modules from component parts. It handles production batch creation, BOM (Bill of Materials) management, serial number assignment, and production workflows. This is the core module that supports LuxeonStar's LED module assembly business.

---

## Scope

### In Scope
- Production batch creation and management
- BOM/Assembly definitions (component recipes)
- "Can build" calculations based on component availability
- Batch workflows (create, build, receive)
- Serial number assignment and tracking
- Array optimization for manufacturing efficiency
- Production documentation (batch reports, labels)
- Component hard-lock reservations

### Out of Scope
- Purchasing/PO management (see QPM)
- Inventory/bin management (see QIM)
- Order shipping/fulfillment (see QFM)
- Custom product configuration (separate module or WooCommerce)

---

## Legacy OM Replacement

| Legacy File | Functionality | QAM Replacement |
|-------------|---------------|-----------------|
| `prod-batch-list.php` | List production batches | Batch list dashboard |
| `prod-generate.php` | Generate batch candidates | Batch creation wizard |
| `prod-batch.php` | View/manage batch | Batch detail screen |
| `prod-report.php` | Production report | Batch documentation |
| `prod-batch-receive.php` | Receive completed modules | Receiving workflow |
| `prod-batch-labels.php` | Print module labels | Label generation |
| `prod-assemblies.php` | View/edit BOMs | Assembly management |
| `prod-binning-report.php` | Bin assignment report | Integrated in receiving |
| `gen_asmlist.php` | Generate build candidates | "Can build" engine |
| `gen_batch.php` | Create production batch | Batch creation |

**Legacy Tables:**
- `oms_prod_batch` (3,225 records) - Production batches
- `oms_batch_items` - Batch line items
- `oms_assemblies` (5,875 records) - BOM definitions
- `oms_canbuild` - Build candidates (regenerated)
- `oms_candidates` - Candidate staging

---

## Core Concepts

### Assembly (BOM)
- Defines the component recipe for a finished module
- Assembly SKU = finished product SKU (e.g., STAR-34924)
- Components include: Base, LEDs (by position), 0R resistors, connectors
- Each LED position can have different LED SKU

### Production Batch
- Collection of modules grouped for manufacturing
- Single base type per batch (different LED variations allowed)
- Cross-order batching supported for efficiency
- Tracked from creation through completion

### Array Optimization
- Bases delivered in arrays (panels) with specific counts (e.g., 15-up, 8-up)
- Complete arrays preferred to minimize waste
- Partial arrays acceptable for high-priority orders

### Serial Numbers
- 8-digit unique identifier per manufactured module
- Assigned when batch is created and confirmed
- Encoded in Quadica 5x5 Micro-ID on physical module
- Links to full production history and specifications

---

## Core Workflows

### 1. View Production Queue
1. Display all orders with modules needing production
2. Show buildability status per order (fully buildable, partial, blocked)
3. Indicate which components are blocking unbuildable items
4. Filter by base type, priority, date, buildability

### 2. Create Production Batch
1. Select base type to batch
2. System shows eligible modules across all orders
3. PM selects which modules/orders to include
4. System calculates array usage (complete vs. partial)
5. System validates component availability
6. Confirm batch creation (hard-locks components)
7. Assign serial numbers to all modules in batch

### 3. Generate Production Documentation
1. Print batch production report for assembly team
2. Report includes: module details, LED positions, component SKUs
3. Generate module labels with barcodes/serial numbers
4. Export batch data for pick-and-place equipment

### 4. Receive Completed Batch
1. Production staff marks modules as built
2. Assign bin locations for completed modules (QIM integration)
3. Update WooCommerce stock for finished products
4. Mark batch complete when all modules received
5. Release any unused component reservations

### 5. Manage Assemblies (BOMs)
1. View existing assembly definitions
2. Create new assembly for new module design
3. Define component positions and quantities
4. Link assembly SKU to WooCommerce product
5. Deactivate assemblies for discontinued products

---

## Key Features

- **Cross-Order Batching:** Combine same base type across orders for efficiency
- **Array Optimization:** Suggest batch sizes for complete array usage
- **Component Reservation:** Hard-lock components when batch created
- **Serial Number System:** Unique traceability for every module
- **Continuous Visibility:** See full queue, not just buildable items
- **Production Documentation:** Reports, labels, CSV exports
- **Buildability Engine:** Real-time "can build" calculations

---

## Data Model (Conceptual)

```
qam_assemblies
  - assembly_id (PK)
  - assembly_sku (unique - matches WC product SKU)
  - base_sku
  - description
  - is_active
  - created_at
  - updated_at

qam_assembly_components
  - id (PK)
  - assembly_id (FK)
  - component_sku
  - position (LED position 1, 2, 3...)
  - quantity
  - component_type (led/base/resistor/connector)

qam_batches
  - batch_id (PK)
  - batch_number (display)
  - base_sku
  - status (pending/in_progress/complete/cancelled)
  - created_by
  - created_at
  - started_at
  - completed_at
  - notes

qam_batch_items
  - item_id (PK)
  - batch_id (FK)
  - order_id (WC order)
  - assembly_sku
  - serial_number (8-digit)
  - quantity
  - status (pending/built/received)
  - bin_id (FK to QIM, after receiving)
  - led_positions (JSON - LED SKUs by position)
  - custom_instructions
  - received_at

qam_serial_numbers
  - serial_number (PK, 8-digit)
  - batch_id (FK)
  - assembly_sku
  - order_id
  - production_date
  - status (assigned/produced/shipped/void)
```

---

## Integration Points

| Module | Integration |
|--------|-------------|
| **QIM** | Component availability for buildability; hard-lock reservations; bin assignment for completed modules |
| **QPM** | Visibility into incoming components (POs in transit) |
| **QFM** | Batch completion triggers order fulfillment eligibility |
| **WooCommerce** | Order data for batch creation; stock updates for completed modules |

---

## Success Criteria

1. Production queue shows complete visibility (not just buildable items)
2. Batch creation is faster and more intuitive than legacy system
3. Array optimization reduces waste
4. Serial number system provides full traceability
5. Component reservation prevents over-commitment
6. Production documentation is accurate and complete

---

## Migration Considerations

- Migrate `oms_assemblies` (5,875 records) - critical manufacturing data
- Migrate `oms_prod_batch` and `oms_batch_items` for historical reference
- Validate assembly definitions match current WooCommerce products
- Serial number migration (if existing modules have serial numbers)

---

## Dependencies

- **QIM** required for component availability and bin assignment
- WooCommerce orders and products must be accessible
- QPM helpful but not required (purchasing visibility)

---

## Open Questions

1. Should historical batches be fully migrated or archived as read-only?
2. Integration with existing QSA Engraving module for label generation?
3. Serial number range management (avoid conflicts with legacy)?

---

*Document created by Claude Code + Chris Warris - January 2026*

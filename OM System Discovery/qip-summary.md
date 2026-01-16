# QIP - Quadica Integration Platform

**Status:** Planning / Partial Implementation
**Created:** January 2026

---

## Purpose

QIP is a suite of WordPress/WooCommerce plugins designed to replace the legacy Order Management (OM) system. The legacy system, originally built in 2002 and last significantly updated in 2013, handles production, inventory, purchasing, and fulfillment for LuxeonStar's LED module business.

QIP modernizes this functionality with:
- Native WordPress/WooCommerce integration
- Modern PHP (8.1+) and JavaScript
- Proper authentication and security
- Modular architecture for maintainability

---

## Module Overview

| Code | Module | Status | Purpose |
|------|--------|--------|---------|
| **QIM** | Quadica Inventory Management | Planned | Warehouse bins, stock visibility, component reservations |
| **QPM** | Quadica Purchasing Management | Planned | Purchase orders, vendor management, receiving |
| **QAM** | Quadica Assembly Management | Planned | Production batches, BOMs, serial numbers |
| **QSA** | Quadica Standard Array Engraving | In Development | Laser engraving SVG generation, serial tracking |
| **QFM** | Quadica Fulfillment Management | Planned | Shipping batches, picklists, order completion |
| **QMF** | Quadica Manufacturing | Discovery | Overarching production system planning |

---

## System Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         CUSTOMER ORDER (WooCommerce)                     │
└─────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                              QIM (Inventory)                             │
│  • Check component availability                                          │
│  • Soft-reserve components for orders                                    │
│  • Identify what can be built vs. what needs purchasing                  │
└─────────────────────────────────────────────────────────────────────────┘
                          │                       │
            ┌─────────────┘                       └─────────────┐
            ▼                                                   ▼
┌───────────────────────────────┐               ┌───────────────────────────────┐
│        QPM (Purchasing)        │               │        QAM (Assembly)          │
│  • Create POs for missing      │               │  • Create production batches   │
│    components                  │               │  • Hard-lock components        │
│  • Receive against POs         │               │  • Generate build docs         │
│  • Update bin inventory        │◄──────────────│                                │
│                                │   Components   │                                │
└───────────────────────────────┘    received    └───────────────────────────────┘
                                                              │
                                                              │ Batch created
                                                              ▼
                                    ┌───────────────────────────────────────────┐
                                    │            QSA (Engraving)                 │
                                    │  • Generate serial numbers                 │
                                    │  • Create SVG files for laser engraving    │
                                    │  • Send to LightBurn via SFTP              │
                                    │  • Track engraving completion              │
                                    └───────────────────────────────────────────┘
                                                              │
                                                              │ Arrays engraved
                                                              ▼
                                    ┌───────────────────────────────────────────┐
                                    │          Physical Assembly                 │
                                    │  • Manual LED pick-and-place               │
                                    │  • Module assembly on engraved arrays      │
                                    │  • Quality inspection                      │
                                    └───────────────────────────────────────────┘
                                                              │
                                                              │ Modules built
                                                              ▼
                                    ┌───────────────────────────────────────────┐
                                    │        QAM (Assembly - Receiving)          │
                                    │  • Receive completed modules               │
                                    │  • Assign bin locations (QIM)              │
                                    │  • Update WooCommerce stock                │
                                    └───────────────────────────────────────────┘
                                                              │
                                                              │ Modules in stock
                                                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           QFM (Fulfillment)                              │
│  • Calculate "can ship" status                                           │
│  • Create shipping batches                                               │
│  • Generate picklists                                                    │
│  • Update WooCommerce order status                                       │
│  • Sync to ShipStation                                                   │
└─────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                              SHIPPED TO CUSTOMER                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Module Responsibilities

### QIM - Quadica Inventory Management
**Foundation layer - no dependencies on other Q modules**

- Bin location management (physical warehouse locations)
- Stock level visibility synchronized with WooCommerce
- Component reservation system (soft reserves, hard locks)
- Stock movement history and audit trail
- Reorder point alerts

### QPM - Quadica Purchasing Management
**Depends on: QIM**

- Purchase order creation and lifecycle management
- Vendor master data management
- Receiving workflows with bin assignment
- Reorder suggestions based on stock levels
- PO email notifications to vendors

### QAM - Quadica Assembly Management
**Depends on: QIM (required), QPM (optional visibility)**

- Production batch creation from eligible orders
- BOM (Bill of Materials) / Assembly definitions
- "Can build" calculations based on component availability
- Production documentation (reports, labels)
- Component hard-lock reservations during production
- Receiving completed modules into inventory

### QSA - Quadica Standard Array Engraving
**Depends on: QAM (production batches), Order BOM CPT, WooCommerce products**

- Engraving batch creation from production batch modules
- Serial number generation and lifecycle tracking (reserved/engraved/voided)
- SVG file generation with Micro-ID, Data Matrix, module ID, LED codes
- LightBurn integration via SFTP watcher
- LED optimization sorting to minimize pick-and-place transitions
- QSA position coordinate configuration and tweaking
- Batch history for re-engraving workflows

### QFM - Quadica Fulfillment Management
**Depends on: QIM, QAM**

- "Can ship" calculations for order fulfillment
- Shipping batch creation and management
- Picklist generation sorted by bin location
- Order status tracking through fulfillment
- ShipStation integration for carrier labels

---

## Integration Matrix

|  | QIM | QPM | QAM | QSA | QFM | WooCommerce | External |
|--|-----|-----|-----|-----|-----|-------------|----------|
| **QIM** | - | Bin assignment | Hard locks | - | Stock queries | Stock sync | - |
| **QPM** | Stock levels | - | PO visibility | - | - | Product SKUs | - |
| **QAM** | Reservations | Incoming POs | - | Batch modules | Completion trigger | Orders, products | - |
| **QSA** | - | - | Batch data | - | - | LED codes | LightBurn |
| **QFM** | Bin locations | - | Batch completion | - | - | Order status | ShipStation |

---

## Data Flow Summary

1. **Order Received** - WooCommerce creates order
2. **Availability Check** - QIM calculates what components are available
3. **Production Planning** - QAM identifies buildable modules, QPM identifies components to order
4. **Purchasing** - QPM creates POs, receives against POs, updates QIM bins
5. **Batch Creation** - QAM creates production batches, hard-locks components
6. **Engraving** - QSA generates serial numbers, creates SVG files, sends to LightBurn
7. **Assembly** - Physical LED placement on engraved arrays (manual process)
8. **Receiving** - QAM receives completed modules into QIM bins
9. **Fulfillment** - QFM calculates "can ship", creates shipping batches
10. **Shipping** - QFM syncs to ShipStation, updates WooCommerce order status

---

## Legacy OM Replacement Mapping

| Legacy Area | Legacy Files | QIP Module |
|-------------|--------------|------------|
| PO Management | `po-*.php`, `vendors-*.php` | QPM |
| Inventory/Bins | `report-inventory.php`, `*bin*.php` | QIM |
| Production Batches | `prod-*.php`, `gen_asmlist.php`, `gen_batch.php` | QAM |
| Engraving/Labels | Label generation, barcode files | QSA |
| Shipping | `shipbatch*.php`, `gen_canship.php`, `gen_shipbatch.php` | QFM |

---

## Implementation Approach

### Current Status

| Module | Status | Notes |
|--------|--------|-------|
| QSA | In Development | Core functionality implemented, testing in progress |
| QIM | Planned | Foundation module, high priority |
| QPM | Planned | Depends on QIM |
| QAM | Planned | Currently uses legacy `oms_batch_items` |
| QFM | Planned | Final module in workflow |

### Recommended Build Order

1. **QSA** - Currently in development, can work with legacy batch tables
2. **QIM** - Foundation with no Q module dependencies
3. **QPM** - Purchasing can work with just QIM
4. **QAM** - Production needs QIM, currently bridges to QSA
5. **QFM** - Fulfillment needs QIM and QAM completion data

### Migration Strategy

- QSA deployed first using legacy `oms_batch_items` for production batch data
- Each subsequent module migrates its corresponding `oms_*` tables
- Historical data preserved as read-only where practical
- Parallel operation period before decommissioning legacy OM
- WooCommerce remains source of truth for orders and products

---

## Key Design Principles

1. **WooCommerce Native** - Built as proper WordPress plugins, not standalone PHP
2. **Single Source of Truth** - WooCommerce owns orders, products, customers
3. **Modular Independence** - Each module can function (with reduced capability) if others unavailable
4. **Audit Trail** - All significant actions logged for traceability
5. **Real-time Visibility** - Dashboard views show current state, not stale snapshots
6. **Legacy Bridge** - New modules can integrate with legacy tables during transition

---

*Document created by Claude Code + Chris Warris - January 2026*

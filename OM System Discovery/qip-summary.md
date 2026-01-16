# QIP - Quadica Integration Platform

**Status:** Planning
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

| Code | Module | Purpose |
|------|--------|---------|
| **QIM** | Quadica Inventory Management | Warehouse bins, stock visibility, component reservations |
| **QPM** | Quadica Purchasing Management | Purchase orders, vendor management, receiving |
| **QAM** | Quadica Assembly Management | Production batches, BOMs, serial numbers |
| **QFM** | Quadica Fulfillment Management | Shipping batches, picklists, order completion |
| **QMF** | Quadica Manufacturing | Overarching production system (discovery/planning) |

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
│  • Receive against POs         │               │  • Assign serial numbers       │
│  • Update bin inventory        │◄──────────────│  • Generate build docs         │
│                                │   Components   │  • Receive completed modules   │
└───────────────────────────────┘    received    └───────────────────────────────┘
                                                              │
                                                              │ Modules
                                                              │ completed
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
- Serial number assignment and tracking
- Production documentation (reports, labels)
- Component hard-lock reservations during production

### QFM - Quadica Fulfillment Management
**Depends on: QIM, QAM**

- "Can ship" calculations for order fulfillment
- Shipping batch creation and management
- Picklist generation sorted by bin location
- Order status tracking through fulfillment
- ShipStation integration for carrier labels

---

## Integration Matrix

|  | QIM | QPM | QAM | QFM | WooCommerce | ShipStation |
|--|-----|-----|-----|-----|-------------|-------------|
| **QIM** | - | Bin assignment | Hard locks | Stock queries | Stock sync | - |
| **QPM** | Stock levels | - | PO visibility | - | Product SKUs | - |
| **QAM** | Reservations | Incoming POs | - | Completion trigger | Orders, products | - |
| **QFM** | Bin locations | - | Batch completion | - | Order status | Order export |

---

## Data Flow Summary

1. **Order Received** - WooCommerce creates order
2. **Availability Check** - QIM calculates what components are available
3. **Production Planning** - QAM identifies buildable modules, QPM identifies components to order
4. **Purchasing** - QPM creates POs, receives against POs, updates QIM bins
5. **Production** - QAM creates batches, hard-locks components, assigns serial numbers
6. **Receiving** - QAM receives completed modules into QIM bins
7. **Fulfillment** - QFM calculates "can ship", creates shipping batches
8. **Shipping** - QFM syncs to ShipStation, updates WooCommerce order status

---

## Legacy OM Replacement Mapping

| Legacy Area | Legacy Files | QIP Module |
|-------------|--------------|------------|
| PO Management | `po-*.php`, `vendors-*.php` | QPM |
| Inventory/Bins | `report-inventory.php`, `*bin*.php` | QIM |
| Production | `prod-*.php`, `gen_asmlist.php`, `gen_batch.php` | QAM |
| Shipping | `shipbatch*.php`, `gen_canship.php`, `gen_shipbatch.php` | QFM |

---

## Implementation Approach

### Recommended Build Order

1. **QIM** - Foundation with no Q module dependencies
2. **QPM** - Purchasing can work with just QIM
3. **QAM** - Production needs QIM, benefits from QPM visibility
4. **QFM** - Fulfillment needs QIM and QAM completion data

### Migration Strategy

- Each module migrates its corresponding `oms_*` tables
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

---

*Document created by Claude Code + Chris Warris - January 2026*

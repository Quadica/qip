# Session 053: QIP Discovery Documents and Repository Organization

- Date/Time: 2026-01-16
- Session Type(s): documentation|refactoring|planning
- Primary Focus Area(s): architecture|documentation

## Overview

This session focused on organizing and documenting the OM system replacement modules (QIP - Quadica Integrated Platform). Major accomplishments included renaming QPM to QMF for Production Management (since QPM was already used for Purchasing), creating comprehensive discovery documents for four core modules, and building a complete QIP system summary document.

## Changes Made

### Files Created

**OM System Discovery Directory (`OM System Discovery/`):**

- `qip-summary.md`: Master overview document covering all QIP modules (QIM, QPM, QAM, QSA, QFM, QMF), system flow diagram, module dependencies, and recommended build order
- `qsa-summary.md`: QSA (Quadica Standard Array Engraving) module documentation - serial number generation, SVG file generation, LightBurn integration, engraving batch management
- `qpm-discovery.md` (~400 lines): Quadica Purchasing Management discovery - legacy PO generation, receiving, vendor management, database schemas, existing QPM plugin status
- `qim-discovery.md` (~280 lines): Quadica Inventory Management discovery - bin management, stock tracking, reservation system design, WooCommerce synchronization
- `qam-discovery.md` (~320 lines): Quadica Assembly Management discovery - production batch generation, "can build" calculations, QSA integration
- `qfm-discovery.md` (~360 lines): Quadica Fulfillment Management discovery - "can ship" logic, picklist generation, ShipStation integration

### Files Modified

- `OM System Discovery/qmf-discovery.md`: Renamed from QPM to QMF (Quadica Manufacturing), consolidated reference document as appendix (146 occurrences updated)

### Files Removed

- `OM System Discovery/qmf-discovery reference.md`: Merged into main `qmf-discovery.md` document
- `OM System Discovery/om-qmf-transition-plan.md`: Removed (to be recreated when implementation begins)

### Tasks Addressed

- Repository organization for QIP planning documentation
- Module naming clarification (QPM = Purchasing, QMF = Manufacturing)
- Discovery documentation for all OM replacement modules

### New Functionality Added

None - this was a documentation and planning session.

### Problems & Bugs Fixed

None - this was a documentation and planning session.

### Git Commits

Key commits from this session (chronological order):

1. `d8b4172` - Reorganize OM planning docs and add Knowledge Base
2. `51301f2` - Rename Production Management from QPM to QMF
3. `058dd6b` - Rename to Quadica Manufacturing (QMF) in docs
4. `d1f10d1` - Add QIP system summary document
5. `d4197b4` - Restore accidentally deleted transition plan
6. `9d0efce` - Add QSA summary and update QIP overview
7. `c5f9b5c` - Consolidate QMF discovery reference into main document
8. `9d960de` - Remove QMF transition plan document
9. `c7b6fd4` - Add discovery documents for QPM, QIM, QAM, and QFM modules
10. `50d9b9f` - Update QIP summary with correct module statuses

## Technical Decisions

1. **Module Naming Convention**: Renamed Production Management from QPM to QMF (Quadica Manufacturing) to avoid conflict with existing QPM (Quadica Purchasing Management) plugin already in development.

2. **Discovery Document Consolidation**: Merged the reference document into the main QMF discovery document as an appendix, reducing redundant files.

3. **Module Status Classification**:
   - QPM: "In Development" (existing plugin found with substantial implementation)
   - QIM, QAM, QFM: "Discovery" (docs now exist but no implementation)
   - QSA: "Production" (already deployed)
   - QMF: "Discovery" (docs exist, no implementation)

## Key Discoveries

### Existing QPM Plugin

Found substantial Quadica Purchasing Management (QPM) plugin already in development at `/home/chris/Documents/Quadica Plugin Dev/QPM/` with:
- Full vendor management system
- Vendor SKU mapping
- PO candidate workflow
- Purchase order lifecycle management
- BOM module with automation
- WP-CLI commands for operations

### Legacy OM System Files Reviewed

Analyzed actual OM system files via SSH:
- `po-gen.php`, `po-receive.php` - PO workflows
- `report-inventory.php` - Inventory management
- `prod-generate.php`, `gen_asmlist.php` - Production batches
- `shipbatch-generate.php`, `gen_canship.php` - Shipping workflows

### Database Schemas Documented

- **Purchasing**: `oms_vendors`, `oms_po`, `oms_po_items`
- **Inventory**: `oms_bins`, `oms_bin_log`, `oms_currentstock`
- **Assembly**: `oms_prod_batch`, `oms_batch_items`, `oms_assemblies`
- **Fulfillment**: `oms_shipbatches`, `oms_shipbatch_items`, `oms_canship`

## Current State

### Documentation Status

| Module | Status | Documentation |
|--------|--------|---------------|
| QIP Overview | Complete | `qip-summary.md` |
| QSA (Engraving) | Production | `qsa-summary.md` |
| QPM (Purchasing) | In Development | `qpm-discovery.md` |
| QIM (Inventory) | Discovery | `qim-discovery.md` |
| QAM (Assembly) | Discovery | `qam-discovery.md` |
| QFM (Fulfillment) | Discovery | `qfm-discovery.md` |
| QMF (Manufacturing) | Discovery | `qmf-discovery.md` |

### QIP System Overview

```
Order Flow:
WooCommerce Order -> QIM (reserve) -> QAM (assemble) -> QFM (ship) -> ShipStation

Purchasing Flow:
QPM (reorder) -> Vendor -> QPM (receive) -> QIM (stock)

Manufacturing Flow:
QMF (production batch) -> QSA (engrave) -> QIM (update stock)
```

## Next Steps

### Immediate Tasks

- [ ] Review discovery documents with stakeholders
- [ ] Prioritize module development order
- [ ] Begin QIM implementation (foundation layer for all modules)
- [ ] Continue QPM development in existing repo

### Future Development Sequence

1. **QIM (Inventory)** - Foundation layer, required by all other modules
2. **QPM (Purchasing)** - Continue existing development
3. **QAM (Assembly)** - Depends on QIM foundation
4. **QFM (Fulfillment)** - Downstream of all others, last to build

### Known Considerations

- **QIM is the foundation**: Inventory visibility is critical for purchasing decisions, assembly planning, and fulfillment calculations
- **QPM already has traction**: Existing plugin can be accelerated once QIM provides inventory data
- **QSA Integration**: Assembly module (QAM) needs tight integration with existing QSA engraving system
- **ShipStation Integration**: QFM needs to handle custom currency conversion and customs data

## Notes for Next Session

### QIP Module Dependencies

```
QPM (Purchasing)
 |
 v
QIM (Inventory) <---+
 |                  |
 v                  |
QAM (Assembly) -----+
 |
 v
QFM (Fulfillment)
 |
 v
ShipStation
```

### Existing QPM Plugin Location

The QPM (Quadica Purchasing Management) plugin is a separate repository:
- Location: `/home/chris/Documents/Quadica Plugin Dev/QPM/`
- Status: Substantial implementation exists
- Custom Post Types: `quad_vendor`, `quad_vendor_sku`, `quad_required_sku`, `quad_po_candidate`, `quad_purchase_order`

### Documentation File Locations

All discovery documents are in the `OM System Discovery/` directory (not `docs/plans/` as in previous session). This directory is currently untracked (shown in git status with `??`).

### Key Decisions Made

- QMF = Quadica Manufacturing (not QPM for Production)
- Discovery documents created based on SSH analysis of live OM files
- Module statuses updated to reflect actual implementation state
- Transition plan removed until implementation phase begins

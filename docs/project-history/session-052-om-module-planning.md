# Session 052: OM System Module Planning

**Date:** January 15, 2026
**Author:** Claude Code + Chris Warris
**Status:** Discovery / Planning

---

## Session Summary

This session focused on strategic planning for replacing the legacy Order Management (OM) system with a modular architecture. We reviewed the OM system discovery document, clarified module naming, created summary documents for four planned modules, and identified functionality gaps.

---

## Key Discussions

### 1. OM System Assessment

Reviewed the `om-system-discovery.md` document which details the 23-year-old legacy system. Key observations:

**Strengths:**
- Handles essential manufacturing workflows (production batching, shipping, BOM management)
- Contains valuable institutional data (5,875 assemblies, 3,012 POs, 988 bins)
- Pragmatic WooCommerce integration via `wp-load.php`

**Concerns:**
- No authentication (security risk)
- Severely outdated JavaScript (jQuery 1.8.0 from 2012)
- Direct mysqli queries bypass WordPress security abstractions
- Dual data model creates sync risks

### 2. Module Naming Clarification

**Important clarification from Chris Warris:**
- QPM = **Quadica Purchasing Management** (not Production Management)
- The existing `qpm-discovery.md` document is actually about Production Management
- QPM (Purchasing) is the first module to be built

### 3. Proposed Module Architecture

Four modules identified to replace OM functionality:

| Code | Name | Purpose |
|------|------|---------|
| **QPM** | Quadica Purchasing Management | PO creation, vendor management, receiving |
| **QIM** | Quadica Inventory Management | Bin locations, stock visibility, reservations |
| **QAM** | Quadica Assembly Management | Production batching, BOMs, serial numbers |
| **QFM** | Quadica Fulfillment Management | Shipping batches, picklists, order completion |

**Dependency Flow:**
```
QPM → QIM ← QAM → QFM → ShipStation
```

### 4. Gap Analysis

Identified functionality not fully addressed by the four modules:

| Gap | Status |
|-----|--------|
| Product Data Management (`oms_led_data`, `oms_product_data`) | Not covered - may need QPD module or ACF migration |
| Label Printing | Partially covered in QAM - may need dedicated utility |
| ShipStation Integration (custom currency conversion) | Under-addressed - WC plugin may not suffice |
| Reporting/Dashboards | Under-addressed - cross-module visibility needed |
| Custom SKU Generation | Not covered - likely part of QAM |

---

## Files Created

### Module Summary Documents

All saved to `docs/plans/`:

1. **`qpm-summary.md`** - Quadica Purchasing Management
   - Scope: PO creation, vendor management, receiving workflows
   - Replaces: `po-*.php`, `vendors-*.php`
   - Key data: 3,012 POs, 32 vendors

2. **`qim-summary.md`** - Quadica Inventory Management
   - Scope: Bin management, stock visibility, component reservations
   - Replaces: `report-inventory.php`, bin management functions
   - Key data: 988 bins

3. **`qam-summary.md`** - Quadica Assembly Management
   - Scope: Production batching, BOMs, serial numbers, array optimization
   - Replaces: `prod-*.php`, `gen_*.php`, assembly management
   - Key data: 5,875 assemblies, 3,225 production batches

4. **`qfm-summary.md`** - Quadica Fulfillment Management
   - Scope: Shipping batches, picklists, "can ship" calculations
   - Replaces: `shipbatch-*.php`, order status reporting
   - Key data: 6,143 shipping batches

### Command Structure

Created `.claude/commands/log-session.md` for session reporting in this project.

---

## Recommended Implementation Sequence

1. **QPM (Purchasing)** - Foundation, already identified as first priority
2. **QIM (Inventory)** - Required by both production and fulfillment
3. **QAM (Assembly)** - Core manufacturing, depends on QIM
4. **QFM (Fulfillment)** - Downstream of all others

---

## Open Questions

1. Should Product Data Management be a separate module (QPD) or migrate to ACF fields?
2. Does the custom ShipStation integration (currency conversion, customs data) require custom code or can WC plugin handle it?
3. How to handle the naming confusion with existing `qpm-discovery.md` (which is about Production, not Purchasing)?
4. Label printing scope - shared utility or embedded in QAM/QFM?

---

## Next Steps

- [ ] Decide on Product Data Management approach
- [ ] Verify ShipStation WC plugin capabilities
- [ ] Consider renaming/reorganizing discovery documents for clarity
- [ ] Begin QPM detailed PRD development

---

*Session report created by Claude Code + Chris Warris - January 2026*

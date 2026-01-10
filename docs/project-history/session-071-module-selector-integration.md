This report provides details of the code that was created to implement phase [4] of this project.

Please perform a comprehensive code and security review covering:
- Correctness of functionality vs. intended behavior
- Code quality (readability, maintainability, adherence to best practices)
- Security vulnerabilities (injection, XSS, CSRF, data validation, authentication, authorization, etc.)
- Performance and scalability concerns
- Compliance with WordPress and WooCommerce coding standards (if applicable)

Provide your response in this structure:
- Summary of overall findings
- Detailed list of issues with file name, line numbers (if applicable), issue description, and recommended fix
- Security risk level (Low / Medium / High) for each issue
- Suggested improvements or refactoring recommendations
- End with a brief final assessment (e.g., "Ready for deployment", "Requires moderate refactoring", etc.).

---

# Session 071: Module Selector Integration with Legacy SKU Resolver
- Date/Time: 2026-01-10 14:08
- Session Type(s): feature
- Primary Focus Area(s): backend

## Overview
Implemented Phase 4 (Module Selector Integration) of the Legacy SKU Mapping project. Modified the Module_Selector class to integrate with Legacy_SKU_Resolver, enabling both native QSA-format SKUs and mapped legacy SKUs to be included in the engraving workflow. The implementation maintains full backward compatibility when no resolver is injected.

## Changes Made
### Files Modified
- `/home/warrisr/qip/wp-content/plugins/qsa-engraving/includes/Services/class-module-selector.php`: Added Legacy_SKU_Resolver integration with optional nullable constructor parameter, modified query strategy, added resolution filtering and data augmentation
- `/home/warrisr/qip/wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php`: Added 5 new smoke tests (TC-MSI-001 through TC-MSI-005) for Module Selector Integration

### Tasks Addressed
- `docs/plans/legacy-sku-mapping-plan.md` - Phase 4: Module Selector Integration - complete
- PRD: Legacy SKU Mapping Support - Module selection with resolver integration

### New Functionality Added

**Module_Selector Changes:**

1. **Constructor Enhancement:**
   - Added `$legacy_resolver` property typed as `Legacy_SKU_Resolver|null`
   - Updated constructor signature to accept optional `?Legacy_SKU_Resolver` parameter (defaults to null)
   - Full backward compatibility maintained when no resolver is passed

2. **Query Strategy (get_modules_awaiting):**
   - **With resolver:** Removes REGEXP filter from SQL, fetches ALL modules with positive `qty_to_engrave`, then filters in PHP using the resolver
   - **Without resolver:** Falls back to original REGEXP-based filtering (`^[A-Z]{4}[a-z]?-[0-9]{5}$`) for backward compatibility

3. **New Private Method `resolve_and_filter_modules()`:**
   - Iterates through raw module records
   - Calls `$legacy_resolver->resolve()` for each SKU
   - Filters out modules that return `null` (unmapped legacy or invalid)
   - Augments passing modules with resolution data:
     - `original_sku` - The input SKU
     - `canonical_code` - 4-letter design code
     - `canonical_sku` - Synthetic SKU for config lookup
     - `revision` - Optional revision letter
     - `is_legacy` - Boolean flag
     - `config_number` - 5-digit config code

4. **Updated `group_by_base_type()`:**
   - Uses canonical data (canonical_code + revision) when available
   - Falls back to SKU string extraction when resolution data is absent
   - Propagates resolution fields through to grouped output for downstream use

5. **New Private Method `get_module_base_type()`:**
   - Helper to determine base type from module record
   - Uses `canonical_code` + `revision` if resolution data present
   - Falls back to `extract_base_type()` for raw SKU parsing

6. **Updated `get_modules_for_order()`:**
   - Also supports legacy SKU resolution when resolver is available
   - Consistent behavior with `get_modules_awaiting()`

**New Smoke Tests (5 tests):**

| Test ID | Description |
|---------|-------------|
| TC-MSI-001 | Constructor accepts Legacy_SKU_Resolver without error |
| TC-MSI-002 | Backward compatible without resolver (null parameter) |
| TC-MSI-003 | Module grouping uses canonical_code when available |
| TC-MSI-004 | Unmapped legacy SKUs are filtered out by resolver |
| TC-MSI-005 | Resolution data structure is correct for downstream use |

### Problems & Bugs Fixed
- None - this was new feature implementation

### Git Commits
Key commits from this session (newest first):
- `91d2bdc` - Integrate Module_Selector with Legacy_SKU_Resolver (Phase 4)

## Technical Decisions

- **Nullable resolver parameter:** Made the resolver optional (`?Legacy_SKU_Resolver = null`) to ensure full backward compatibility. Existing code that constructs `Module_Selector` without a resolver continues to work with original REGEXP filtering.

- **PHP filtering vs SQL filtering:** When resolver is available, SQL fetches all modules with positive `qty_to_engrave` and filtering happens in PHP. This is necessary because legacy SKU mappings (pattern matching) cannot be efficiently expressed in SQL JOINs. For production workloads (typically <100 modules in queue), this is acceptable.

- **Resolution data propagation:** Augmented module arrays carry resolution data through to grouped output. This enables downstream processing (SVG generation, config lookup) to use `canonical_code` and `canonical_sku` without re-resolving.

- **Base type derivation priority:** When resolution data is present, `canonical_code + revision` is used for base type. This ensures legacy modules are grouped correctly even when their original SKU format differs from QSA pattern.

## Current State
The Module_Selector service now supports dual-mode operation:

1. **With Legacy_SKU_Resolver:** Includes both QSA-format SKUs and mapped legacy SKUs in the engraving queue. Unmapped legacy SKUs are silently filtered out.

2. **Without resolver:** Original behavior preserved - only QSA-format SKUs matching the REGEXP pattern are included.

All 157 smoke tests pass (152 existing + 5 new for Module Selector Integration).

## Next Steps
### Immediate Tasks
- [ ] Phase 5: Batch Creation Integration - Update Batch_Ajax_Handler to use resolver during validation
- [ ] Phase 6: Config Loader Integration - Update parse_sku() to accept legacy canonical format
- [ ] Phase 7: Admin UI for SKU Mapping Management

### Known Issues
- None identified during this implementation

## Notes for Next Session
- The resolver is now integrated into Module_Selector but not yet wired into the main plugin bootstrap. Phase 8 (Plugin Wiring) will handle dependency injection into all services.
- Phase 5 should update `Batch_Ajax_Handler::validate_selection()` to use resolver instead of `Module_Selector::is_qsa_compatible()`.
- The `get_modules_for_order()` method was also updated for consistency, though it may not be used in the current workflow.

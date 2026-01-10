This report provides details of the code that was created to implement phase [3] of the Legacy SKU Mapping project.

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

# Session 070: Legacy SKU Resolver Service (Phase 3)
- Date/Time: 2026-01-10 13:53
- Session Type(s): implementation
- Primary Focus Area(s): backend, services

## Overview
Implemented Phase 3 of the Legacy SKU Mapping system. This session created the Legacy_SKU_Resolver service class that provides a unified interface for resolving both native QSA SKUs and legacy module SKUs to their canonical form. The resolver includes per-request caching for performance and batch operations for bulk processing. All 151 smoke tests pass (140 existing + 11 new).

## Changes Made
### Files Created
- `wp-content/plugins/qsa-engraving/includes/Services/class-legacy-sku-resolver.php`: Service class for SKU resolution with caching and batch operations

### Files Modified
- `wp-content/plugins/qsa-engraving/qsa-engraving.php`: Added `$legacy_sku_resolver` property and `get_legacy_sku_resolver()` getter method
- `wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php`: Added 11 new smoke tests (TC-RES-001 through TC-RES-011)

### Tasks Addressed
- `docs/plans/legacy-sku-mapping-plan.md` - Phase 3: Legacy SKU Resolver Service - Completed
- `docs/plans/legacy-sku-mapping-plan.md` - Testing Plan: Smoke tests for resolver implemented

### New Functionality Added

#### Legacy_SKU_Resolver Class
The service provides SKU resolution and format detection:

**Public Constants:**
- `QSA_SKU_PATTERN`: Regex `/^([A-Z]{4})([a-z])?-(\d{5})$/` for detecting native QSA format
- `LEGACY_CONFIG_SUFFIX`: String `'LEGAC'` used as synthetic config number for legacy SKUs

**Core Methods:**
- `resolve(string $sku): ?array` - Main resolution method returning structured array or null
  - For QSA SKUs: Parses directly without database lookup
  - For legacy SKUs: Looks up mapping via SKU_Mapping_Repository
  - Returns null for unmapped/unrecognized SKUs (silent ignore behavior)
  - Per-request caching prevents duplicate lookups

**Resolution Return Structure:**
```php
[
    'is_legacy'      => bool,    // Whether this is a mapped legacy SKU
    'original_sku'   => string,  // Input SKU exactly as provided
    'canonical_code' => string,  // 4-letter design code (e.g., STAR, SP01)
    'revision'       => ?string, // Revision letter (a-z) or null
    'canonical_sku'  => string,  // Synthetic SKU for config lookup
    'mapping_id'     => ?int,    // ID of matching mapping (null for QSA)
    'config_number'  => string,  // Config portion (5-digit or 'LEGAC')
]
```

**Format Detection:**
- `is_qsa_sku(string $sku): bool` - Checks if SKU matches native QSA pattern
- `is_legacy_sku(string $sku): bool` - Inverse of is_qsa_sku

**Batch Operations:**
- `resolve_batch(array $skus): array` - Resolve multiple SKUs, returns keyed by original SKU
- `filter_resolvable(array $skus): array` - Filter array to only resolvable SKUs

**Utility Methods:**
- `get_base_type(string $sku): ?string` - Returns canonical_code + revision for grouping
- `clear_cache(): void` - Clears resolution cache (for testing/bulk mapping changes)
- `get_cache_count(): int` - Returns cache size (for testing/debugging)

#### Plugin Integration
Added to `qsa-engraving.php`:
- Property: `private ?Services\Legacy_SKU_Resolver $legacy_sku_resolver = null`
- Getter: `public function get_legacy_sku_resolver(): ?Services\Legacy_SKU_Resolver`

#### Smoke Tests Added
| Test ID | Description |
|---------|-------------|
| TC-RES-001 | Legacy_SKU_Resolver class exists |
| TC-RES-002 | Resolver constants defined (QSA_SKU_PATTERN, LEGACY_CONFIG_SUFFIX) |
| TC-RES-003 | is_qsa_sku validates QSA format (valid and invalid patterns) |
| TC-RES-004 | resolve() handles QSA SKUs directly without mapping |
| TC-RES-005 | resolve() handles legacy SKU with mapping (creates/removes test mapping) |
| TC-RES-006 | resolve() returns null for unmapped legacy SKU |
| TC-RES-007 | Resolution cache works (cache count increases, cache clear works) |
| TC-RES-008 | get_base_type returns correct value (with/without revision) |
| TC-RES-009 | resolve_batch handles multiple SKUs in single call |
| TC-RES-010 | filter_resolvable filters correctly |
| TC-RES-011 | resolve() handles empty and whitespace SKUs |

### Problems & Bugs Fixed
None - this was greenfield implementation following the established plan.

### Git Commits
Key commits from this session (newest first):
- `001110f` - Implement Legacy SKU Resolver service class (Phase 3)

## Technical Decisions
- **Per-request caching**: Results are cached in `$cache` array for the duration of the request, keyed by original SKU. This prevents repeated database lookups when the same SKU is resolved multiple times (e.g., during batch creation with validation).
- **Null for unresolvable**: Unmapped legacy SKUs return `null` rather than `WP_Error`, allowing downstream code to filter them out silently. This supports gradual adoption without disrupting existing workflows.
- **Synthetic config number**: Legacy SKUs use `'LEGAC'` as a synthetic config number in `canonical_sku` (e.g., `SP01-LEGAC`). This differentiates from real QSA config numbers while remaining parseable by existing code.
- **Dependency injection**: Repository is injected via constructor, defaulting to new instance if not provided. This enables testing with mock repositories.
- **Trim input**: SKUs are trimmed of whitespace before processing to handle common input issues.

## Current State
Phase 3 of the Legacy SKU Mapping implementation is complete. The resolver service is deployed and tested on staging. All 151 smoke tests pass (140 existing + 11 new TC-RES tests).

The system now has:
- Phase 1: Database schema (quad_sku_mappings table)
- Phase 2: SKU_Mapping_Repository (CRUD + pattern matching)
- Phase 3: Legacy_SKU_Resolver (resolution + caching + batch operations)

The resolver is wired into the plugin but not yet connected to other services. The getter method is available for future integration.

## Next Steps
### Immediate Tasks
- [ ] Implement Phase 4: Module_Selector integration - remove REGEXP filter, use resolver
- [ ] Implement Phase 5: Batch_Ajax_Handler integration - update validation
- [ ] Implement Phase 6: Config_Loader integration - accept legacy canonical format
- [ ] Implement Phase 7: Admin UI for mapping management
- [ ] Implement Phase 8: Complete plugin wiring

### Known Issues
- None identified

## Notes for Next Session
The resolver is fully functional but not yet integrated with other services. When implementing Phase 4-6:

1. **Module_Selector integration**: Remove the REGEXP filter from the SQL query, fetch all modules with positive qty_to_engrave, and use `$resolver->resolve()` to filter in PHP.

2. **Batch_Ajax_Handler integration**: Replace `is_qsa_compatible()` validation with `$resolver->resolve()`. If null, return `WP_Error('unknown_sku_format', ...)`.

3. **Config_Loader integration**: Update `parse_sku()` regex to accept both `\d{5}` and `LEGAC` as the config portion. Alternatively, use the resolver directly for unknown formats.

4. **Testing with mapped SKUs**: The TC-RES-005 test creates and cleans up a test mapping. For manual testing, add a mapping via SQL:
   ```sql
   INSERT INTO lw_quad_sku_mappings
     (legacy_pattern, match_type, canonical_code, description)
   VALUES
     ('SP-01', 'exact', 'SP01', 'SinkPAD Single Rebel LED');
   ```

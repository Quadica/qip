# Session 059: Phase 3 Code Review Fixes
- Date/Time: 2026-01-08 23:39
- Session Type(s): bugfix
- Primary Focus Area(s): backend

## Overview
Addressed two code review issues in the QSA Identifier Repository related to sequence validation and concurrency safety. Added post-allocation validation to prevent sequence overflow under concurrent requests, and added range validation to format_qsa_id() to ensure it only produces valid QSA IDs.

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/includes/Database/class-qsa-identifier-repository.php`: Added sequence range validation to format_qsa_id() (lines 368-390), added post-allocation concurrency safety check to get_next_sequence() (lines 551-578), updated create_qsa_id() to handle WP_Error from format_qsa_id() (lines 440-443)
- `wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php`: Updated TC-QSA-003 to handle string|WP_Error return type, added TC-QSA-003b for sequence range validation testing

### Tasks Addressed
- `DEVELOPMENT-PLAN.md` - Phase 3: QSA Identifier Repository - code review fixes
- `docs/plans/qr-code-implementation-plan.md` - Phase 3 implementation complete

### Problems & Bugs Fixed

#### Issue 1: get_next_sequence() concurrency vulnerability
**Problem:** The pre-allocation check at line 504 could be bypassed under concurrent requests. When `current_sequence = MAX_SEQUENCE - 1`, two concurrent requests could both pass the pre-check, then one would receive MAX_SEQUENCE (valid) while another would receive MAX_SEQUENCE + 1 (invalid).

**Solution:** Added post-allocation validation after `SELECT LAST_INSERT_ID()`:
```php
// POST-ALLOCATION VALIDATION: Critical concurrency safety check.
if ( $next_sequence > self::MAX_SEQUENCE ) {
    // Roll back the counter to MAX_SEQUENCE
    $this->wpdb->query(
        $this->wpdb->prepare(
            "UPDATE {$this->sequence_table_name} SET current_sequence = %d WHERE design = %s AND current_sequence > %d",
            self::MAX_SEQUENCE,
            $design,
            self::MAX_SEQUENCE
        )
    );
    return new WP_Error('sequence_overflow', ...);
}
```

The pre-check remains as an optimization to avoid unnecessary DB writes in the common case. The post-check provides the definitive validation.

#### Issue 2: format_qsa_id() no range validation
**Problem:** format_qsa_id() accepted any sequence number, including values that would produce IDs that parse_qsa_id() would reject. Sequences < 1 or > 99999 would produce invalid 5-digit representations.

**Solution:** Changed return type from `string` to `string|WP_Error` and added validation:
```php
public function format_qsa_id( string $design, int $sequence ): string|WP_Error {
    if ( $sequence < 1 ) {
        return new WP_Error('invalid_sequence', ...);
    }
    if ( $sequence > self::MAX_SEQUENCE ) {
        return new WP_Error('sequence_overflow', ...);
    }
    return strtoupper( $design ) . str_pad( (string) $sequence, self::SEQUENCE_DIGITS, '0', STR_PAD_LEFT );
}
```

### Git Commits
Key commits from this session (newest first):
- `2c342cd` - Fix QSA ID sequence validation for concurrency safety

## Technical Decisions

- **Post-allocation check vs atomic SQL guard:** The INSERT...ON DUPLICATE KEY UPDATE pattern is already atomic at the DB level. Adding a WHERE clause to prevent incrementing beyond max would complicate the SQL and could cause silent failures. The post-allocation check is simple, explicit, provides clear error messages, and rolls back the counter cleanly.

- **Counter rollback on overflow:** Without rollback, a concurrent overflow would leave the counter at MAX_SEQUENCE + N, causing all future requests to immediately fail the pre-check and requiring manual database intervention. The rollback ensures the counter stays at MAX_SEQUENCE.

- **Keeping the pre-check:** The pre-check at line 504 remains as an optimization. For the common case where the sequence is clearly exhausted, it avoids an unnecessary database write. The post-check handles the edge case of concurrent access near the boundary.

## Current State
The QSA Identifier Repository now handles all edge cases for sequence allocation:
1. Pre-check prevents unnecessary DB writes when sequence is clearly exhausted
2. Atomic INSERT...ON DUPLICATE KEY UPDATE allocates sequences safely
3. Post-allocation check catches concurrent overflow scenarios
4. Counter rollback prevents drift beyond MAX_SEQUENCE
5. format_qsa_id() validates all inputs before producing output
6. All 113 smoke tests pass (TC-QSA-003b is new)

## Next Steps
### Immediate Tasks
- [ ] Continue with Phase 4: QR Code SVG generation
- [ ] Replace Data Matrix SVG generation with QR code generation

### Known Issues
- None identified for the QSA Identifier Repository

## Notes for Next Session
The sequence validation is now comprehensive. The system handles:
- Normal allocation (common case)
- Pre-exhausted sequences (optimization check)
- Concurrent allocation at boundary (post-check with rollback)
- Invalid input sequences (format_qsa_id validation)

Phase 3 implementation is complete and reviewed. Ready to proceed with Phase 4 (QR Code SVG Generation).

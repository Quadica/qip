# Session 101: AI Code Removal and Cleanup
- Date/Time: 2026-01-15 10:39
- Session Type(s): refactor, cleanup
- Primary Focus Area(s): backend

## Overview
Removed all Claude Vision API and AI-based Micro-ID decoder functionality from the plugin. This code was experimental and has been superseded by the human-in-the-loop manual decoder approach (implemented in sessions 098-100). Also fixed a critical error on the `/id` page by restoring image validation constants that were accidentally removed with the AI code.

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/includes/Admin/class-admin-menu.php`: Removed AI settings section from admin menu (Claude API key field, model selection dropdown, log retention settings, test connection button)
- `wp-content/plugins/qsa-engraving/includes/Ajax/class-lightburn-ajax-handler.php`: Removed AI settings processing (test connection handler, save AI settings logic)
- `wp-content/plugins/qsa-engraving/includes/Ajax/class-microid-decoder-ajax-handler.php`: Simplified to only handle `serial_lookup` and `full_details` endpoints; restored `MAX_IMAGE_SIZE`, `MIN_IMAGE_DIMENSION`, and `ALLOWED_MIME_TYPES` constants for landing page validation
- `wp-content/plugins/qsa-engraving/qsa-engraving.php`: Removed AI class properties (`$claude_vision_client`, `$decode_log_repository`) and their getter methods
- `wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php`: Removed 30 AI-related smoke tests (now 203 tests from original 233)

### Files Deleted
- `wp-content/plugins/qsa-engraving/includes/Services/class-claude-vision-client.php` (~1,370 lines): Claude Vision API client class
- `wp-content/plugins/qsa-engraving/includes/Database/class-decode-log-repository.php` (~580 lines): Decode log database repository class

### Tasks Addressed
- `docs/plans/microid-manual-decoder-plan.md` - Cleanup of deprecated AI approach
- `docs/project-history/session-097-ai-vision-pivot.md` - Follow-through on decision to abandon AI vision approach

### New Functionality Added
- None (removal/cleanup session)

### Problems & Bugs Fixed
- **Critical error on /id page**: After AI code removal, the `/id` landing page threw a fatal error due to missing constants (`MAX_IMAGE_SIZE`, `MIN_IMAGE_DIMENSION`, `ALLOWED_MIME_TYPES`). These constants are referenced by the landing page handler for client-side validation hints. Restored the constants to the AJAX handler class.

### Git Commits
Key commits from this session (newest first):
- `620ff55` - Restore image validation constants for landing page
- `3edd1ef` - Remove AI-based Micro-ID decoder functionality

## Technical Decisions
- **Complete removal over deprecation**: Rather than deprecating the AI code, fully removed it since the human-in-the-loop approach is now the chosen solution and the AI code was never deployed to production.
- **Constants placement**: Restored image validation constants to `MicroID_Decoder_Ajax_Handler` class even though they're primarily used by the landing page, since that's where the landing page handler references them from.
- **Test count reduction acceptable**: Reduced from 233 to 203 smoke tests. All removed tests were AI-specific and no longer relevant.

## Current State
The Micro-ID decoder functionality now consists only of:
1. **Manual decoder** (`/decode` page): Human-in-the-loop approach where users identify dots on a 5x5 grid
2. **Serial lookup** (`serial_lookup` AJAX endpoint): Validates serial and returns basic module info
3. **Full details** (`full_details` AJAX endpoint, staff-only): Returns complete module details for logged-in staff
4. **Landing page** (`/id` page): Displays module information for a given serial number

All Claude Vision API integration, decode logging, and AI settings have been removed.

### Code Reduction Summary
| Component | Lines Removed |
|-----------|---------------|
| Claude Vision Client | ~1,370 |
| Decode Log Repository | ~580 |
| AJAX Handler (AI portions) | ~500 |
| Admin Menu (AI settings) | ~150 |
| Smoke Tests (AI tests) | ~900 |
| Main Plugin File | ~40 |
| **Total** | **~3,540 lines** |

All 203 remaining smoke tests pass.

## Next Steps
### Immediate Tasks
- [ ] Test manual decoder on iOS Safari (session 100 tested Android only)
- [ ] Add camera permission denial handling
- [ ] Improve mobile camera UI polish

### Known Issues
- **iOS Safari untested**: The getUserMedia camera implementation from session 100 needs verification on iOS Safari
- **No fallback for browsers without getUserMedia**: Should add graceful fallback to file input

## Notes for Next Session
The AI vision approach has been fully removed from the codebase. Key context:

1. **Why AI was removed**: Sessions 095-097 tested multiple AI models (Claude Opus 4.5, GPT-4o, Gemini, etc.) and found ~70-80% accuracy even on synthetic images. This was deemed insufficient for production use.

2. **Manual decoder is complete**: Sessions 098-100 implemented the human-in-the-loop manual decoder which achieves near 100% accuracy.

3. **No database tables removed**: The AI code removal did not require any database schema changes. The decode_log table was never deployed to production.

4. **Test environment stable**: All 203 smoke tests pass. The plugin is in a clean state for future development.

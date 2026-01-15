# Session 095: Micro-ID Decoder Prompt Engineering
- Date/Time: 2026-01-14 00:43
- Session Type(s): feature, bugfix, optimization
- Primary Focus Area(s): backend, API integration

## Overview
This session consolidated work from Sessions 093-094 and added prompt engineering improvements to the Claude Vision Client. Phase 5 (Plugin Wiring) was implemented, code review fixes were applied, and the Micro-ID decode prompt was updated to better handle real-world smartphone images of laser-engraved Micro-ID codes on PCBs.

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/qsa-engraving.php`: Added 4 service properties, initialization code, getter methods, and activation hook rewrite rule fixes
- `wp-content/plugins/qsa-engraving/includes/Frontend/class-microid-landing-handler.php`: Added `is_decoder_enabled()` check and `render_disabled_page()` method
- `wp-content/plugins/qsa-engraving/includes/Services/class-claude-vision-client.php`: Updated decode prompt for real-world image recognition
- `wp-content/plugins/qsa-engraving/tests/smoke/wp-smoke.php`: Added 12 new smoke tests (TC-MID-P5-001 through TC-MID-P5-012)

### Tasks Addressed
- `docs/plans/microid-decoder-plan.md` - Phase 5: Plugin Wiring - COMPLETE
- All 5 phases of the Micro-ID Decoder feature are now complete
- Prompt engineering improvements for Claude Vision API (not documented in plan, operational enhancement)

### New Functionality Added

#### Phase 5: Plugin Wiring (Session 093)
Four new service properties added to the Plugin class:
- `Decode_Log_Repository` - initialized in `init_repositories()`
- `Claude_Vision_Client` - initialized in `init_services()`
- `MicroID_Decoder_Ajax_Handler` - receives three dependencies via constructor injection
- `MicroID_Landing_Handler` - initialized in `init_services()`

AJAX Endpoints Now Active:
- `wp_ajax_nopriv_qsa_microid_decode` - Public decode (unauthenticated users)
- `wp_ajax_qsa_microid_decode` - Authenticated decode (logged-in users)
- `wp_ajax_qsa_microid_full_details` - Staff-only full details (requires `manage_woocommerce`)

URL Endpoint Active:
- `/id/` - The Micro-ID Decoder landing page accessible to the public

### Problems & Bugs Fixed

#### Issue 1: Landing handler ignores enable/disable toggle (Session 094)
**Problem:** The `/id` URL was always active regardless of the admin toggle in Settings > QSA Engraving.

**Solution:** Added `is_decoder_enabled()` method to check settings and `render_disabled_page()` to display a professional "Decoder Unavailable" message with Contact Us button when disabled.

#### Issue 2: Activation hook doesn't register rewrite rules before flush (Session 094)
**Problem:** Plugin activation called `flush_rewrite_rules()` before registering the `/id` rewrite rule, causing 404 errors until permalinks were manually saved.

**Solution:** Added `add_rewrite_rule()` calls for both `/qsa/` and `/id/` URL patterns directly in the `activate()` function BEFORE calling `flush_rewrite_rules()`.

#### Issue 3: Prompt not optimized for real-world smartphone images (Session 095)
**Problem:** Original prompt described "black dots" but actual laser-engraved Micro-IDs appear differently depending on PCB layers beneath:
- Copper/reddish/bronze when engraved over copper plane (laser ablation exposes copper)
- Dark brown/black when engraved over FR4 substrate (no copper beneath)

Claude was confusing the tiny Micro-ID grid with larger PCB features like solder pads, vias, and mounting holes.

**Solution:** Updated `get_decode_prompt()` in Claude_Vision_Client with:
1. Added "How to Find the Micro-ID Code" section with disambiguation guidance
2. Added "What to IGNORE" section listing solder pads, vias, mounting holes, LED domes, and through-hole pads
3. Updated dot color description to support both copper/bronze AND dark brown/black appearances
4. Emphasized the tiny size (1mm x 1mm total, "pinhead sized")
5. Added location hint (usually near product text/branding on the module)

### Git Commits
Key commits from this session (newest first):
- `95e3809` - Support both copper and dark brown/black Micro-ID dot colors
- `e9f10e0` - Update Micro-ID decode prompt for real-world smartphone images
- `f6db797` - Fix code review issues in Micro-ID Decoder Phase 5
- `682ef73` - Implement Micro-ID Decoder Phase 5 - Plugin Wiring

## Technical Decisions
- **Dependency Injection Pattern:** Followed existing plugin pattern where services receive dependencies via constructor injection. MicroID_Decoder_Ajax_Handler receives Claude_Vision_Client, Decode_Log_Repository, and Serial_Repository.
- **Keep /id URL valid when disabled:** Rather than return 404, display a friendly "Decoder Unavailable" message. This is better UX than a confusing 404 page.
- **Duplicate rewrite rules in activation hook:** Intentional duplication - activation hook ensures rules work immediately on first activation, while handler method ensures rules persist through normal WordPress init cycles.
- **Prompt engineering for robustness:** Added explicit disambiguation guidance rather than assuming Claude would automatically distinguish PCB features. Real-world photos have many circular features that could be confused for dot patterns.

## Current State
The Micro-ID Decoder feature is fully operational:

1. **Database Layer (Phase 1):** `quad_microid_decode_logs` table for tracking decode attempts
2. **API Client (Phase 1):** Claude Vision Client with improved real-world image recognition
3. **AJAX Handler (Phase 2):** Three endpoints for public decode, authenticated decode, and staff-only full details
4. **Frontend Landing (Phase 3):** `/id/` URL with photo upload interface and result display
5. **Admin Integration (Phase 4):** Settings for Claude API key, decoder enable/disable, and connection testing
6. **Plugin Wiring (Phase 5):** All services connected and operational

When the decoder is enabled:
- Users navigate to `/id/` to access the Micro-ID Decoder
- Upload a photo of an LED module with a Micro-ID code
- Receive basic product information (Serial, SKU, Product, Date)
- Staff users can request full traceability details (Order ID, Customer, LED Codes, Batch ID)

When the decoder is disabled:
- Users see a "Decoder Unavailable" page with Contact Us button

### Test Results
- **Total smoke tests:** 233
- **Passed:** 233
- **Failed:** 0

## Screenshots
- `docs/screenshots/dev/phase5-microid-landing-page.png` - Working decoder UI when enabled
- `docs/screenshots/dev/phase5-microid-disabled-page.png` - "Decoder Unavailable" page when toggle is off

## Next Steps
### Immediate Tasks
- [ ] Configure Claude API key in admin settings (Settings > QSA Engraving)
- [ ] Enable the Micro-ID Decoder toggle in settings
- [ ] Perform manual testing with real Micro-ID images to validate prompt improvements
- [ ] Test enable/disable toggle to verify page transitions correctly
- [ ] Monitor decode logs for any issues

### Known Issues
- Testing with sample image (`micro-id-smartphone-sample.jpg`) showed Claude initially struggling to identify the Micro-ID grid
- Prompt has been updated to better describe dot colors and help distinguish from other PCB features
- Further testing needed to validate prompt improvements work with various smartphone photos
- May need additional prompt refinements based on test results with production images

## Notes for Next Session
The Micro-ID Decoder feature is complete from a development perspective. All five phases plus code review fixes are done. The prompt engineering work was exploratory, responding to observed challenges in real-world image recognition.

Key prompt changes that may need further iteration:
1. Dot color now described as copper/bronze OR dark brown/black depending on underlying PCB layers
2. Added explicit "What to IGNORE" list for common PCB features
3. Emphasized the tiny 1mm x 1mm footprint to help Claude look for the right scale of features

If Claude continues to struggle with certain image types, consider:
- Adding more specific examples of what solder pads vs Micro-ID dots look like
- Providing reference grid overlay examples
- Testing with different Claude models (currently using claude-sonnet-4-5-20250929)

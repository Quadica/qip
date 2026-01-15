# Session 098: Micro-ID Manual Decoder Phase 1
- Date/Time: 2026-01-15 00:55
- Session Type(s): feature
- Primary Focus Area(s): frontend

## Overview
This session implemented Phase 1 of the Human-in-the-Loop Micro-ID Manual Decoder feature, as planned in `docs/plans/microid-manual-decoder-plan.md`. This follows the strategic decision in Session 097 to pivot from AI vision to human-assisted decoding after determining AI vision models were unreliable (~70-80% accuracy even on synthetic images).

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/qsa-engraving.php`: Added handler registration, getter method, and rewrite rule to activation hook for /decode URL

### Files Created
- `wp-content/plugins/qsa-engraving/includes/Frontend/class-microid-manual-decoder-handler.php` (~1440 lines): Complete frontend handler implementing the manual decoder interface

### Tasks Addressed
- `docs/plans/microid-manual-decoder-plan.md` - Phase 1: Frontend Handler Class - Complete
- `docs/plans/microid-manual-decoder-plan.md` - Phase 2 (partial): Core Features - Implemented upload, Cropper.js, grid, and validation
- `docs/reference/quadica-micro-id-specs.md` - Grid-to-bit mapping specification followed exactly

### New Functionality Added
- **Interactive /decode URL**: New public-facing page at `/decode` for human-assisted Micro-ID decoding
- **Photo Upload with Camera Capture**: Mobile devices can use camera directly via `capture="environment"` attribute; desktop users can drag-and-drop or use file picker
- **Cropper.js Integration**: Full image manipulation with zoom, rotate (90 degree increments), and square crop functionality using Cropper.js v1.6.1 from CDN
- **Interactive 5x5 Grid**: Pure CSS Grid with tap-to-toggle cells for manual dot identification; corner anchors (positions 0, 4, 20, 24) are pre-filled and cannot be toggled
- **Real-time Parity Validation**: JavaScript-based validation runs on every cell toggle, providing instant feedback on code validity
- **Mobile-Friendly Interface**: Touch-optimized button sizes (44px minimum), responsive layout that stacks on narrow screens, viewport meta tag prevents unwanted zooming
- **Help Modal**: Explains what a Micro-ID is and provides step-by-step instructions
- **Disabled State Page**: When decoder is disabled in plugin settings, shows appropriate message with contact link

### Problems & Bugs Fixed
- No bugs fixed (new feature implementation)

### Git Commits
Key commits from this session (newest first):
- `19b6409` - Add Micro-ID Manual Decoder for human-in-the-loop decoding

## Technical Decisions
- **CDN for Cropper.js**: Used CDN (cdnjs.cloudflare.com) rather than bundling to keep the implementation simple and follow the existing pattern of inline CSS/JS in handler classes
- **Grid-to-Bit Mapping as PHP Constant**: Defined `GRID_TO_BIT_MAP` as a class constant with 25 entries mapping grid positions to bit indices (-1 for anchors, -2 for parity bit, 0-19 for data bits)
- **Client-Side Only Validation**: All validation happens in JavaScript; no AJAX calls needed until the user clicks "View Module Info" which simply redirects to the existing `/id` page
- **Redirect to Existing /id Page**: When a valid code is decoded, the "View Module Info" button redirects to `/id?serial=XXXXXXXX` rather than duplicating lookup functionality
- **Self-Contained Handler**: Following the `MicroID_Landing_Handler` pattern, all HTML/CSS/JS is rendered inline in a single PHP file with no external dependencies except Cropper.js CDN

## Current State
The `/decode` page is fully functional for Phase 1:
1. User can upload a photo or take one directly on mobile
2. User can crop, zoom, and rotate the image to isolate the Micro-ID area
3. User sees cropped image alongside an interactive 5x5 grid
4. Tapping grid cells toggles dots on/off (except corner anchors which are always on)
5. Status display shows real-time feedback:
   - Neutral: "Tap cells to match the dots you see"
   - Invalid: "Parity check failed - please verify your entry"
   - Valid: Shows decoded serial number and "View Module Info" button
6. Valid codes redirect to `/id?serial=XXXXXXXX` for module lookup

The feature respects the `microid_decoder_enabled` plugin setting - when disabled, shows a "Decoder Unavailable" page with contact link.

All 233 existing smoke tests continue to pass.

## Next Steps
### Immediate Tasks
- [ ] Phase 2: Add support for `serial` query parameter on `/id` page to auto-load module data
- [ ] Integration testing on mobile devices (iPhone Safari, Android Chrome)
- [ ] User acceptance testing with real Micro-ID photos from production modules

### Known Issues
- No known issues at this time

## Notes for Next Session
- The `/id` page currently only supports the `code` query parameter (full 25-char grid code). Phase 2 of the plan calls for adding `serial` query parameter support so the manual decoder redirect works seamlessly.
- The rewrite rule for `/decode` was added to both the `init` action (in the handler) and the plugin activation hook (in qsa-engraving.php). After deployment, may need to flush permalinks manually if the URL doesn't work immediately.
- The grid-to-bit mapping follows the specification in `docs/reference/quadica-micro-id-specs.md` exactly:
  - Row 0: [Anchor] [Bit19] [Bit18] [Bit17] [Anchor]
  - Row 1: [Bit16] [Bit15] [Bit14] [Bit13] [Bit12]
  - Row 2: [Bit11] [Bit10] [Bit9] [Bit8] [Bit7]
  - Row 3: [Bit6] [Bit5] [Bit4] [Bit3] [Bit2]
  - Row 4: [Anchor] [Bit1] [Bit0] [Parity] [Anchor]

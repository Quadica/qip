# Session 096: Micro-ID Multi-Image Reference POC
- Date/Time: 2026-01-14 11:56
- Session Type(s): feature|optimization
- Primary Focus Area(s): backend|testing

## Overview
This session focused on testing and improving the Micro-ID decoder's accuracy using multi-image reference support and prompt engineering. A proof-of-concept was implemented allowing reference images (location markers, sample photos) to be included in API requests. Despite extensive testing and prompt refinements, the API-based decode accuracy remains unreliable compared to web-based chat interfaces.

## Changes Made
### Files Modified
- `wp-content/plugins/qsa-engraving/includes/Services/class-claude-vision-client.php`: Added multi-image reference support methods and rewrote decode prompt for methodical analysis
- `wp-content/plugins/qsa-engraving/tests/smoke/test-microid-decode-poc.php`: New POC test script for WP-CLI testing with reference images
- `wp-content/plugins/qsa-engraving/assets/reference-images/`: New directory containing SZ-04 module reference images

### New Files Created
- `wp-content/plugins/qsa-engraving/assets/reference-images/sz04-location-marker.jpg` - Annotated image showing Micro-ID location (red box)
- `wp-content/plugins/qsa-engraving/assets/reference-images/sz04-sample-1.jpg` - Smartphone photo sample
- `wp-content/plugins/qsa-engraving/assets/reference-images/sz04-sample-2.jpg` - Smartphone photo sample
- `wp-content/plugins/qsa-engraving/assets/reference-images/sz04-sample-3.jpg` - Smartphone photo sample
- `wp-content/plugins/qsa-engraving/assets/reference-images/sz04-sample-1-zoomed.jpg` - Zoomed version
- `wp-content/plugins/qsa-engraving/assets/reference-images/sz04-sample-2-zoomed.jpg` - Zoomed version
- `wp-content/plugins/qsa-engraving/assets/reference-images/sz04-sample-3-zoomed.jpg` - Zoomed version
- `wp-content/plugins/qsa-engraving/assets/reference-images/test-decode-target.jpg` - Original test image
- `docs/sample-data/reference-images/` - Source reference images directory

### Tasks Addressed
- `microid-decoder-prd.md` - Phase 5: AI Vision Integration - Extended prompt engineering work
- Exploratory work for improving decode accuracy (not tied to specific completion criteria)

### New Functionality Added
- **Multi-Image Reference System**: New `decode_with_references()` method in Claude Vision Client allows including reference images in API requests. Helper method `build_request_with_references()` handles multi-image payload construction.
- **Reference-Aware Prompt**: New `get_decode_prompt_with_references()` prompt explains reference images to the AI model.
- **Methodical Decode Prompt**: Completely rewrote main `get_decode_prompt()` to require explicit row-by-row grid mapping, emphasize finding 4 corner anchors first, make parity verification mandatory, and add hint about typical serial number ranges.
- **POC Test Script**: `test-microid-decode-poc.php` supports environment variables (TEST_IMAGE, WITH_REFS, REFS_ONLY) for flexible testing scenarios.

### Problems & Bugs Fixed
- **POC Script Compatibility**: Fixed test script to work with `wp eval-file` command format

### Git Commits
Key commits from this session (newest first):
- `60edc7b` - Rework decode prompt for methodical step-by-step analysis
- `763fc6f` - Add zoomed Micro-ID sample images for decode testing
- `1576109` - Add original test image for decode comparison
- `ffb4aab` - Move reference images into plugin assets for deployment
- `18b77ce` - Fix POC test script for wp eval-file compatibility
- `6569fe7` - Add multi-image reference support for Micro-ID decoder POC

## Technical Decisions
- **Reference Images in Plugin Assets**: Moved reference images from docs/sample-data to plugin assets directory so they deploy with the plugin and are accessible at runtime.
- **Methodical Prompt Structure**: Rewrote prompt to force step-by-step analysis with explicit work shown before JSON output. This improved visibility into the AI's reasoning but did not significantly improve accuracy.
- **Serial Number Hint**: Added hint that most serials are low numbers (< 1000) to help guide the AI toward plausible results.
- **Zoomed Image Testing**: Created zoomed versions of sample images to test if closer crops improve accuracy (marginal improvement observed).

## Current State
The Micro-ID decoder has enhanced multi-image reference support implemented as a POC. However, extensive testing revealed that:
- Neither WITH refs nor WITHOUT refs consistently produces correct results via API
- The methodical prompt improves process visibility but not final accuracy
- Zoomed images perform only marginally better than full images
- Closest result achieved: 00000218 (off by 10 from actual 00000208)
- Manual decode by Claude in extended reasoning achieves correct results, suggesting the issue is API interaction pattern rather than model capability

Test results with known serial numbers:
- sample-1: actual 00000208, API results inconsistent
- sample-2: actual 00000208, API results inconsistent
- sample-3: actual 00000204, API results inconsistent

## Next Steps
### Immediate Tasks
- [ ] Research why web-based chat gives better results than API
- [ ] Investigate extended thinking mode (API feature not currently used)
- [ ] Test with different temperature settings
- [ ] Consider different model versions for decode task

### Known Issues
- **API vs Web Chat Discrepancy**: Web-based Claude chat reliably decodes Micro-IDs, but API calls produce inconsistent results. Root cause unknown.
- **Accuracy Limitations**: Current implementation may not be reliable enough for production use without additional improvements.

## Notes for Next Session
The multi-image reference approach is implemented but does not solve the fundamental decode accuracy problem. Key areas to explore:
1. **Extended Thinking Mode**: The API supports extended thinking which may improve reasoning quality
2. **Temperature Settings**: Lower temperature might produce more consistent results
3. **Alternative Approaches**: Traditional computer vision or user-guided cropping may be needed as fallback
4. **Model Selection**: Claude Opus 4.5 was recommended in session 095, but performance differences between models should be tested

The POC test script (`tests/smoke/test-microid-decode-poc.php`) is available for continued testing:
```bash
# Test without references
wp eval-file tests/smoke/test-microid-decode-poc.php

# Test with references
WITH_REFS=1 wp eval-file tests/smoke/test-microid-decode-poc.php

# Test references only (no target image)
REFS_ONLY=1 wp eval-file tests/smoke/test-microid-decode-poc.php
```

This session represents exploratory work that may inform future architectural decisions about the Micro-ID decoder feature.

# Session 097: AI Vision Testing and Human-in-the-Loop Pivot
- Date/Time: 2026-01-15 00:44
- Session Type(s): feature|refactor|documentation
- Primary Focus Area(s): backend|testing

## Overview
This session conducted extensive multi-model AI vision testing for Micro-ID decoding, revealing fundamental accuracy limitations across all tested models. After discovering that even synthetic test images produced only 70-80% accuracy, the decision was made to pivot to a human-in-the-loop approach. All AI vision POC code was removed and a comprehensive plan was created for a manual decoder tool.

## Changes Made
### Files Modified
- None remaining (all POC code removed)

### Files Removed
- `docs/poc/decode-visual-grid.py`: Visual grid decode test script
- `docs/poc/generate-test-microid.py`: Synthetic test image generator
- `docs/poc/test-openrouter-decode.py`: Multi-model OpenRouter test script
- `docs/poc/microid-preprocessor.py`: OpenCV-based image preprocessor
- `docs/poc/microid-preprocessor-results.md`: Preprocessor test results
- `docs/poc/MICROID-DECODE-FINDINGS.md`: AI decode findings document
- `wp-content/plugins/qsa-engraving/tests/smoke/test-microid-decode-poc.php`: POC smoke test
- `wp-content/plugins/qsa-engraving/tests/smoke/test-preprocessed-decode.php`: Preprocessed decode test
- `docs/poc/` directory: Entire POC directory removed

### Files Created
- `docs/plans/microid-manual-decoder-plan.md`: Comprehensive plan for human-in-the-loop decoder tool

### Tasks Addressed
- `microid-decoder-prd.md` - Phase 5: AI Vision Integration - Concluded as infeasible with current technology
- Exploratory work evaluating AI vision model capabilities
- Architecture decision to pivot to human-assisted decoding

### New Functionality Added
- None (exploratory/research session leading to pivot decision)

### Problems & Bugs Fixed
- None (testing session)

### Git Commits
Key commits from this session (newest first):
- `493a9b0` - Remove AI vision Micro-ID decode POC code
- `3561d8a` - Document Micro-ID decode POC findings and add parity validation
- `4754b54` - Add Micro-ID visual grid decode POC scripts

## Technical Decisions
- **AI Vision Deemed Unreliable for Production**: After testing multiple models (Claude Opus 4.5, Claude 3.5 Sonnet, GPT-4o, Gemini, Qwen, Llama), concluded that AI vision accuracy is insufficient for production Micro-ID decoding. Even with synthetic perfect images, accuracy topped out at ~80%.

- **Root Cause Identified**: Models can read grids (mostly correct) and perform binary conversion math correctly. The failure point is precise visual perception - models hallucinate "phantom dots" or miss actual dots. This is a fundamental limitation of current vision models, not a prompt engineering issue.

- **Parity Validation Catches but Cannot Fix**: The built-in parity bit in Micro-ID encoding reliably detects errors, but when vision fails there's no way to automatically correct.

- **Human-in-the-Loop Architecture Selected**: Designed approach where humans identify dot positions using an interactive grid overlay, while code handles all conversion and validation logic. This achieves near 100% accuracy.

- **POC Code Removed**: All experimental AI vision code removed to keep repository clean. Findings documented in session report for future reference.

## Current State
The Micro-ID decoder feature requires a new implementation approach:

**What Was Proven:**
- AI vision models cannot reliably read 5x5 Micro-ID grids from photographs
- Accuracy varies by model but none achieve production-ready reliability
- Claude Opus 4.5: Most consistent (~80% on synthetic images) but systematic phantom dot errors
- Claude 3.5 Sonnet via OpenRouter: Highly inconsistent (~33% accuracy)
- GPT-4o: Inconsistent (~40% accuracy)
- Parity validation successfully catches errors but cannot auto-correct

**AI Vision Model Test Results:**

| Model | Consistency | Accuracy | Notes |
|-------|-------------|----------|-------|
| Claude Opus 4.5 | High | ~80% | Systematic phantom dots |
| Claude 3.5 Sonnet | Low | ~33% | Highly variable results |
| GPT-4o | Low | ~40% | Inconsistent grid reading |
| Gemini | Low | ~50% | Better on synthetic images |
| Qwen/Llama | Low | <30% | Not viable |

**New Architecture Planned:**
- `/decode` URL with mobile-friendly UI
- Photo upload with camera capture on mobile
- Cropper.js for zoom/crop/rotate
- Interactive 5x5 grid for manual dot identification
- Real-time JavaScript parity validation
- Redirect to module info on valid decode

## Next Steps
### Immediate Tasks
- [ ] Implement Phase 1: `class-microid-manual-decoder-handler.php` frontend handler
- [ ] Create HTML/CSS/JS for interactive decoder UI
- [ ] Integrate Cropper.js for image manipulation
- [ ] Implement JavaScript parity validation
- [ ] Add `/decode` rewrite rules to plugin
- [ ] Test on mobile devices (iPhone Safari, Android Chrome)

### Known Issues
- None (clean slate for new implementation)

## Notes for Next Session
The AI vision approach has been thoroughly tested and documented. Key insights for future consideration:

1. **AI vision limitations are fundamental**: Not a prompt engineering problem. Current models lack the precise visual perception needed for small-grid reading.

2. **Human-in-the-loop is proven viable**: When humans identify dots, code can reliably convert and validate.

3. **Plan is comprehensive**: `docs/plans/microid-manual-decoder-plan.md` contains full implementation details including:
   - UI mockups
   - Cropper.js integration code
   - JavaScript validation logic
   - Bit position mapping
   - Test cases with known serial numbers

4. **Known test serials available**:
   - Serial 203: Grid `1000100000000011001011111`
   - Serial 207: Grid `1000100000000011001111111`

5. **Implementation estimate**: ~680 lines total (100 PHP, 80 HTML, 200 CSS, 300 JS) - single file implementation following existing landing handler pattern.

The pivot decision was validated through systematic testing with controlled synthetic images, eliminating variables like image quality or camera angle as confounding factors.

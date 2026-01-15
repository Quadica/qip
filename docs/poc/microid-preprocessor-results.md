# Micro-ID Preprocessor POC Results

**Date:** 2026-01-14
**Version:** v6 (Coordinate-Based Extraction)

## Approach

The POC uses coordinate-based extraction based on known Micro-ID position per module type:
- Module type: SZ-04 (20mm x 20mm)
- Micro-ID bounding box: 1.825mm square
- Position: (4.1281mm, 6.2953mm) from module bottom-left corner
- Internal padding: 0.3mm

The script tries 4 orientations (0, 90, 180, 270) to handle rotated images.

## Test Results

| Sample | Actual Rotation | Micro-ID Captured | Correct Crop |
|--------|-----------------|-------------------|--------------|
| sample-5 | ~0 (upright) | **YES** | crop_0 |
| sample-7 | ~180 | **YES** | crop_2 |
| sample-4 | ~225 (diagonal) | NO | - |
| sample-6 | ~45 (diagonal) | NO | - |

### Success Cases

**Sample-5 (upright):** The Micro-ID 5x5 dot matrix is clearly captured in crop_0. The dots are distinct and should be decodeable by Claude Vision.

**Sample-7 (180 rotated):** The Micro-ID is clearly captured in crop_2. Excellent dot visibility.

### Failure Cases

**Sample-4 and Sample-6 (diagonal rotations):** All 4 orientation crops miss the Micro-ID entirely because the module is rotated at an arbitrary angle (~45 or ~225) that doesn't align with the axis-aligned extraction boxes.

## Conclusions

1. **Coordinate-based extraction works** for axis-aligned images (0, 90, 180, 270 rotation)
2. **Arbitrary rotations require rotation correction** before extraction
3. **Image quality is sufficient** - when captured, the Micro-ID dots are clear

## Recommended Next Steps

### Option A: Require Axis-Aligned Photos (Simple)
- Instruct users to photograph the module with edges roughly parallel to the phone frame
- Simplest implementation, 4-orientation approach handles 0/90/180/270 variations
- May reduce user experience slightly

### Option B: Implement Rotation Detection (Better UX)
1. Detect module edges using Hough line transform or contour analysis
2. Calculate rotation angle from detected edges
3. Rotate image to align module with frame
4. Apply coordinate-based extraction

### Option C: Hybrid Approach (Recommended)
1. First try coordinate-based extraction with 4 orientations
2. If no clear Micro-ID detected in any crop, attempt rotation detection
3. After rotation correction, retry extraction
4. Provides fallback for diagonal photos

## Technical Notes

- Scale calculation: ~138 pixels/mm for 3072x4080 images with centered 20mm module
- Crop size: 504x504 pixels (~3.65mm square including margin)
- Enhancement: CLAHE + sharpening produces clear dot contrast
- Module outline detection failed due to corner cutouts; frame-based fallback used

## Files

- Script: `docs/poc/microid-preprocessor.py`
- Test images: `docs/sample-data/reference-images/sz04-sample-[4-7].jpg`
- Results: `docs/poc/test-results/`

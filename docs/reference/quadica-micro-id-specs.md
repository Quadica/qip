# Specification: Quadica 5x5 Micro-ID System
**Version:** 1.0  
**Date:** November 20, 2025  
**Application:** Unique Identification of LED Modules  
**Status:** Approved - Ready For Use  

## 1. System Overview
This specification defines the physical geometry, data structure, and encoding/decoding algorithms for a proprietary 2D dot-matrix code. The system is designed for direct part marking (DPM) via UV laser on white masked PCBs where available space is limited to 1.5 mm².

The code utilizes a **5x5 Dot Matrix** with a **4-Corner Anchor** architecture, providing a capacity of **1,048,576 unique identifiers** with built-in error detection. While the internal data structure is a raw binary integer, the system output is standardized as an **8-digit numeric string** (e.g., `00000001`).

## 2. Physical Specifications

### 2.1. Geometry
*   **Matrix Size:** 5 rows x 5 columns
*   **Total Footprint:** 1.0 mm x 1.0 mm
*   **Dot Diameter:** 0.10 mm
*   **Dot Pitch:** 0.225 mm (center-to-center)
*   **Clearance:** The code requires a minimum quiet zone of 0.25 mm around the perimeter (contained within the 1.5 mm available space). A single fixed orientation dot is allowed in this quiet zone (see 2.3).

### 2.2. Coordinate System
The grid is indexed (row, col) from (0,0) at the Top-Left to (4,4) at the Bottom-Right.
Coordinate calculation for dot centers relative to top-left origin (0,0):
X = 0.05 + (col x 0.225)
Y = 0.05 + (row x 0.225)
*   **Origin reference:** (0,0) is the top-left corner of the 1.0 mm x 1.0 mm grid area. When centering within the 1.5 mm available space, apply an offset of **+0.25 mm, +0.25 mm** to all coordinates.

### 2.3. Orientation Marker (Quiet-Zone Dot)
*   A fixed dot is burned in the quiet zone just outside the top-left anchor to disambiguate 180° rotations.
*   **Location:** X = -0.175 mm, Y = 0.05 mm (aligned vertically with the top row centers, 0.225 mm left of the top-left anchor center).
*   **Size:** Same diameter as standard dots (0.10 mm).
*   **State:** Always ON; never carries data.
*   **Purpose:** Provides an in-pattern asymmetry the decoder can rely on even when the v-score is not visible in the captured image.

### 2.4. Manufacturing Tolerances & Contrast
*   **Dot Diameter:** 0.10 mm ± 0.01 mm
*   **Dot Center Placement:** ± 0.015 mm relative to ideal coordinates.
*   **Quiet Zone:** Minimum 0.25 mm clear on all sides; only the orientation dot may intrude the quiet zone.
*   **Flatness/Skew:** Minor panel warp allowed; decoder applies affine normalization (see 5).
*   **Contrast:** Marking is black on white surface. No minimum specified; if automated inspection reports low-contrast (<~30% difference), flag for re-mark but continue if decoding passes parity/orientation.

## 3. Data Topology

The matrix contains **25 positions**. These are categorized into three types:
1.  **Anchors (4 dots):** Fixed reference points used for finding the grid.
2.  **Data Payload (20 dots):** Stores the Serial Number.
3.  **Parity Bit (1 dot):** Used for error checking.

### 3.1. Bit Map
The grid is read in **Row-Major Order** (Left-to-Right, Top-to-Bottom).

| Row \ Col | 0 | 1 | 2 | 3 | 4 |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **0** | **ANCHOR** | Bit 19 (MSB) | Bit 18 | Bit 17 | **ANCHOR** |
| **1** | Bit 16 | Bit 15 | Bit 14 | Bit 13 | Bit 12 |
| **2** | Bit 11 | Bit 10 | Bit 9 | Bit 8 | Bit 7 |
| **3** | Bit 6 | Bit 5 | Bit 4 | Bit 3 | Bit 2 |
| **4** | **ANCHOR** | Bit 1 | Bit 0 (LSB) | **PARITY** | **ANCHOR** |

*(Note: Bit 0 is the Least Significant Bit. Bit 19 is the Most Significant Bit.)*

### 3.2. Data Constraints & Formatting
*   **Supported Characters:** Integers only (0–9). Alphanumeric characters are **not** supported.
*   **Technical Range:** 0 to 1,048,575 (20-bit capacity).
*   **Business Range:** 1 to 1,048,575. Serial number 0 is reserved/unused to avoid confusion with "no serial assigned" states in database systems.
*   **Input/Output Format:** Although the laser encodes a raw integer, the Input/Output interface must treat this as a **fixed-width 8-character string**.
    *   Input `1` -> Encoded as `1` -> Output `00000001`
    *   Input `1048575` -> Encoded as `1048575` -> Output `01048575`

## 4. Encoding Algorithm (Generation)

**Input:** Integer `ID` or Numeric String (Business Range: 1 to 1,048,575).

1.  **Validation:**
    If `ID > 1,048,575` or `ID < 1`, return Error (Out of Bounds).
    *Note: While the encoding technically supports ID 0, business systems reserve 0 for "no serial assigned" states.*

2.  **Binary Conversion:**
    Convert `ID` to a 20-bit binary string. Padding with leading zeros if necessary.
    *Example:* Input `1` (or string "00000001") becomes `00000000000000000001`.

3.  **Parity Calculation:**
    Count the number of `1`s in the 20-bit binary string.
    *   If count is **Even** (e.g., 8), Parity Bit = `0`.
    *   If count is **Odd** (e.g., 7), Parity Bit = `1`.
    *   *Goal:* The total sum of "ON" bits (Data + Parity) must always be Even.

4.  **Construct Stream:**
    Create a linear stream of 21 bits: `[Bit 19]...[Bit 0] + [Parity]`.

5.  **Map to Grid:**
    Initialize a 5x5 grid with zeros.
    *   Set `(0,0)`, `(0,4)`, `(4,0)`, `(4,4)` to **1** (Anchors).
    *   Fill the remaining 21 empty slots with the Stream in Row-Major order.

6.  **Render:**
    Output the coordinates for laser marking.

---

## 5. Decoding Algorithm (Reading)

**Input:** Image file (Bitmap/JPEG) of the module.

1.  **Preprocessing:**
    *   Convert image to grayscale.
    *   Apply thresholding to binary (Black/White).

2.  **Anchor Detection:**
    *   Locate the 4 blob centroids closest to the corners of the bounding box.
    *   Verify alignment: The centers should form a square roughly proportional to the pitch specification.
    *   Detect the fixed quiet-zone orientation dot near the top-left anchor (expected at X = -0.175 mm, Y = 0.05 mm relative to the top-left anchor center).
    *   Orientation rule:
        *   If the orientation dot is found adjacent to one anchor, rotate the sampling grid so that dot becomes “top-left”.
        *   If the dot is missing/occluded, fail with “Orientation not detected” rather than decode a potentially rotated payload.
    *   Compute an affine transform from detected anchors to the ideal square; apply it to normalize for skew/scale before sampling. If residual fit error exceeds placement tolerance (±0.015 mm), fail with “Geometry fit failed”.

3.  **Grid Sampling:**
    *   Calculate the theoretical center of all 25 cells based on the 4 anchors.
    *   Sample the pixel intensity at each center point.
    *   Determine state: `1` (Marked) or `0` (Unmarked).

4.  **Extraction:**
    *   Discard the 4 corner positions.
    *   Extract the remaining 21 bits into a temporary stream.
    *   Separate:
        *   **Payload:** First 20 bits.
        *   **Checksum:** Last bit (21st bit).

5.  **Verification:**
    *   Sum the number of `1`s in the Payload.
    *   Add the Checksum bit.
    *   If `(Sum % 2) != 0`, **FAIL**. Return "Read Error" (Glare/Dust detected).
    *   If orientation was not confidently resolved, **FAIL**. Return "Orientation not detected".

6.  **Integer Reconstruction & Formatting:**
    *   Convert the 20-bit Payload back to Decimal Integer.
    *   **Format as String:** Convert the integer to an 8-character string, padding with leading zeros.
    *   *Example:* Integer `50` -> Return `"00000050"`.

---

## 6. Implementation Reference Data

### 6.1. Mask Array
To simplify programming, use this mask. `1` indicates a Data/Parity bit, `0` indicates an Anchor.

```text
0 1 1 1 0
1 1 1 1 1
1 1 1 1 1
1 1 1 1 1
0 1 1 1 0
```

### 6.2. Example: ID "00600001" (Raw Int: 600001)

**Formatted Output:** `"00600001"`  
**Raw Integer:** 600,001 (Hex `0x927C1`)  
**Binary Payload:** `10010 01001 11110 00001`  
**Bit Count:** 8 (Even)  
**Parity Bit:** `0` (To keep total Even)  

**Visual Layout:**
( ● = Laser ON, ○ = Laser OFF )

| Row | Visual | Description |
| :--- | :--- | :--- |
| **0** | ● ● ○ ○ ● | Anchor, `100`, Anchor |
| **1** | ● ○ ○ ○ ○ | `10000` |
| **2** | ● ○ ○ ● ○ | `10010` |
| **3** | ○ ● ● ● ● | `01111` |
| **4** | ● ○ ○ ○ ● | Anchor, `000`, Parity(0), Anchor |

---

## 7. Notes for Laser Operator
*   **Contrast:** The reading algorithm relies on high contrast. Adjust laser frequency/speed to create dark marks on aluminum (annealing) or ablation of surface coating.
*   **Depth:** Marks should have slight depth to cast a shadow, aiding readability under ring-lighting.
*   **Distortion:** Ensure the aspect ratio is maintained. If the grid is engraved as a rectangle 0.9 mm x 1.1 mm rather than a square, the sampling algorithm may fail.
*   **Contrast Note:** Production is black-on-white under controlled process. If inspection tools flag low contrast, allow a re-burn but decoding may proceed if orientation and parity checks pass.

## 8. Capture & Submission Guidelines (Customer Smartphone)
*   **Framing:** Capture the entire code plus the quiet-zone orientation dot; avoid tight crops. Including the nearby v-score line is helpful.
*   **Resolution target:** Aim for ≥150–200 pixels across the 1 mm printed code (≈6–8 px per 0.1 mm dot). Server will reject if detected code width < 120 px.
*   **Focus/blur:** Image must be sharp; server applies a sharpness check (e.g., variance of Laplacian). If below threshold, request a retake.
*   **Lighting:** Prefer diffuse light; avoid strong specular glare. Server rejects if saturation in the code region exceeds ~5% of pixels.
*   **Angle:** Keep phone roughly head-on. The decoder applies affine normalization, but if anchor-fit residual exceeds ±0.015 mm tolerance, the image is rejected.
*   **Retries:** On failure (orientation missing, geometry fit failed, too small, or too blurry), prompt for another photo and keep the best-scoring attempt.
*   **Privacy:** If possible, crop to the module area before upload; the backend will still crop to the detected code.

## 9. Reference Files

Three reference SVG files have been created 
- [micro-id-00000001.svg](/home/warrisr/lmb/docs/sample-data/micro-id-00000001.svg) - Low density ID Code
- [micro-id-00333333.svg](/home/warrisr/lmb/docs/sample-data/micro-id-00333333.svg) - Medium density ID Code
- [micro-id-01048575.svg](/home/warrisr/lmb/docs/sample-data/micro-id-01048575.svg) - High density ID Code

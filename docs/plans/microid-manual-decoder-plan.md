# Human-in-the-Loop Micro-ID Decoder

**Goal:** Create a mobile-friendly web tool that lets users decode Micro-ID codes by manually matching a 5x5 dot grid, with real-time validation and module information lookup.

**Why:** AI vision models proved unreliable (~70-80% accuracy even on synthetic images). Human visual identification + code-based conversion achieves near 100% accuracy.

---

## User Flow

```
1. User takes photo of LED module with smartphone
2. User opens /decode page
3. User uploads or takes photo directly
4. User crops/zooms to Micro-ID area using touch gestures
5. User optionally rotates to orient correctly
6. Side-by-side view: zoomed photo | empty 5x5 grid
7. User taps grid cells to match visible dots
8. Real-time feedback: parity check validates entry
9. When valid code detected → show "View Module Info" button
10. Click button → redirect to module details page
```

---

## UI Mockup

```
┌─────────────────────────────────────────────────────────────┐
│  MICRO-ID DECODER                                    [Help] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────┐  ┌─────────────────────┐          │
│  │                     │  │ ● │   │   │   │ ●  │          │
│  │   [Cropped Photo]   │  ├───┼───┼───┼───┼───┤          │
│  │                     │  │   │   │   │   │    │ Tap to   │
│  │      ● · · · ●      │  ├───┼───┼───┼───┼───┤ toggle   │
│  │      · · · · ·      │  │   │   │   │   │ ●  │ dots     │
│  │      · · · · ●      │  ├───┼───┼───┼───┼───┤          │
│  │      ● · · ● ·      │  │ ● │   │   │ ● │    │          │
│  │      ● ● ● ● ●      │  ├───┼───┼───┼───┼───┤          │
│  │                     │  │ ● │ ● │ ● │ ● │ ●  │          │
│  └─────────────────────┘  └─────────────────────┘          │
│                                                             │
│  [Rotate ↻]  [Zoom +]  [Zoom -]     [Clear Grid]           │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  ✓ Valid Code Detected!                                    │
│  Serial: 00000203                                          │
│                                                             │
│  [ 🔍 View Module Information ]                            │
└─────────────────────────────────────────────────────────────┘
```

---

## Technical Implementation

### Phase 1: Frontend Handler Class

**New File:** `includes/Frontend/class-microid-manual-decoder-handler.php`

Following the existing `class-microid-landing-handler.php` pattern:
- Register rewrite rule for `/decode` URL
- Render complete HTML/CSS/JS inline (no external files)
- Use vanilla JavaScript (no jQuery or React)
- Mobile-first responsive design

**URL:** `https://luxeonstar.com/decode`

### Phase 2: Core Features

#### 2.1 Image Upload & Camera Capture

```html
<input type="file" accept="image/*" capture="environment">
```

Features:
- Direct camera capture on mobile
- File picker fallback on desktop
- Drag-and-drop zone
- Image preview

#### 2.2 Image Manipulation (Cropper.js)

**Library:** Cropper.js v1.6.1 (CDN)
- ~30KB minified, well-maintained, touch-friendly
- Provides: crop box, pinch-to-zoom, drag to pan, rotation

```javascript
const cropper = new Cropper(imageElement, {
    aspectRatio: 1,           // Square crop for grid
    viewMode: 1,              // Restrict to canvas
    dragMode: 'move',         // Pan image
    autoCropArea: 0.8,        // Initial crop size
    responsive: true,
    zoomable: true,
    rotatable: true,
    minCropBoxWidth: 100,
    minCropBoxHeight: 100,
});
```

Controls:
- Rotate left/right (90°) buttons
- Zoom in/out buttons
- Reset button
- Touch gestures work automatically

#### 2.3 Interactive 5x5 Grid

Pure CSS Grid + JavaScript:

```css
.decode-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 2px;
    aspect-ratio: 1;
    max-width: 300px;
}

.grid-cell {
    aspect-ratio: 1;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.15s;
}

.grid-cell.has-dot {
    background-color: #CD7F32;  /* Copper/bronze color */
}

.grid-cell.anchor {
    background-color: #CD7F32;
    pointer-events: none;       /* Cannot toggle */
    opacity: 0.7;
}
```

**Corner Anchors Pre-filled:**
- Cells (0,0), (0,4), (4,0), (4,4) always have dots
- Visually distinct (dimmed)
- Cannot be toggled (helps user orient)

#### 2.4 Real-Time Validation (JavaScript)

```javascript
function validateGrid(grid) {
    // grid is 25-char string of 0s and 1s

    // Check anchors
    if (grid[0] !== '1' || grid[4] !== '1' ||
        grid[20] !== '1' || grid[24] !== '1') {
        return { valid: false, error: 'anchors' };
    }

    // Extract 20 data bits + parity
    const bits = extractDataBits(grid);  // Returns array of 20 bits
    const parityBit = parseInt(grid[23]); // Position (4,3)

    // Count ones in data bits
    const dataOnes = bits.reduce((sum, b) => sum + b, 0);

    // Even parity check
    const parityValid = (dataOnes + parityBit) % 2 === 0;

    if (!parityValid) {
        return { valid: false, error: 'parity', dataOnes, parityBit };
    }

    // Convert to serial
    const binary = bits.join('');
    const serial = parseInt(binary, 2);
    const serialFormatted = serial.toString().padStart(8, '0');

    return {
        valid: true,
        serial: serial,
        serialFormatted: serialFormatted,
        binary: binary
    };
}
```

**Bit Position Mapping:**

```
Grid Layout:
Row 0: [ANCHOR] [Bit19] [Bit18] [Bit17] [ANCHOR]
Row 1: [Bit16]  [Bit15] [Bit14] [Bit13] [Bit12]
Row 2: [Bit11]  [Bit10] [Bit9]  [Bit8]  [Bit7]
Row 3: [Bit6]   [Bit5]  [Bit4]  [Bit3]  [Bit2]
Row 4: [ANCHOR] [Bit1]  [Bit0]  [PARITY][ANCHOR]
```

#### 2.5 Status Display

Three states:

```
1. Initial:     "Tap cells to match the dots you see"
2. Invalid:     "⚠ Parity check failed - please verify your entry"
3. Valid:       "✓ Valid code detected! Serial: 00000203"
                [View Module Information] button
```

Color coding:
- Neutral: gray background
- Invalid: yellow/warning
- Valid: green/success

#### 2.6 Module Lookup

When user clicks "View Module Information":

```javascript
// Redirect to existing QSA landing page pattern
window.location.href = `/id?serial=${serialFormatted}`;

// Or use existing Serial_Repository lookup via AJAX
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: new FormData({
        action: 'qsa_serial_lookup',
        serial: serialFormatted,
        nonce: qsaNonce
    })
});
```

**Option A:** Redirect to existing `/id` page with serial parameter
**Option B:** Display inline with AJAX lookup

Recommend Option A - simpler, reuses existing UI.

---

## Files to Create/Modify

| Action | File | Purpose |
|--------|------|---------|
| CREATE | `includes/Frontend/class-microid-manual-decoder-handler.php` | Main handler with HTML/CSS/JS |
| MODIFY | `qsa-engraving.php` | Register handler, add rewrite rules |
| MODIFY | `includes/Frontend/class-microid-landing-handler.php` | Add serial query param support |

---

## Implementation Details

### Handler Class Structure

```php
namespace Quadica\QSA_Engraving\Frontend;

class Microid_Manual_Decoder_Handler {

    public function __construct() {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'handle_request']);
    }

    public function register_rewrite_rules(): void {
        add_rewrite_rule(
            '^decode/?$',
            'index.php?qsa_manual_decode=1',
            'top'
        );
    }

    public function register_query_vars(array $vars): array {
        $vars[] = 'qsa_manual_decode';
        return $vars;
    }

    public function handle_request(): void {
        if (!get_query_var('qsa_manual_decode')) {
            return;
        }

        $this->render_page();
        exit;
    }

    private function render_page(): void {
        // Output complete HTML page with inline CSS/JS
        ?>
        <!DOCTYPE html>
        <html>
        <head>...</head>
        <body>...</body>
        </html>
        <?php
    }
}
```

### Cropper.js Integration

Load from CDN (no npm/webpack needed):

```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
```

### Mobile Considerations

- Touch-friendly button sizes (min 44px)
- Large grid cells for fat-finger tapping
- Responsive layout (stack on narrow screens)
- Viewport meta tag for proper scaling
- Prevent zoom on double-tap (CSS touch-action)

---

## Validation & Testing

### Manual Test Cases

| Test | Steps | Expected |
|------|-------|----------|
| TC-DEC-001 | Load /decode on mobile | Page loads, camera prompt available |
| TC-DEC-002 | Upload image, crop to Micro-ID | Cropper works with touch gestures |
| TC-DEC-003 | Tap all 4 corners (pre-filled) | Cannot toggle anchor cells |
| TC-DEC-004 | Enter valid grid for serial 203 | Shows "Valid code" + correct serial |
| TC-DEC-005 | Enter invalid grid (parity fail) | Shows parity error message |
| TC-DEC-006 | Click "View Module Info" | Redirects to /id?serial=00000203 |
| TC-DEC-007 | Test on iPhone Safari | Full functionality works |
| TC-DEC-008 | Test on Android Chrome | Full functionality works |

### Known Serial Numbers for Testing

From existing sample data:
- Serial 203: Grid `1000100000000011001011111`
- Serial 207: Grid `1000100000000011001111111`

---

## Estimated Scope

| Component | Complexity | Lines (est) |
|-----------|------------|-------------|
| PHP handler class | Low | ~100 |
| HTML structure | Low | ~80 |
| CSS (inline) | Medium | ~200 |
| JavaScript (inline) | Medium | ~300 |
| **Total** | **Medium** | **~680** |

Single file implementation following existing patterns.

---

## Future Enhancements (Out of Scope)

- Grid overlay on cropped image (automatic alignment)
- History of decoded serials (local storage)
- Share decoded result
- Batch decode mode
- PWA for offline use

---

## Verification Steps

1. **Deploy to staging:** Git push triggers deployment
2. **Load /decode URL:** Verify page renders
3. **Test on mobile:** Camera capture + touch gestures
4. **Decode known serial:** Enter grid for serial 203
5. **Verify redirect:** Click "View Module Info" → /id page loads
6. **Test invalid entry:** Confirm parity error displays

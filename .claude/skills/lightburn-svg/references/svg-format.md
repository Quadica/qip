# SVG Format Specification for Lightburn

## Document Structure

### XML Declaration and Root Element
```xml
<?xml version="1.0" encoding="utf-8" ?>
<svg baseProfile="full" 
     height="210mm" 
     version="1.1" 
     viewBox="0 0 210 210" 
     width="210mm" 
     xmlns="http://www.w3.org/2000/svg" 
     xmlns:ev="http://www.w3.org/2001/xml-events" 
     xmlns:xlink="http://www.w3.org/1999/xlink">
  <defs />
  <!-- Content here -->
</svg>
```

**Critical points:**
- `width` and `height` use `mm` suffix
- `viewBox` uses unitless values (implicit mm)
- All coordinate values in content are unitless (interpreted as mm)

## Text Rendering

### Font Configuration
| Property | Value |
|----------|-------|
| font-family | `Roboto Thin, sans-serif` |
| font-weight | `normal` |
| font-style | `normal` |
| text-anchor | `middle` (centers text on x,y coordinates) |

### Character Spacing
Insert Unicode U+200A (hair space) between each character to improve engraving clarity at small sizes:
```php
$spaced_text = implode("\u{200A}", str_split($text));
// "W4" becomes "W W4" (with hair spaces)
```

### Font Size Calculation
The specified `height` is the desired engraved character height in mm. Compensate for font metrics:
```php
$font_size = $height * (0.7 / 0.498);  // ≈ 1.4056
```
This ratio accounts for whitespace above/below characters in the font.

### Text Element Example
```xml
<text font-family="Roboto Thin, sans-serif" 
      font-size="1.5461847389558234" 
      font-style="normal" 
      font-weight="normal" 
      text-anchor="middle" 
      transform="rotate(-270.0 72.7007 77.8698)" 
      x="72.7007" 
      y="77.8698">W 4</text>
```

### Rotation Transform
Text rotation is applied via the `transform` attribute:
```
transform="rotate(angle cx cy)"
```
Where:
- `angle` is in degrees, negative for clockwise
- `cx, cy` is the rotation center (same as text position)

**Angle adjustment:** Add 180° to flip text for proper orientation, then negate:
```php
$adjusted_angle = ($original_angle + 180) % 360;
$transform = "rotate({-$adjusted_angle} {$x} {$y})";
```

## Alignment Marks

### Center Crosshair
Red cross at canvas center for alignment:
```xml
<line stroke="red" stroke-width="0.2" x1="103.0" x2="107.0" y1="105.0" y2="105.0" />
<line stroke="red" stroke-width="0.2" x1="105.0" x2="105.0" y1="103.0" y2="107.0" />
```
Cross size: 4mm (±2mm from center)

### Boundary Rectangle
Optional framing rectangle (set to non-engraving layer in Lightburn):
```xml
<rect fill="none" height="205" stroke="#FF0000" stroke-width="0.5" width="205" x="2.5" y="2.5" />
```

## Coordinate System

### Origin and Axes
- Origin (0,0) is top-left of canvas
- X increases rightward
- Y increases downward
- All values in mm

### Centering Content
To center PCB content on the 210×210mm canvas:
```php
// PCB has its own center point (in PCB coordinates)
$pcb_center_x = $pcb_data['CenterPoint']['x'];
$pcb_center_y = $pcb_data['CenterPoint']['y'];

// Offset to move PCB center to canvas center (105, 105)
$x_offset = 105 - $pcb_center_x;
$y_offset = 105 - $pcb_center_y;

// Transform any PCB coordinate to canvas coordinate
function to_canvas($x, $y, $x_offset, $y_offset, $pcb_x_offset = 0, $pcb_y_offset = 0) {
    $new_x = $x + $x_offset + $pcb_x_offset;
    $new_y = $y + $y_offset + $pcb_y_offset;
    // Clamp to canvas bounds
    return [
        max(0, min(210, $new_x)),
        max(0, min(210, $new_y))
    ];
}
```

### PCB-Specific Calibration Offsets

Each PCB type may require fine-tuning offsets to compensate for mechanical alignment differences in the laser fixture. These calibration offsets are stored externally (e.g., database or configuration file) and applied during coordinate transforms.

```php
// Load PCB-specific offsets from configuration
$pcb_offsets = get_pcb_calibration_offsets($pcb_type);
// Returns: ['x_offset' => 0.5, 'y_offset' => -0.25]

// Apply during coordinate transform
function transform_coords($x, $y, $center_x, $center_y, $pcb_offsets) {
    $canvas_offset_x = 105 - $center_x;
    $canvas_offset_y = 105 - $center_y;

    // Add PCB-specific calibration offsets
    $pcb_x_offset = $pcb_offsets['x_offset'] ?? 0;
    $pcb_y_offset = $pcb_offsets['y_offset'] ?? 0;

    return [
        max(0, min(210, $x + $canvas_offset_x + $pcb_x_offset)),
        max(0, min(210, $y + $canvas_offset_y + $pcb_y_offset))
    ];
}
```

**Note:** In the current production system, these offsets are stored in a Google Sheets document and fetched at runtime. For the QOM system, consider storing these in a WordPress options table or custom database table for better integration.

## Shapes for Non-Text Content

### Circles (for Micro-ID dots)
```xml
<circle cx="10.5" cy="10.5" r="0.05" fill="#000000" />
```
- `cx, cy`: center coordinates in mm
- `r`: radius in mm
- `fill`: solid fill color (no stroke for dots)

### Rectangles
```xml
<rect x="10" y="10" width="5" height="3" fill="none" stroke="#000000" stroke-width="0.1" />
```

### Paths (for complex shapes, QR codes)
```xml
<path d="M10 10 L15 10 L15 15 L10 15 Z" fill="#000000" />
```
Use paths for QR code modules—more efficient than individual rectangles.

## Layer Management via Color

Lightburn assigns shapes to layers based on stroke/fill color:
| Color | Typical Use |
|-------|-------------|
| `#000000` | Primary engraving content |
| `#FF0000` | Alignment marks (set as Tool layer, not engraved) |
| `#0000FF` | Secondary layer if needed |

**Important:** Configure layer settings in Lightburn after import. The SVG only determines layer assignment, not engraving parameters (power, speed, frequency).

## PHP Helper Functions

### Complete Text Renderer
```php
function svg_text(string $text, float $x, float $y, float $height, float $angle = 0): string {
    $half_space = "\u{200A}";
    $spaced = implode($half_space, str_split($text));
    $adjusted_angle = ($angle + 180) % 360;
    $font_size = $height * (0.7 / 0.498);
    
    return sprintf(
        '<text font-family="Roboto Thin, sans-serif" font-size="%s" ' .
        'font-style="normal" font-weight="normal" text-anchor="middle" ' .
        'transform="rotate(%s %s %s)" x="%s" y="%s">%s</text>',
        $font_size,
        -$adjusted_angle,
        $x, $y,
        $x, $y,
        htmlspecialchars($spaced, ENT_XML1)
    );
}
```

### SVG Document Builder
```php
function create_svg_document(array $elements, float $center_x = 105, float $center_y = 105): string {
    $svg = '<?xml version="1.0" encoding="utf-8" ?>' . "\n";
    $svg .= '<svg baseProfile="full" height="210mm" version="1.1" ';
    $svg .= 'viewBox="0 0 210 210" width="210mm" ';
    $svg .= 'xmlns="http://www.w3.org/2000/svg" ';
    $svg .= 'xmlns:ev="http://www.w3.org/2001/xml-events" ';
    $svg .= 'xmlns:xlink="http://www.w3.org/1999/xlink">';
    $svg .= '<defs />';
    
    // Boundary rectangle
    $svg .= '<rect fill="none" height="205" stroke="#FF0000" stroke-width="0.5" width="205" x="2.5" y="2.5" />';
    
    // Center crosshair
    $cross_size = 2;
    $svg .= sprintf('<line stroke="red" stroke-width="0.2" x1="%s" x2="%s" y1="%s" y2="%s" />',
        $center_x - $cross_size, $center_x + $cross_size, $center_y, $center_y);
    $svg .= sprintf('<line stroke="red" stroke-width="0.2" x1="%s" x2="%s" y1="%s" y2="%s" />',
        $center_x, $center_x, $center_y - $cross_size, $center_y + $cross_size);
    
    // Add all content elements
    foreach ($elements as $element) {
        $svg .= $element;
    }
    
    $svg .= '</svg>';
    return $svg;
}
```

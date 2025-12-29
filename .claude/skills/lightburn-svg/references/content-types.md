# Content Types for Laser Engraving

## Implementation Status

| Content Type | Current Production | QOM System |
|--------------|-------------------|------------|
| Text Labels (LED/Lens/Connector codes) | ✅ Implemented | ✅ Planned |
| Micro-ID 5×5 Dot Matrix | ❌ Not implemented | ✅ Planned |
| QR Codes | ❌ Not implemented | 🔄 Future |
| Barcodes | ❌ Not implemented | 🔄 Future |

**Note:** The current Python production system only handles text labels. Micro-ID, QR codes, and barcodes are documented here for the planned QOM WordPress integration.

---

## Text Labels

See [svg-format.md](svg-format.md) for text rendering details. Key points:
- Use `Roboto Thin` font
- Apply hair-space character spacing
- Adjust font size by 1.4056× ratio

## QR Codes

### Recommended Library
Use `endroid/qr-code` for PHP QR generation:
```bash
composer require endroid/qr-code
```

### SVG Generation Pattern
```php
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

function generate_qr_svg(string $data, float $size_mm): string {
    $result = Builder::create()
        ->writer(new SvgWriter())
        ->data($data)
        ->encoding(new Encoding('UTF-8'))
        ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
        ->size((int)($size_mm * 10))  // Library uses arbitrary units
        ->margin(0)
        ->build();
    
    return $result->getString();
}
```

### Embedding in Main SVG
Extract the path data from the QR SVG and position it:
```php
function embed_qr(string $qr_svg, float $x, float $y, float $size_mm): string {
    // Parse QR SVG to extract path
    $dom = new DOMDocument();
    $dom->loadXML($qr_svg);
    $paths = $dom->getElementsByTagName('path');
    
    // Get the QR viewBox to calculate scale
    $svg_elem = $dom->getElementsByTagName('svg')->item(0);
    $viewBox = explode(' ', $svg_elem->getAttribute('viewBox'));
    $qr_size = floatval($viewBox[2]);
    $scale = $size_mm / $qr_size;
    
    $output = sprintf('<g transform="translate(%s %s) scale(%s)">', $x, $y, $scale);
    foreach ($paths as $path) {
        $d = $path->getAttribute('d');
        $fill = $path->getAttribute('fill') ?: '#000000';
        $output .= sprintf('<path d="%s" fill="%s" />', $d, $fill);
    }
    $output .= '</g>';
    
    return $output;
}
```

### QR Size Guidelines
| Content Length | Minimum Size | Recommended Size |
|---------------|--------------|------------------|
| ≤20 chars | 3mm | 5mm |
| 21-50 chars | 5mm | 8mm |
| 51-100 chars | 8mm | 10mm |

Use Error Correction Level M (Medium) for balance of density and reliability.

## Barcodes

### Code 128 (Recommended for alphanumeric)
Use `picqer/php-barcode-generator`:
```bash
composer require picqer/php-barcode-generator
```

```php
use Picqer\Barcode\BarcodeGeneratorSVG;

function generate_barcode_svg(string $data, float $height_mm, float $width_factor = 1.0): string {
    $generator = new BarcodeGeneratorSVG();
    $barcode = $generator->getBarcode($data, $generator::TYPE_CODE_128);
    return $barcode;
}
```

### Barcode Size Guidelines
- Minimum bar width: 0.15mm for reliable scanning
- Height: typically 3-5mm for small labels
- Quiet zone: 2mm minimum on each side

## Micro-ID 5×5 Dot Matrix Code

### Overview
Proprietary 2D code for unique LED module identification. Encodes 20-bit integer (0–1,048,575) with parity check.

### Physical Specifications
| Parameter | Value |
|-----------|-------|
| Matrix size | 5×5 dots |
| Total footprint | 1.0×1.0mm |
| Dot diameter | 0.10mm |
| Dot pitch | 0.225mm (center-to-center) |
| Quiet zone | 0.25mm minimum |

### Grid Layout
```
Position: (row, col) from (0,0) at top-left

     Col 0   Col 1   Col 2   Col 3   Col 4
Row 0  [A]    B19     B18     B17     [A]
Row 1  B16    B15     B14     B13     B12
Row 2  B11    B10     B9      B8      B7
Row 3  B6     B5      B4      B3      B2
Row 4  [A]    B1      B0      [P]     [A]

[A] = Anchor (always ON)
[P] = Parity bit
B0-B19 = Data bits (B19 is MSB)
```

### Coordinate Calculation
Dot center positions relative to grid origin (top-left of 1.0mm area):
```php
function dot_center(int $row, int $col): array {
    return [
        0.05 + ($col * 0.225),  // X
        0.05 + ($row * 0.225)   // Y
    ];
}
```

When centering in 1.5mm available space, add +0.25mm offset to all coordinates.

### Orientation Marker
Fixed dot in quiet zone for rotation detection:
- Position: X = -0.175mm, Y = 0.05mm (relative to grid origin)
- Always ON, not part of data

### Encoding Algorithm
```php
function encode_micro_id(int $id): array {
    if ($id < 0 || $id > 1048575) {
        throw new InvalidArgumentException("ID must be 0-1048575");
    }
    
    // Convert to 20-bit binary
    $bits = str_pad(decbin($id), 20, '0', STR_PAD_LEFT);
    
    // Calculate parity (even parity)
    $ones = substr_count($bits, '1');
    $parity = ($ones % 2 == 0) ? 0 : 1;
    
    // Build 5x5 grid
    $grid = [];
    $bit_positions = [
        [null, 19, 18, 17, null],  // Row 0: anchors at corners
        [16, 15, 14, 13, 12],
        [11, 10, 9, 8, 7],
        [6, 5, 4, 3, 2],
        [null, 1, 0, 'P', null]    // Row 4: anchors, parity
    ];
    
    for ($row = 0; $row < 5; $row++) {
        $grid[$row] = [];
        for ($col = 0; $col < 5; $col++) {
            $pos = $bit_positions[$row][$col];
            if ($pos === null) {
                // Anchor - always ON
                $grid[$row][$col] = 1;
            } elseif ($pos === 'P') {
                // Parity bit
                $grid[$row][$col] = $parity;
            } else {
                // Data bit
                $grid[$row][$col] = (int)$bits[19 - $pos];
            }
        }
    }
    
    return $grid;
}
```

### SVG Rendering
```php
function render_micro_id(int $id, float $center_x, float $center_y): string {
    $grid = encode_micro_id($id);
    $dot_radius = 0.05;  // 0.10mm diameter
    $pitch = 0.225;
    $grid_size = 1.0;
    
    // Offset to center the 1.0mm grid at the specified position
    $origin_x = $center_x - ($grid_size / 2);
    $origin_y = $center_y - ($grid_size / 2);
    
    $svg = '';
    
    // Render orientation marker (in quiet zone)
    $orient_x = $origin_x - 0.175;
    $orient_y = $origin_y + 0.05;
    $svg .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="#000000" />', 
        $orient_x, $orient_y, $dot_radius);
    
    // Render grid dots
    for ($row = 0; $row < 5; $row++) {
        for ($col = 0; $col < 5; $col++) {
            if ($grid[$row][$col] === 1) {
                $cx = $origin_x + 0.05 + ($col * $pitch);
                $cy = $origin_y + 0.05 + ($row * $pitch);
                $svg .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="#000000" />', 
                    $cx, $cy, $dot_radius);
            }
        }
    }
    
    return $svg;
}
```

### ID Formatting
Although encoded as integer, display as 8-digit zero-padded string:
```php
$display_id = str_pad($id, 8, '0', STR_PAD_LEFT);  // 1 → "00000001"
```

### Visual Example: ID 600001 (0x927C1)
```
Binary: 10010010011111000001
Parity: 0 (8 ones, even)

● ● ○ ○ ●
● ○ ○ ○ ○
● ○ ○ ● ○
○ ● ● ● ●
● ○ ○ ○ ●

● = Laser ON (marked)
○ = Laser OFF (unmarked)
```

## Combined Content Example

Typical LED module marking includes:
1. LED bin code (text, e.g., "W4")
2. Micro-ID (dot matrix)
3. Optional QR code linking to documentation

```php
function render_module_marking(array $module_data): string {
    $elements = [];
    
    // LED code text
    $elements[] = svg_text(
        $module_data['led_code'],
        $module_data['led_x'],
        $module_data['led_y'],
        $module_data['text_height'],
        $module_data['rotation']
    );
    
    // Micro-ID
    if (isset($module_data['micro_id'])) {
        $elements[] = render_micro_id(
            $module_data['micro_id'],
            $module_data['id_x'],
            $module_data['id_y']
        );
    }
    
    return implode('', $elements);
}
```

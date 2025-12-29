# Laser Engraving SVG Generator Plugin

**Document:** Implementation Plan
**Date:** November 30, 2025
**Status:** Initial Draft

## Overview

Create a minimal WordPress plugin that generates SVG files for UV laser engraving on LED module carrier arrays. The plugin will output Lightburn-compatible SVG files containing text (as path outlines), Quadica 5x5 Micro-ID codes, and Data Matrix ECC 200 barcodes positioned at specified X/Y coordinates.

**Target:** Standard Array Configuration (148mm x 113.7mm with 10 modules)

## Technical Requirements

| Requirement | Specification |
|-------------|---------------|
| Input format | JSON with element positions and content |
| Output format | SVG with mm units, Lightburn compatible |
| Text rendering | Path outlines (no font dependencies) |
| Character set | A-Z, 0-9, . - / : (basic set) |
| Micro-ID | Quadica 5x5 dot matrix per `/docs/reference/quadica-micro-id-specs.md` |
| Data Matrix | ECC 200 via `tecnickcom/tc-lib-barcode` library |
| Platform | WordPress plugin (PHP 8.1+) |

## Plugin Structure

```
wp-content/plugins/quadica-svg-engraver/
├── quadica-svg-engraver.php           # Main plugin file with autoloader
├── includes/
│   ├── class-svg-generator.php        # Main orchestrator
│   ├── class-micro-id-encoder.php     # 5x5 Micro-ID encoding
│   ├── class-data-matrix.php          # Data Matrix wrapper
│   ├── class-stroke-font.php          # Text-to-path renderer
│   └── class-json-processor.php       # JSON validation
├── assets/
│   └── stroke-font-data.php           # Character path definitions
├── composer.json
└── tests/
    └── test-micro-id.php              # Encoding tests
```

## JSON Input Schema

```json
{
  "array": {
    "width_mm": 148,
    "height_mm": 113.7
  },
  "modules": [
    {
      "position": 1,
      "serial_number": "00123456",
      "elements": [
        {
          "type": "micro_id",
          "x_mm": 5.2,
          "y_mm": 8.1,
          "value": "00123456"
        },
        {
          "type": "data_matrix",
          "x_mm": 12.5,
          "y_mm": 8.0,
          "size_mm": 3.0,
          "value": "QUADI.ca/23546764"
        },
        {
          "type": "text",
          "x_mm": 2.0,
          "y_mm": 3.0,
          "value": "QUADI.CA",
          "font_size_mm": 1.2,
          "anchor": "start"
        }
      ]
    }
  ],
  "global_elements": []
}
```

### Element Types

| Type | Description | Required Fields |
|------|-------------|-----------------|
| `micro_id` | Quadica 5x5 Micro-ID | x_mm, y_mm, value (8-digit string) |
| `data_matrix` | Data Matrix ECC 200 | x_mm, y_mm, value, size_mm |
| `text` | Text rendered as paths | x_mm, y_mm, value, font_size_mm, anchor |

## Implementation Phases

### Phase 1: Plugin Foundation
- Create plugin scaffold with PSR-4 autoloader
- Set up Composer with `tecnickcom/tc-lib-barcode ^2.3`
- Implement `JSON_Processor` class with validation:
  - Depth limiting (max 10 levels)
  - Size limiting (max 1MB)
  - Required field validation
  - Coordinate bounds checking

### Phase 2: Micro-ID Encoder
Port the encoding algorithm from `/docs/reference/quadica-micro-id-specs.md`:
- 20-bit binary conversion for IDs 0-1,048,575
- Even parity calculation
- 5x5 grid with 4 corner anchors (always ON)
- Orientation marker dot at (-0.175mm, 0.05mm)
- Output as SVG `<g>` with circles:
  - Dot diameter: 0.10mm
  - Dot pitch: 0.225mm center-to-center
  - Grid size: 1.0mm x 1.0mm

### Phase 3: Stroke Font Renderer
- Define basic character paths (A-Z, 0-9, .-/:)
- Each character stored as SVG path data with width
- Scale paths to requested font_size_mm
- Support anchor positions: start, middle, end
- Output as SVG `<g>` with `<path>` elements
- Stroke width proportional to font size

### Phase 4: Data Matrix Integration
- Wrap `tecnickcom/tc-lib-barcode` for Data Matrix ECC 200
- Extract SVG output from library
- Scale to requested size_mm
- Output as SVG `<g>` element

### Phase 5: SVG Generator
- Create base SVG with mm viewBox: `viewBox="0 0 148 113.7"`
- Set dimensions: `width="148mm" height="113.7mm"`
- Position elements at X/Y coordinates using `transform="translate(x,y)"`
- Generate final Lightburn-compatible SVG with inline styles

### Phase 6: Testing
- Unit tests for Micro-ID encoding:
  - ID 1 (minimum with data)
  - ID 333333 (medium density)
  - ID 1048575 (maximum)
  - Parity calculation verification
- JSON validation tests
- Manual Lightburn import verification

## Key Classes

### SVG_Generator (main orchestrator)
```php
class SVG_Generator {
    public function generate(string $json): string|WP_Error;
    public function save(string $svg, string $filename): string|WP_Error;
    private function create_base_svg(float $width, float $height): DOMDocument;
    private function add_element(DOMDocument $doc, array $element): void;
}
```

### Micro_ID_Encoder
```php
class Micro_ID_Encoder {
    const DOT_DIAMETER = 0.10;
    const DOT_PITCH = 0.225;
    const GRID_SIZE = 1.0;

    public function encode(int $id): array;        // Returns bit array
    public function render_svg(int $id): string;   // Returns <g> element
    private function calculate_parity(array $bits): int;
    private function map_to_grid(array $bits): array;
    private function get_dot_coordinates(): array;
}
```

### Stroke_Font
```php
class Stroke_Font {
    public function render_text(string $text, float $size_mm, string $anchor = 'start'): string;
    public function get_text_width(string $text, float $size_mm): float;
    private function get_character_path(string $char): array;
    private function scale_path(string $d, float $scale): string;
}
```

### Data_Matrix
```php
class Data_Matrix {
    public function render_svg(string $data, float $size_mm): string;
    private function generate_barcode(string $data): object;
    private function extract_svg(object $barcode): string;
    private function scale_to_size(string $svg, float $target_size): string;
}
```

### JSON_Processor
```php
class JSON_Processor {
    const MAX_SIZE = 1048576;  // 1MB
    const MAX_DEPTH = 10;

    public function parse(string $json): array|WP_Error;
    public function validate(array $data): true|WP_Error;
    private function validate_element(array $element): true|WP_Error;
    private function check_bounds(float $x, float $y, array $bounds): bool;
}
```

## SVG Output Format

```xml
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"
     width="148mm" height="113.7mm"
     viewBox="0 0 148 113.7">

  <!-- Module 1 -->
  <g id="module-1">

    <!-- Micro-ID at (5.2, 8.1) -->
    <g id="micro-id-1" transform="translate(5.2,8.1)">
      <!-- Orientation marker -->
      <circle cx="-0.175" cy="0.05" r="0.05" fill="black"/>
      <!-- Corner anchors -->
      <circle cx="0.05" cy="0.05" r="0.05" fill="black"/>
      <circle cx="0.95" cy="0.05" r="0.05" fill="black"/>
      <circle cx="0.05" cy="0.95" r="0.05" fill="black"/>
      <circle cx="0.95" cy="0.95" r="0.05" fill="black"/>
      <!-- Data dots... -->
    </g>

    <!-- Data Matrix at (12.5, 8.0) -->
    <g id="datamatrix-1" transform="translate(12.5,8.0)">
      <rect x="0" y="0" width="0.15" height="0.15" fill="black"/>
      <!-- ... more modules -->
    </g>

    <!-- Text at (2.0, 3.0) -->
    <g id="text-1" transform="translate(2.0,3.0)">
      <path d="M0 1.2 L0.6 0 L1.2 1.2 M0.2 0.8 L1.0 0.8"
            fill="none" stroke="black" stroke-width="0.06"/>
      <!-- ... more characters -->
    </g>

  </g>

  <!-- Additional modules... -->

</svg>
```

## Usage (Initial - No Admin UI)

```php
// Via PHP code
$generator = new Quadica_SVG_Engraver\SVG_Generator();
$json = file_get_contents('batch-data.json');
$result = $generator->generate($json);

if (is_wp_error($result)) {
    error_log($result->get_error_message());
} else {
    $generator->save($result, 'batch-001.svg');
}
```

```bash
# Via WP-CLI (future enhancement)
wp svg-engraver generate batch-data.json --output=batch-001.svg
```

## Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `tecnickcom/tc-lib-barcode` | ^2.3 | Data Matrix ECC 200 generation |
| WordPress | 6.8+ | Core platform |
| PHP | 8.1+ | Runtime (matches existing stack) |

### Composer Configuration

```json
{
    "name": "quadica/svg-engraver",
    "description": "SVG generator for laser engraving LED module arrays",
    "require": {
        "php": ">=8.1",
        "tecnickcom/tc-lib-barcode": "^2.3"
    },
    "autoload": {
        "psr-4": {
            "Quadica_SVG_Engraver\\": "includes/"
        }
    }
}
```

## Critical Reference Files

| File | Purpose |
|------|---------|
| `/docs/reference/quadica-micro-id-specs.md` | Full Micro-ID encoding specification |
| `/docs/reference/quadica-standard-array.jpg` | Visual layout reference |
| `/docs/sample-data/micro-id-00333333.svg` | Reference SVG format |
| `/wp-content/plugins/led-module-builder/includes/class-lmb-svg-manager.php` | SVG handling patterns |

## Security Considerations

- JSON depth limiting (max 10 levels) to prevent stack overflow
- JSON size limiting (max 1MB) to prevent memory exhaustion
- Coordinate bounds validation (elements must fit within array dimensions)
- Filename sanitization for output files
- No user-supplied code execution

## Future Enhancements (Not in Initial Scope)

- Admin UI for JSON upload/preview/download
- Extended character set (lowercase, additional punctuation)
- Batch processing of multiple arrays
- Integration with LMB/QPM production systems
- WP-CLI command interface
- SVG preview in WordPress admin

## Research Sources

- [tecnickcom/tc-lib-barcode on GitHub](https://github.com/tecnickcom/tc-lib-barcode) - Data Matrix library
- [Stack Overflow - ECC200 Datamatrix in PHP](https://stackoverflow.com/questions/36575896/ecc200-datamatrix-generation-in-php) - Implementation guidance

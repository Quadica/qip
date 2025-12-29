---
name: lightburn-svg
description: Generate SVG files for UV laser engraving on aluminum LED modules using Lightburn 1.7 software. Use when writing PHP/WordPress code that creates SVG files for laser marking text, QR codes, barcodes, or Micro-ID dot matrix codes. Covers SVG format requirements, coordinate systems, text rendering, and Lightburn UDP integration.
---

# Lightburn SVG Generation Skill

Generate laser-engraving-ready SVG files for the Cloudray UV-5 laser controlled by Lightburn 1.7.

## System Context

### Current Production System
The existing production system (Python-based) handles:
- **Text codes only:** LED codes (2-char like "W4"), lens codes (3-char "L##"), connector codes (3-char "C##")
- **Data source:** CSV files from legacy order management system
- **PCB offsets:** Loaded from Google Sheets at runtime

### Planned QOM System (WordPress/WooCommerce)
The new WordPress-integrated system will add:
- **Micro-ID 5×5 dot matrix:** Unique part identification (see quadica-micro-id-specs.md)
- **QR codes:** For expanded data capacity (future)
- **Barcodes:** For compatibility with existing scanners (future)
- **Data source:** WooCommerce order data and custom database tables
- **PCB offsets:** Stored in WordPress database

This skill documents both the current production patterns and the planned QOM extensions.

## Quick Reference

### SVG Document Structure
```php
$svg = '<?xml version="1.0" encoding="utf-8" ?>';
$svg .= '<svg baseProfile="full" height="210mm" version="1.1" ';
$svg .= 'viewBox="0 0 210 210" width="210mm" ';
$svg .= 'xmlns="http://www.w3.org/2000/svg">';
$svg .= '<defs />';
// ... content ...
$svg .= '</svg>';
```

### Key Parameters
| Parameter | Value | Notes |
|-----------|-------|-------|
| Canvas size | 210×210mm | viewBox uses unitless values |
| Work area center | (105, 105) | For centering content |
| Font | `Roboto Thin, sans-serif` | Must be installed on engraving PC |
| Character spacing | Unicode U+200A (hair space) | Insert between each character |
| Font size ratio | `height × 1.4056` | Compensates for font metrics |

### Text Element Pattern
```php
function render_text($text, $x, $y, $height, $angle = 0) {
    $spaced = implode("\u{200A}", str_split($text));
    $adjusted_angle = ($angle + 180) % 360;
    $font_size = $height * (0.7 / 0.498);
    
    return sprintf(
        '<text font-family="Roboto Thin, sans-serif" font-size="%s" ' .
        'text-anchor="middle" transform="rotate(%s %s %s)" x="%s" y="%s">%s</text>',
        $font_size, -$adjusted_angle, $x, $y, $x, $y, htmlspecialchars($spaced)
    );
}
```

### Layer Colors
Lightburn maps stroke/fill colors to layers. Use consistent colors:
- `#000000` (black) - Primary engraving layer
- `#FF0000` (red) - Alignment/framing marks (typically set to "Tool" layer, not engraved)

## Detailed References

For complete specifications, consult these reference files:

- **[references/svg-format.md](references/svg-format.md)** - Complete SVG structure, text rendering, coordinate transforms, alignment marks
- **[references/content-types.md](references/content-types.md)** - QR codes, barcodes, Micro-ID overview
- **[references/quadica-micro-id-specs.md](references/quadica-micro-id-specs.md)** - Authoritative Micro-ID 5×5 specification (encoding, decoding, tolerances)
- **[references/lightburn-integration.md](references/lightburn-integration.md)** - UDP commands, file loading, batch processing workflow

### Sample Files

- **[samples/micro-id-00000001.svg](samples/micro-id-00000001.svg)** - Low density example (ID: 1)
- **[samples/micro-id-00333333.svg](samples/micro-id-00333333.svg)** - Medium density example (ID: 333,333)
- **[samples/micro-id-01048575.svg](samples/micro-id-01048575.svg)** - Maximum value example (ID: 1,048,575)

## PHP Implementation Notes

### File Output
Save SVG files with `.svg` extension to the configured output directory. Current production path:
```
Q:\Shared drives\Quadica\Production\Layout App Print Files\UV Laser Engrave Files
```

### Coordinate Transform Pattern
When placing content on a PCB panel, transform from PCB coordinates to SVG canvas:
```php
function transform_coords($x, $y, $center_x, $center_y, $x_offset = 0, $y_offset = 0) {
    $canvas_offset_x = 105 - $center_x + $x_offset;
    $canvas_offset_y = 105 - $center_y + $y_offset;
    return [
        max(0, min(210, $x + $canvas_offset_x)),
        max(0, min(210, $y + $canvas_offset_y))
    ];
}
```

### WordPress Integration Points
- SVG generation is user-initiated (not triggered by order status changes)
- Admin interface allows users to select modules for a production batch
- Store SVG file paths and job metadata in custom database tables
- Use WP filesystem API for writing files to shared drive locations

## Workflow Summary

1. User selects modules to build from production queue (admin interface)
2. Calculate coordinate transforms to center content on 210×210mm canvas
3. Generate SVG elements for each content type (text, QR, Micro-ID)
4. Add alignment marks (red crosshair at center, boundary rectangle)
5. Save SVG file with appropriate naming convention
6. Optionally trigger Lightburn via UDP to load file

## External References

For additional documentation and updates, consult these external resources:

### Software Documentation
- **[LightBurn Documentation](https://docs.lightburnsoftware.com/latest/)** - Official LightBurn user documentation
- **[LightBurn GitHub](https://github.com/LightBurnSoftware)** - LightBurn software repositories and issue tracking

### Hardware Documentation
- **[Cloudray UV Laser Software](https://www.cloudraylaser.com/pages/software-download)** - Cloudray UV laser documentation and software downloads

# Micro-ID Reference Images

This directory contains reference images used to improve Micro-ID decode accuracy with Claude Vision API.

## Image Types

### Location Markers
Annotated photos showing the exact location of the Micro-ID code on each module type.
- Use a visible box, circle, or arrow pointing to the Micro-ID location
- Include the full module in frame for context
- Name format: `{module-type}-location-marker.jpg`

### Sample Photos
Clear smartphone photos of modules with visible Micro-ID codes.
- Take photos similar to what customers would submit
- Good lighting, reasonable focus
- Name format: `{module-type}-sample-{n}.jpg`

## Current Module Types
- `sz04` - STAR module (SZ-04)
- Future: `cube`, `pico`, etc.

## Example Files
```
sz04-location-marker.jpg  - Annotated image showing Micro-ID location on SZ-04
sz04-sample-1.jpg         - Clear smartphone photo of SZ-04 module
sz04-sample-2.jpg         - Another smartphone photo example
```

## Requirements
- Format: JPEG, PNG, or WebP
- Max file size: 5 MB per image
- Recommended: Resize large images to ~1000px on longest edge

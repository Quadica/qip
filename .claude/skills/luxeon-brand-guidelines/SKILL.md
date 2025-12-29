---
name: luxeon-brand-guidelines
description: Applies Luxeon Star LEDs / Quadica LEDs official brand identity to documents, presentations, web artifacts, and marketing materials. Use when creating or styling content requiring brand colors, typography, logo usage, or company design standards. Triggers for company documents, product sheets, presentations, customer communications, HTML/React artifacts, or any output needing consistent brand styling.
---

# Luxeon Star LEDs / Quadica LEDs Brand Guidelines

Apply these guidelines when creating any branded content. The brand is transitioning from "Luxeon Star LEDs" to "Quadica LEDs" - both use identical design elements.

## Brand Identity

**Current brand:** LUXEON STAR LEDs by Quadica®
**Future brand:** QUADICA® LEDs

Core values: Trust, Technology, Sophistication, Service

## Primary Color Palette

| Name | Hex | RGB | CMYK | Usage |
|------|-----|-----|------|-------|
| Deep Navy | `#01236d` | 1, 35, 109 | 100, 93, 27, 20 | Primary brand, dark backgrounds |
| Royal Blue | `#092dc9` | 9, 45, 201 | 92, 83, 0, 0 | Headlines, emphasis |
| Electric Blue | `#005fe4` | 0, 95, 228 | 84, 65, 0, 0 | Accent, links |
| Sky Blue | `#109cf6` | 16, 156, 246 | 70, 30, 0, 0 | Highlights, light accents |

## Typography

**Primary typeface:** Montserrat (Google Fonts)

| Element | Weight | Usage |
|---------|--------|-------|
| Headlines, titles | Semi Bold (600) | Headings, covers, statements |
| Body copy | Regular (400) | Paragraphs, general text |

**Font stack for web:**
```css
font-family: 'Montserrat', 'Segoe UI', 'Arial', sans-serif;
```

**Typography color usage:**
- Deep Navy (`#01236d`) for primary/heavy text
- Royal Blue (`#092dc9`) for headlines and bold emphasis
- Sky Blue (`#109cf6`) for accent words within paragraphs

## Logo Usage

### Configurations
- Horizontal brand mark (preferred)
- Vertical brand mark
- Symbol only (gear/LED icon)

### Versions
- Full color (preferred)
- Single color black
- White (for dark backgrounds)

### Requirements
- Minimum size: 1" width for full brand mark, 0.75" for reduced versions
- Clear space: 0.125" minimum around all sides
- Never alter proportions, skew, or add effects
- One logo placement per layout maximum

### Text Usage
Correct: `Quadica LEDs`, `QUADICA LEDs`, `quadica leds`
Incorrect: `Quadica-LEDs`, `QUADICA LEDS`, `QUADICA LED's`, `Quadica's`

## CSS Variables

```css
:root {
  /* Primary palette */
  --quadica-deep-navy: #01236d;
  --quadica-royal-blue: #092dc9;
  --quadica-electric-blue: #005fe4;
  --quadica-sky-blue: #109cf6;
  
  /* Functional */
  --brand-primary: var(--quadica-deep-navy);
  --brand-accent: var(--quadica-sky-blue);
  --text-primary: var(--quadica-deep-navy);
  --text-heading: var(--quadica-royal-blue);
  --link-color: var(--quadica-electric-blue);
}
```

## Tailwind Configuration

```javascript
colors: {
  quadica: {
    'deep-navy': '#01236d',
    'royal-blue': '#092dc9', 
    'electric-blue': '#005fe4',
    'sky-blue': '#109cf6',
  }
}
```

## Application Patterns

### Web Artifacts (HTML/React)
- Background: White or Deep Navy for hero sections
- Headings: Royal Blue
- Body text: Deep Navy
- Links: Electric Blue, hover to Sky Blue
- Buttons: Deep Navy background, white text; hover to Royal Blue
- Border radius: 4px for buttons and cards

### Documents
- Title: Royal Blue, Montserrat Semi Bold
- Headings: Deep Navy, Montserrat Semi Bold  
- Body: Deep Navy, Montserrat Regular
- Accent text: Sky Blue for emphasis

### Presentations
- Title slides: Deep Navy background, white text
- Content slides: White background, Deep Navy text
- Accent elements: Sky Blue

## Photography Guidelines

- Favor imagery with blue tones matching brand palette
- Clean, modern, technology-focused
- High contrast, crisp/pixel-perfect quality
- LED and lighting themes when relevant

## Resources

### assets/
Contains logo files for use in outputs:
- `Luxeon_Logo_Dark.png` - Full color logo for light backgrounds
- `Luxeon_Logo_White.png` - White logo for dark backgrounds

### references/
- `brand-specifications.md` - Extended color values and detailed typography specs

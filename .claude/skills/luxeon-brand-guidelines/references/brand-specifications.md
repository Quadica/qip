# Quadica / Luxeon Star LEDs - Complete Brand Specifications

## Color System - Extended Details

### Primary Brand Colors

#### Deep Navy
The foundation color representing trust and sophistication.
- **Hex:** #01236d
- **RGB:** R=1, G=35, B=109
- **CMYK:** C=100, M=93, Y=27, K=20
- **HSL:** 221°, 98%, 22%
- **Use for:** Dark backgrounds, primary text, logo backgrounds

#### Royal Blue  
Action and emphasis color.
- **Hex:** #092dc9
- **RGB:** R=9, G=45, B=201
- **CMYK:** C=92, M=83, Y=0, K=0
- **HSL:** 229°, 91%, 41%
- **Use for:** Headlines, buttons, CTAs, bold emphasis

#### Electric Blue
Links and interactive elements.
- **Hex:** #005fe4
- **RGB:** R=0, G=95, B=228
- **CMYK:** C=84, M=65, Y=0, K=0
- **HSL:** 215°, 100%, 45%
- **Use for:** Links, hover states, secondary buttons

#### Sky Blue
Highlights and accent text.
- **Hex:** #109cf6
- **RGB:** R=16, G=156, B=246
- **CMYK:** C=70, M=30, Y=0, K=0
- **HSL:** 203°, 93%, 51%
- **Use for:** Accent text emphasis, highlights, light elements

### Neutrals (Supplementary)

| Name | Hex | Usage |
|------|-----|-------|
| Pure White | #FFFFFF | Backgrounds, reversed text |
| Light Gray | #F5F7FA | Section backgrounds |
| Medium Gray | #6B7280 | Secondary text, captions |
| Dark Gray | #374151 | Body text alternative |
| Pure Black | #000000 | High contrast needs only |

## Typography - Extended Specifications

### Montserrat Font Family

**Google Fonts import:**
```css
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap');
```

**HTML link:**
```html
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
```

### Type Scale

| Element | Size (px) | Size (rem) | Weight | Line Height | Letter Spacing |
|---------|-----------|------------|--------|-------------|----------------|
| Display | 48px | 3rem | 700 | 1.1 | -0.02em |
| H1 | 36px | 2.25rem | 600 | 1.2 | -0.01em |
| H2 | 28px | 1.75rem | 600 | 1.3 | 0 |
| H3 | 22px | 1.375rem | 600 | 1.4 | 0 |
| H4 | 18px | 1.125rem | 600 | 1.4 | 0 |
| Body Large | 18px | 1.125rem | 400 | 1.6 | 0 |
| Body | 16px | 1rem | 400 | 1.6 | 0 |
| Body Small | 14px | 0.875rem | 400 | 1.5 | 0 |
| Caption | 12px | 0.75rem | 400 | 1.4 | 0.01em |

## Component Styling

### Buttons

**Primary Button:**
```css
.btn-primary {
  background-color: #01236d;
  color: #ffffff;
  font-family: 'Montserrat', sans-serif;
  font-weight: 600;
  padding: 12px 24px;
  border-radius: 4px;
  border: none;
  transition: background-color 0.2s ease;
}
.btn-primary:hover {
  background-color: #092dc9;
}
```

**Secondary Button:**
```css
.btn-secondary {
  background-color: transparent;
  color: #01236d;
  border: 2px solid #01236d;
  font-family: 'Montserrat', sans-serif;
  font-weight: 600;
  padding: 10px 22px;
  border-radius: 4px;
  transition: all 0.2s ease;
}
.btn-secondary:hover {
  background-color: #01236d;
  color: #ffffff;
}
```

### Links

```css
a {
  color: #005fe4;
  text-decoration: none;
  transition: color 0.2s ease;
}
a:hover {
  color: #109cf6;
  text-decoration: underline;
}
```

### Cards

```css
.card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(1, 35, 109, 0.1);
}
.card-header {
  color: #092dc9;
  font-weight: 600;
  margin-bottom: 12px;
}
```

## Tailwind CSS Utilities

### Custom Color Classes
```
text-quadica-deep-navy     bg-quadica-deep-navy
text-quadica-royal-blue    bg-quadica-royal-blue
text-quadica-electric-blue bg-quadica-electric-blue
text-quadica-sky-blue      bg-quadica-sky-blue
```

### Common Patterns
```html
<!-- Primary heading -->
<h1 class="text-3xl font-semibold text-quadica-royal-blue">

<!-- Body text -->
<p class="text-base text-quadica-deep-navy">

<!-- Primary button -->
<button class="bg-quadica-deep-navy hover:bg-quadica-royal-blue text-white font-semibold px-6 py-3 rounded">

<!-- Link -->
<a class="text-quadica-electric-blue hover:text-quadica-sky-blue">

<!-- Hero section -->
<section class="bg-quadica-deep-navy text-white">
```

## Logo File Specifications

### Available Formats
- PNG (provided): For web and digital use
- SVG (recommended): For scalable applications

### File Naming Convention
- `Luxeon_Logo_Dark.png` - Full color for light backgrounds
- `Luxeon_Logo_White.png` - White version for dark backgrounds

### Usage in Code

**HTML:**
```html
<img src="assets/Luxeon_Logo_Dark.png" alt="Luxeon Star LEDs by Quadica" height="48">
```

**React with dark background:**
```jsx
<img src={LuxeonLogoWhite} alt="Luxeon Star LEDs by Quadica" className="h-12" />
```

## Brand Voice Notes

- Professional and technically precise
- Helpful and service-oriented
- Avoid jargon when possible, but use correct technical terms
- Emphasize expertise (21+ years in LED industry)
- Reference product lines: SABER, SinkPAD, Star/O series
- Mention Lumileds partnership for credibility

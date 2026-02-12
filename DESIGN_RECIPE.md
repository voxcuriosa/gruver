# Vox Portal Tile Design Recipe

This document defines the standard for creating new project tiles and logos for the Vox Curiosa portal.

## 1. Tile Structure
Each tile (`.card`) follows this HTML structure:
```html
<a href="/project-url/" class="card">
    <div class="card-icon">
        <!-- SVG Goes Here -->
    </div>
    <h2>Project Name</h2>
    <p>Short description of the project.</p>
    <span class="btn">ACTION TEXT →</span>
</a>
```

## 2. Logo / Icon Guidelines
All icons are **inline SVGs**. This ensures fast loading, crisp scaling, and easy color manipulation via CSS.

### Dimensions
- **ViewBox**: `0 0 24 24` (Standard grid)
- **Display Size**: `48px x 48px` (Controlled by CSS `.card-icon svg`)

### Style - "Neon Line Art"
- **Stroke Color**: `var(--primary)` (Default: `#00f2ff`)
- **Fill Color**: `none` (Transparent) or `rgba(0, 242, 255, 0.1)` for sub-elements.
- **Stroke Width**: `1.5`
- **Line Cap/Join**: `round` / `round`
- **Minimalism**: Use simple geometric shapes. Avoid complex shading or too many details.

### Template
Copy this template for new logos:
```xml
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
    <!-- Path commands go here -->
</svg>
```

## 4. Shared Components
To ensure consistency across portal applications, use these shared components:

### Back Link
A standardized link to return to the main portal.
```html
<a href="../index.html" class="back-link">← Tilbake til Portal</a>
```
```css
.back-link {
    display: inline-block;
    color: var(--primary);
    text-decoration: none;
    margin-bottom: 20px;
    font-weight: 600;
}
.back-link:hover {
    text-decoration: underline;
}
```

### PIN Contact Info
A standardized footer for authentication screens.
```html
<div class="contact-info">
    Kontakt Christian Borchgrevink-Vigeland på
    <span id="email-placeholder" class="email-reveal" onclick="revealEmail()">[klikk for å vise e-post]</span>
    for å få kode.
</div>
```
```css
.contact-info {
    text-align: center;
    color: #888;
    font-size: 0.9rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.email-reveal {
    color: var(--primary);
    cursor: pointer;
    text-decoration: underline;
}
```

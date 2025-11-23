# UAE Dirham Symbol Implementation Guide

## Overview
This document outlines the implementation of the UAE Dirham symbol across the AleppGift website, replacing the previous HTML entity `&#x00EA;` with a proper symbol from the assets.

## Available Assets
- **SVG**: `public/assets/svg/UAE_Dirham_Symbol.svg` - Scalable vector graphic
- **PNG**: `public/assets/png/UAE_Dirham_Symbol.png` - Raster image
- **Font**: `public/assets/font/font.*` - Custom font files (ttf, woff, woff2, otf)

## Implementation Methods

### 1. SVG Image Method (Recommended)
```html
<img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" class="uae-symbol">
```

**Advantages:**
- Scalable and crisp at all sizes
- Consistent appearance across browsers
- Good accessibility with alt text
- Small file size

**CSS Styling:**
```css
.uae-symbol {
  display: inline-block;
  width: 0.8em;
  height: 0.8em;
  margin-right: 0.25rem;
  vertical-align: baseline;
  filter: brightness(0); /* Make it black */
  font-weight: 700;
}
```

### 2. Custom Font Method (Alternative)
```html
<span class="uae-symbol-font">﷼ <!-- or custom character --></span>
```

**CSS Styling:**
```css
@font-face {
  font-family: 'UAESymbol';
  src: url('../font/font.ttf') format('truetype'),
       url('../font/font.woff2') format('woff2'),
       url('../font/font.woff') format('woff'),
       url('../font/font.otf') format('opentype');
  font-display: swap;
}

.uae-symbol-font {
  font-family: 'UAESymbol', Arial, sans-serif;
  font-weight: 700;
  margin-right: 0.25rem;
}
```

### 3. Text Fallback Method (Backup)
```html
<span class="uae-symbol-text"></span>
```

**CSS Styling:**
```css
.uae-symbol-text::before {
  content: "د.إ";
  margin-right: 0.25rem;
  font-weight: 700;
}
```

## Files Updated

### PHP Files
1. **`public/index.php`** (Line 415)
   - Before: `<span class="uae-symbol">&#x00EA;</span>`
   - After: `<img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" class="uae-symbol">`

2. **`public/ajax/get_cart_preview.php`** (Lines 34, 48)
   - Updated cart item prices and total to use SVG symbol

### JavaScript Files
1. **`public/assets/js/enhanced-main.js`** (Line 252)
   - Updated quick view modal price display

### CSS Files
1. **`public/assets/css/style.css`**
2. **`public/assets/css/index.css`**
3. **`public/assets/css/ui-components.css`**

## Usage Examples

### Product Price Display
```php
<div class="product-price">
    <span class="price-current">
        <img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" class="uae-symbol"><?= number_format($price); ?>
    </span>
    <span class="price-usd">$<?= number_format($price/3.68, 2); ?></span>
</div>
```

### JavaScript Dynamic Content
```javascript
<span class="price-aed">
    <img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" class="uae-symbol">${priceAED.toLocaleString()}
</span>
```

### Inline Styling (for modals/special cases)
```html
<img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" 
     style="width: 1em; height: 1em; margin-right: 0.25rem; vertical-align: baseline; filter: brightness(0);">
```

## Browser Compatibility
- **SVG Method**: Supported in all modern browsers (IE9+)
- **Font Method**: Good support with proper fallbacks
- **Text Method**: Universal support

## Accessibility Features
- Alt text for screen readers
- Proper semantic markup
- Consistent visual hierarchy
- High contrast support

## Performance Considerations
- SVG files are small and cacheable
- Font files are loaded once and cached
- Minimal impact on page load times

## Testing
Test files created:
- `test_uae_symbol.html` - Visual comparison of different methods
- `test_font_characters.html` - Font character analysis

## Future Enhancements
1. Consider implementing CSS custom properties for easy theme switching
2. Add support for multiple currencies if needed
3. Implement RTL (right-to-left) language support
4. Add animation effects for price changes

## Maintenance Notes
- Ensure SVG files remain in the assets directory
- Update paths if directory structure changes
- Test across different devices and browsers
- Monitor for font loading issues

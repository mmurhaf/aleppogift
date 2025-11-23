<?php
/**
 * UAE Dirham Symbol Utility Functions
 * 
 * This file provides utility functions for displaying UAE Dirham symbols
 * consistently across the AleppGift website.
 */

/**
 * Get UAE Dirham symbol as SVG image
 * 
 * @param string $classes Additional CSS classes
 * @param string $style Additional inline styles
 * @return string HTML img tag for UAE symbol
 */
function getUAESymbolSVG($classes = 'uae-symbol', $style = '') {
    $alt = 'AED';
    $src = 'assets/svg/UAE_Dirham_Symbol.svg';
    
    if (!empty($style)) {
        $style = ' style="' . htmlspecialchars($style) . '"';
    }
    
    return '<img src="' . $src . '" alt="' . $alt . '" class="' . htmlspecialchars($classes) . '"' . $style . '>';
}

/**
 * Get UAE Dirham symbol using custom font
 * 
 * @param string $classes Additional CSS classes
 * @return string HTML span tag with font symbol
 */
function getUAESymbolFont($classes = 'uae-symbol-font') {
    // Using the Unicode character for UAE Dirham
    return '<span class="' . htmlspecialchars($classes) . '">﷼</span>';
}

/**
 * Get UAE Dirham symbol as text
 * 
 * @param string $classes Additional CSS classes
 * @return string HTML span tag with text symbol
 */
function getUAESymbolText($classes = 'uae-symbol-text') {
    return '<span class="' . htmlspecialchars($classes) . '"></span>';
}

/**
 * Format price with UAE Dirham symbol
 * 
 * @param float $price Price amount
 * @param string $method Symbol method ('svg', 'font', 'text')
 * @param int $decimals Number of decimal places
 * @return string Formatted price with symbol
 */
function formatPriceAED($price, $method = 'svg', $decimals = 0) {
    $formattedPrice = number_format($price, $decimals);
    
    switch ($method) {
        case 'font':
            $symbol = getUAESymbolFont();
            break;
        case 'text':
            $symbol = getUAESymbolText();
            break;
        case 'svg':
        default:
            $symbol = getUAESymbolSVG();
            break;
    }
    
    return $symbol . $formattedPrice;
}

/**
 * Format price with both AED and USD
 * 
 * @param float $price Price in AED
 * @param float $exchangeRate Exchange rate (default: 3.68)
 * @param string $method Symbol method for AED
 * @return string HTML with both currencies
 */
function formatPriceDual($price, $exchangeRate = 3.68, $method = 'svg') {
    $aedPrice = formatPriceAED($price, $method);
    $usdPrice = number_format($price / $exchangeRate, 2);
    
    return '<span class="price-current">' . $aedPrice . '</span>' .
           '<span class="price-usd">($' . $usdPrice . ')</span>';
}

/**
 * Get JavaScript code for UAE symbol
 * 
 * @param string $method Symbol method ('svg', 'font', 'text')
 * @return string JavaScript code for dynamic content
 */
function getUAESymbolJS($method = 'svg') {
    switch ($method) {
        case 'font':
            return '\'<span class="uae-symbol-font">﷼</span>\'';
        case 'text':
            return '\'<span class="uae-symbol-text"></span>\'';
        case 'svg':
        default:
            return '\'<img src="assets/svg/UAE_Dirham_Symbol.svg" alt="AED" class="uae-symbol">\'';
    }
}
?>

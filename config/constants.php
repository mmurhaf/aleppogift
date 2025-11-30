<?php
/**
 * =================================================================
 * ALEPPOGIFT CONFIGURATION CONSTANTS
 * Centralized configuration for repeated values across the application
 * =================================================================
 */

// Prevent direct access
if (!defined('ALEPPOGIFT_INIT')) {
    die('Direct access not permitted');
}

// =================================================================
// SITE CONFIGURATION
// =================================================================
define('SITE_NAME', 'Aleppo Gift');
define('SITE_URL', 'https://aleppogift.com');
define('SITE_DESCRIPTION', 'Premium Gifts & Home Decor UAE');

// =================================================================
// PAGINATION SETTINGS
// =================================================================
define('PRODUCTS_PER_PAGE', 16);
define('ORDERS_PER_PAGE', 20);
define('REVIEWS_PER_PAGE', 10);

// =================================================================
// CONTACT INFORMATION
// =================================================================
define('WHATSAPP_NUMBER', '971561125320');
define('WHATSAPP_INTERNATIONAL', '+971561125320');
define('SUPPORT_EMAIL', 'info@aleppogift.com');
define('ADMIN_EMAIL', 'admin@aleppogift.com');

// =================================================================
// CURRENCY SETTINGS
// =================================================================
define('DEFAULT_CURRENCY', 'AED');
define('USD_TO_AED_RATE', 3.68);
define('CURRENCY_SYMBOL', 'د.إ');

// =================================================================
// IMAGE SETTINGS
// =================================================================
define('DEFAULT_IMAGE', 'assets/images/no-image.png');
define('DEFAULT_PRODUCT_IMAGE', 'assets/images/no-image.png');
define('MAX_IMAGE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Upload paths
define('UPLOAD_PATH_PRODUCTS', 'uploads/products/');
define('UPLOAD_PATH_BRANDS', 'uploads/brands/');
define('UPLOAD_PATH_CATEGORIES', 'uploads/categories/');

// =================================================================
// SHIPPING SETTINGS
// =================================================================
define('FREE_SHIPPING_THRESHOLD', 200); // AED
define('DEFAULT_SHIPPING_COST', 25); // AED
define('EXPRESS_SHIPPING_COST', 50); // AED

// =================================================================
// ORDER STATUS
// =================================================================
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_SHIPPED', 'shipped');
define('ORDER_STATUS_DELIVERED', 'delivered');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// =================================================================
// SESSION SETTINGS
// =================================================================
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('CART_SESSION_KEY', 'cart');
define('USER_SESSION_KEY', 'user_id');

// =================================================================
// SECURITY SETTINGS
// =================================================================
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// =================================================================
// FEATURE FLAGS
// =================================================================
define('ENABLE_REVIEWS', true);
define('ENABLE_WISHLIST', true);
define('ENABLE_COMPARE', false);
define('ENABLE_PRODUCT_RATINGS', true);
define('ENABLE_GUEST_CHECKOUT', true);

// =================================================================
// SOCIAL MEDIA LINKS
// =================================================================
define('SOCIAL_FACEBOOK', 'https://facebook.com/aleppogift');
define('SOCIAL_INSTAGRAM', 'https://instagram.com/aleppogift');
define('SOCIAL_TWITTER', 'https://twitter.com/aleppogift');

// =================================================================
// API KEYS (Store in environment variables in production)
// =================================================================
// define('GOOGLE_ANALYTICS_ID', 'UA-XXXXXXXXX-X');
// define('FACEBOOK_PIXEL_ID', 'XXXXXXXXXXXXXXXX');

// =================================================================
// DATE/TIME FORMATS
// =================================================================
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd M Y');
define('DISPLAY_DATETIME_FORMAT', 'd M Y H:i');

// =================================================================
// HELPER FUNCTIONS
// =================================================================

/**
 * Format price in AED
 * @param float $price Price value
 * @param bool $includeSymbol Include currency symbol
 * @return string Formatted price
 */
function formatPrice($price, $includeSymbol = true) {
    $formatted = number_format($price, 2);
    return $includeSymbol ? CURRENCY_SYMBOL . ' ' . $formatted : $formatted;
}

/**
 * Convert USD to AED
 * @param float $usd USD amount
 * @return float AED amount
 */
function usdToAed($usd) {
    return $usd * USD_TO_AED_RATE;
}

/**
 * Convert AED to USD
 * @param float $aed AED amount
 * @return float USD amount
 */
function aedToUsd($aed) {
    return $aed / USD_TO_AED_RATE;
}

/**
 * Get WhatsApp link for product
 * @param string $productName Product name
 * @param float $price Product price
 * @param int $productId Product ID
 * @return string WhatsApp URL
 */
function getWhatsAppLink($productName, $price, $productId) {
    $message = urlencode(
        SITE_NAME . ': I am interested in this product: ' . 
        $productName . ' - AED ' . number_format($price, 0) . 
        ' - ' . SITE_URL . '/product.php?id=' . $productId
    );
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . $message;
}

/**
 * Get full URL for asset
 * @param string $path Relative path to asset
 * @return string Full URL
 */
function assetUrl($path) {
    return SITE_URL . '/' . ltrim($path, '/');
}

/**
 * Check if free shipping applies
 * @param float $total Cart total
 * @return bool True if free shipping applies
 */
function isFreeShipping($total) {
    return $total >= FREE_SHIPPING_THRESHOLD;
}

/**
 * Calculate shipping cost
 * @param float $total Cart total
 * @param bool $express Express shipping
 * @return float Shipping cost
 */
function calculateShipping($total, $express = false) {
    if (isFreeShipping($total)) {
        return 0;
    }
    return $express ? EXPRESS_SHIPPING_COST : DEFAULT_SHIPPING_COST;
}

// =================================================================
// VALIDATION HELPERS
// =================================================================

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool True if valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (UAE format)
 * @param string $phone Phone number
 * @return bool True if valid
 */
function isValidUAEPhone($phone) {
    $pattern = '/^(\+971|00971|971|0)?[0-9]{9}$/';
    return preg_match($pattern, $phone);
}

/**
 * Sanitize output for HTML display
 * @param string $text Text to sanitize
 * @return string Sanitized text
 */
function sanitizeOutput($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// =================================================================
// End of Configuration
// =================================================================

<?php
// Session validation helper for cart functionality

if (!function_exists('validateCartSession')) {
    function validateCartSession() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Only log in debug mode
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Session Validation - ID: " . session_id() . ", Cart items: " . count($_SESSION['cart']));
        }
        
        return true;
    }
}

if (!function_exists('findCartItem')) {
    function findCartItem($cart, $product_id, $variation_id = null) {
        foreach ($cart as $key => $item) {
            if ($item['product_id'] == $product_id && 
                ($item['variation_id'] ?? null) == $variation_id) {
                return ['key' => $key, 'item' => $item];
            }
        }
        return false;
    }
}

if (!function_exists('validateCartItems')) {
    function validateCartItems($db, $cart) {
        $valid_items = [];
        $invalid_items = [];
        
        foreach ($cart as $key => $item) {
            try {
                // Check if product exists and is active
                $product = $db->query(
                    "SELECT id, name_en, status FROM products WHERE id = :id", 
                    ['id' => $item['product_id']]
                )->fetch(PDO::FETCH_ASSOC);
                
                if ($product && $product['status'] == 1) {
                    $valid_items[] = ['key' => $key, 'item' => $item];
                } else {
                    $invalid_items[] = ['key' => $key, 'item' => $item, 'reason' => 'Product not found or inactive'];
                }
            } catch (Exception $e) {
                $invalid_items[] = ['key' => $key, 'item' => $item, 'reason' => 'Database error: ' . $e->getMessage()];
            }
        }
        
        return [$valid_items, $invalid_items];
    }
}

/**
 * Resolve image path for display
 * Handles various path formats and provides fallback
 * 
 * @param string|null $path The image path from database
 * @param string $default Default image path if none provided
 * @return string Resolved image path
 */
if (!function_exists('resolveImagePath')) {
    function resolveImagePath($path, $default = 'uploads/default-product.jpg') {
        if (empty($path)) {
            return $default;
        }
        
        // Remove leading ../ patterns
        $path = ltrim(preg_replace('/^(\.\.\/)+/', '', $path), '/');
        
        // Ensure path doesn't have double slashes
        $path = preg_replace('/\/+/', '/', $path);
        
        return $path;
    }
}

/**
 * Get cart item count safely
 * 
 * @return int Total items in cart
 */
if (!function_exists('getCartCount')) {
    function getCartCount() {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            return 0;
        }
        return array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
}
?>

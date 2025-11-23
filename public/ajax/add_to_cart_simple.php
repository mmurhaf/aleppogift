<?php
/**
 * Simplified and Optimized Add to Cart
 * Cleaned up version with better performance
 */
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

// Simple headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Input validation - simplified
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;
    $variation_id = filter_input(INPUT_POST, 'variation_id', FILTER_VALIDATE_INT);
    
    // Quick validation
    if (!$product_id || $product_id < 1) {
        throw new Exception('Invalid product');
    }
    
    if ($quantity < 1) {
        throw new Exception('Invalid quantity');
    }
    
    $db = new Database();
    
    // Single query to get product info
    $sql = "SELECT id, name_en, price, stock, status FROM products WHERE id = ? AND status = 1";
    $stmt = $db->conn->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    // Stock check (only if stock tracking is enabled)
    if ($product['stock'] !== null && $product['stock'] < $quantity) {
        throw new Exception('Insufficient stock');
    }
    
    // Initialize cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Find existing item
    $found_key = null;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id && 
            ($item['variation_id'] ?? null) == $variation_id) {
            $found_key = $key;
            break;
        }
    }
    
    // Add or update cart
    if ($found_key !== null) {
        $new_qty = $_SESSION['cart'][$found_key]['quantity'] + $quantity;
        
        // Final stock check
        if ($product['stock'] !== null && $new_qty > $product['stock']) {
            throw new Exception('Cannot add more - exceeds stock limit');
        }
        
        $_SESSION['cart'][$found_key]['quantity'] = $new_qty;
    } else {
        $_SESSION['cart'][] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'variation_id' => $variation_id,
            'added_at' => time()
        ];
    }
    
    // Calculate cart count
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Added to cart',
        'product_name' => $product['name_en'],
        'count' => $cart_count
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

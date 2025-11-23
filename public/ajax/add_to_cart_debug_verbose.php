<?php
// Ensure session is started first and only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../config/config.php');
require_once('../../includes/Database.php');

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Debug logging
error_log("=== ADD TO CART DEBUG ===");
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION cart before: " . print_r($_SESSION['cart'] ?? 'NOT SET', true));

try {
    $db = new Database();
    
    // Get raw input first for debugging
    $raw_product_id = $_POST['product_id'] ?? 'NOT SET';
    $raw_quantity = $_POST['quantity'] ?? 'NOT SET';
    
    error_log("Raw product_id: " . $raw_product_id);
    error_log("Raw quantity: " . $raw_quantity);
    
    // Get and validate input with comprehensive error handling
    $product_id = isset($_POST['product_id']) ? filter_var($_POST['product_id'], FILTER_VALIDATE_INT) : 0;
    $quantity = isset($_POST['quantity']) ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT) : 1;
    $variation_id = isset($_POST['variation_id']) ? filter_var($_POST['variation_id'], FILTER_VALIDATE_INT) : null;

    error_log("After filter_var - product_id: " . var_export($product_id, true));
    error_log("After filter_var - quantity: " . var_export($quantity, true));
    
    // Ensure minimum values
    $product_id = ($product_id === false) ? 0 : $product_id;
    $quantity = ($quantity === false) ? 0 : $quantity;
    
    error_log("Final values - product_id: " . $product_id);
    error_log("Final values - quantity: " . $quantity);
    
    // Enhanced validation
    if ($product_id < 1) {
        error_log("VALIDATION FAILED: Invalid product ID");
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid product ID or quantity',
            'error_code' => 'INVALID_PRODUCT_ID',
            'details' => 'Product ID must be a positive integer',
            'debug' => [
                'raw_product_id' => $raw_product_id,
                'filtered_product_id' => $product_id
            ]
        ]);
        exit;
    }
    
    if ($quantity < 1) {
        error_log("VALIDATION FAILED: Invalid quantity");
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid product ID or quantity',
            'error_code' => 'INVALID_QUANTITY',
            'details' => 'Quantity must be a positive integer',
            'debug' => [
                'raw_quantity' => $raw_quantity,
                'filtered_quantity' => $quantity
            ]
        ]);
        exit;
    }

    // Validate product exists and is active
    $product = $db->query(
        "SELECT id, name_en, price, stock, status FROM products WHERE id = :id AND status = 1", 
        ['id' => $product_id]
    )->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        error_log("VALIDATION FAILED: Product not found");
        echo json_encode([
            'success' => false, 
            'message' => 'Product not found or not available',
            'debug' => [
                'product_id' => $product_id,
                'query_result' => 'null'
            ]
        ]);
        exit;
    }
    
    error_log("Product found: " . print_r($product, true));

    // Check stock availability
    if ($product['stock'] !== null && $product['stock'] < $quantity) {
        echo json_encode([
            'success' => false, 
            'message' => 'Insufficient stock. Available: ' . $product['stock']
        ]);
        exit;
    }

    // Validate variation if provided
    if ($variation_id) {
        $variation = $db->query(
            "SELECT id, size, color, additional_price, stock FROM product_variations WHERE id = :id AND product_id = :product_id", 
            ['id' => $variation_id, 'product_id' => $product_id]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$variation) {
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid product variation'
            ]);
            exit;
        }

        // Check variation stock
        if ($variation['stock'] !== null && $variation['stock'] < $quantity) {
            echo json_encode([
                'success' => false, 
                'message' => 'Insufficient variation stock. Available: ' . $variation['stock']
            ]);
            exit;
        }
    }

    // Initialize cart if empty
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Create unique cart key for product + variation combination
    $cart_key = $product_id . '_' . ($variation_id ?? 0);

    // Check if we already have this exact item in cart
    $existing_quantity = 0;
    $existing_key = null;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id && 
            ($item['variation_id'] ?? null) == $variation_id) {
            $existing_quantity = $item['quantity'];
            $existing_key = $key;
            break;
        }
    }

    $new_total_quantity = $existing_quantity + $quantity;

    // Check total stock again
    $stock_limit = $variation_id ? $variation['stock'] : $product['stock'];
    if ($stock_limit !== null && $new_total_quantity > $stock_limit) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot add more items. Total would exceed stock limit: ' . $stock_limit
        ]);
        exit;
    }

    // Add or update item in cart
    if ($existing_key !== null) {
        $_SESSION['cart'][$existing_key]['quantity'] = $new_total_quantity;
    } else {
        $_SESSION['cart'][] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'variation_id' => $variation_id,
            'added_at' => time()
        ];
    }

    // Calculate total items in cart
    $total_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

    error_log("Cart updated successfully. Total count: " . $total_count);
    error_log("SESSION cart after: " . print_r($_SESSION['cart'], true));

    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully',
        'count' => $total_count,
        'product_name' => $product['name_en']
    ]);

} catch (Exception $e) {
    error_log("Add to cart error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>

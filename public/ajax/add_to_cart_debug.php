<?php
// Debug version of add_to_cart.php
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

header('Content-Type: application/json');

// Log all incoming data for debugging
error_log("=== ADD TO CART DEBUG ===");
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

try {
    $db = new Database();
    
    // Get and validate input with comprehensive error handling
    $product_id = isset($_POST['product_id']) ? filter_var($_POST['product_id'], FILTER_VALIDATE_INT) : 0;
    $quantity = isset($_POST['quantity']) ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT) : 1;
    $variation_id = isset($_POST['variation_id']) ? filter_var($_POST['variation_id'], FILTER_VALIDATE_INT) : null;

    // Log validation results
    error_log("Raw product_id: " . (isset($_POST['product_id']) ? $_POST['product_id'] : 'NOT SET'));
    error_log("Raw quantity: " . (isset($_POST['quantity']) ? $_POST['quantity'] : 'NOT SET'));
    error_log("Filtered product_id: " . $product_id);
    error_log("Filtered quantity: " . $quantity);

    // Ensure minimum values
    $product_id = ($product_id === false) ? 0 : $product_id;
    $quantity = ($quantity === false) ? 1 : $quantity;  // Default to 1 instead of 0
    
    error_log("Final product_id: " . $product_id);
    error_log("Final quantity: " . $quantity);
    
    // Enhanced validation
    if ($product_id < 1) {
        error_log("VALIDATION FAILED: Invalid product ID");
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid product ID or quantity',
            'error_code' => 'INVALID_PRODUCT_ID',
            'details' => 'Product ID must be a positive integer',
            'debug' => [
                'raw_product_id' => $_POST['product_id'] ?? 'NOT SET',
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
                'raw_quantity' => $_POST['quantity'] ?? 'NOT SET',
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
                'product_id' => $product_id
            ]
        ]);
        exit;
    }

    // Initialize cart if empty
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Create unique cart key for product + variation combination
    $cart_key = $product_id . '_' . ($variation_id ?? 0);

    // Add item to cart
    $_SESSION['cart'][] = [
        'product_id' => $product_id,
        'quantity' => $quantity,
        'variation_id' => $variation_id,
        'added_at' => time()
    ];

    // Calculate total items in cart
    $total_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

    error_log("SUCCESS: Product added to cart");
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully',
        'count' => $total_count,
        'product_name' => $product['name_en'],
        'debug' => [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'cart_items' => count($_SESSION['cart'])
        ]
    ]);

} catch (Exception $e) {
    error_log("EXCEPTION: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.',
        'debug' => [
            'exception' => $e->getMessage()
        ]
    ]);
}
?>

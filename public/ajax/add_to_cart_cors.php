<?php
/**
 * Cross-Origin Add to Cart Endpoint
 * Specifically designed for external domain requests
 */

// Ensure session is started first and only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../config/config.php');
require_once('../../includes/Database.php');

// Strict CORS configuration for external requests
$allowed_origins = [
    'https://aleppogift.com',
    'https://www.aleppogift.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
} else {
    // Reject requests from unauthorized origins
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized origin',
        'error_code' => 'CORS_VIOLATION'
    ]);
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Additional security logging for cross-origin requests
error_log("CORS Request from: " . $origin . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

try {
    $db = new Database();
    
    // Get and validate input with comprehensive error handling
    $raw_product_id = $_POST['product_id'] ?? null;
    $raw_quantity = $_POST['quantity'] ?? null;
    $raw_variation_id = $_POST['variation_id'] ?? null;
    
    // Validate and convert product_id
    if (empty($raw_product_id)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Product ID is required',
            'error_code' => 'MISSING_PRODUCT_ID'
        ]);
        exit;
    }
    
    $product_id = filter_var($raw_product_id, FILTER_VALIDATE_INT);
    if ($product_id === false || $product_id < 1) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid product ID',
            'error_code' => 'INVALID_PRODUCT_ID'
        ]);
        exit;
    }
    
    // Validate and convert quantity - default to 1 if not provided or invalid
    $quantity = 1; // Default value
    if (!empty($raw_quantity)) {
        $filtered_quantity = filter_var($raw_quantity, FILTER_VALIDATE_INT);
        if ($filtered_quantity !== false && $filtered_quantity >= 1) {
            $quantity = $filtered_quantity;
        }
    }
    
    // Validate variation_id if provided
    $variation_id = null;
    if (!empty($raw_variation_id)) {
        $variation_id = filter_var($raw_variation_id, FILTER_VALIDATE_INT);
        if ($variation_id === false) {
            $variation_id = null;
        }
    }

    // Validate product exists and is active
    $product = $db->query(
        "SELECT id, name_en, price, stock, status FROM products WHERE id = :id AND status = 1", 
        ['id' => $product_id]
    )->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode([
            'success' => false, 
            'message' => 'Product not found or not available'
        ]);
        exit;
    }

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

    // Log successful cross-origin cart addition
    error_log("CORS Cart Addition - Origin: $origin, Product: {$product['name_en']}, Quantity: $quantity");

    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully',
        'count' => $total_count,
        'product_name' => $product['name_en'],
        'origin' => $origin // For debugging
    ]);

} catch (Exception $e) {
    error_log("CORS Add to cart error from $origin: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>

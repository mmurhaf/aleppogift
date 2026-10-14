<?php
// CORS Headers - Set before any other headers or session start
$allowed_origins = [
    'https://www.aleppogift.com',
    'https://aleppogift.com',
    'https://aleppogift.com',
    'https://www.aleppogift.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: https://www.aleppogift.com');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Use the secure bootstrap file for initialization
require_once(__DIR__ . '/../../includes/bootstrap.php');

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
            'message' => t('cart.product_id_required'),
            'error_code' => 'MISSING_PRODUCT_ID'
        ]);
        exit;
    }
    
    $product_id = filter_var($raw_product_id, FILTER_VALIDATE_INT);
    if ($product_id === false || $product_id < 1) {
        echo json_encode([
            'success' => false, 
            'message' => t('cart.invalid_product_id'),
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
    
    // At this point, we have valid product_id and quantity

    // Validate product exists and is active
    $publishedSql = ag_has_column($db, 'products', 'listing_status')
        ? " AND (listing_status IS NULL OR listing_status = '' OR listing_status = 'published')"
        : '';
    $product = $db->query(
        "SELECT id, name_en, name_ar, price, stock, status FROM products WHERE id = :id AND status = 1 $publishedSql", 
        ['id' => $product_id]
    )->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode([
            'success' => false, 
            'message' => t('cart.product_unavailable')
        ]);
        exit;
    }

    // Check stock availability
    if ($product['stock'] !== null && $product['stock'] < $quantity) {
        echo json_encode([
            'success' => false, 
            'message' => t('cart.insufficient_stock', ['stock' => $product['stock']])
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
                'message' => t('cart.invalid_variation')
            ]);
            exit;
        }

        // Check variation stock
        if ($variation['stock'] !== null && $variation['stock'] < $quantity) {
            echo json_encode([
                'success' => false, 
                'message' => t('cart.insufficient_variation_stock', ['stock' => $variation['stock']])
            ]);
            exit;
        }
    }

    // Initialize cart if empty
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $reserved = cartReservedQuantity($_SESSION['cart'], $product_id, $variation_id);
    $new_total_quantity = $reserved + $quantity;
    $stock_limit = $variation_id ? $variation['stock'] : $product['stock'];
    if ($stock_limit !== null && $new_total_quantity > $stock_limit) {
        echo json_encode([
            'success' => false,
            'message' => t('cart.exceeds_stock', ['stock' => $stock_limit])
        ]);
        exit;
    }

    $existing = findCartItem($_SESSION['cart'], $product_id, $variation_id);
    if ($existing) {
        $_SESSION['cart'][$existing['key']]['quantity'] += $quantity;
    } else {
        $bundleLine = findCartItem($_SESSION['cart'], $product_id, $variation_id, 'bundle');
        $_SESSION['cart'][] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'variation_id' => $variation_id,
            'added_at' => time(),
            'line_role' => $bundleLine ? 'extra' : 'standalone',
            'bundle_id' => $bundleLine['item']['bundle_id'] ?? null,
        ];
    }

    // Calculate total items in cart
    $total_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

    echo json_encode([
        'success' => true,
        'message' => t('cart.added'),
        'count' => $total_count,
        'cart_count' => $total_count,
        'product_name' => loc_field($product, 'name')
    ]);

} catch (Exception $e) {
    error_log("Add to cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => t('error_generic')
    ]);
}

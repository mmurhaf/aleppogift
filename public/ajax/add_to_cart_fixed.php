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

// Start session before including config to avoid session ini conflicts
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables and configuration
require_once(__DIR__ . '/../../includes/env_loader.php');
EnvLoader::load();
require_once(__DIR__ . '/../../config/config.php');

// Database connection using environment variables
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Get and validate input - Handle both web and CLI contexts
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if ($product_id === null || $product_id === false) {
        // Fallback for CLI testing or when filter_input fails
        $product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    }
    
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    if ($quantity === null || $quantity === false) {
        // Fallback for CLI testing or when filter_input fails
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
    }
    
    $variation_id = filter_input(INPUT_POST, 'variation_id', FILTER_VALIDATE_INT);
    if ($variation_id === null || $variation_id === false) {
        // Fallback for CLI testing or when filter_input fails
        $variation_id = filter_var($_POST['variation_id'] ?? null, FILTER_VALIDATE_INT);
    }
    
    if (!$product_id || $product_id <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid product ID'
        ]);
        exit;
    }
    
    if (!$quantity || $quantity <= 0) {
        $quantity = 1;
    }
    
    // Check if product exists (using correct column names for multilingual support)
    $stmt = $pdo->prepare("SELECT id, name_en, name_ar, price FROM products WHERE id = ? AND status = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode([
            'success' => false,
            'error' => 'Product not found or unavailable'
        ]);
        exit;
    }
    
    // Use English name as default, fallback to Arabic if English is empty
    $product_name = !empty($product['name_en']) ? $product['name_en'] : $product['name_ar'];
    
    // Initialize cart in session if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Create cart item key
    $cart_key = $product_id;
    if ($variation_id) {
        $cart_key .= '_' . $variation_id;
    }
    
    // Add to cart
    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$cart_key] = [
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $quantity,
            'name' => $product_name,
            'price' => $product['price']
        ];
    }
    
    // Calculate cart totals
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    $cart_total = array_sum(array_map(function($item) {
        return $item['quantity'] * $item['price'];
    }, $_SESSION['cart']));
    
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully',
        'cart_count' => $cart_count,
        'cart_total' => number_format($cart_total, 2),
        'product' => [
            'id' => $product_id,
            'name' => $product_name,
            'quantity' => $quantity
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

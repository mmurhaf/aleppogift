<?php
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

header('Content-Type: application/json');

try {
    $db = new Database();
    
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($product_id < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    // Find the cart item
    $cart_key = null;
    $cart_item = null;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id) {
            $cart_key = $key;
            $cart_item = $item;
            break;
        }
    }

    if ($cart_key === null) {
        echo json_encode(['success' => false, 'message' => 'Product not found in cart']);
        exit;
    }

    // Get product and variation info for stock checking
    $product = $db->query(
        "SELECT stock FROM products WHERE id = :id", 
        ['id' => $product_id]
    )->fetch(PDO::FETCH_ASSOC);

    $variation = null;
    if (!empty($cart_item['variation_id'])) {
        $variation = $db->query(
            "SELECT stock FROM product_variations WHERE id = :id", 
            ['id' => $cart_item['variation_id']]
        )->fetch(PDO::FETCH_ASSOC);
    }

    $current_quantity = $cart_item['quantity'];
    $new_quantity = $current_quantity;

    if ($action === 'increase') {
        $new_quantity = $current_quantity + 1;
        
        // Check stock limit
        $stock_limit = $variation ? $variation['stock'] : $product['stock'];
        if ($stock_limit !== null && $new_quantity > $stock_limit) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot increase quantity. Stock limit: ' . $stock_limit
            ]);
            exit;
        }
        
        $_SESSION['cart'][$cart_key]['quantity'] = $new_quantity;
        
    } elseif ($action === 'decrease') {
        if ($current_quantity > 1) {
            $new_quantity = $current_quantity - 1;
            $_SESSION['cart'][$cart_key]['quantity'] = $new_quantity;
        } else {
            // Remove item if quantity becomes 0
            unset($_SESSION['cart'][$cart_key]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    $total_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

    echo json_encode([
        'success' => true,
        'count' => $total_count,
        'new_quantity' => $new_quantity,
        'message' => 'Cart updated successfully'
    ]);

} catch (Exception $e) {
    error_log("Update cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.'
    ]);
}

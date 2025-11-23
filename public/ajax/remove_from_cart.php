<?php
session_start();

header('Content-Type: application/json');

try {
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

    if ($product_id < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    // Find and remove the cart item
    $item_found = false;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id) {
            unset($_SESSION['cart'][$key]);
            $item_found = true;
            break;
        }
    }

    if (!$item_found) {
        echo json_encode(['success' => false, 'message' => 'Product not found in cart']);
        exit;
    }

    // Reindex the array to maintain proper structure
    $_SESSION['cart'] = array_values($_SESSION['cart']);

    $total_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

    echo json_encode([
        'success' => true,
        'count' => $total_count,
        'message' => 'Item removed from cart'
    ]);

} catch (Exception $e) {
    error_log("Remove from cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.'
    ]);
}

<?php
session_start();
require_once __DIR__ . '/../../includes/i18n.php';

header('Content-Type: application/json');

try {
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $cart_key = isset($_POST['cart_key']) ? (int) $_POST['cart_key'] : -1;

    if ($cart_key < 0 && $product_id < 1) {
        echo json_encode(['success' => false, 'message' => t('cart.invalid_product_id')]);
        exit;
    }

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => t('header.cart_empty')]);
        exit;
    }

    $item_found = false;
    if ($cart_key >= 0 && isset($_SESSION['cart'][$cart_key])) {
        unset($_SESSION['cart'][$cart_key]);
        $item_found = true;
    } else {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id && ($item['line_role'] ?? 'standalone') !== 'bundle') {
                unset($_SESSION['cart'][$key]);
                $item_found = true;
                break;
            }
        }
        if (!$item_found) {
            foreach ($_SESSION['cart'] as $key => $item) {
                if ($item['product_id'] == $product_id) {
                    unset($_SESSION['cart'][$key]);
                    $item_found = true;
                    break;
                }
            }
        }
    }

    if (!$item_found) {
        echo json_encode(['success' => false, 'message' => t('cart.not_in_cart')]);
        exit;
    }

    // Reindex the array to maintain proper structure
    $_SESSION['cart'] = array_values($_SESSION['cart']);

    $total_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    session_write_close();

    echo json_encode([
        'success' => true,
        'count' => $total_count,
        'message' => t('cart.flash_item_removed')
    ]);

} catch (Exception $e) {
    error_log("Remove from cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => t('error_generic')
    ]);
}

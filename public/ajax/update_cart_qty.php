<?php
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');
require_once __DIR__ . '/../../includes/i18n.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $requested_key = isset($_POST['cart_key']) ? (int) $_POST['cart_key'] : -1;
    $action = $_POST['action'] ?? '';

    if ($requested_key < 0 && $product_id < 1) {
        echo json_encode(['success' => false, 'message' => t('cart.invalid_product_id')]);
        exit;
    }

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => t('header.cart_empty')]);
        exit;
    }

    $cart_key = null;
    $cart_item = null;
    if ($requested_key >= 0 && isset($_SESSION['cart'][$requested_key])) {
        $cart_key = $requested_key;
        $cart_item = $_SESSION['cart'][$requested_key];
        $product_id = (int)$cart_item['product_id'];
    } else {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id && ($item['line_role'] ?? 'standalone') !== 'bundle') {
                $cart_key = $key;
                $cart_item = $item;
                break;
            }
        }
        if ($cart_key === null) {
            foreach ($_SESSION['cart'] as $key => $item) {
                if ($item['product_id'] == $product_id) {
                    $cart_key = $key;
                    $cart_item = $item;
                    break;
                }
            }
        }
    }

    if ($cart_key === null) {
        echo json_encode(['success' => false, 'message' => t('cart.not_in_cart')]);
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

    $current_quantity = (int)$cart_item['quantity'];
    $new_quantity = $current_quantity;

    if ($action === 'increase' || $action === 'set') {
        if ($action === 'increase') {
            $new_quantity = $current_quantity + 1;
        } else {
            $requested = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($requested === false || $requested < 1) {
                echo json_encode(['success' => false, 'message' => t('cart.min_quantity')]);
                exit;
            }
            $new_quantity = min(9999, (int)$requested);
        }

        if ($new_quantity > $current_quantity) {
            $stock_limit = $variation ? $variation['stock'] : ($product['stock'] ?? null);
            $reserved_others = 0;
            foreach ($_SESSION['cart'] as $other_key => $other_item) {
                if ((int)$other_key === (int)$cart_key) {
                    continue;
                }
                $same_variation = (int)($other_item['variation_id'] ?? 0) === (int)($cart_item['variation_id'] ?? 0);
                if ((int)$other_item['product_id'] === $product_id && $same_variation) {
                    $reserved_others += (int)$other_item['quantity'];
                }
            }
            if ($stock_limit !== null && ($reserved_others + $new_quantity) > (int)$stock_limit) {
                $available = max(0, (int)$stock_limit - $reserved_others);
                echo json_encode([
                    'success' => false,
                    'message' => t('cart.stock_limit', ['stock' => $available]),
                    'available' => $available
                ]);
                exit;
            }
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
        echo json_encode(['success' => false, 'message' => t('cart.invalid_action')]);
        exit;
    }

    $total_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

    echo json_encode([
        'success' => true,
        'count' => $total_count,
        'new_quantity' => $new_quantity,
        'message' => t('cart.updated')
    ]);

} catch (Exception $e) {
    error_log("Update cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => t('error_generic')
    ]);
}

<?php
/**
 * Simplified Cart Operations
 * Handles all cart operations in one file
 */
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    $action = $_POST['action'] ?? '';
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Handle get_count action early (doesn't need product_id)
    if ($action === 'get_count') {
        $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
        echo json_encode([
            'success' => true,
            'count' => $cart_count
        ]);
        exit;
    }
    
    // For other actions, validate product_id
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    
    if (!$product_id || $product_id < 1) {
        throw new Exception('Invalid product ID');
    }
    
    $db = new Database();
    
    // Find cart item
    $cart_key = null;
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id) {
            $cart_key = $key;
            break;
        }
    }
    
    switch ($action) {
        case 'get_count':
            // Just return current cart count
            $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
            echo json_encode([
                'success' => true,
                'count' => $cart_count
            ]);
            exit;
            
        case 'increase':
            if ($cart_key === null) {
                throw new Exception('Item not in cart');
            }
            
            // Quick stock check
            $stmt = $db->conn->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $new_qty = $_SESSION['cart'][$cart_key]['quantity'] + 1;
            if ($product['stock'] !== null && $new_qty > $product['stock']) {
                throw new Exception('Stock limit reached');
            }
            
            $_SESSION['cart'][$cart_key]['quantity'] = $new_qty;
            $result_qty = $new_qty;
            break;
            
        case 'decrease':
            if ($cart_key === null) {
                throw new Exception('Item not in cart');
            }
            
            if ($_SESSION['cart'][$cart_key]['quantity'] > 1) {
                $_SESSION['cart'][$cart_key]['quantity']--;
                $result_qty = $_SESSION['cart'][$cart_key]['quantity'];
            } else {
                unset($_SESSION['cart'][$cart_key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                $result_qty = 0;
            }
            break;
            
        case 'remove':
            if ($cart_key !== null) {
                unset($_SESSION['cart'][$cart_key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
            }
            $result_qty = 0;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    
    echo json_encode([
        'success' => true,
        'count' => $cart_count,
        'new_quantity' => $result_qty ?? 0,
        'message' => ucfirst($action) . ' successful'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

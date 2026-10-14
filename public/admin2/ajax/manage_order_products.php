<?php
// File: admin/ajax/manage_order_products.php
// Handles AJAX requests for managing order products

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(dirname(dirname(__DIR__)));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/includes/helpers/cart.php');
require_once($root_dir . '/includes/shipping.php');

// Require admin login
require_admin_login();

header('Content-Type: application/json');

// Get POST data
$action = $_POST['action'] ?? '';
$order_id = intval($_POST['order_id'] ?? 0);

$db = new Database();

// CSRF token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    switch ($action) {
        case 'get_products':
            // Get all active products
            $products = $db->query(
                "SELECT id, name_en, price, stock, weight 
                 FROM products 
                 WHERE status = 1 
                 ORDER BY name_en ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
            break;

        case 'add_product':
            $product_id = intval($_POST['product_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);
            
            if (!$order_id || !$product_id || $quantity < 1) {
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }
            
            // Check if product exists and is active
            $product = $db->query(
                "SELECT id, name_en, price, stock, weight FROM products WHERE id = ? AND status = 1",
                [$product_id]
            )->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                echo json_encode(['success' => false, 'error' => 'Product not found or inactive']);
                exit;
            }
            
            // Check stock
            if ($product['stock'] !== null && $product['stock'] < $quantity) {
                echo json_encode([
                    'success' => false, 
                    'error' => "Insufficient stock. Available: {$product['stock']}"
                ]);
                exit;
            }
            
            // Check if product already exists in order
            $existing = $db->query(
                "SELECT id, quantity FROM order_items WHERE order_id = ? AND product_id = ?",
                [$order_id, $product_id]
            )->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                $db->query(
                    "UPDATE order_items SET quantity = ? WHERE id = ?",
                    [$new_quantity, $existing['id']]
                );
                $item_id = $existing['id'];
            } else {
                // Insert new item
                $db->query(
                    "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)",
                    [$order_id, $product_id, $quantity, $product['price']]
                );
                $item_id = $db->lastInsertId();
            }
            
            // Calculate new totals
            $totals = calculateOrderTotals($db, $order_id);
            
            // Update order totals
            updateOrderTotals($db, $order_id, $totals);
            
            // Get updated item info
            $item = $db->query(
                "SELECT oi.*, p.name_en, p.price, p.weight 
                 FROM order_items oi
                 LEFT JOIN products p ON oi.product_id = p.id
                 WHERE oi.id = ?",
                [$item_id]
            )->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Product added successfully',
                'item' => $item,
                'totals' => $totals
            ]);
            break;

        case 'remove_product':
            $item_id = intval($_POST['item_id'] ?? 0);
            
            if (!$order_id || !$item_id) {
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }
            
            // Delete item
            $db->query(
                "DELETE FROM order_items WHERE id = ? AND order_id = ?",
                [$item_id, $order_id]
            );
            
            // Calculate new totals
            $totals = calculateOrderTotals($db, $order_id);
            
            // Update order totals
            updateOrderTotals($db, $order_id, $totals);
            
            echo json_encode([
                'success' => true,
                'message' => 'Product removed successfully',
                'totals' => $totals
            ]);
            break;

        case 'update_quantity':
            $item_id = intval($_POST['item_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);
            
            if (!$order_id || !$item_id || $quantity < 1) {
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }
            
            // Get item and product info
            $item = $db->query(
                "SELECT oi.*, p.stock 
                 FROM order_items oi
                 LEFT JOIN products p ON oi.product_id = p.id
                 WHERE oi.id = ? AND oi.order_id = ?",
                [$item_id, $order_id]
            )->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                echo json_encode(['success' => false, 'error' => 'Item not found']);
                exit;
            }
            
            // Check stock
            if ($item['stock'] !== null && $item['stock'] < $quantity) {
                echo json_encode([
                    'success' => false,
                    'error' => "Insufficient stock. Available: {$item['stock']}"
                ]);
                exit;
            }
            
            // Update quantity
            $db->query(
                "UPDATE order_items SET quantity = ? WHERE id = ?",
                [$quantity, $item_id]
            );
            
            // Calculate new totals
            $totals = calculateOrderTotals($db, $order_id);
            
            // Update order totals
            updateOrderTotals($db, $order_id, $totals);
            
            // Get updated item
            $updated_item = $db->query(
                "SELECT oi.*, p.name_en, p.price, p.weight 
                 FROM order_items oi
                 LEFT JOIN products p ON oi.product_id = p.id
                 WHERE oi.id = ?",
                [$item_id]
            )->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Quantity updated successfully',
                'item' => $updated_item,
                'totals' => $totals
            ]);
            break;

        case 'calculate_totals':
            if (!$order_id) {
                echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
                exit;
            }
            
            $totals = calculateOrderTotals($db, $order_id);
            
            echo json_encode([
                'success' => true,
                'totals' => $totals
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Order products management error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ]);
}

/**
 * Calculate order totals including subtotal, shipping, and grand total
 */
function calculateOrderTotals($db, $order_id) {
    // Get order items
    $items = $db->query(
        "SELECT oi.*, p.price, p.weight 
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = ?",
        [$order_id]
    )->fetchAll(PDO::FETCH_ASSOC);
    
    $subtotal = 0;
    $total_weight = 0;
    
    foreach ($items as $item) {
        $price = $item['price'];
        $weight = $item['weight'] ?? 1;
        $quantity = $item['quantity'];
        
        $subtotal += $price * $quantity;
        $total_weight += $weight * $quantity;
    }
    
    // Get customer location for shipping calculation
    $order = $db->query(
        "SELECT o.*, c.country, c.city 
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.id
         WHERE o.id = ?",
        [$order_id]
    )->fetch(PDO::FETCH_ASSOC);
    
    $country = $order['country'] ?? 'United Arab Emirates';
    $city = $order['city'] ?? '';
    
    // Calculate shipping using the same logic as checkout
    $shipping = calculateShippingCost($country, $city, $total_weight);
    
    // Calculate grand total
    $grand_total = $subtotal + $shipping;
    
    return [
        'subtotal' => round($subtotal, 2),
        'shipping' => round($shipping, 2),
        'total' => round($grand_total, 2),
        'weight' => round($total_weight, 2),
        'items_count' => count($items)
    ];
}

/**
 * Update order totals in database
 */
function updateOrderTotals($db, $order_id, $totals) {
    $db->query(
        "UPDATE orders SET 
            total_amount = ?,
            shipping_aed = ?
         WHERE id = ?",
        [$totals['total'], $totals['shipping'], $order_id]
    );
}

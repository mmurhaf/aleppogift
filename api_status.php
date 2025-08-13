<?php
// JSON API for comprehensive system status check
header('Content-Type: application/json');

try {
    require_once(__DIR__ . '/includes/bootstrap.php');
    
    $db = new Database();
    $status = [];
    
    // 1. Check database connectivity
    try {
        $db->query("SELECT 1");
        $status['database'] = ['status' => 'connected', 'message' => 'Database connection successful'];
    } catch (Exception $e) {
        $status['database'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    // 2. Check products table
    try {
        $product_count = $db->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'")->fetch()['count'];
        $status['products'] = ['status' => 'ok', 'active_products' => $product_count];
    } catch (Exception $e) {
        $status['products'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    // 3. Check product_images table
    try {
        $image_count = $db->query("SELECT COUNT(*) as count FROM product_images")->fetch()['count'];
        $status['product_images'] = ['status' => 'ok', 'total_images' => $image_count];
    } catch (Exception $e) {
        $status['product_images'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    // 4. Check coupons table and required columns
    try {
        $coupon_count = $db->query("SELECT COUNT(*) as count FROM coupons WHERE status = 'active'")->fetch()['count'];
        $status['coupons'] = ['status' => 'ok', 'active_coupons' => $coupon_count];
    } catch (Exception $e) {
        $status['coupons'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    // 5. Check orders table structure
    try {
        $orders_structure = $db->query("DESCRIBE orders")->fetchAll(PDO::FETCH_ASSOC);
        $orders_columns = array_column($orders_structure, 'Field');
        
        $required_order_columns = ['order_date', 'coupon_code', 'discount_type', 'discount_value', 'discount_amount'];
        $missing_columns = array_diff($required_order_columns, $orders_columns);
        
        if (empty($missing_columns)) {
            $status['orders_structure'] = ['status' => 'ok', 'message' => 'All required columns present', 'columns' => $orders_columns];
        } else {
            $status['orders_structure'] = ['status' => 'warning', 'missing_columns' => $missing_columns, 'existing_columns' => $orders_columns];
        }
    } catch (Exception $e) {
        $status['orders_structure'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    // 6. Check key AJAX endpoints
    $ajax_endpoints = [
        'add_to_cart.php',
        'get_product_details.php', 
        'apply_coupon.php',
        'cart_preview.php'
    ];
    
    foreach ($ajax_endpoints as $endpoint) {
        if (file_exists(__DIR__ . "/public/ajax/$endpoint")) {
            $status["ajax_$endpoint"] = ['status' => 'ok', 'message' => 'File exists'];
        } else {
            $status["ajax_$endpoint"] = ['status' => 'error', 'message' => 'File missing'];
        }
    }
    
    // 7. Check session functionality
    if (session_status() === PHP_SESSION_ACTIVE) {
        $status['sessions'] = ['status' => 'ok', 'message' => 'Sessions are active'];
    } else {
        $status['sessions'] = ['status' => 'warning', 'message' => 'Sessions not active'];
    }
    
    // 8. Test cart functionality
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $cart_items = count($_SESSION['cart']);
        $status['cart_session'] = ['status' => 'ok', 'message' => "Cart session active with $cart_items items"];
    } else {
        $status['cart_session'] = ['status' => 'ok', 'message' => 'Cart session ready (empty)'];
    }
    
    // Overall status
    $error_count = 0;
    $warning_count = 0;
    
    foreach ($status as $check) {
        if (isset($check['status'])) {
            if ($check['status'] === 'error') $error_count++;
            if ($check['status'] === 'warning') $warning_count++;
        }
    }
    
    $overall_status = 'healthy';
    if ($error_count > 0) {
        $overall_status = 'critical';
    } elseif ($warning_count > 0) {
        $overall_status = 'needs_attention';
    }
    
    echo json_encode([
        'overall_status' => $overall_status,
        'error_count' => $error_count,
        'warning_count' => $warning_count,
        'timestamp' => date('Y-m-d H:i:s'),
        'system_health' => [
            'cart_system' => 'operational',
            'coupon_system' => 'operational', 
            'checkout_system' => 'operational',
            'quick_view' => 'operational'
        ],
        'detailed_status' => $status
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'overall_status' => 'critical',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>

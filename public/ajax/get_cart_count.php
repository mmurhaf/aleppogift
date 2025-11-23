<?php
// Ensure session is started first and only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../config/config.php');

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Get cart count from session
    $cart_count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
    
    echo json_encode([
        'success' => true,
        'count' => $cart_count,
        'session_id' => session_id(),
        'debug_info' => [
            'cart_items' => count($_SESSION['cart'] ?? []),
            'session_status' => session_status(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get cart count error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Error getting cart count'
    ]);
}
?>
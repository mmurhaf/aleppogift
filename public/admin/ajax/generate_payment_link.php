<?php
// File: public/admin/ajax/generate_payment_link.php
// Generate payment link for an order

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $root_dir = dirname(dirname(dirname(__DIR__)));
    require_once($root_dir . '/includes/session_helper.php');
    require_once($root_dir . '/config/config.php');
    require_once($root_dir . '/includes/Database.php');
    require_once($root_dir . '/includes/generate_payment_link.php');

    // Start session and check admin login
    start_session_safely();

    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access - please login']);
        exit;
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input: ' . json_last_error_msg()]);
        exit;
    }

    // Validate CSRF token
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Validate order ID
    if (!isset($input['order_id']) || !is_numeric($input['order_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }

    
    $orderId = (int)$input['order_id'];

    $db = new Database();
    
    // Fetch order details
    $order = $db->query("
        SELECT o.*, c.fullname, c.email, c.phone
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = :id
    ", ['id' => $orderId])->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Generate payment link
    $paymentLinkGenerator = new PaymentLinkGenerator($db);
    $paymentLink = $paymentLinkGenerator->generatePaymentLink($orderId, $order['total_amount']);
    
    // Prepare response
    echo json_encode([
        'success' => true,
        'payment_link' => $paymentLink,
        'order_id' => $orderId,
        'order_total' => $order['total_amount'],
        'customer_name' => $order['fullname'],
        'customer_email' => $order['email'],
        'customer_phone' => $order['phone'],
        'message' => 'Payment link generated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Payment link generation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
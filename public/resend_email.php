<?php
require_once('../includes/bootstrap.php');

// Set content type for JSON response
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get order ID from POST data
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Set global $db for email functions
    global $db;
    $db = new Database();
    
    // First check if the order exists
    $order_exists = $db->query("SELECT COUNT(*) as count FROM orders WHERE id = :id", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);
    
    if ($order_exists['count'] == 0) {
        error_log("❌ Order #$order_id does not exist");
        echo json_encode(['success' => false, 'message' => "Order #$order_id does not exist. Please check the order number."]);
        exit;
    }
    
    // Fetch the order details
    $order = $db->query("
        SELECT 
            o.*, 
            c.fullname AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            c.address AS customer_address,
            c.city AS customer_city,
            c.country AS customer_country
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = :id
    ", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("❌ Order #$order_id found but missing customer data");
        echo json_encode(['success' => false, 'message' => 'Order found but customer information is missing']);
        exit;
    }

    $email = $order['customer_email'];
    if (empty($email)) {
        error_log("❌ No email address for Order #$order_id");
        echo json_encode(['success' => false, 'message' => 'No email address associated with this order']);
        exit;
    }

    // Prepare customer data for enhanced email
    $customer_data = [
        'fullname' => $order['customer_name'] ?? '',
        'phone' => $order['customer_phone'] ?? '',
        'address' => $order['customer_address'] ?? '',
        'city' => $order['customer_city'] ?? '',
        'country' => $order['customer_country'] ?? ''
    ];

    // Check if PDF invoice exists
    $invoicePath = "../invoice/invoice_{$order_id}.pdf";
    $fullPath = file_exists($invoicePath) ? $invoicePath : null;

    $fullname = $order['customer_name'] ?? '';
    
    error_log("📧 Attempting to resend email for Order #$order_id to $email");
    
    // Try to send email with timeout protection
    set_time_limit(30); // Limit execution time to 30 seconds
    
    $status = false;
    $error_message = '';
    
    try {
        // Start with simple email method that's more likely to work
        error_log("📧 Trying sendOrderEmail for Order #$order_id");
        $status = sendOrderEmail($email, $order_id, $fullname);
        
        if (!$status && function_exists('sendEnhancedOrderEmail')) {
            error_log("📧 Basic email failed, trying enhanced email for Order #$order_id");
            $status = sendEnhancedOrderEmail($email, $order_id, $fullname, $customer_data, null);
        }
        
    } catch (Exception $email_error) {
        error_log("❌ Email sending exception: " . $email_error->getMessage());
        $error_message = "Email system error: " . $email_error->getMessage();
    }

    if ($status) {
        error_log("✅ Email resent successfully for Order #$order_id to $email");
        echo json_encode([
            'success' => true, 
            'message' => 'Email has been resent successfully to ' . $email
        ]);
    } else {
        $final_message = 'Failed to send email. ';
        if (!empty($error_message)) {
            $final_message .= $error_message;
        } else {
            $final_message .= 'This may be due to email server configuration. Please contact support.';
        }
        
        error_log("❌ Failed to resend email for Order #$order_id to $email. Error: $final_message");
        echo json_encode([
            'success' => false, 
            'message' => $final_message
        ]);
    }

} catch (Exception $e) {
    error_log("❌ Exception in resend_email.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing the request: ' . $e->getMessage()
    ]);
}
?>

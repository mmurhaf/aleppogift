<?php
// File: public/admin/ajax/create_quick_payment.php
// Create a quick payment link with minimal order

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    $root_dir = dirname(dirname(dirname(__DIR__)));
    
    if (!file_exists($root_dir . '/includes/session_helper.php')) {
        throw new Exception('Session helper not found');
    }
    
    require_once($root_dir . '/includes/session_helper.php');
    require_once($root_dir . '/config/config.php');
    require_once($root_dir . '/includes/Database.php');
    require_once($root_dir . '/includes/generate_payment_link.php');

    // Start session and check admin login
    start_session_safely();
} catch (Exception $e) {
    error_log("Quick payment initialization error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System initialization error: ' . $e->getMessage()]);
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    error_log("Quick payment access denied - not logged in");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access - please login']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    // Validate CSRF token
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid CSRF token');
    }

    // Validate required fields
    if (!isset($input['amount']) || !isset($input['customer_name']) || !isset($input['customer_phone'])) {
        throw new Exception('Missing required fields');
    }

    $amount = floatval($input['amount']);
    $customerName = trim($input['customer_name']);
    $customerPhone = trim($input['customer_phone']);
    $customerEmail = isset($input['customer_email']) ? trim($input['customer_email']) : '';
    $description = isset($input['description']) ? trim($input['description']) : 'Quick Payment';

    // Validate amount
    if ($amount < 1) {
        throw new Exception('Amount must be at least 1 AED');
    }

    // Validate customer name
    if (strlen($customerName) < 2) {
        throw new Exception('Please provide a valid customer name');
    }

    // Validate phone
    if (strlen($customerPhone) < 8) {
        throw new Exception('Please provide a valid phone number');
    }
} catch (Exception $e) {
    error_log("Quick payment validation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getPdo();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if customer exists by phone
    $existingCustomer = $db->query("
        SELECT id FROM customers 
        WHERE phone = :phone
        LIMIT 1
    ", ['phone' => $customerPhone])->fetch(PDO::FETCH_ASSOC);
    
    if ($existingCustomer) {
        // Update existing customer
        $customerId = $existingCustomer['id'];
        $db->query("
            UPDATE customers 
            SET fullname = :name,
                email = :email
            WHERE id = :id
        ", [
            'name' => $customerName,
            'email' => $customerEmail ?: null,
            'id' => $customerId
        ]);
    } else {
        // Create new customer
        $db->query("
            INSERT INTO customers (fullname, email, phone, country)
            VALUES (:name, :email, :phone, 'UAE')
        ", [
            'name' => $customerName,
            'email' => $customerEmail ?: null,
            'phone' => $customerPhone
        ]);
        $customerId = $pdo->lastInsertId();
    }
    
    // Create order
    $orderDate = date('Y-m-d H:i:s');
    $paymentReference = 'QP-' . strtoupper(substr(md5($customerPhone . time()), 0, 8));
    
    $db->query("
        INSERT INTO orders (
            customer_id,
            total_amount,
            shipping_aed,
            payment_method,
            payment_status,
            payment_reference,
            order_date
        ) VALUES (
            :customer_id,
            :total_amount,
            0,
            'online_payment',
            'pending',
            :payment_reference,
            :order_date
        )
    ", [
        'customer_id' => $customerId,
        'total_amount' => $amount,
        'payment_reference' => $paymentReference,
        'order_date' => $orderDate
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // Create a dummy order item for the payment
    $db->query("
        INSERT INTO order_items (
            order_id,
            product_id,
            product_name,
            quantity,
            price,
            total
        ) VALUES (
            :order_id,
            0,
            :product_name,
            1,
            :price,
            :total
        )
    ", [
        'order_id' => $orderId,
        'product_name' => $description,
        'price' => $amount,
        'total' => $amount
    ]);
    
    // Generate payment link
    $paymentLinkGenerator = new PaymentLinkGenerator($db);
    $paymentLink = $paymentLinkGenerator->generatePaymentLink($orderId, $amount);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'payment_link' => $paymentLink,
        'order_id' => $orderId,
        'customer_id' => $customerId,
        'amount' => $amount,
        'payment_reference' => $paymentReference,
        'message' => 'Payment link created successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Quick payment creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create payment link: ' . $e->getMessage()
    ]);
}

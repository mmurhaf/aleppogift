<?php
// File: admin/ajax/process_admin_order.php
// Process admin-created orders

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$root_dir = dirname(dirname(dirname(__DIR__)));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/includes/generate_invoice_pdf.php');
require_once($root_dir . '/includes/generate_payment_link.php');
require_once($root_dir . '/includes/send_email_simple.php');

// Require admin login
require_admin_login();

header('Content-Type: application/json');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate CSRF token
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    // Validate required fields
    if (empty($input['customer']) || empty($input['products'])) {
        throw new Exception('Customer and products are required');
    }

    if (empty($input['shipping_method']) || empty($input['payment_method'])) {
        throw new Exception('Shipping and payment methods are required');
    }

    $db = new Database();
    $db->getPdo()->beginTransaction();

    // Step 1: Create or get customer
    $customerId = null;
    
    if (isset($input['customer']['id']) && $input['customer']['id'] > 0) {
        // Existing customer
        $customerId = (int)$input['customer']['id'];
        
        // Verify customer exists
        $customer = $db->query("SELECT id FROM customers WHERE id = ?", [$customerId])->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            throw new Exception('Customer not found');
        }
    } else {
        // Create new customer
        $customerData = $input['customer'];
        
        // Check if customer with this email already exists
        $existingCustomer = $db->query(
            "SELECT id FROM customers WHERE email = ?", 
            [$customerData['email']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if ($existingCustomer) {
            $customerId = $existingCustomer['id'];
        } else {
            // Insert new customer
            $db->query("
                INSERT INTO customers (fullname, email, phone, address, city, country)
                VALUES (:name, :email, :phone, :address, :city, :country)
            ", [
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'phone' => $customerData['phone'],
                'address' => $customerData['address'],
                'city' => $customerData['city'],
                'country' => $customerData['country']
            ]);
            
            // Get the new customer ID
            $customerId = $db->getPdo()->lastInsertId();
        }
    }

    // Step 2: Calculate order totals
    $subtotal = 0;
    $totalWeight = 0;
    
    foreach ($input['products'] as $product) {
        $subtotal += $product['price'] * $product['quantity'];
        
        // Get product weight
        $productData = $db->query(
            "SELECT weight FROM products WHERE id = ? AND status = 1", 
            [$product['id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if ($productData && $productData['weight']) {
            $totalWeight += $productData['weight'] * $product['quantity'];
        }
    }

    $shippingCost = floatval($input['shipping_cost']);
    $totalAmount = $subtotal + $shippingCost;

    // Step 3: Create order
    $db->query("
        INSERT INTO orders (
            customer_id, 
            order_date, 
            total_amount, 
            payment_status, 
            payment_method,
            shipping_method,
            shipping_aed,
            total_weight,
            note
        ) VALUES (
            :customer_id,
            NOW(),
            :total_amount,
            :payment_status,
            :payment_method,
            :shipping_method,
            :shipping_cost,
            :total_weight,
            :note
        )
    ", [
        'customer_id' => $customerId,
        'total_amount' => $totalAmount,
        'payment_status' => $input['payment_status'],
        'payment_method' => $input['payment_method'],
        'shipping_method' => $input['shipping_method'],
        'shipping_cost' => $shippingCost,
        'total_weight' => $totalWeight,
        'note' => $input['notes'] ?? ''
    ]);

    $orderId = $db->getPdo()->lastInsertId();

    // Step 4: Add order items
    foreach ($input['products'] as $product) {
        // Verify product exists and has stock
        $productData = $db->query(
            "SELECT id, stock FROM products WHERE id = ? AND status = 1", 
            [$product['id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$productData) {
            throw new Exception('Product ID ' . $product['id'] . ' not found or inactive');
        }
        
        if ($productData['stock'] < $product['quantity']) {
            throw new Exception('Insufficient stock for product ID ' . $product['id']);
        }

        // Insert order item
        $db->query("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (:order_id, :product_id, :quantity, :price)
        ", [
            'order_id' => $orderId,
            'product_id' => $product['id'],
            'quantity' => $product['quantity'],
            'price' => $product['price']
        ]);

        // Update product stock
        $db->query("
            UPDATE products 
            SET stock = stock - :quantity 
            WHERE id = :product_id
        ", [
            'quantity' => $product['quantity'],
            'product_id' => $product['id']
        ]);
    }

    // Step 5: Generate PDF Invoice
    $invoiceGenerator = new PDFInvoiceGenerator();
    $invoiceResult = $invoiceGenerator->generateInvoicePDF($orderId);
    
    if ($invoiceResult['success']) {
        // Update order with invoice path
        $db->query("
            UPDATE orders 
            SET invoice_pdf = :invoice_pdf 
            WHERE id = :order_id
        ", [
            'invoice_pdf' => basename($invoiceResult['file_path']),
            'order_id' => $orderId
        ]);
    }

    // Commit transaction
    $db->getPdo()->commit();

    // Step 6: Send payment link if requested
    $paymentLinkSent = false;
    if (!empty($input['send_payment_link']) && $input['payment_status'] === 'pending') {
        // Get customer details
        $customer = $db->query(
            "SELECT * FROM customers WHERE id = ?", 
            [$customerId]
        )->fetch(PDO::FETCH_ASSOC);

        // Generate payment link
        $paymentLinkGenerator = new PaymentLinkGenerator($db);
        $paymentLink = $paymentLinkGenerator->generatePaymentLink($orderId, $totalAmount);

        // Send email with payment link and PDF invoice
        $emailSent = sendPaymentLinkEmail(
            $customer['email'],
            $customer['fullname'],
            $orderId,
            $paymentLink,
            $invoiceResult['file_path'] ?? null
        );

        $paymentLinkSent = $emailSent;
        
        error_log("Payment link sent for order #$orderId: " . ($emailSent ? 'Success' : 'Failed'));
    }

    // Return success
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'customer_id' => $customerId,
        'total_amount' => $totalAmount,
        'invoice_generated' => $invoiceResult['success'] ?? false,
        'payment_link_sent' => $paymentLinkSent,
        'message' => 'Order created successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->getPdo()->inTransaction()) {
        $db->getPdo()->rollBack();
    }
    
    error_log("Admin order creation error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

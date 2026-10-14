<?php
require_once('../includes/bootstrap.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order ID provided']);
    exit;
}

try {
    $result = fulfillCustomerInvoiceRequest($order_id);
    if (empty($result['success'])) {
        $status = (($result['error'] ?? '') === 'You cannot request an invoice for this order') ? 403 : 400;
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error during invoice generation',
        ]);
        exit;
    }

    if (!empty($result['admin_only'])) {
        echo json_encode([
            'success' => true,
            'admin_only' => true,
            'message' => 'Your request was sent to the store. The invoice is issued after you pay on delivery.',
            'order_id' => $order_id,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => !empty($result['emailed'])
            ? 'Invoice generated and sent to your email'
            : 'Invoice generated successfully',
        'order_id' => $order_id,
        'invoice_number' => $result['invoice_number'],
        'download_url' => $result['download_url'],
        'emailed' => !empty($result['emailed']),
    ]);
} catch (Throwable $e) {
    error_log("Invoice API Error for Order #$order_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ]);
}

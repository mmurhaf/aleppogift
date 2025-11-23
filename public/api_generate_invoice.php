<?php
// Simple API endpoint to generate invoice PDFs
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in JSON response

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get order ID from POST data
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order ID provided']);
    exit;
}

try {
    require_once('../includes/generate_invoice_pdf.php');
    
    $generator = new PDFInvoiceGenerator();
    $result = $generator->generateInvoicePDF($order_id);
    
    if ($result['success']) {
        // Check if file actually exists
        if (file_exists($result['file_path'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Invoice generated successfully',
                'order_id' => $order_id,
                'invoice_number' => $result['invoice_number'],
                'file_path' => $result['file_path'],
                'file_size' => filesize($result['file_path']),
                'download_url' => "download_invoice.php?id=$order_id"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invoice generation reported success but file was not created'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error during invoice generation'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Invoice API Error for Order #$order_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
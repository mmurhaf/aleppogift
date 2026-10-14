<?php
// File: admin/generate_invoice.php
// This file provides admin access to invoice generation with authentication check

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/generate_invoice_pdf.php');

// Require admin login before allowing access
require_admin_login();

// Validate that order ID is provided
if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = intval($_GET['id']);

// Generate invoice using PDFInvoiceGenerator class
$generator = new PDFInvoiceGenerator();

try {
    $result = $generator->generateInvoicePDF($order_id);
    
    if ($result['success'] && file_exists($result['file_path'])) {
        // Serve the PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice_' . $order_id . '.pdf"');
        header('Content-Length: ' . filesize($result['file_path']));
        readfile($result['file_path']);
        exit;
    } else {
        throw new Exception('Invoice file not generated');
    }
} catch (Exception $e) {
    echo "<div style='padding: 20px; text-align: center;'>";
    echo "<h3>Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='orders.php' style='color: #007bff; text-decoration: none;'>Back to Orders</a>";
    echo "</div>";
}
?>

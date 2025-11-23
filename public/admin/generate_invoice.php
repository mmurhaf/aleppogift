<?php
// File: admin/generate_invoice.php
// This file provides admin access to invoice generation with authentication check
// Output is identical to includes/generate_invoice.php

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/generate_invoice.php');

// Require admin login before allowing access
require_admin_login();

// Validate that order ID is provided
if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = intval($_GET['id']);

// Generate invoice using the InvoiceGenerator class from includes/generate_invoice.php
$generator = new InvoiceGenerator();

try {
    // Output is exactly the same as includes/generate_invoice.php
    echo $generator->generateInvoice($order_id);
} catch (Exception $e) {
    echo "<div style='padding: 20px; text-align: center;'>";
    echo "<h3>Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='orders.php' style='color: #007bff; text-decoration: none;'>Back to Orders</a>";
    echo "</div>";
}
?>

<?php
ob_start();
require_once('../includes/bootstrap.php');

// Bootstrap already loads all necessary components
$email = 'mmurhaf1@gmail.com';
$order_id = 23;

// 🔥 Use absolute path to invoice folder
$invoiceDir = realpath(__DIR__ . '/../invoice');
$invoiceFile = "invoice_$order_id.pdf";
$fullPath = "$invoiceDir/$invoiceFile";

// ✅ Make sure the file exists
if (!file_exists($fullPath)) {
    echo "❌ Invoice PDF not found at $fullPath";
    exit;
}

// Send email
$success = sendInvoiceEmail($email, $order_id, $fullPath);

// Output result
echo json_encode([
    'status' => $success ? '✅ Email sent' : '❌ Email sending failed',
    'full_path' => $fullPath,
    'public_path' => "invoice/$invoiceFile",
    'web_url' => SITE_URL . "/invoice/$invoiceFile"
]);

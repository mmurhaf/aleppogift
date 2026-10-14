<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

// Require admin authentication
require_admin_login();

$db = new Database();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid order ID.");
}

$order_id = (int)$_GET['id'];

// Check if order exists and is paid
$order = $db->query("SELECT * FROM orders WHERE id = :id", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die("Order not found.");
}

if ($order['payment_status'] !== 'paid') {
    die("Invoice only available for paid orders.");
}

$invoice_path = "../invoice/invoice_$order_id.pdf";

if (!file_exists($invoice_path)) {
    die("Invoice file not found.");
}

// Serve PDF file securely
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice_' . $order_id . '.pdf"');
header('Content-Length: ' . filesize($invoice_path));
readfile($invoice_path);
exit;

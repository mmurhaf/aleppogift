<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_customer();

$orderId = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);
if ($orderId <= 0 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: account_orders.php');
    exit;
}

$result = fulfillCustomerInvoiceRequest($orderId);
$flag = !empty($result['success']) ? 'sent' : 'failed';
header('Location: account_order.php?id=' . $orderId . '&invoice=' . $flag);
exit;

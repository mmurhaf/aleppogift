<?php
session_start();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_phone = '';
$user_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_phone = trim($_POST['phone'] ?? '');
    $user_email = trim($_POST['email'] ?? '');
}

if (!$order_id) {
    http_response_code(400);
    exit('Invalid request.');
}

require_once('../config/config.php');
require_once('../includes/Database.php');

$db = new Database();

// Join orders with customers to get customer contact info
$order = $db->query("
    SELECT o.*, c.phone AS customer_phone, c.email AS customer_email
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.id = :id
    LIMIT 1
", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$invoice_file = "../invoice/invoice_{$order_id}.pdf";

// ✅ Allow access if session proves ownership
if (!empty($_SESSION['valid_invoice_' . $order_id])) {
    if (file_exists($invoice_file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice.pdf"');
        readfile($invoice_file);
        exit;
    } else {
        http_response_code(404);
        exit('Invoice file not found.');
    }
}else 

// ✅ Allow access if phone/email matches customer info
if (
    ($user_phone && $user_phone === $order['customer_phone']) ||
    ($user_email && $user_email === $order['customer_email'])
) {
    if (file_exists($invoice_file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice.pdf"');
        readfile($invoice_file);
        exit;
    } else {
        http_response_code(404);
        exit('Invoice file not found.');
    }
       

// ❌ Access denied – show validation form

} else {
     http_response_code(404);
     ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Invoice Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2 class="mb-4">Verify to Access Your Invoice</h2>
    <p class="text-danger">To protect customer privacy, please confirm your phone or email used during checkout.</p>
    <form action="download_invoice.php?id=<?= htmlspecialchars($order_id) ?>" method="post" target="_blank">
        <input type="text" name="phone" placeholder="Phone used in order" class="form-control my-2">
        <input type="email" name="email" placeholder="Or Email used in order" class="form-control my-2">
        <button type="submit" class="btn btn-primary">Download Invoice</button>
    </form>
    <?php
        exit('Invoice file not found.');
    }
    if (file_exists($invoice_file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice.pdf"');
        header('Content-Length: ' . filesize($invoice_file));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Content-Description: File Transfer');
    }
?>
</body>
</html>

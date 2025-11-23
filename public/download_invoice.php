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
        header('Content-Disposition: attachment; filename="invoice_' . $order_id . '.pdf"');
        header('Content-Length: ' . filesize($invoice_file));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        readfile($invoice_file);
        exit;
    } else {
        http_response_code(404);
        exit('Invoice file not found.');
    }
}

// ✅ Allow access if phone/email matches customer info  
if (
    ($user_phone && $user_phone === $order['customer_phone']) ||
    ($user_email && $user_email === $order['customer_email'])
) {
    // Set session to allow future access
    $_SESSION['valid_invoice_' . $order_id] = true;
    
    if (file_exists($invoice_file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice_' . $order_id . '.pdf"');
        header('Content-Length: ' . filesize($invoice_file));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        readfile($invoice_file);
        exit;
    } else {
        http_response_code(404);
        exit('Invoice file not found.');
    }
}

// ❌ Access denied – show validation form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Invoice Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 500px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h2 { color: #d4af37; }
        .verification-form { background: #f8f9fa; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #d4af37; border-color: #d4af37; }
        .btn-primary:hover { background-color: #b8941f; border-color: #b8941f; }
    </style>
</head>
<body class="container py-5">
    <div class="header">
        <h2>📄 Invoice Access Verification</h2>
        <p class="text-muted">Order #<?= htmlspecialchars($order_id) ?></p>
    </div>
    
    <div class="verification-form">
        <div class="alert alert-info">
            <strong>📧 Security Notice:</strong> To protect customer privacy, please confirm your contact information used during checkout.
        </div>
        
        <form action="download_invoice.php?id=<?= htmlspecialchars($order_id) ?>" method="post">
            <div class="mb-3">
                <label for="phone" class="form-label">📱 Phone Number (used during checkout)</label>
                <input type="text" id="phone" name="phone" placeholder="Enter your phone number" class="form-control" value="<?= htmlspecialchars($user_phone) ?>">
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">📧 Email Address (used during checkout)</label>
                <input type="email" id="email" name="email" placeholder="Enter your email address" class="form-control" value="<?= htmlspecialchars($user_email) ?>">
            </div>
            
            <div class="mb-3">
                <small class="text-muted">You only need to provide one of the above (phone OR email).</small>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">📥 Download Invoice PDF</button>
        </form>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($user_phone || $user_email)): ?>
            <div class="alert alert-danger mt-3">
                <strong>❌ Verification Failed:</strong> The provided information doesn't match our records for this order.
            </div>
        <?php endif; ?>
        
        <div class="mt-4 text-center">
            <small class="text-muted">
                Having trouble? Contact us at <a href="mailto:sales@aleppogift.com">sales@aleppogift.com</a>
            </small>
        </div>
    </div>
</body>
</html>

<?php
require_once('../includes/bootstrap.php');

// Bootstrap already loads all necessary components
$db = new Database();
$order_id = isset($_GET['order']) ? (int)$_GET['order'] : 0;

if (!$order_id) {
    header("Location: index.php");
    exit;
}

// Fetch the order
$order = $db->query("
    SELECT 
        o.*, 
        c.fullname AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,
        c.address AS customer_address,
        c.city AS customer_city,
        c.country AS customer_country
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.id = :id
", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: index.php");
    exit;
}

// Update status to paid if not already
if ($order['payment_status'] !== 'paid') {
    $db->query("UPDATE orders SET payment_status = 'paid', updated_at = NOW() WHERE id = :id", ['id' => $order_id]);
}

// Generate invoice
require_once '../includes/generate_invoice.php';
$invoicePath = "../invoice/invoice_{$order_id}.pdf";
$_SESSION['valid_invoice_' . $order_id] = true;





// In your POST handler, after creating the real order:
if (isset($_SESSION['temp_order_id'])) {
    // Delete the temporary quotation order
    $db->query("DELETE FROM orders WHERE id = :id", ['id' => $_SESSION['temp_order_id']]);
    unset($_SESSION['temp_order_id']);
    
    // Delete the temporary quotation file
    if (file_exists($publicQuotationPath)) {
        unlink($publicQuotationPath);
    }
}

$_SESSION['valid_invoice_' . $order_id] = true;

// --- Ziina payment ---
$payment_method = $order['payment_method'] ?? '';
$grandTotal = $order['total_amount'] ?? 0;
$fullname = $order['customer_name'] ?? '';
$email = $order['customer_email'] ?? '';

if ($payment_method === 'Ziina') {
    send_confirmation($order_id, $fullname, $grandTotal, $payment_method, $email);
}

// Clear session data
unset($_SESSION['cart']);
unset($_SESSION['temp_order_id']);
unset($_SESSION['discount_amount']);
unset($_SESSION['payment_method']);
unset($_SESSION['order_id']); 
unset($_SESSION['grandTotal']); 
unset($_SESSION['fullname']);
unset($_SESSION['email']); 

// Clear any temporary order session data
if (isset($_SESSION['temp_order'])) {
    unset($_SESSION['temp_order']);
}

// --- Confirmation function ---
function send_confirmation($order_id, $fullname, $grandTotal, $payment_method, $email) {
        sendAdminWhatsApp($order_id, $fullname, $grandTotal, $payment_method);

        ob_start();
        $invoiceInfo = require('../includes/generate_invoice.php');
        ob_end_clean();

        $fullPath = $invoiceInfo['full_path'] ?? '';

    // Send order confirmation email
        if (!empty($fullPath) && file_exists($fullPath)) {
            $status = sendInvoiceEmail($email, $order_id, $fullPath);
            if (!$status) {
                error_log("❌ Failed to send email for Order #$order_id to $email.");
            }
        } else {
            error_log("⚠️ PDF path missing or file not found for Order #$order_id.");
        }

        $_SESSION['cart'] = [];
        if (ob_get_level()) ob_end_clean();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
     <!--<link rel="stylesheet" href="assets/css/style.css">-->
	<link rel="stylesheet" href="assets/css/index.css">
	<link rel="stylesheet" href="assets/css/enhanced-design.css">
	<link rel="stylesheet" href="assets/css/components.css">
	<link rel="stylesheet" href="assets/css/ui-components.css">
	
	<!-- Google Fonts for Enhanced Typography -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

</head>
<body>

    <!-- Main Content -->
    <style>
        :root {
            --primary-color: #4e6bff;
            --secondary-color: #f8f9fa;
            --success-color: #28a745;
            --light-text: #6c757d;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .thank-you-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
            padding: 3rem 2rem;
            margin: 2rem auto;
            max-width: 800px;
        }
        
        .thank-you-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), #8a2be2);
        }
        
        .confirmation-icon {
            font-size: 5rem;
            color: var(--success-color);
            margin-bottom: 1.5rem;
            animation: bounce 1s ease;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-20px);}
            60% {transform: translateY(-10px);}
        }
        
        .thank-you-header {
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }
        
        .order-summary {
            background: var(--secondary-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin: 2.5rem 0;
            border-left: 4px solid var(--primary-color);
        }
        
        .order-summary h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .order-summary h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--primary-color);
        }
        
        .order-detail {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-detail:last-child {
            border-bottom: none;
        }
        
        .order-detail-label {
            font-weight: 500;
            color: var(--light-text);
        }
        
        .order-detail-value {
            font-weight: 600;
        }
        
        .btn-download {
            background: #dc3545;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-block;
            margin: 1rem 0;
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }
        
        .btn-download:hover {
            background: #c82333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d6;
            color: white;
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .whats-next {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid #eee;
        }
        
        .whats-next h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .steps {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 1rem;
            background: var(--secondary-color);
            border-radius: var(--border-radius);
            position: relative;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-weight: bold;
        }
        
        .step-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .thank-you-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .steps {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include('../includes/header.php'); ?>

    <main class="thank-you-container">
        <div class="text-center py-4">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="thank-you-header">Thank You For Your Order!</h1>
            <p class="lead">Your payment has been successfully processed. We've sent a confirmation to <strong><?= htmlspecialchars($order['customer_email']); ?></strong></p>
            <p class="text-muted">Order ID: #<?= $order['id']; ?></p>
        </div>

        <div class="order-summary">
            <h3 class="h5">Order Summary</h3>
            <div class="order-detail">
                <span class="order-detail-label">Order Number:</span>
                <span class="order-detail-value">#<?= $order['id']; ?></span>
            </div>
            <div class="order-detail">
                <span class="order-detail-label">Date:</span>
                <span class="order-detail-value"><?= date('F j, Y', strtotime($order['order_date'])); ?></span>
            </div>
            <div class="order-detail">
                <span class="order-detail-label">Customer Name:</span>
                <span class="order-detail-value"><?= htmlspecialchars($order['customer_name']); ?></span>
            </div>
            <div class="order-detail">
                <span class="order-detail-label">Payment Method:</span>
                <span class="order-detail-value"><?= htmlspecialchars($order['payment_method']); ?></span>
            </div>
            <div class="order-detail">
                <span class="order-detail-label">Total Amount:</span>
                <span class="order-detail-value text-success fw-bold">AED <?= number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>

        <div class="whats-next">
            <h4 class="h5">What's Next?</h4>
            <p>Your order is being processed and will be shipped soon. Here's what to expect:</p>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-title">Order Processing</div>
                    <div class="step-desc">We're preparing your items for shipment</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-title">Shipping</div>
                    <div class="step-desc">Your order will be dispatched within 1-2 business days</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-title">Delivery</div>
                    <div class="step-desc">Expected delivery in 3-5 business days</div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <?php if (file_exists("../invoice/invoice_{$order_id}.pdf")): ?>
                <a href="download_invoice.php?id=<?= $order_id ?>" target="_blank" class="btn btn-download">
                    <i class="fas fa-file-pdf me-2"></i> Download Invoice
                </a>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Invoice is being generated. Please refresh the page in a moment.
                </div>
            <?php endif; ?>
            <div class="mt-4">
                <p class="mb-3">Need help with your order?</p>
                <a href="contact.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-headset me-2"></i> Contact Support
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i> Back to Home
                </a>
            </div>
        </div>
    </main>
    
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/main.js"></script>
	<script src="assets/js/enhanced-main.js"></script>
</body>
</html>
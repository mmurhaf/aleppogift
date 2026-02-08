<?php
// File: public/payment.php
// Secure payment page for order completion

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('../config/config.php');
require_once('../includes/Database.php');
require_once('../includes/generate_payment_link.php');

$db = new Database();
$paymentLinkGen = new PaymentLinkGenerator($db);

// Get order ID and token from URL
$orderId = isset($_GET['order']) ? (int)$_GET['order'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

$error = null;
$order = null;
$customer = null;
$orderItems = [];

// Validate order and token
if ($orderId <= 0 || empty($token)) {
    $error = "Invalid payment link. Please contact support.";
} else {
    // Verify token
    if (!$paymentLinkGen->verifyPaymentToken($orderId, $token)) {
        $error = "This payment link has expired or is invalid. Please contact support.";
    } else {
        // Get order details
        try {
            $order = $db->query("
                SELECT o.*, c.*
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.id = ?
            ", [$orderId])->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                $error = "Order not found.";
            } elseif ($order['payment_status'] === 'paid') {
                $error = "This order has already been paid.";
            } else {
                // Get order items
                $orderItems = $db->query("
                    SELECT 
                        oi.*,
                        p.name_en,
                        p.name_ar,
                        (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as image_path
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ", [$orderId])->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Payment page error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
}

// Handle payment processing (if form submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $order) {
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    if ($paymentMethod === 'cod') {
        // For COD, just confirm the order
        try {
            $db->query("
                UPDATE orders 
                SET payment_method = 'COD',
                    payment_status = 'pending',
                    updated_at = NOW()
                WHERE id = ?
            ", [$orderId]);
            
            header("Location: thankyou.php?order_id=" . $orderId);
            exit;
        } catch (Exception $e) {
            error_log("COD confirmation error: " . $e->getMessage());
            $error = "Failed to confirm order. Please try again.";
        }
    } elseif ($paymentMethod === 'online') {
        // Redirect to payment gateway
        // For now, redirect to a placeholder or integrate with actual payment gateway
        header("Location: checkout.php?order_id=" . $orderId . "&token=" . $token);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - Order #<?php echo $orderId; ?> - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .payment-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .payment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .payment-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .payment-body {
            padding: 2rem;
        }
        
        .order-summary {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 1rem;
        }
        
        .payment-method-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method-card:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .payment-method-card.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .payment-method-card input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        
        .total-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 1rem 3rem;
            font-size: 1.2rem;
            border-radius: 50px;
            font-weight: bold;
            width: 100%;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .error-container {
            text-align: center;
            padding: 3rem;
        }
        
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        
        .invoice-link {
            display: inline-block;
            margin-top: 1rem;
            color: #667eea;
            text-decoration: none;
        }
        
        .invoice-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-card">
            <?php if ($error): ?>
                <div class="payment-header">
                    <h1><i class="fas fa-exclamation-triangle"></i> Payment Error</h1>
                </div>
                <div class="payment-body">
                    <div class="error-container">
                        <div class="error-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3>Oops! Something went wrong</h3>
                        <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
                        <a href="<?php echo SITE_URL; ?>" class="btn btn-primary mt-3">
                            <i class="fas fa-home me-2"></i> Back to Home
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="payment-header">
                    <h1><i class="fas fa-shopping-bag me-2"></i> Complete Your Order</h1>
                    <p class="mb-0">Order #<?php echo $orderId; ?></p>
                </div>
                
                <div class="payment-body">
                    <!-- Customer Info -->
                    <div class="mb-4">
                        <h5><i class="fas fa-user me-2"></i> Customer Information</h5>
                        <div class="bg-light p-3 rounded">
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['fullname']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                            <p class="mb-0"><strong>City:</strong> <?php echo htmlspecialchars($order['city'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="mb-4">
                        <h5><i class="fas fa-box me-2"></i> Order Items</h5>
                        <?php foreach ($orderItems as $item): ?>
                            <div class="product-item">
                                <?php if ($item['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" class="product-image" alt="<?php echo htmlspecialchars($item['name_en']); ?>">
                                <?php else: ?>
                                    <div class="product-image bg-secondary d-flex align-items-center justify-content-center">
                                        <i class="fas fa-image text-white"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($item['name_en']); ?></strong><br>
                                    <small class="text-muted">Quantity: <?php echo $item['quantity']; ?> × AED <?php echo number_format($item['price'], 2); ?></small>
                                </div>
                                <div>
                                    <strong>AED <?php echo number_format($item['quantity'] * $item['price'], 2); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <?php 
                        $aed_to_usd = 0.27;
                        $subtotal = $order['total_amount'] - ($order['shipping_aed'] ?? 0);
                        $subtotal_usd = $subtotal * $aed_to_usd;
                        $shipping_usd = ($order['shipping_aed'] ?? 0) * $aed_to_usd;
                        $total_usd = $order['total_amount'] * $aed_to_usd;
                        ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong>AED <?php echo number_format($subtotal, 2); ?> ($<?php echo number_format($subtotal_usd, 2); ?>)</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping:</span>
                            <strong>AED <?php echo number_format($order['shipping_aed'] ?? 0, 2); ?> ($<?php echo number_format($shipping_usd, 2); ?>)</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h5 mb-0">Total Amount:</span>
                            <span class="total-amount">AED <?php echo number_format($order['total_amount'], 2); ?> ($<?php echo number_format($total_usd, 2); ?>)</span>
                        </div>
                    </div>
                    
                    <!-- Invoice Download -->
                    <?php if (!empty($order['invoice_pdf'])): ?>
                        <div class="text-center mb-4">
                            <a href="../invoice/<?php echo htmlspecialchars($order['invoice_pdf']); ?>" 
                               class="invoice-link" target="_blank">
                                <i class="fas fa-file-pdf me-2"></i> Download Invoice (PDF)
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Payment Methods -->
                    <form method="POST" id="paymentForm">
                        <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i> Select Payment Method</h5>
                        
                        <div class="payment-method-card" onclick="selectPaymentMethod('online')">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="online" id="online" class="me-3">
                                <div class="flex-grow-1">
                                    <label for="online" class="mb-0" style="cursor: pointer;">
                                        <i class="fas fa-credit-card fa-2x text-primary me-3"></i>
                                        <strong>Online Payment</strong>
                                        <p class="text-muted mb-0 small">Pay securely with credit/debit card</p>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payment-method-card" onclick="selectPaymentMethod('cod')">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="cod" id="cod" class="me-3">
                                <div class="flex-grow-1">
                                    <label for="cod" class="mb-0" style="cursor: pointer;">
                                        <i class="fas fa-money-bill-wave fa-2x text-success me-3"></i>
                                        <strong>Cash on Delivery (COD)</strong>
                                        <p class="text-muted mb-0 small">Pay when you receive your order</p>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-pay" id="submitBtn" disabled>
                                <i class="fas fa-lock me-2"></i> Proceed to Payment
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i> Secure Payment | 
                            <i class="fas fa-truck me-1"></i> Fast Delivery | 
                            <i class="fas fa-headset me-1"></i> 24/7 Support
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function selectPaymentMethod(method) {
            document.getElementById(method).checked = true;
            
            // Remove selected class from all cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
        }
        
        // Handle form submission
        document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
        });
    </script>
</body>
</html>

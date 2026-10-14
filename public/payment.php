<?php
// File: public/payment.php
// Secure payment page for order completion

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('../config/config.php');
require_once('../includes/Database.php');
require_once('../includes/generate_payment_link.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('store_url')) {
    require_once __DIR__ . '/../includes/functions.php';
}
if (!function_exists('loc_field')) {
    require_once __DIR__ . '/../includes/store_features.php';
}

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
    $error = t('order.err_invalid_link');
} else {
    // Verify token
    if (!$paymentLinkGen->verifyPaymentToken($orderId, $token)) {
        $error = t('order.err_expired_link');
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
                $error = t('order.err_not_found');
            } elseif ($order['payment_status'] === 'paid') {
                $error = t('order.err_already_paid');
            } else {
                // Get order items
                $orderItems = $db->query("
                    SELECT 
                        oi.*,
                        p.name_en,
                        p.name_ar,
                        p.name_ru,
                        p.name_sp,
                        (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as image_path
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ", [$orderId])->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Payment page error: " . $e->getMessage());
            $error = t('order.err_try_later');
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
            
            if (!function_exists('reserve_order_stock')) {
                require_once __DIR__ . '/../includes/store_features.php';
            }
            reserve_order_stock($db, $orderId);

            if (!function_exists('sendConfirmedOrderEmail')) {
                require_once __DIR__ . '/../includes/order_email_layout.php';
                require_once __DIR__ . '/../includes/send_email_simple.php';
            }
            if (!function_exists('sendAdminWhatsApp')) {
                require_once __DIR__ . '/../includes/whatsapp_notify.php';
            }
            $customerData = [
                'fullname' => $order['fullname'] ?? '',
                'phone' => $order['phone'] ?? '',
                'address' => $order['address'] ?? '',
                'city' => $order['city'] ?? '',
                'country' => $order['country'] ?? '',
            ];
            $customerEmail = trim((string)($order['email'] ?? ''));
            sendAdminWhatsApp($orderId, $customerData['fullname'], $order['total_amount'] ?? 0, 'COD');
            sendAdminOrderNotification($orderId, $customerData['fullname'], $order['total_amount'] ?? 0, 'COD', $customerData);
            if ($customerEmail !== '') {
                sendConfirmedOrderEmail($customerEmail, $orderId, $customerData['fullname'], $customerData);
            }
            $_SESSION['confirmed_order_' . $orderId] = true;
            header("Location: thankyou.php?order=" . $orderId);
            exit;
        } catch (Exception $e) {
            error_log("COD confirmation error: " . $e->getMessage());
            $error = t('order.err_confirm_failed');
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
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo te('order.payment_title', ['id' => $orderId]); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    <style>
        body {
            background: #fff8ee;
            min-height: 100vh;
            font-family: Inter, "Segoe UI", system-ui, sans-serif;
            color: #23170d;
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
            background: linear-gradient(140deg, #fff5df 0%, #fbe5bc 100%);
            color: #2c1505;
            padding: 2rem;
            text-align: center;
        }
        
        .payment-header h1 {
            margin: 0;
            font-family: "Playfair Display", Georgia, serif;
            font-size: 2rem;
            font-weight: 700;
            color: #2c1505;
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
            border-color: #d4840e;
            background: #fff8ee;
        }
        
        .payment-method-card.selected {
            border-color: #d4840e;
            background: #fff8ee;
        }
        
        .payment-method-card input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        
        .total-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #9a3412;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, #d4840e 0%, #b8620a 100%);
            border: none;
            color: white;
            padding: 1rem 3rem;
            font-size: 1.2rem;
            border-radius: 12px;
            font-weight: bold;
            width: 100%;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(184, 98, 10, 0.28);
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
            color: #8a4b08;
            text-decoration: none;
        }
        
        .invoice-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    <div class="payment-container">
        <div class="payment-card">
            <?php if ($error): ?>
                <div class="payment-header">
                    <h1><i class="fas fa-exclamation-triangle"></i> <?php echo te('order.payment_error'); ?></h1>
                </div>
                <div class="payment-body">
                    <div class="error-container">
                        <div class="error-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3><?php echo te('order.oops'); ?></h3>
                        <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
                        <a href="<?php echo htmlspecialchars(store_url()); ?>" class="btn btn-primary mt-3">
                            <i class="fas fa-home me-2"></i> <?php echo te('order.back_home'); ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="payment-header">
                    <h1><i class="fas fa-shopping-bag me-2"></i> <?php echo te('order.complete_order'); ?></h1>
                    <p class="mb-0"><?php echo te('order.order_hash', ['id' => $orderId]); ?></p>
                </div>
                
                <div class="payment-body">
                    <!-- Customer Info -->
                    <div class="mb-4">
                        <h5><i class="fas fa-user me-2"></i> <?php echo te('order.customer_info'); ?></h5>
                        <div class="bg-light p-3 rounded">
                            <p class="mb-1"><strong><?php echo te('order.name'); ?></strong> <?php echo htmlspecialchars($order['fullname']); ?></p>
                            <p class="mb-1"><strong><?php echo te('order.email'); ?></strong> <?php echo htmlspecialchars($order['email']); ?></p>
                            <p class="mb-1"><strong><?php echo te('order.phone'); ?></strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                            <p class="mb-0"><strong><?php echo te('order.city'); ?></strong> <?php echo htmlspecialchars($order['city'] ?? t('order.not_available')); ?></p>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="mb-4">
                        <h5><i class="fas fa-box me-2"></i> <?php echo te('order.order_items'); ?></h5>
                        <?php foreach ($orderItems as $item): ?>
                            <?php $itemName = loc_field($item, 'name'); ?>
                            <div class="product-item">
                                <?php if ($item['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" class="product-image" alt="<?php echo htmlspecialchars($itemName); ?>">
                                <?php else: ?>
                                    <div class="product-image bg-secondary d-flex align-items-center justify-content-center">
                                        <i class="fas fa-image text-white"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($itemName); ?></strong><br>
                                    <small class="text-muted"><?php echo te('order.quantity_line', ['qty' => $item['quantity'], 'price' => number_format($item['price'], 2)]); ?></small>
                                </div>
                                <div>
                                    <strong><?php echo te('order.amount_aed', ['amount' => number_format($item['quantity'] * $item['price'], 2)]); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <?php 
                        $aed_to_eur = 0.23;
                        $subtotal = $order['total_amount'] - ($order['shipping_aed'] ?? 0);
                        $subtotal_eur = $subtotal * $aed_to_eur;
                        $shipping_eur = ($order['shipping_aed'] ?? 0) * $aed_to_eur;
                        $total_eur = $order['total_amount'] * $aed_to_eur;
                        ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?php echo te('order.subtotal'); ?></span>
                            <strong>AED <?php echo number_format($subtotal, 2); ?> (€<?php echo number_format($subtotal_eur, 2); ?>)</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span><?php echo te('order.shipping_label'); ?></span>
                            <strong>AED <?php echo number_format($order['shipping_aed'] ?? 0, 2); ?> (€<?php echo number_format($shipping_eur, 2); ?>)</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h5 mb-0"><?php echo te('order.total_amount'); ?></span>
                            <span class="total-amount">AED <?php echo number_format($order['total_amount'], 2); ?> (€<?php echo number_format($total_eur, 2); ?>)</span>
                        </div>
                    </div>
                    
                    <!-- Invoice Download: hidden until a cash-on-delivery order is paid -->
                    <?php
                    $paymentMethodShown = strtolower(trim((string)($order['payment_method'] ?? '')));
                    $paymentStatusShown = strtolower(trim((string)($order['payment_status'] ?? '')));
                    $codUnpaid = in_array($paymentMethodShown, ['cod', 'cash', 'cash on delivery', 'cash_on_delivery'], true)
                        && !in_array($paymentStatusShown, ['paid', 'completed'], true);
                    ?>
                    <?php if (!empty($order['invoice_pdf']) && !$codUnpaid): ?>
                        <div class="text-center mb-4">
                            <?php
                            $invoiceBase = preg_replace('#/public$#', '', store_base_path());
                            $invoiceHref = ($invoiceBase === '' ? '' : $invoiceBase) . '/invoice/' . rawurlencode(basename((string)$order['invoice_pdf']));
                            ?>
                            <a href="<?php echo htmlspecialchars($invoiceHref); ?>" 
                               class="invoice-link" target="_blank">
                                <i class="fas fa-file-pdf me-2"></i> <?php echo te('order.download_invoice_pdf'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Payment Methods -->
                    <form method="POST" id="paymentForm">
                        <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i> <?php echo te('order.select_payment'); ?></h5>
                        
                        <div class="payment-method-card" onclick="selectPaymentMethod('online')">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="online" id="online" class="me-3">
                                <div class="flex-grow-1">
                                    <label for="online" class="mb-0" style="cursor: pointer;">
                                        <i class="fas fa-credit-card fa-2x text-primary me-3"></i>
                                        <strong><?php echo te('order.online_payment'); ?></strong>
                                        <p class="text-muted mb-0 small"><?php echo te('order.online_payment_desc'); ?></p>
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
                                        <strong><?php echo te('order.cod'); ?></strong>
                                        <p class="text-muted mb-0 small"><?php echo te('order.cod_desc'); ?></p>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-pay" id="submitBtn" disabled>
                                <i class="fas fa-lock me-2"></i> <?php echo te('order.proceed_payment'); ?>
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i> <?php echo te('order.secure_payment'); ?> | 
                            <i class="fas fa-truck me-1"></i> <?php echo te('order.fast_delivery'); ?> | 
                            <i class="fas fa-headset me-1"></i> <?php echo te('order.support_247'); ?>
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> ' + <?php echo json_encode(te('order.processing'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
        });
    </script>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

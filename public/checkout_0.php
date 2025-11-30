<?php
require_once('../includes/send_email_working.php');
ob_start(); // Start output buffering

require_once('../includes/bootstrap.php');

// Bootstrap already loads all necessary components
// No need to load them individually anymore

// Check if cart exists and is not empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$db = new Database();
$cart = $_SESSION['cart'];

// Validate cart items before checkout
list($valid_items, $invalid_items) = validateCartItems($db, $cart);

// Remove invalid items and show warning
if (!empty($invalid_items)) {
    foreach ($invalid_items as $invalid) {
        unset($_SESSION['cart'][$invalid['key']]);
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    $_SESSION['checkout_error'] = count($invalid_items) . ' item(s) were removed from your cart due to availability issues. Please review your order.';
    header("Location: cart.php");
    exit;
}

// Check if cart is empty after validation
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

list($cartTotal, $totalWeight) = getCartTotalAndWeight($db, $cart);

$country = $_POST['country'] ?? 'United Arab Emirates';
$city = $_POST['city'] ?? '';
$shippingAED = calculateShippingCost($country, $city, $totalWeight);

// Apply discount if available
$discount = $_SESSION['discount_amount'] ?? 0;
$subtotal = max(0, $cartTotal - $discount);
$grandTotal = $subtotal + $shippingAED;

// Include the quotation generation script
// This will generate a quotation PDF if needed
// It can be used to provide a downloadable quotation for the customer  
//require_once '../includes/generate_quotation.php';
 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid security token. Please refresh the page and try again.');
        }

        // Validate and sanitize input
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $payment_method = $_POST['payment_method'] ?? '';
        $note = trim($_POST['note'] ?? '');

        // Validation
        if (strlen($fullname) < 2) {
            throw new Exception('Full name must be at least 2 characters long.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please provide a valid email address.');
        }

        $phone = preg_replace('/[^0-9+\-\s()]/', '', $phone);
        if (strlen($phone) < 6) {
            throw new Exception('Please provide a valid phone number.');
        }

        if (strlen($address) < 5) {
            throw new Exception('Please provide a complete address.');
        }

        if (strlen($city) < 2) {
            throw new Exception('Please provide a valid city name.');
        }

        if (strlen($country) < 2) {
            throw new Exception('Please select a valid country.');
        }

        if (!in_array($payment_method, ['COD', 'Ziina'])) {
            throw new Exception('Please select a valid payment method.');
        }

        // Check COD availability
        if ($payment_method === 'COD' && !in_array(strtolower($country), ['uae', 'united arab emirates'])) {
            throw new Exception('Cash on Delivery is only available in the United Arab Emirates.');
        }

        // Final cart validation before order creation
        list($valid_cart_items, $invalid_cart_items) = validateCartItems($db, $_SESSION['cart']);
        
        if (!empty($invalid_cart_items)) {
            throw new Exception('Some items in your cart are no longer available. Please review your cart and try again.');
        }

        if (empty($valid_cart_items)) {
            throw new Exception('Your cart is empty. Please add items before checkout.');
        }

        // Recalculate totals with validated cart
        list($finalCartTotal, $finalTotalWeight) = getCartTotalAndWeight($db, $_SESSION['cart']);
        $finalShippingCost = calculateShippingCost($country, $city, $finalTotalWeight);
        $finalDiscount = $_SESSION['discount_amount'] ?? 0;
        $finalSubtotal = max(0, $finalCartTotal - $finalDiscount);
        $finalGrandTotal = $finalSubtotal + $finalShippingCost;

        // Sanitize data for database
        $fullname = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($city, ENT_QUOTES, 'UTF-8');
        $country = htmlspecialchars($country, ENT_QUOTES, 'UTF-8');
        $note = htmlspecialchars($note, ENT_QUOTES, 'UTF-8');

        // Check if customer already exists
        $existing_customer = $db->query(
            "SELECT id FROM customers WHERE email = :email LIMIT 1", 
            ['email' => $email]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing_customer) {
            $customer_id = $existing_customer['id'];
            // Update customer information
            $db->query(
                "UPDATE customers SET fullname = :fullname, phone = :phone, address = :address, city = :city, country = :country WHERE id = :id",
                compact('fullname', 'phone', 'address', 'city', 'country') + ['id' => $customer_id]
            );
        } else {
            // Create new customer
            $db->query(
                "INSERT INTO customers (fullname, email, phone, address, city, country, created_at) VALUES 
                (:fullname, :email, :phone, :address, :city, :country, NOW())", 
                compact('fullname', 'email', 'phone', 'address', 'city', 'country')
            );
            $customer_id = $db->lastInsertId();
        }

        // Determine payment status
        $paymentStatus = ($payment_method === 'COD') ? 'pending' : 'processing';

        // Prepare coupon data from session
        $coupon = $_SESSION['applied_coupon'] ?? null;

        // Create order
        $db->query(
            "INSERT INTO orders 
                (customer_id, total_amount, total_weight, shipping_aed, payment_status, payment_method, note, remarks, 
                 coupon_code, discount_type, discount_value, discount_amount, order_date)
            VALUES 
                (:customer_id, :total, :total_weight, :shipping_aed, :payment_status, :payment_method, :note, :remarks,
                 :coupon_code, :discount_type, :discount_value, :discount_amount, NOW())",
            [
                'customer_id'     => $customer_id,
                'total'           => $finalGrandTotal,
                'total_weight'    => $finalTotalWeight,
                'shipping_aed'    => $finalShippingCost,
                'payment_status'  => $paymentStatus,
                'payment_method'  => $payment_method,
                'note'            => $note,
                'remarks'         => null,
                'coupon_code'     => $coupon['code'] ?? null,
                'discount_type'   => $coupon['discount_type'] ?? null,
                'discount_value'  => $coupon['discount_value'] ?? null,
                'discount_amount' => $finalDiscount
            ]
        );
        $order_id = $db->lastInsertId();

        // Save Order Items with stock checking
        foreach ($_SESSION['cart'] as $item) {
            $product = $db->query(
                "SELECT id, name_en, price, stock FROM products WHERE id = :id AND status = 1", 
                ['id' => $item['product_id']]
            )->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Product {$item['product_id']} is no longer available.");
            }

            $price = $product['price'];
            $variation_details = null;

            // Handle variations
            if (!empty($item['variation_id'])) {
                $variation = $db->query(
                    "SELECT id, additional_price, size, color, stock FROM product_variations WHERE id = :id AND product_id = :product_id",
                    ['id' => $item['variation_id'], 'product_id' => $item['product_id']]
                )->fetch(PDO::FETCH_ASSOC);

                if (!$variation) {
                    throw new Exception("Product variation is no longer available.");
                }

                $price += $variation['additional_price'];
                $variation_details = "Size: {$variation['size']}, Color: {$variation['color']}";

                // Check variation stock
                if ($variation['stock'] !== null && $variation['stock'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for {$product['name_en']} variation.");
                }
            } else {
                // Check product stock
                if ($product['stock'] !== null && $product['stock'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for {$product['name_en']}.");
                }
            }

            // Insert order item
            $db->query(
                "INSERT INTO order_items (order_id, product_id, variation_id, quantity, price) 
                VALUES (:order_id, :product_id, :variation_id, :qty, :price)", 
                [
                    'order_id' => $order_id,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'qty' => $item['quantity'],
                    'price' => $price
                ]
            );

            // Update stock (optional - uncomment if you want to reserve stock)
            /*
            if ($item['variation_id']) {
                if ($variation['stock'] !== null) {
                    $db->query(
                        "UPDATE product_variations SET stock = stock - :qty WHERE id = :id",
                        ['qty' => $item['quantity'], 'id' => $item['variation_id']]
                    );
                }
            } else {
                if ($product['stock'] !== null) {
                    $db->query(
                        "UPDATE products SET stock = stock - :qty WHERE id = :id",
                        ['qty' => $item['quantity'], 'id' => $item['product_id']]
                    );
                }
            }
            */
        }

        // Process payment based on method
        if ($payment_method === 'COD') {
            // COD - Send immediate confirmation
            send_confirmation($order_id, $fullname, $finalGrandTotal, $payment_method, $email);
            exit;
        } elseif ($payment_method === 'Ziina') {
            // Store order details in session for payment callback
            $_SESSION['payment_data'] = [
                'order_id' => $order_id,
                'customer_name' => $fullname,
                'customer_email' => $email,
                'total_amount' => $finalGrandTotal,
                'payment_method' => $payment_method
            ];

            try {
                $ziina = new ZiinaPayment();
                $response = $ziina->createPaymentIntent(
                    $order_id, 
                    $finalGrandTotal, 
                    "AleppoGift Order #$order_id"
                );

                if ($response['success']) {
                    // Update order with payment intent details
                    $db->query(
                        "UPDATE orders 
                         SET payment_status = 'processing', remarks = :resp 
                         WHERE id = :id",
                        [
                            'resp' => json_encode($response, JSON_UNESCAPED_UNICODE),
                            'id'   => $order_id
                        ]
                    );

                    // Redirect to Ziina payment page
                    header("Location: " . $response['payment_url']);
                    exit;
                } else {
                    throw new Exception("Payment service unavailable: " . ($response['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                // Log payment error
                error_log("Ziina payment error for Order #$order_id: " . $e->getMessage());
                
                // Update order status to failed
                $db->query(
                    "UPDATE orders SET payment_status = 'failed', remarks = :error WHERE id = :id",
                    ['error' => $e->getMessage(), 'id' => $order_id]
                );
                
                throw new Exception("Payment processing failed. Please try again or contact support.");
            }
        } else {
            throw new Exception("Invalid payment method selected.");
        }

    } catch (Exception $e) {
        // Handle all errors gracefully
        error_log("Checkout error: " . $e->getMessage());
        $_SESSION['checkout_error'] = $e->getMessage();
        // Don't redirect, show error on same page
    }
}

// --- Confirmation function ---
function send_confirmation($order_id, $fullname, $grandTotal, $payment_method, $email) {
    global $db;
    
    try {
        // Send WhatsApp notification to admin
        sendAdminWhatsApp($order_id, $fullname, $grandTotal, $payment_method);

        // Generate and send invoice
        require_once('../includes/generate_invoice_pdf.php');
        $generator = new PDFInvoiceGenerator();
        $invoiceInfo = $generator->generateInvoicePDF($order_id);
        
        $fullPath = $invoiceInfo['file_path'] ?? '';

        if (!empty($fullPath) && file_exists($fullPath)) {
            $status = sendInvoiceEmail($email, $order_id, $fullPath);
            if (!$status) {
                error_log("❌ Failed to send email for Order #$order_id to $email.");
            }
        } else {
            error_log("⚠️ PDF path missing or file not found for Order #$order_id.");
        }

        // Clear cart only after successful confirmation
        $_SESSION['cart'] = [];
        unset($_SESSION['applied_coupon']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['payment_data']);
        
        // Clean output buffer and redirect
        if (ob_get_level()) ob_end_clean();
        header("Location: thankyou.php?order=$order_id");
        exit;
        
    } catch (Exception $e) {
        error_log("Confirmation error for Order #$order_id: " . $e->getMessage());
        // Don't clear cart if confirmation fails
        header("Location: thankyou.php?order=$order_id&error=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts for Enhanced Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Main CSS files to match index.php -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/enhanced-design.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <link rel="stylesheet" href="assets/css/checkout-clean.css">
</head>
<body>
    <?php
require_once('../includes/send_email_working.php'); require_once(__DIR__ . '/../includes/header.php'); ?>

    <!-- Cart Preview -->
    <div id="cartPreview" class="card shadow position-absolute end-0 mt-2 me-4 cart-preview" style="display: none; z-index: 1050;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0"><i class="fas fa-shopping-cart me-2"></i>Your Cart</h5>
                <button type="button" class="btn-close" aria-label="Close cart" onclick="toggleCart()"></button>
            </div>
            <div id="cart-items-preview">
                <p class="text-muted text-center py-3">Your cart is empty</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="container my-5">
        <div class="checkout-container">
            <div class="checkout-header mb-4">
                <h2 class="fw-bold text-primary"><i class="fas fa-shopping-cart me-2"></i>Checkout</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item"><a href="cart.php" class="text-decoration-none">Cart</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Checkout</li>
                    </ol>
                </nav>
            </div>

            <?php
require_once('../includes/send_email_working.php'); if (isset($_SESSION['checkout_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?php
require_once('../includes/send_email_working.php'); echo htmlspecialchars($_SESSION['checkout_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php
require_once('../includes/send_email_working.php'); unset($_SESSION['checkout_error']); ?>
            <?php
require_once('../includes/send_email_working.php'); endif; ?>
        
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-5">
                            <form method="post" class="checkout-form" id="checkout-form" novalidate>
                                <!-- CSRF token -->
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                <!-- Contact Information Section -->
                                <div class="mb-6">
                                    <h4 class="mb-4 text-primary border-bottom pb-3">
                                        <i class="fas fa-user me-2"></i> Contact Information
                                    </h4>
                                    <div class="row g-4">
                                        <div class="col-12">
                                            <label for="fullname" class="form-label fw-semibold mb-2">Full Name *</label>
                                            <input type="text" id="fullname" name="fullname" required minlength="2" maxlength="100"
                                                   value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"
                                                   class="form-control form-control-lg py-3">
                                            <div class="invalid-feedback">Please provide a valid full name (at least 2 characters).</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label fw-semibold mb-2">Email Address *</label>
                                            <input type="email" id="email" name="email" required maxlength="100"
                                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                                   class="form-control form-control-lg py-3">
                                            <div class="invalid-feedback">Please provide a valid email address.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label fw-semibold mb-2">Phone Number *</label>
                                            <input type="tel" id="phone" name="phone" required minlength="6" maxlength="20"
                                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                                   class="form-control form-control-lg py-3" 
                                                   pattern="[0-9+\-\s()]{6,20}">
                                            <div class="invalid-feedback">Please provide a valid phone number (at least 6 digits).</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="note" class="form-label fw-semibold mb-2">Order Notes (Optional)</label>
                                            <textarea id="note" name="note" rows="4" maxlength="500" 
                                                      class="form-control py-3"
                                                      placeholder="Special instructions for your order..."><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Shipping Address Section -->
                                <div class="mb-6">
                                    <h4 class="mb-4 text-primary border-bottom pb-3">
                                        <i class="fas fa-truck me-2"></i> Shipping Address
                                    </h4>
                                    <div class="row g-4">
                                        <div class="col-12">
                                            <label for="address" class="form-label fw-semibold mb-2">Complete Address *</label>
                                            <textarea id="address" name="address" required minlength="5" maxlength="300" rows="4"
                                                      class="form-control py-3"
                                                      placeholder="Enter your complete address including building, street, and area"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                                            <div class="invalid-feedback">Please provide a complete address (at least 5 characters).</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="country" class="form-label fw-semibold mb-2">Country *</label>
                                            <select id="country" name="country" required class="form-select form-select-lg py-3">
                                                <?php
require_once('../includes/send_email_working.php'); 
                                                require_once 'ajax/countries.php';
                                                $selectedCountry = $_POST['country'] ?? 'United Arab Emirates';
                                                foreach ($countries as $name => $flag): ?>
                                                    <option value="<?= htmlspecialchars($name) ?>" 
                                                            <?= $name === $selectedCountry ? 'selected' : '' ?>>
                                                        <?= $flag ?> <?= htmlspecialchars($name) ?>
                                                    </option>
                                                <?php
require_once('../includes/send_email_working.php'); endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Please select your country.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="city" class="form-label fw-semibold mb-2">City *</label>
                                            <input type="text" id="city" name="city" required minlength="2" maxlength="50"
                                                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                                   class="form-control form-control-lg py-3">
                                            <div class="invalid-feedback">Please provide a valid city name.</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="alert alert-info d-flex justify-content-between align-items-center py-3">
                                                <span><i class="fas fa-shipping-fast me-2"></i>Shipping Cost:</span>
                                                <strong id="shipping-cost"><?= number_format($shippingAED, 2) ?> AED</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Method Section -->
                                <div class="mb-5">
                                    <h4 class="mb-4 text-primary border-bottom pb-3">
                                        <i class="fas fa-credit-card me-2"></i> Payment Method
                                    </h4>
                                    <div class="row">
                                        <div class="col-12">
                                            <label for="payment_method" class="form-label fw-semibold mb-2">Select Payment Method *</label>
                                            <select id="payment_method" name="payment_method" required class="form-select form-select-lg py-3">
                                                <option value="">-- Choose Payment Method --</option>
                                                <option value="COD" <?= ($_POST['payment_method'] ?? '') === 'COD' ? 'selected' : '' ?>>
                                                    💵 Cash on Delivery (UAE Only)
                                                </option>
                                                <option value="Ziina" <?= ($_POST['payment_method'] ?? '') === 'Ziina' ? 'selected' : '' ?>>
                                                    💳 Credit Card (Online Payment via Ziina)
                                                </option>
                                            </select>
                                            <div class="invalid-feedback">Please select a payment method.</div>
                                            <small class="text-muted mt-3 d-block">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <span id="payment-info">Cash on Delivery is available for UAE orders. Credit card payment is also available.</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-grid mt-5">
                                    <button type="submit" class="btn btn-success btn-lg py-4" id="submit-btn" style="font-size: 1.1rem;">
                                        <i class="fas fa-credit-card me-2"></i>
                                        <span class="btn-text">Place Order</span>
                                    </button>
                                    <small class="text-muted mt-4 text-center">
                                        By placing this order, you agree to our 
                                        <a href="terms_of_service.html" target="_blank" class="text-decoration-none">Terms of Service</a> and 
                                        <a href="privacy_policy.html" target="_blank" class="text-decoration-none">Privacy Policy</a>
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary Sidebar -->
                <div class="col-lg-4">
                    <div class="sticky-top" style="top: 100px;">
                        <!-- Order Summary Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <?php
require_once('../includes/send_email_working.php'); foreach ($_SESSION['cart'] as $item):
                                        $product = $db->query(
                                            "SELECT name_en, price FROM products WHERE id = :id",
                                            ['id' => $item['product_id']]
                                        )->fetch(PDO::FETCH_ASSOC);
                                        if (!$product) continue;
                                        $price = $product['price'];
                                        if ($item['variation_id']) {
                                            $variation = $db->query(
                                                "SELECT additional_price FROM product_variations WHERE id = :id",
                                                ['id' => $item['variation_id']]
                                            )->fetch(PDO::FETCH_ASSOC);
                                            if ($variation) {
                                                $price += $variation['additional_price'];
                                            }
                                        }
                                        ?>
                                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($product['name_en']) ?></div>
                                                <small class="text-muted">Qty: <?= htmlspecialchars($item['quantity']) ?></small>
                                            </div>
                                            <div class="fw-bold text-success"><?= number_format($price * $item['quantity'], 2) ?> AED</div>
                                        </div>
                                    <?php
require_once('../includes/send_email_working.php'); endforeach; ?>
                                </div>
                                
                                <div class="border-top pt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Items Total:</span>
                                        <span id="item-total" data-value="<?= $cartTotal ?>"><?= number_format($cartTotal, 2) ?> AED</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Shipping:</span>
                                        <span id="shipping-cost2"><?= number_format($shippingAED, 2) ?> AED</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2" id="discount-row" style="display:none!important;">
                                        <span>Discount:</span>
                                        <span id="discount-amount" class="text-success"></span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fs-5 fw-bold">
                                        <span>Grand Total:</span>
                                        <span id="grand-total" class="text-success"><?= number_format($cartTotal + $shippingAED, 2) ?> AED</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Coupon Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-tag me-2"></i>Have a Coupon Code?</h6>
                            </div>
                            <div class="card-body">
                                <div class="input-group">
                                    <input type="text" id="coupon-code" class="form-control" 
                                           placeholder="Enter your code here" aria-label="Coupon code">
                                    <button type="button" class="btn btn-outline-primary" onclick="applyCoupon()">
                                        Apply
                                    </button>
                                </div>
                                <p id="coupon-message" class="mt-2 mb-0 small"></p>
                            </div>
                        </div>
                        
                        <!-- Quotation Card -->
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-file-download me-2"></i>Download Quotation</h6>
                            </div>
                            <div class="card-body">
                                <p class="small mb-3">Need a formal quotation? Download it here:</p>
                                <a href="/download_quotation.php" target="_blank" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-download me-2"></i>Download PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" 
             id="loadingOverlay" style="display: none!important; background: rgba(0,0,0,0.7); z-index: 9999;">
            <div class="text-center text-white">
                <div class="spinner-border loading-spinner mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h4>Processing your order...</h4>
                <p class="text-light">Please wait while we confirm your payment</p>
            </div>
        </div>
    </main>

    <?php
require_once('../includes/send_email_working.php'); require_once(__DIR__ . '/../includes/footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const countrySelect = document.getElementById('country');
        const cityInput = document.getElementById('city');
        const paymentMethodSelect = document.getElementById('payment_method');
        const codOption = paymentMethodSelect.querySelector('option[value="COD"]');
        const ziinaOption = paymentMethodSelect.querySelector('option[value="Ziina"]');
        const paymentInfo = document.getElementById('payment-info');

        function handlePaymentMethodByCountry() {
            const selectedCountry = countrySelect.value.trim().toLowerCase();
            const isUAE = selectedCountry === 'united arab emirates';

            if (isUAE) {
                // UAE: Enable COD and set as default
                codOption.disabled = false;
                ziinaOption.disabled = false;
                
                // Set COD as default for UAE if no payment method is selected
                if (!paymentMethodSelect.value || paymentMethodSelect.value === '') {
                    paymentMethodSelect.value = 'COD';
                }
                
                paymentInfo.textContent = 'Cash on Delivery is available for UAE orders. Credit card payment is also available.';
            } else {
                // Other countries: Disable COD, force Ziina
                codOption.disabled = true;
                ziinaOption.disabled = false;
                
                // Force Ziina for non-UAE countries
                paymentMethodSelect.value = 'Ziina';
                
                paymentInfo.textContent = 'Only credit card payment via Ziina is available for international orders.';
            }
        }

        function updateShippingCost() {
            const country = countrySelect.value.trim().toLowerCase();
            const totalWeight = <?= json_encode($totalWeight) ?>;
            let city = cityInput.value.trim().toLowerCase();
            if (!city) {
                city = '_';
            }
            
            console.log(`Calculating shipping for country: ${country}, city: ${city}`);
            if (!country || !city) {
                document.getElementById('shipping-cost').textContent = '30.00 AED';
                return;
            } else {
                document.getElementById('shipping-cost').textContent = 'Calculating...';
                
                fetch('ajax/calculate_shipping.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `country=${encodeURIComponent(country)}&city=${encodeURIComponent(city)}&totalWeight=${encodeURIComponent(totalWeight)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Raw shipping response:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed shipping data:', data);
                        
                        if (data.success && data.shippingAED !== undefined) {
                            document.getElementById('shipping-cost').textContent = `${data.shippingAED} AED`;
                            document.getElementById('shipping-cost2').textContent = `${data.shippingAED} AED`;
                            updateGrandTotal(data.shippingCost);
                        } else {
                            console.error('Shipping calculation failed:', data.error || 'Unknown error');
                            document.getElementById('shipping-cost').textContent = '30.00 AED'; // Fallback
                            updateGrandTotal(30);
                        }
                    } catch (parseError) {
                        console.error('Failed to parse shipping response:', parseError);
                        console.error('Raw response:', text);
                        document.getElementById('shipping-cost').textContent = '30.00 AED'; // Fallback
                        updateGrandTotal(30);
                    }
                })
                .catch(error => {
                    console.error('Shipping cost update failed:', error);
                    document.getElementById('shipping-cost').textContent = '30.00 AED'; // Fallback
                    updateGrandTotal(30);
                });
            }
        }

        function updateGrandTotal(shipping) {
            console.log('updateGrandTotal: shipping:', shipping);
            const itemTotal = parseFloat(document.getElementById('item-total').dataset.value || 0);
            const grandTotal = itemTotal + parseFloat(shipping);
            console.log('grandTotal=', grandTotal, ' itemTotal=', itemTotal, ' shipping=', parseFloat(shipping));
            document.getElementById('grand-total').textContent = `${grandTotal.toFixed(2)} AED`;
            document.getElementById('shipping-cost').textContent = `${parseFloat(shipping).toFixed(2)} AED`;
        }

        // Bind events
        countrySelect.addEventListener('change', () => {
            handlePaymentMethodByCountry();
            updateShippingCost();
        });

        cityInput.addEventListener('input', updateShippingCost);

        // Initial run
        handlePaymentMethodByCountry();
        updateShippingCost();
    });
    

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('checkout-form');
        const overlay = document.getElementById('loadingOverlay');

        if (form && overlay) {
            form.addEventListener('submit', function () {
                overlay.style.display = 'flex'; // Or block, depending on your CSS
            });
        }
    });
	
	function applyCoupon() {
    const code = document.getElementById('coupon-code').value.trim();
    if (!code) return;

    fetch('ajax/apply_coupon.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'code=' + encodeURIComponent(code)
    })
    .then(response => response.text())
    .then(text => {
        console.log('Coupon raw response:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                document.getElementById('coupon-message').textContent = "Coupon applied!";
                updateGrandTotalWithDiscount(data.discountAmount);
            } else {
                document.getElementById('coupon-message').style.color = "red";
                document.getElementById('coupon-message').textContent = data.message;
            }
        } catch (err) {
            console.error("JSON parse error:", err, text);
            document.getElementById('coupon-message').style.color = "red";
            document.getElementById('coupon-message').textContent = "Coupon system error. Please contact support.";
        }
    });
    }

    function updateGrandTotalWithDiscount(discountAmount) {
        discountAmount = parseFloat(discountAmount) || 0;

        const itemTotal = parseFloat(document.getElementById('item-total').dataset.value || 0);
        const shipping = parseFloat(document.getElementById('shipping-cost2').textContent) || 0;

        const grandBeforeDiscount = itemTotal + shipping;
        const grandTotal = Math.max(grandBeforeDiscount - discountAmount, 0);

        document.getElementById('grand-total').textContent = grandTotal.toFixed(2) + ' AED';

        // Show discount visually
        const discountRow = document.getElementById('discount-row');
        const discountDisplay = document.getElementById('discount-amount');
        if (discountRow && discountDisplay) {
            discountDisplay.textContent = "- " + discountAmount.toFixed(2) + ' AED';
            discountRow.style.display = 'flex';
        }
    }

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/enhanced-main.js"></script>

</body>
</html>

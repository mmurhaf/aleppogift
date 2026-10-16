<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);

// Include required files
require_once('../config/config.php');
require_once('../includes/whatsapp_notify.php');
require_once('../includes/send_email_simple.php');

// Custom error handler for checkout
function checkout_error_handler($severity, $message, $file, $line) {
    $error_types = [
        E_ERROR => 'FATAL ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE ERROR',
        E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE ERROR',
        E_COMPILE_WARNING => 'COMPILE WARNING',
        E_USER_ERROR => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE => 'USER NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR'
    ];
    
    $error_type = $error_types[$severity] ?? 'UNKNOWN ERROR';
    
    error_log("🚨 PHP $error_type in checkout.php: $message");
    error_log("📍 Location: $file at line $line");
    
    // Log POST data if available
    if (!empty($_POST)) {
        error_log("📥 POST data during error: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));
    }
    
    // Don't execute PHP internal error handler
    return true;
}

// Custom exception handler
function checkout_exception_handler($exception) {
    error_log("💥 UNCAUGHT EXCEPTION in checkout.php: " . $exception->getMessage());
    error_log("📍 Location: " . $exception->getFile() . " at line " . $exception->getLine());
    error_log("🔍 Stack trace: " . $exception->getTraceAsString());
    
    // Set a user-friendly error message
    $_SESSION['checkout_error'] = function_exists('t') ? t('checkout.error_unexpected_support') : "An unexpected error occurred. Please try again or contact support.";
}

// Set custom handlers
set_error_handler('checkout_error_handler');
set_exception_handler('checkout_exception_handler');

ob_start(); // Start output buffering

require_once('../includes/bootstrap.php');

// Bootstrap already loads all necessary components
// No need to load them individually anymore

// Card cancel/failure returns here as checkout.php?order=ID.
// If the cart is gone, say so. Otherwise keep the cart and explain the payment.
$returnOrderId = (int)($_GET['order'] ?? $_GET['order_id'] ?? 0);
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    if ($returnOrderId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['cart_error'] = t('checkout.payment_not_completed_empty');
    }
    header("Location: cart.php");
    exit;
}
if ($returnOrderId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['checkout_error'] = t('checkout.payment_not_completed');
    header('Location: checkout.php');
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
    $_SESSION['checkout_error'] = t('checkout.items_removed', ['count' => count($invalid_items)]);
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

$pricedCart = priceCart($db, $cart);
list($cartTotal, $totalWeight) = [$pricedCart['total'], $pricedCart['weight']];
$discount = refreshSessionCoupon($cartTotal);

$country = $_POST['country'] ?? 'United Arab Emirates';
$city = $_POST['city'] ?? '';
$subtotal = max(0, $cartTotal - $discount);
$shippingAED = calculateShippingCost($country, $city, $totalWeight, $subtotal, false);
$grandTotal = $subtotal + $shippingAED;
$savedAddresses = [];
if (current_customer_id() > 0 && ag_has_column($db, 'customer_addresses', 'id')) {
    $savedAddresses = $db->fetchAll('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [current_customer_id()]);
}

// Include the quotation generation script
// This will generate a quotation PDF if needed
// It can be used to provide a downloadable quotation for the customer  
//require_once '../includes/generate_quotation.php';
 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log the incoming POST request for debugging
    error_log("🚀 CHECKOUT POST REQUEST RECEIVED");
    error_log("📥 POST Data: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));
    error_log("🛒 Cart Items: " . count($_SESSION['cart'] ?? []));
    error_log("💰 Session Discount: " . ($_SESSION['discount_amount'] ?? 0));
    
    // Test database connection
    try {
        $db->query("SELECT 1")->fetch();
        error_log("✅ Database connection verified");
    } catch (Exception $e) {
        error_log("❌ Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection error. Please try again later.");
    }
    
    // Verify required functions are available
    $required_functions = ['validateCartItems', 'getCartTotalAndWeight', 'calculateShippingCost'];
    foreach ($required_functions as $func) {
        if (!function_exists($func)) {
            error_log("❌ Required function $func not found");
            throw new Exception("System configuration error. Please contact support.");
        }
    }
    error_log("✅ All required functions verified");
    
    try {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("🔒 CSRF token validation failed");
            throw new Exception(t('checkout.error_csrf'));
        }
        error_log("✅ CSRF token validated successfully");

        // Validate and sanitize input
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $payment_method = $_POST['payment_method'] ?? '';
        $note = trim($_POST['note'] ?? '');
        if (!empty($_POST['address_id']) && current_customer_id() > 0) {
            $saved = $db->fetch('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [(int)$_POST['address_id'], current_customer_id()]);
            if ($saved) {
                $fullname = $fullname !== '' ? $fullname : (string)$saved['fullname'];
                $phone = $phone !== '' ? $phone : (string)$saved['phone'];
                $address = $address !== '' ? $address : trim((string)$saved['address_line1'] . ' ' . (string)$saved['address_line2']);
                $city = $city !== '' ? $city : (string)$saved['city'];
                $country = $country !== '' ? $country : (string)$saved['country'];
            }
        }

        $isZiinaCheckout = ($payment_method === 'Ziina');

        if ($isZiinaCheckout && $fullname === '') {
            $fullname = 'Guest Customer';
        }

        if ($isZiinaCheckout && strlen($phone) < 6) {
            $phone = '';
        }

        if ($isZiinaCheckout && $country === '') {
            $country = 'Unknown';
        }

        if ($isZiinaCheckout && $city === '') {
            $city = 'Unknown';
        }

        if ($address === '' && $isZiinaCheckout) {
            $address = trim($city . ($country !== '' ? ', ' . $country : ''));
        }
        
        error_log("📝 Input data sanitized - Email: $email, Payment: $payment_method, Country: $country");

        // Validation
        if (strlen($fullname) < 2) {
            throw new Exception(t('checkout.error_fullname'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(t('checkout.error_email'));
        }

        $phone = preg_replace('/[^0-9+\-\s()]/', '', $phone);
        if (!$isZiinaCheckout && strlen($phone) < 6) {
            throw new Exception(t('checkout.error_phone'));
        }

        if ($payment_method === 'COD' && strlen($address) < 5) {
            throw new Exception(t('checkout.error_address'));
        }

        if (!$isZiinaCheckout && strlen($city) < 2) {
            throw new Exception(t('checkout.error_city'));
        }

        if (!$isZiinaCheckout && strlen($country) < 2) {
            throw new Exception(t('checkout.error_country'));
        }

        if (!in_array($payment_method, ['COD', 'Ziina'])) {
            error_log("❌ Invalid payment method: $payment_method");
            throw new Exception(t('checkout.error_payment_method'));
        }
        error_log("✅ Payment method validated: $payment_method");

        // Check COD availability
        if ($payment_method === 'COD' && !in_array(strtolower($country), ['uae', 'united arab emirates'])) {
            error_log("❌ COD not available for country: $country");
            throw new Exception(t('checkout.error_cod_uae'));
        }

        // Final cart validation before order creation
        error_log("🛒 Starting cart validation...");
        list($valid_cart_items, $invalid_cart_items) = validateCartItems($db, $_SESSION['cart']);
        
        if (!empty($invalid_cart_items)) {
            error_log("❌ Invalid cart items found: " . count($invalid_cart_items));
            throw new Exception(t('checkout.error_items_unavailable'));
        }

        if (empty($valid_cart_items)) {
            error_log("❌ Cart is empty after validation");
            throw new Exception(t('checkout.error_cart_empty'));
        }
        error_log("✅ Cart validation passed - " . count($valid_cart_items) . " items");

        // Recalculate totals with validated cart
        error_log("💰 Calculating final totals...");
        $finalPricedCart = priceCart($db, $_SESSION['cart']);
        list($finalCartTotal, $finalTotalWeight) = [$finalPricedCart['total'], $finalPricedCart['weight']];
        error_log("💰 Raw cart calculation - Total: $finalCartTotal, Weight: $finalTotalWeight");
        
        $finalDiscount = refreshSessionCoupon($finalCartTotal);
        $finalSubtotal = max(0, $finalCartTotal - $finalDiscount);
        $sameDay = !empty($_POST['same_day']) && is_uae_country($country) && same_day_city($city);
        $finalShippingCost = calculateShippingCost($country, $city, $finalTotalWeight, $finalSubtotal, $sameDay);
        error_log("🚚 Shipping calculation - Country: $country, City: $city, Weight: $finalTotalWeight, Cost: $finalShippingCost");
        $unitCount = 0;
        foreach ($_SESSION['cart'] as $cartLine) {
            $unitCount += (int)($cartLine['quantity'] ?? 0);
        }
        $wrapAmount = !empty($_POST['gift_wrap']) ? gift_wrap_fee($db) * $unitCount : 0;
        $vatConfig = vat_settings($db);
        $vatRate = $vatConfig['enabled'] ? $vatConfig['rate'] : 0;
        $vatAmount = $vatConfig['enabled'] ? round(($finalSubtotal + $finalShippingCost + $wrapAmount) * $vatRate / 100, 2) : 0;
        $orderTrn = $vatAmount > 0 ? $vatConfig['trn'] : '';
        $finalGrandTotal = $finalSubtotal + $finalShippingCost + $wrapAmount + $vatAmount;
        
        error_log("💰 Final totals - Cart: $finalCartTotal, Shipping: $finalShippingCost, Discount: $finalDiscount, Grand Total: $finalGrandTotal");

        // Note: PDO parameterized queries already protect against SQL injection.
        // htmlspecialchars() is NOT applied here — it must only be used at output/display time,
        // never before storing data in the database (would corrupt stored values).

        // Check if customer already exists
        error_log("👤 Checking existing customer for email: $email");
        try {
            $existing_customer = $db->query(
                "SELECT id FROM customers WHERE email = :email LIMIT 1", 
                ['email' => $email]
            )->fetch(PDO::FETCH_ASSOC);

            if ($existing_customer) {
                $customer_id = $existing_customer['id'];
                error_log("👤 Updating existing customer ID: $customer_id");
                // Update customer information
                $db->query(
                    "UPDATE customers SET fullname = :fullname, phone = :phone, address = :address, city = :city, country = :country WHERE id = :id",
                    compact('fullname', 'phone', 'address', 'city', 'country') + ['id' => $customer_id]
                );
                error_log("👤 Customer updated successfully");
            } else {
                error_log("👤 Creating new customer for email: $email");
                // Create new customer
                $db->query(
                    "INSERT INTO customers (fullname, email, phone, address, city, country, created_at) VALUES 
                    (:fullname, :email, :phone, :address, :city, :country, NOW())", 
                    compact('fullname', 'email', 'phone', 'address', 'city', 'country')
                );
                $customer_id = $db->lastInsertId();
                error_log("👤 New customer created with ID: $customer_id");
            }
        } catch (Exception $e) {
            error_log("❌ Database error during customer operation: " . $e->getMessage());
            throw new Exception(t('checkout.error_customer_db'));
        }

        // Determine payment status
        $paymentStatus = ($payment_method === 'COD') ? 'pending' : 'processing';

        // Prepare coupon data from session
        $coupon = $_SESSION['applied_coupon'] ?? null;

        // Create order
        error_log("📦 Creating order for customer ID: $customer_id");
        try {
            $orderParams = [
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
                ];
            $giftSql = '';
            $giftValues = '';
            if (ag_has_column($db, 'orders', 'gift_wrap')) {
                $giftSql = ', gift_recipient_name, gift_recipient_phone, gift_card_message, gift_wrap, gift_wrap_amount, hide_prices, vat_rate, vat_amount, trn, same_day';
                $giftValues = ', :gift_recipient_name, :gift_recipient_phone, :gift_card_message, :gift_wrap, :gift_wrap_amount, :hide_prices, :vat_rate, :vat_amount, :trn, :same_day';
                $orderParams += [
                    'gift_recipient_name' => trim((string)($_POST['gift_recipient_name'] ?? '')),
                    'gift_recipient_phone' => trim((string)($_POST['gift_recipient_phone'] ?? '')),
                    'gift_card_message' => trim((string)($_POST['gift_card_message'] ?? '')),
                    'gift_wrap' => $wrapAmount > 0 ? 1 : 0,
                    'gift_wrap_amount' => $wrapAmount,
                    'hide_prices' => !empty($_POST['hide_prices']) ? 1 : 0,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'trn' => $orderTrn,
                    'same_day' => $sameDay ? 1 : 0,
                ];
            }
            $db->query(
                "INSERT INTO orders 
                    (customer_id, total_amount, total_weight, shipping_aed, payment_status, payment_method, note, remarks, 
                     coupon_code, discount_type, discount_value, discount_amount, order_date $giftSql)
                VALUES 
                    (:customer_id, :total, :total_weight, :shipping_aed, :payment_status, :payment_method, :note, :remarks,
                     :coupon_code, :discount_type, :discount_value, :discount_amount, NOW() $giftValues)",
                $orderParams
            );
            $order_id = $db->lastInsertId();
            error_log("📦 Order created successfully with ID: $order_id");
        } catch (Exception $e) {
            error_log("❌ Database error during order creation: " . $e->getMessage());
            throw new Exception(t('checkout.error_order_db'));
        }

        // Save Order Items with stock checking
        error_log("🛍️ Processing " . count($_SESSION['cart']) . " cart items...");
        foreach ($_SESSION['cart'] as $itemKey => $item) {
            try {
                $product = $db->query(
                    "SELECT * FROM products WHERE id = :id AND status = 1", 
                    ['id' => $item['product_id']]
                )->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    error_log("❌ Product {$item['product_id']} not found or inactive");
                    throw new Exception(t('checkout.error_product_unavailable', ['id' => $item['product_id']]));
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
                        error_log("❌ Variation {$item['variation_id']} not found for product {$item['product_id']}");
                        throw new Exception(t('checkout.error_variation_unavailable'));
                    }

                    $price += $variation['additional_price'];
                    $variation_details = "Size: {$variation['size']}, Color: {$variation['color']}";

                    // Check variation stock
                    $needed = cartReservedQuantity($_SESSION['cart'], (int)$item['product_id'], $item['variation_id'] ?? null);
                    if ($variation['stock'] !== null && (int)$variation['stock'] < $needed) {
                        error_log("❌ Insufficient stock for variation {$item['variation_id']} - Required: {$item['quantity']}, Available: {$variation['stock']}");
                        throw new Exception(t('checkout.error_variation_stock', ['name' => loc_field($product, 'name')]));
                    }
                } else {
                    // Check product stock
                    $needed = cartReservedQuantity($_SESSION['cart'], (int)$item['product_id'], null);
                    if ($product['stock'] !== null && (int)$product['stock'] < $needed) {
                        error_log("❌ Insufficient stock for product {$item['product_id']} - Required: {$item['quantity']}, Available: {$product['stock']}");
                        throw new Exception(t('checkout.error_stock', ['name' => loc_field($product, 'name')]));
                    }
                }

                $chargedPrice = $price;
                $lineRole = 'standalone';
                $catalogPrice = $price;
                $discountPercent = null;
                $bundleId = null;
                $pricedLine = $finalPricedCart['lines'][$itemKey] ?? null;
                if (is_array($pricedLine) && !empty($pricedLine['quantity'])) {
                    $chargedPrice = round($pricedLine['line_total'] / $pricedLine['quantity'], 2);
                    $lineRole = $pricedLine['line_role'] ?? 'standalone';
                    $catalogPrice = $pricedLine['catalog_unit'] ?? $price;
                    $discountPercent = $pricedLine['discount_percent'] ?? null;
                    $bundleId = $pricedLine['bundle_id'] ?? null;
                }
                $db->query(
                    "INSERT INTO order_items
                        (order_id, product_id, variation_id, quantity, price, bundle_id, line_role, catalog_price, bundle_discount_percent)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $order_id,
                        $item['product_id'],
                        $item['variation_id'] ?? null,
                        $item['quantity'],
                        $chargedPrice,
                        $bundleId,
                        $lineRole,
                        $catalogPrice,
                        $discountPercent,
                    ]
                );
                
                error_log("✅ Order item added - Product: {$item['product_id']}, Qty: {$item['quantity']}, Price: $price");

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
                
            } catch (Exception $e) {
                error_log("❌ Error processing cart item {$item['product_id']}: " . $e->getMessage());
                throw $e; // Re-throw to be caught by the main try-catch
            }
        }
        
        if ($payment_method === 'COD') {
            reserve_order_stock($db, (int)$order_id);
        }

        foreach ($finalPricedCart['bundles'] as $bundleRow) {
            $db->query(
                "INSERT INTO order_bundles
                    (order_id, bundle_id, bundle_name_en, bundle_name_ar, discount_percent, removed_value, replacement_value, discount_voided, discount_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $order_id,
                    $bundleRow['bundle_id'] ?: null,
                    $bundleRow['name_en'],
                    $bundleRow['name_ar'],
                    $bundleRow['discount_percent'],
                    $bundleRow['removed_value'],
                    $bundleRow['replacement_value'],
                    $bundleRow['discount_voided'] ? 1 : 0,
                    $bundleRow['discount_amount'],
                ]
            );
        }

        error_log("✅ All cart items processed successfully");

        // Process payment based on method
        error_log("💳 Processing payment method: $payment_method");
        if ($payment_method === 'COD') {
            // COD - Send immediate confirmation
            error_log("💰 Processing COD payment for order: $order_id");
            send_confirmation($order_id, $fullname, $finalGrandTotal, $payment_method, $email, [
                'fullname' => $fullname,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'country' => $country
            ]);
            exit;
        } elseif ($payment_method === 'Ziina') {
            error_log("💳 Processing Ziina payment for order: $order_id");
            // Store order details in session for payment callback
            $_SESSION['payment_data'] = [
                'order_id' => $order_id,
                'customer_name' => $fullname,
                'customer_email' => $email,
                'total_amount' => $finalGrandTotal,
                'payment_method' => $payment_method
            ];
            error_log("💳 Payment data stored in session");

            try {
                error_log("💳 Initializing Ziina payment gateway...");
                
                // Check if ZiinaPayment class exists
                if (!class_exists('ZiinaPayment')) {
                    error_log("❌ ZiinaPayment class not found");
                    throw new Exception("Payment gateway not available");
                }
                
                $ziina = new ZiinaPayment();
                error_log("💳 Ziina payment object created");
                
                $response = $ziina->createPaymentIntent(
                    $order_id, 
                    $finalGrandTotal, 
                    "AleppoGift Order #$order_id"
                );
                
                error_log("💳 Ziina response: " . json_encode($response, JSON_UNESCAPED_UNICODE));

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
                    
                    error_log("💳 Order updated with payment intent, redirecting to: " . $response['payment_url']);

                    // Redirect to Ziina payment page
                    header("Location: " . $response['payment_url']);
                    exit;
                } else {
                    error_log("❌ Ziina payment intent creation failed: " . ($response['error'] ?? 'Unknown error'));
                    throw new Exception("Payment service unavailable: " . ($response['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                // Log payment error
                error_log("❌ Ziina payment error for Order #$order_id: " . $e->getMessage());
                error_log("📍 Error details: " . $e->getFile() . " at line " . $e->getLine());
                
                // Update order status to failed
                $db->query(
                    "UPDATE orders SET payment_status = 'failed', remarks = :error WHERE id = :id",
                    ['error' => $e->getMessage(), 'id' => $order_id]
                );
                
                throw new Exception(t('checkout.error_payment_failed'));
            }
        } else {
            throw new Exception(t('checkout.error_payment_method'));
        }

    } catch (Exception $e) {
        // Enhanced error logging with full context
        $error_context = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'stack_trace' => $e->getTraceAsString(),
            'post_data' => $_POST,
            'session_cart' => $_SESSION['cart'] ?? 'empty',
            'session_discount' => $_SESSION['discount_amount'] ?? 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        // Log detailed error information
        error_log("🚨 CHECKOUT ERROR - " . $e->getMessage());
        error_log("📊 ERROR CONTEXT: " . json_encode($error_context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Log specific error details
        error_log("📍 Error occurred in: " . $e->getFile() . " at line " . $e->getLine());
        error_log("🔍 Stack trace: " . $e->getTraceAsString());
        
        $_SESSION['checkout_error'] = $e->getMessage();
        // Don't redirect, show error on same page
    } catch (Error $e) {
        // Catch fatal errors as well
        $error_context = [
            'timestamp' => date('Y-m-d H:i:s'),
            'fatal_error' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'stack_trace' => $e->getTraceAsString(),
            'post_data' => $_POST,
            'session_data' => [
                'cart' => $_SESSION['cart'] ?? 'empty',
                'discount' => $_SESSION['discount_amount'] ?? 0,
                'csrf_token' => isset($_SESSION['csrf_token']) ? 'present' : 'missing'
            ]
        ];
        
        error_log("💥 FATAL ERROR IN CHECKOUT: " . $e->getMessage());
        error_log("📊 FATAL ERROR CONTEXT: " . json_encode($error_context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $_SESSION['checkout_error'] = t('checkout.error_system');
    } catch (Throwable $e) {
        // Catch any other type of error
        error_log("⚠️ UNEXPECTED ERROR IN CHECKOUT: " . $e->getMessage());
        error_log("📍 File: " . $e->getFile() . " Line: " . $e->getLine());
        error_log("🔍 Trace: " . $e->getTraceAsString());
        
        $_SESSION['checkout_error'] = t('checkout.error_unexpected');
    }
}

// --- Confirmation function ---
function send_confirmation($order_id, $fullname, $grandTotal, $payment_method, $email, $customer_data = []) {
    global $db;
    
    error_log("📧 Starting confirmation process for Order #$order_id");
    
    try {
        // Send WhatsApp notification to admin
        error_log("📱 Sending WhatsApp notification for Order #$order_id");
        sendAdminWhatsApp($order_id, $fullname, $grandTotal, $payment_method);
        error_log("✅ WhatsApp sent for Order #$order_id");

        // Send email notification to admin
        error_log("📧 Sending admin email notification for Order #$order_id");
        sendAdminOrderNotification($order_id, $fullname, $grandTotal, $payment_method, $customer_data);
        error_log("✅ Admin email notification sent for Order #$order_id");

        $isUnpaidCod = strcasecmp((string)$payment_method, 'COD') === 0;
        if ($isUnpaidCod) {
            error_log("📄 Skipping invoice for unpaid cash-on-delivery order #$order_id");
        } else {
            error_log("📄 Starting invoice PDF generation for Order #$order_id");
            try {
                require_once('../includes/generate_invoice_pdf.php');
                $generator = new PDFInvoiceGenerator();
                $result = $generator->generateInvoicePDF($order_id);
                if (!empty($result['success'])) {
                    error_log("✅ Invoice PDF generated for Order #$order_id at: " . ($result['file_path'] ?? ''));
                } else {
                    error_log("❌ Invoice PDF generation failed for Order #$order_id: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (Throwable $e) {
                error_log("❌ Invoice generation error for Order #$order_id: " . $e->getMessage());
            }
        }

        try {
            $status = sendConfirmedOrderEmail($email, $order_id, $fullname, $customer_data);
            error_log($status
                ? "✅ Confirmation email sent for Order #$order_id to $email."
                : "❌ Confirmation email failed for Order #$order_id to $email.");
        } catch (Throwable $e) {
            error_log("❌ Confirmation email error for Order #$order_id: " . $e->getMessage());
        }

        $_SESSION['confirmed_order_' . $order_id] = true;

        // Clear cart only after successful confirmation
        error_log("🧹 Clearing session data for Order #$order_id");
        sharedCartMarkUsed();
        $_SESSION['cart'] = [];
        unset($_SESSION['applied_coupon']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['payment_data']);
        
        // Clean output buffer and redirect
        if (ob_get_level()) {
            $buffer_content = ob_get_clean();
            if (!empty($buffer_content)) {
                error_log("⚠️ Output buffer contained data: " . substr($buffer_content, 0, 200));
            }
        }
        
        error_log("🎉 Redirecting to thank you page for Order #$order_id");
        header("Location: thankyou.php?order=$order_id");
        exit;
        
    } catch (Exception $e) {
        error_log("❌ Confirmation error for Order #$order_id: " . $e->getMessage());
        error_log("📍 Error details: " . $e->getFile() . " at line " . $e->getLine());
        error_log("🔍 Stack trace: " . $e->getTraceAsString());
        // Don't clear cart if confirmation fails
        header("Location: thankyou.php?order=$order_id&error=1");
        exit;
    } catch (Throwable $e) {
        error_log("💥 Fatal error in confirmation for Order #$order_id: " . $e->getMessage());
        error_log("📍 Error details: " . $e->getFile() . " at line " . $e->getLine());
        error_log("🔍 Stack trace: " . $e->getTraceAsString());
        header("Location: thankyou.php?order=$order_id&error=1");
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
    <title><?= te('checkout.page_title') ?></title>
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/checkout-clean.css')) ?>?v=20261006m">
</head>
<body>
    <?php
require_once(__DIR__ . '/../includes/header.php'); ?>

    <main class="container store-main my-4">
        <header class="page-hero">
            <p class="page-kicker"><?= te('checkout') ?></p>
            <h1><?= te('checkout.heading') ?></h1>
            <p class="page-lead"><?= te('checkout.lead') ?></p>
        </header>
        <ol class="flow-steps" aria-label="<?= te('checkout.progress_aria') ?>">
            <li class="is-done"><a href="cart.php"><span>1</span> <?= te('cart') ?></a></li>
            <li class="is-current"><span>2</span> <?= te('checkout') ?></li>
            <li><span>3</span> <?= te('checkout.step_confirmation') ?></li>
        </ol>
        <div class="checkout-page">

            <?php
if (isset($_SESSION['checkout_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?= te('checkout.error_label') ?></strong> <?php
echo htmlspecialchars($_SESSION['checkout_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= te('close') ?>"></button>
            </div>
            <?php
unset($_SESSION['checkout_error']); ?>
            <?php endif; ?>
        
            <div class="checkout-layout">
                <div class="checkout-main">
                    <div class="checkout-panel">
                            <form method="post" class="checkout-form" id="checkout-form" novalidate>
                                <!-- CSRF token -->
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                <section class="checkout-block">
                                    <header class="checkout-block-head">
                                        <span class="checkout-step-num">1</span>
                                        <div>
                                            <h2><?= te('checkout.contact_title') ?></h2>
                                            <p><?= te('checkout.contact_text') ?></p>
                                        </div>
                                    </header>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="fullname" class="form-label fw-semibold"><?= te('checkout.full_name') ?></label>
                                            <input type="text" id="fullname" name="fullname" required minlength="2" maxlength="100"
                                                   value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"
                                                   class="form-control" autocomplete="name">
                                            <div class="invalid-feedback"><?= te('checkout.invalid_fullname') ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label fw-semibold"><?= te('checkout.email') ?></label>
                                            <input type="email" id="email" name="email" required maxlength="100"
                                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                                   class="form-control" autocomplete="email">
                                            <div class="invalid-feedback"><?= te('checkout.error_email') ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label fw-semibold"><?= te('checkout.phone') ?></label>
                                            <input type="tel" id="phone" name="phone" required minlength="6" maxlength="20"
                                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                                   class="form-control" autocomplete="tel"
                                                   pattern="[0-9+\-\s()]{6,20}">
                                            <div class="invalid-feedback"><?= te('checkout.invalid_phone') ?></div>
                                        </div>
                                        <div class="col-12">
                                            <h3 class="h6"><?= htmlspecialchars(t('gift')) ?></h3>
                                            <input class="form-control mb-2" name="gift_recipient_name" placeholder="<?= te('checkout.recipient_name') ?>">
                                            <input class="form-control mb-2" name="gift_recipient_phone" placeholder="<?= te('checkout.recipient_phone') ?>">
                                            <textarea class="form-control mb-2" name="gift_card_message" placeholder="<?= te('checkout.card_message') ?>"></textarea>
                                            <label class="form-check"><input class="form-check-input" type="checkbox" name="gift_wrap" id="gift_wrap" value="1"> <?= te('checkout.gift_wrap_fee', ['fee' => number_format(gift_wrap_fee($db), 2)]) ?></label>
                                            <label class="form-check"><input class="form-check-input" type="checkbox" name="hide_prices" value="1"> <?= te('checkout.hide_prices') ?></label>
                                        </div>
                                        <div class="col-12">
                                            <label for="note" class="form-label fw-semibold"><?= te('checkout.order_notes') ?></label>
                                            <textarea id="note" name="note" rows="2" maxlength="500"
                                                      class="form-control"
                                                      placeholder="<?= te('checkout.notes_placeholder') ?>"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </section>

                                <section class="checkout-block">
                                    <header class="checkout-block-head">
                                        <span class="checkout-step-num">2</span>
                                        <div>
                                            <h2><?= te('checkout.delivery_title') ?></h2>
                                            <p><?= te('checkout.delivery_text') ?></p>
                                        </div>
                                    </header>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="address" class="form-label fw-semibold"><?= te('checkout.street_address') ?></label>
                                            <textarea id="address" name="address" required minlength="5" maxlength="300" rows="3"
                                                      class="form-control" autocomplete="street-address"
                                                      placeholder="<?= te('checkout.address_placeholder') ?>"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                                            <div class="invalid-feedback"><?= te('checkout.invalid_address') ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="country" class="form-label fw-semibold"><?= te('checkout.country') ?></label>
                                            <select id="country" name="country" required class="form-select" autocomplete="country-name">
                                                <?php
                                                require_once 'ajax/countries.php';
                                                $selectedCountry = $_POST['country'] ?? 'United Arab Emirates';
                                                foreach ($countries as $name => $flag): ?>
                                                    <option value="<?= htmlspecialchars($name) ?>" 
                                                            <?= $name === $selectedCountry ? 'selected' : '' ?>>
                                                        <?= $flag ?> <?= htmlspecialchars($name) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback"><?= te('checkout.invalid_country') ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="city" class="form-label fw-semibold"><?= te('checkout.city') ?></label>
                                            <input type="text" id="city" name="city" required minlength="2" maxlength="50"
                                                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                                   class="form-control" autocomplete="address-level2">
                                            <div class="invalid-feedback"><?= te('checkout.error_city') ?></div>
                                        </div>
                                        <?php if ($savedAddresses): ?>
                                        <div class="col-12">
                                            <label class="form-label" for="address_id"><?= te('checkout.saved_address') ?></label>
                                            <select class="form-select" name="address_id" id="address_id">
                                                <option value=""><?= te('checkout.new_address') ?></option>
                                                <?php foreach ($savedAddresses as $savedAddress): ?>
                                                    <option value="<?= (int)$savedAddress['id'] ?>"><?= htmlspecialchars(($savedAddress['label'] ?: $savedAddress['fullname']) . ' — ' . $savedAddress['city']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-12">
                                            <label class="form-check">
                                                <input class="form-check-input" type="checkbox" name="same_day" id="same_day" value="1">
                                                <?= htmlspecialchars(t('same_day')) ?> <?= te('checkout.same_day_cities', ['fee' => number_format(same_day_fee($db), 2)]) ?>
                                            </label>
                                        </div>
                                        <div class="col-12">
                                            <div class="ship-cost">
                                                <span><i class="fas fa-truck me-2"></i><?= te('shipping') ?></span>
                                                <strong id="shipping-cost"><?= formatAedUsd($shippingAED) ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="checkout-block">
                                    <header class="checkout-block-head">
                                        <span class="checkout-step-num">3</span>
                                        <div>
                                            <h2><?= te('checkout.payment_title') ?></h2>
                                            <p id="payment-info"><?= te('checkout.payment_info_uae') ?></p>
                                        </div>
                                    </header>
                                    <div class="pay-options" id="pay-options">
                                        <button type="button" class="pay-option" data-method="COD">
                                            <span class="pay-option-icon"><i class="fas fa-hand-holding-dollar"></i></span>
                                            <span class="pay-option-copy">
                                                <strong><?= te('checkout.cod_title') ?></strong>
                                                <small><?= te('checkout.cod_text') ?></small>
                                            </span>
                                        </button>
                                        <button type="button" class="pay-option" data-method="Ziina">
                                            <span class="pay-option-icon"><i class="fas fa-credit-card"></i></span>
                                            <span class="pay-option-copy">
                                                <strong><?= te('checkout.card_title') ?></strong>
                                                <small><?= te('checkout.card_text') ?></small>
                                            </span>
                                        </button>
                                    </div>
                                    <select id="payment_method" name="payment_method" required class="pay-method-native" tabindex="-1" aria-hidden="true">
                                        <option value=""><?= te('checkout.choose_payment') ?></option>
                                        <option value="COD" <?= ($_POST['payment_method'] ?? '') === 'COD' ? 'selected' : '' ?>><?= te('checkout.cod_option') ?></option>
                                        <option value="Ziina" <?= ($_POST['payment_method'] ?? '') === 'Ziina' ? 'selected' : '' ?>><?= te('checkout.card_title') ?></option>
                                    </select>
                                </section>

                                <div class="checkout-block checkout-trust">
                                    <?php
                                    $location = 'checkout';
                                    include(__DIR__ . '/../includes/trust_badges.php');
                                    ?>
                                </div>
                            </form>
                    </div>
                </div>

                <aside class="checkout-aside">
                        <div class="summary-card">
                            <h2><?= te('checkout.your_order') ?></h2>
                            <?php foreach ($pricedCart['bundles'] as $bundleGroup): ?>
                                <div class="summary-item-meta"><?= htmlspecialchars(loc_field($bundleGroup, 'name')) ?> — <?= htmlspecialchars($bundleGroup['message']) ?></div>
                            <?php endforeach; ?>
                            <?php foreach ($_SESSION['cart'] as $itemKey => $item):
                                $product = $db->query(
                                    "SELECT p.*,
                                        (SELECT image_path FROM product_images
                                         WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
                                     FROM products p WHERE p.id = :id",
                                    ['id' => $item['product_id']]
                                )->fetch(PDO::FETCH_ASSOC);
                                if (!$product) continue;
                                $pricedLine = $pricedCart['lines'][$itemKey] ?? null;
                                $price = $pricedLine['charged_unit'] ?? $product['price'];
                                $displayImage = $product['main_image'];
                                if ($item['variation_id']) {
                                    $variation = $db->query(
                                        "SELECT pv.additional_price, pi.image_path AS variant_image
                                         FROM product_variations pv
                                         LEFT JOIN product_images pi ON pv.image_id = pi.id
                                         WHERE pv.id = :id",
                                        ['id' => $item['variation_id']]
                                    )->fetch(PDO::FETCH_ASSOC);
                                    if ($variation) {
                                        if ($pricedLine === null) {
                                            $price += $variation['additional_price'];
                                        }
                                        if (!empty($variation['variant_image'])) {
                                            $displayImage = $variation['variant_image'];
                                        }
                                    }
                                }
                                $imageSrc = str_replace('../', '', $displayImage ?: 'uploads/default-product.jpg');
                                ?>
                                <div class="summary-item">
                                    <img src="<?= htmlspecialchars($imageSrc) ?>" alt="">
                                    <div>
                                        <div class="summary-item-name"><?= htmlspecialchars(loc_field($product, 'name')) ?></div>
                                        <div class="summary-item-meta"><?= te('checkout.qty', ['qty' => $item['quantity']]) ?></div>
                                        <div class="summary-item-price"><?= formatAedUsd($pricedLine['line_total'] ?? ($price * $item['quantity'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="summary-totals">
                                <div>
                                    <span><?= te('checkout.items') ?></span>
                                    <span id="item-total" data-value="<?= $cartTotal ?>"><?= formatAedUsd($cartTotal) ?></span>
                                </div>
                                <div>
                                    <span><?= te('shipping') ?></span>
                                    <span id="shipping-cost2"><?= formatAedUsd($shippingAED) ?></span>
                                </div>
                                <div id="wrap-row" hidden>
                                    <span><?= te('checkout.gift_wrap') ?></span>
                                    <span id="wrap-amount"></span>
                                </div>
                                <div id="vat-row" hidden>
                                    <span><?= te('checkout.vat') ?></span>
                                    <span id="vat-amount"></span>
                                </div>
                                <div id="discount-row" hidden>
                                    <span><?= te('checkout.discount') ?></span>
                                    <span id="discount-amount"></span>
                                </div>
                                <div class="grand">
                                    <span><?= te('checkout.total') ?></span>
                                    <span id="grand-total"><?= formatAedUsd($cartTotal + $shippingAED) ?></span>
                                </div>
                            </div>
                            <div class="coupon-row">
                                <input type="text" id="coupon-code" class="form-control" placeholder="<?= te('checkout.coupon_code') ?>" aria-label="<?= te('checkout.coupon_code') ?>">
                                <button type="button" class="btn btn-outline-primary" onclick="applyCoupon()"><?= te('checkout.apply') ?></button>
                            </div>
                            <p id="coupon-message" class="mt-2 mb-0"></p>
                            <?php if (function_exists('selected_store_currency') && selected_store_currency()['code'] !== 'AED'): ?>
                                <p class="small text-muted"><?= htmlspecialchars(t('charged_aed')) ?></p>
                            <?php endif; ?>
                            <div class="checkout-paybar">
                                <button type="submit" class="checkout-submit" id="submit-btn" form="checkout-form">
                                    <i class="fas fa-lock me-2"></i>
                                    <span class="btn-text"><?= te('checkout.place_order') ?></span>
                                </button>
                            </div>
                            <small class="checkout-legal">
                                <?= strtr(te('checkout.legal_agree'), [
                                    '{terms}' => '<a href="terms_of_service.php" target="_blank">' . te('footer.terms') . '</a>',
                                    '{privacy}' => '<a href="privacy_policy.php" target="_blank">' . te('footer.privacy') . '</a>',
                                ]) ?>
                            </small>
                            <a href="<?= htmlspecialchars(store_url('download_quotation.php')) ?>" class="quote-link" onclick="downloadQuotationWithShippingDetails(event)">
                                <i class="fas fa-file-arrow-down"></i> <?= te('checkout.download_quote') ?>
                            </a>
                        </div>
                </aside>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" 
             id="loadingOverlay" style="display: none!important; background: rgba(0,0,0,0.7); z-index: 9999;">
            <div class="text-center text-white">
                <div class="spinner-border loading-spinner mb-3" role="status">
                    <span class="visually-hidden"><?= te('loading') ?></span>
                </div>
                <h4><?= te('checkout.processing_order') ?></h4>
                <p class="text-light"><?= te('checkout.please_wait') ?></p>
            </div>
        </div>
    </main>

    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>

    <script>
    const AED_TO_USD_RATE = <?= json_encode(function_exists('getAedToUsdRate') ? getAedToUsdRate() : 0.2723) ?>;
    const CHECKOUT_TEXT = <?= json_encode([
        'paymentInfoUae' => t('checkout.payment_info_uae'),
        'paymentInfoIntl' => t('checkout.payment_info_intl'),
        'calculating' => t('checkout.calculating'),
        'couponApplied' => t('checkout.coupon_applied'),
        'couponError' => t('checkout.coupon_error'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

    function parseDisplayedAed(text) {
        const raw = String(text || '');
        const aedAmount = raw.match(/AED\s*(-?\d+(?:\.\d+)?)/i);
        if (aedAmount) {
            return parseFloat(aedAmount[1]);
        }
        const first = raw.replace(/,/g, '').match(/-?\d+(?:\.\d+)?/);
        return first ? parseFloat(first[0]) : 0;
    }

    function formatAedUsdClient(amount) {
        if (typeof window.formatStoreMoney === 'function') {
            return window.formatStoreMoney(amount, true);
        }
        const numericAmount = parseFloat(amount) || 0;
        const usd = numericAmount * AED_TO_USD_RATE;
        return `${numericAmount.toFixed(2)} AED (USD ${usd.toFixed(2)})`;
    }

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
                
                paymentInfo.textContent = CHECKOUT_TEXT.paymentInfoUae;
            } else {
                codOption.disabled = true;
                ziinaOption.disabled = false;
                paymentMethodSelect.value = 'Ziina';
                paymentInfo.textContent = CHECKOUT_TEXT.paymentInfoIntl;
            }
            syncPayCards();
        }

        function syncPayCards() {
            const value = paymentMethodSelect.value;
            document.querySelectorAll('.pay-option').forEach((card) => {
                const method = card.dataset.method;
                const option = paymentMethodSelect.querySelector('option[value="' + method + '"]');
                const disabled = !option || option.disabled;
                card.disabled = disabled;
                card.classList.toggle('is-selected', value === method);
                card.setAttribute('aria-pressed', value === method ? 'true' : 'false');
            });
        }

        document.querySelectorAll('.pay-option').forEach((card) => {
            card.addEventListener('click', () => {
                if (card.disabled) {
                    return;
                }
                paymentMethodSelect.value = card.dataset.method;
                syncPayCards();
            });
        });

        function updateShippingCost() {
            const country = countrySelect.value.trim().toLowerCase();
            const totalWeight = <?= json_encode($totalWeight) ?>;
            let city = cityInput.value.trim().toLowerCase();
            if (!city) {
                city = '_';
            }
            
            console.log(`Calculating shipping for country: ${country}, city: ${city}`);
            if (!country || !city) {
                document.getElementById('shipping-cost').textContent = formatAedUsdClient(30);
                return;
            } else {
                document.getElementById('shipping-cost').textContent = CHECKOUT_TEXT.calculating;
                
                fetch('ajax/calculate_shipping.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `country=${encodeURIComponent(country)}&city=${encodeURIComponent(city)}&totalWeight=${encodeURIComponent(totalWeight)}&merchandise=${encodeURIComponent(document.getElementById('item-total').dataset.value || 0)}&same_day=${document.getElementById('same_day') && document.getElementById('same_day').checked ? 1 : 0}`
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
                            document.getElementById('shipping-cost').textContent = formatAedUsdClient(data.shippingCost);
                            document.getElementById('shipping-cost2').textContent = formatAedUsdClient(data.shippingCost);
                            updateGrandTotal(data.shippingCost);
                        } else {
                            console.error('Shipping calculation failed:', data.error || 'Unknown error');
                            document.getElementById('shipping-cost').textContent = formatAedUsdClient(30); // Fallback
                            updateGrandTotal(30);
                        }
                    } catch (parseError) {
                        console.error('Failed to parse shipping response:', parseError);
                        console.error('Raw response:', text);
                        document.getElementById('shipping-cost').textContent = formatAedUsdClient(30); // Fallback
                        updateGrandTotal(30);
                    }
                })
                .catch(error => {
                    console.error('Shipping cost update failed:', error);
                    document.getElementById('shipping-cost').textContent = formatAedUsdClient(30); // Fallback
                    updateGrandTotal(30);
                });
            }
        }

        function updateGrandTotal(shipping) {
            console.log('updateGrandTotal: shipping:', shipping);
            const itemTotal = parseFloat(document.getElementById('item-total').dataset.value || 0);
            const wrapFee = <?= json_encode(gift_wrap_fee($db)) ?>;
            const units = <?= json_encode((int)array_sum(array_column($_SESSION['cart'], 'quantity'))) ?>;
            const wrap = document.getElementById('gift_wrap') && document.getElementById('gift_wrap').checked ? wrapFee * units : 0;
            const vatOn = <?= json_encode(vat_settings($db)['enabled']) ?>;
            const vatRate = <?= json_encode(vat_settings($db)['rate']) ?>;
            const vat = vatOn ? Math.round((itemTotal + parseFloat(shipping) + wrap) * vatRate) / 100 : 0;
            const wrapRow = document.getElementById('wrap-row');
            const vatRow = document.getElementById('vat-row');
            if (wrapRow) {
                wrapRow.hidden = wrap <= 0;
                document.getElementById('wrap-amount').textContent = formatAedUsdClient(wrap);
            }
            if (vatRow) {
                vatRow.hidden = vat <= 0;
                document.getElementById('vat-amount').textContent = formatAedUsdClient(vat);
            }
            const grandTotal = itemTotal + parseFloat(shipping) + wrap + vat;
            console.log('grandTotal=', grandTotal, ' itemTotal=', itemTotal, ' shipping=', parseFloat(shipping));
            document.getElementById('grand-total').textContent = formatAedUsdClient(grandTotal);
            document.getElementById('shipping-cost').textContent = formatAedUsdClient(parseFloat(shipping));
            document.getElementById('shipping-cost2').textContent = formatAedUsdClient(parseFloat(shipping));
        }

        // Bind events
        countrySelect.addEventListener('change', () => {
            handlePaymentMethodByCountry();
            updateShippingCost();
        });

        cityInput.addEventListener('input', updateShippingCost);
        const sameDayInput = document.getElementById('same_day');
        if (sameDayInput) sameDayInput.addEventListener('change', updateShippingCost);
        const wrapInput = document.getElementById('gift_wrap');
        if (wrapInput) wrapInput.addEventListener('change', () => updateShippingCost());

        // Initial run
        handlePaymentMethodByCountry();
        updateShippingCost();
    });
    

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('checkout-form');
        const overlay = document.getElementById('loadingOverlay');

        if (form && overlay) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    const firstInvalid = form.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    }
                    form.reportValidity();
                    return;
                }
                overlay.style.setProperty('display', 'flex', 'important');
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
                document.getElementById('coupon-message').textContent = CHECKOUT_TEXT.couponApplied;
                updateGrandTotalWithDiscount(data.discountAmount);
            } else {
                document.getElementById('coupon-message').style.color = "red";
                document.getElementById('coupon-message').textContent = data.message;
            }
        } catch (err) {
            console.error("JSON parse error:", err, text);
            document.getElementById('coupon-message').style.color = "red";
            document.getElementById('coupon-message').textContent = CHECKOUT_TEXT.couponError;
        }
    });
    }

    function updateGrandTotalWithDiscount(discountAmount) {
        discountAmount = parseFloat(discountAmount) || 0;

        const itemTotal = parseFloat(document.getElementById('item-total').dataset.value || 0);
        const shipping = parseDisplayedAed(document.getElementById('shipping-cost2').textContent);

        const grandBeforeDiscount = itemTotal + shipping;
        const grandTotal = Math.max(grandBeforeDiscount - discountAmount, 0);

        document.getElementById('grand-total').textContent = formatAedUsdClient(grandTotal);

        // Show discount visually
        const discountRow = document.getElementById('discount-row');
        const discountDisplay = document.getElementById('discount-amount');
        if (discountRow && discountDisplay) {
            const discountUsd = discountAmount * AED_TO_USD_RATE;
            discountDisplay.textContent = "- " + discountAmount.toFixed(2) + ` AED (-USD ${discountUsd.toFixed(2)})`;
            discountRow.hidden = false;
            discountRow.style.display = 'flex';
        }
    }

    function downloadQuotationWithShippingDetails(event) {
        event.preventDefault();

        const checkoutForm = document.getElementById('checkout-form');
        if (!checkoutForm) {
            window.open(<?= json_encode(store_url('download_quotation.php')) ?>, '_blank');
            return;
        }

        const getValue = (id) => {
            const el = document.getElementById(id);
            return el ? el.value.trim() : '';
        };

        const shippingText = document.getElementById('shipping-cost2')
            ? document.getElementById('shipping-cost2').textContent
            : '';
        const shippingCost = parseFloat((shippingText || '').replace(/[^0-9.]/g, '')) || 0;

        const payload = {
            fullname: getValue('fullname'),
            email: getValue('email'),
            phone: getValue('phone'),
            address: getValue('address'),
            country: getValue('country'),
            city: getValue('city'),
            note: getValue('note'),
            totalWeight: <?= json_encode((float)$totalWeight) ?>,
            shipping_cost: shippingCost
        };

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = <?= json_encode(store_url('download_quotation.php')) ?>;
        form.target = '_blank';

        Object.entries(payload).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                return;
            }

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = String(value);
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="assets/js/main.js?v=20261016qty"></script>
<script src="assets/js/enhanced-main.js?v=20261012i18n"></script>

</body>
</html>

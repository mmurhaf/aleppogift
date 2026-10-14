<?php
/**
 * Simple Email Functions
 * Basic email functionality without external dependencies
 *
 * @author aleppogift Development Team
 * @version 1.0
 * @date August 12, 2025
 */

require_once __DIR__ . '/order_email_layout.php';

/**
 * Send admin notification email when a new order is placed
 */
function sendAdminOrderNotification($order_id, $customer_name, $grand_total, $payment_method, $customer_data = []) {
    if (!defined('EMAIL_FROM') || !defined('EMAIL_FROM_NAME') || !defined('ADMIN_EMAIL')) {
        error_log("❌ Admin email constants not defined — skipping admin notification");
        return false;
    }

    $admin_email = ADMIN_EMAIL;
    $subject = "New order #$order_id — AleppoGift";
    $message = ag_build_admin_order_html($order_id, $customer_name, $grand_total, $payment_method, $customer_data);
    $success = ag_send_html_mail($admin_email, $subject, $message);

    if ($success) {
        error_log("✅ Admin notification email sent to $admin_email for order #$order_id");
    } else {
        error_log("❌ Failed to send admin notification email for order #$order_id");
    }

    return $success;
}

/**
 * Send order confirmation email
 */
function sendOrderEmail($to, $order_id, $customer_name = '') {
    error_log("📧 Starting sendOrderEmail for Order #$order_id to $to");
    
    try {
        // Check if required email constants are defined
        if (!defined('EMAIL_FROM') || !defined('EMAIL_FROM_NAME') || !defined('SITE_URL')) {
            error_log("❌ Required email constants not defined");
            error_log("EMAIL_FROM defined: " . (defined('EMAIL_FROM') ? 'yes' : 'no'));
            error_log("EMAIL_FROM_NAME defined: " . (defined('EMAIL_FROM_NAME') ? 'yes' : 'no'));
            error_log("SITE_URL defined: " . (defined('SITE_URL') ? 'yes' : 'no'));
            return false;
        }
        
        error_log("✅ Email constants check passed");
        error_log("📧 EMAIL_FROM: " . EMAIL_FROM);
        error_log("📧 SITE_URL: " . SITE_URL);
        
        $subject = "Order confirmation #$order_id — AleppoGift";
        $message = ag_build_simple_confirmation_html($order_id, (string)$customer_name);
        
        error_log("📧 Email content prepared for Order #$order_id");
        error_log("📧 Sending email via mail() function...");
        
        $success = ag_send_html_mail($to, $subject, $message);
        
        if ($success) {
            error_log("✅ Order email sent successfully to $to for order #$order_id");
        } else {
            error_log("❌ Failed to send order email to $to for order #$order_id");
            error_log("⚠️ PHP mail() function returned false");
        }
        
        return $success;
        
    } catch (Exception $e) {
        error_log("❌ Exception in sendOrderEmail: " . $e->getMessage());
        error_log("📍 Exception location: " . $e->getFile() . " at line " . $e->getLine());
        return false;
    } catch (Error $e) {
        error_log("💥 Fatal error in sendOrderEmail: " . $e->getMessage());
        error_log("📍 Fatal error location: " . $e->getFile() . " at line " . $e->getLine());
        return false;
    }
}

/**
 * Send enhanced order confirmation email with cart contents, shipping details, and PDF attachment
 */
function sendEnhancedOrderEmail($to, $order_id, $customer_name = '', $customer_data = [], $pdf_path = null, $forceInvoice = false) {
    error_log("📧 Starting sendEnhancedOrderEmail for Order #$order_id to $to");
    
    try {
        // Check if required email constants are defined
        if (!defined('EMAIL_FROM') || !defined('EMAIL_FROM_NAME') || !defined('SITE_URL')) {
            error_log("❌ Required email constants not defined");
            return false;
        }
        
        global $db;
        
        // Get order details from database
        $order = $db->query(
            "SELECT * FROM orders WHERE id = :order_id",
            ['order_id' => $order_id]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            error_log("❌ Order #$order_id not found in database");
            return false;
        }

        $invoiceAllowed = $forceInvoice || ag_order_invoice_allowed($order);
        if (!$invoiceAllowed) {
            $pdf_path = null;
            error_log("📄 Invoice omitted for unpaid cash-on-delivery order #$order_id");
        }
        
        // Get order items
        $itemsSql = "SELECT oi.*, COALESCE(NULLIF(oi.product_name, ''), p.name_en, 'Item') AS display_name,
                    p.name_en, p.name_ar, pv.size, pv.color,
                    COALESCE(pi_variant.image_path, pi_main.image_path) AS image_path
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             LEFT JOIN product_variations pv ON oi.variation_id = pv.id
             LEFT JOIN product_images pi_variant ON pv.image_id = pi_variant.id
             LEFT JOIN product_images pi_main ON p.id = pi_main.product_id AND pi_main.is_main = 1
             WHERE oi.order_id = :order_id";
        try {
            $order_items = $db->query($itemsSql, ['order_id' => $order_id])->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Order email item images unavailable: ' . $e->getMessage());
            $order_items = $db->query(
                "SELECT oi.*, COALESCE(NULLIF(oi.product_name, ''), p.name_en, 'Item') AS display_name,
                        p.name_en, p.name_ar, pv.size, pv.color
                 FROM order_items oi
                 LEFT JOIN products p ON oi.product_id = p.id
                 LEFT JOIN product_variations pv ON oi.variation_id = pv.id
                 WHERE oi.order_id = :order_id",
                ['order_id' => $order_id]
            )->fetchAll(PDO::FETCH_ASSOC);
        }
        
        error_log("✅ Email constants check passed");
        error_log("📧 Found " . count($order_items) . " items for order #$order_id");
        
        $subject = "Order confirmation #$order_id — AleppoGift";
        
        $attachInvoice = $invoiceAllowed && $pdf_path && is_file($pdf_path);
        $message = ag_build_order_confirmation_html(
            $order,
            $order_items,
            (string)$customer_name,
            is_array($customer_data) ? $customer_data : [],
            $attachInvoice
        );
        
        error_log("📧 Email content prepared for Order #$order_id");
        if ($attachInvoice) {
            error_log("📎 Attaching invoice: " . basename($pdf_path));
        }
        error_log("📧 Sending email via mail() function...");
        
        $success = ag_send_html_mail($to, $subject, $message, $attachInvoice ? $pdf_path : null);
        
        if ($success) {
            error_log("✅ Enhanced order email sent successfully to $to for order #$order_id");
        } else {
            error_log("❌ Failed to send enhanced order email to $to for order #$order_id");
            error_log("⚠️ PHP mail() function returned false");
        }
        
        return $success;
        
    } catch (Exception $e) {
        error_log("❌ Exception in sendEnhancedOrderEmail: " . $e->getMessage());
        error_log("📍 Exception location: " . $e->getFile() . " at line " . $e->getLine());
        return false;
    } catch (Error $e) {
        error_log("💥 Fatal error in sendEnhancedOrderEmail: " . $e->getMessage());
        error_log("📍 Fatal error location: " . $e->getFile() . " at line " . $e->getLine());
        return false;
    }
}

/**
 * Send the order confirmation once. Paid orders include the invoice.
 * Unpaid cash on delivery never includes an invoice.
 */
function sendConfirmedOrderEmail($to, $order_id, $customer_name = '', $customer_data = [], $force = false) {
    global $db;
    $order_id = (int)$order_id;

    if (!$force && $db && ag_confirmation_already_sent($db, $order_id)) {
        error_log("📧 Confirmation email already sent for order #$order_id");
        return true;
    }

    $pdf = null;
    $order = null;
    if ($db) {
        try {
            $order = $db->query('SELECT * FROM orders WHERE id = :id', ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $order = null;
        }
    }
    if ($order && ag_order_invoice_allowed($order)) {
        $candidate = ag_invoice_file_path($order_id);
        if (is_file($candidate)) {
            $pdf = $candidate;
        }
    }

    $status = false;
    if (function_exists('sendEnhancedOrderEmail')) {
        $status = sendEnhancedOrderEmail($to, $order_id, $customer_name, $customer_data, $pdf, false);
    }
    if (!$status) {
        $status = sendOrderEmail($to, $order_id, $customer_name);
    }
    if ($status && $db) {
        ag_mark_confirmation_sent($db, $order_id);
    }
    return $status;
}

/**
 * Email an invoice the customer asked for, including unpaid cash on delivery.
 */
function sendRequestedInvoiceEmail($to, $order_id, $customer_name, $pdf_path) {
    if (!$pdf_path || !is_file($pdf_path)) {
        return false;
    }
    if (!defined('EMAIL_FROM') || !defined('EMAIL_FROM_NAME')) {
        return false;
    }
    global $db;
    $order = ['id' => (int)$order_id, 'payment_method' => 'COD', 'payment_status' => 'pending'];
    if ($db) {
        try {
            $loaded = $db->query('SELECT * FROM orders WHERE id = :id', ['id' => (int)$order_id])->fetch(PDO::FETCH_ASSOC);
            if ($loaded) {
                $order = $loaded;
            }
        } catch (Throwable $e) {
            error_log('Requested invoice order lookup: ' . $e->getMessage());
        }
    }
    $html = ag_build_invoice_request_html($order, (string)$customer_name);
    return ag_send_html_mail($to, 'Your invoice for order #' . (int)$order_id . ' — AleppoGift', $html, $pdf_path);
}

function customerMayRequestInvoice($order_id, array $order): bool
{
    $order_id = (int)$order_id;
    if (!empty($_SESSION['confirmed_order_' . $order_id])) {
        return true;
    }
    if (function_exists('current_customer_id')) {
        $customerId = (int)current_customer_id();
        if ($customerId > 0 && $customerId === (int)($order['customer_id'] ?? 0)) {
            return true;
        }
    }
    return false;
}

/**
 * Tell the store that a customer asked for an invoice on an unpaid delivery order.
 * The invoice itself is not created or sent to the customer.
 */
function sendAdminInvoiceRequest(array $order): bool
{
    if (!defined('ADMIN_EMAIL') || !defined('EMAIL_FROM') || !defined('EMAIL_FROM_NAME')) {
        error_log('Admin invoice request skipped: email constants missing');
        return false;
    }
    $orderId = (int)($order['id'] ?? 0);
    $html = ag_build_admin_invoice_request_html($order);
    $sent = ag_send_html_mail(
        ADMIN_EMAIL,
        'Invoice request for unpaid order #' . $orderId . ' — AleppoGift',
        $html
    );
    error_log($sent
        ? "Admin invoice request sent for order #$orderId"
        : "Admin invoice request failed for order #$orderId");
    return $sent;
}

/**
 * Paid orders can receive an invoice. Unpaid cash on delivery only notifies the store.
 */
function fulfillCustomerInvoiceRequest($order_id): array
{
    global $db;
    $order_id = (int)$order_id;
    if (!$db) {
        $db = new Database();
    }

    $order = $db->query(
        "SELECT o.*, c.fullname AS customer_name, c.email AS customer_email,
                c.phone AS customer_phone, c.address AS customer_address,
                c.city AS customer_city, c.country AS customer_country
         FROM orders o
         LEFT JOIN customers c ON o.customer_id = c.id
         WHERE o.id = :id",
        ['id' => $order_id]
    )->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return ['success' => false, 'error' => 'Order not found'];
    }
    if (!customerMayRequestInvoice($order_id, $order)) {
        return ['success' => false, 'error' => 'You cannot request an invoice for this order'];
    }

    if (!ag_order_invoice_allowed($order)) {
        $sent = sendAdminInvoiceRequest($order);
        if (!$sent) {
            return ['success' => false, 'error' => 'The store could not be notified. Please try again.'];
        }
        $_SESSION['invoice_requested_' . $order_id] = true;
        return [
            'success' => true,
            'admin_only' => true,
            'order_id' => $order_id,
            'emailed' => true,
        ];
    }

    require_once __DIR__ . '/generate_invoice_pdf.php';
    $generator = new PDFInvoiceGenerator();
    $result = $generator->generateInvoicePDF($order_id);
    if (empty($result['success']) || empty($result['file_path']) || !is_file($result['file_path'])) {
        return ['success' => false, 'error' => $result['error'] ?? 'Invoice could not be created'];
    }

    $_SESSION['valid_invoice_' . $order_id] = true;
    $_SESSION['invoice_requested_' . $order_id] = true;

    $emailed = false;
    $email = trim((string)($order['customer_email'] ?? ''));
    if ($email !== '') {
        $emailed = sendRequestedInvoiceEmail(
            $email,
            $order_id,
            (string)($order['customer_name'] ?? ''),
            $result['file_path']
        );
    }

    return [
        'success' => true,
        'order_id' => $order_id,
        'invoice_number' => $result['invoice_number'] ?? ('INV-' . str_pad((string)$order_id, 6, '0', STR_PAD_LEFT)),
        'file_path' => $result['file_path'],
        'download_url' => 'download_invoice.php?id=' . $order_id,
        'emailed' => $emailed,
    ];
}

/**
 * Send invoice email (alias for order email)
 */
function sendInvoiceEmail($to, $order_id, $attachmentPath = null) {
    error_log("📧 Starting sendInvoiceEmail for Order #$order_id to $to");
    error_log("📎 Attachment path: " . ($attachmentPath ?? 'none'));
    
    try {
        $result = sendOrderEmail($to, $order_id);
        error_log("📧 sendOrderEmail result: " . ($result ? 'success' : 'failed'));
        return $result;
    } catch (Exception $e) {
        error_log("❌ Error in sendInvoiceEmail: " . $e->getMessage());
        error_log("📍 Error location: " . $e->getFile() . " at line " . $e->getLine());
        return false;
    } catch (Error $e) {
        error_log("💥 Fatal error in sendInvoiceEmail: " . $e->getMessage());
        error_log("📍 Fatal error location: " . $e->getFile() . " at line " . $e->getLine());
        return false;
    }
}

/**
 * Send contact form email
 */
function sendContactEmail($name, $email, $subject, $message) {
    $to = EMAIL_FROM;
    $email_subject = "Contact Form: " . htmlspecialchars($subject);
    
    $email_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Contact Form Submission</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #d4af37;'>New Contact Form Submission</h2>
            
            <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
            </div>
            
            <div style='background: #fff; padding: 15px; border-left: 4px solid #d4af37;'>
                <h3>Message:</h3>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
            </div>
            
            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>
                Sent from aleppogift contact form<br>
                " . date('Y-m-d H:i:s') . "
            </p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    // Sanitize name and email to prevent email header injection
    $safe_name = str_replace(["\r", "\n", ":", ","], '', $name);
    $safe_email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : EMAIL_FROM;
    $headers .= "From: " . $safe_name . " <" . $safe_email . ">\r\n";
    $headers .= "Reply-To: " . $safe_email . "\r\n";
    $headers .= "X-Mailer: aleppogift\r\n";
    
    $success = mail($to, $email_subject, $email_message, $headers);
    
    if ($success) {
        error_log("Contact email sent successfully from $email");
    } else {
        error_log("Failed to send contact email from $email");
    }
    
    return $success;
}

/**
 * Test email function
 */
function testEmail($to = null) {
    $test_email = $to ?? EMAIL_FROM;
    
    $subject = "aleppogift Email Test - " . date('Y-m-d H:i:s');
    $message = "
    <html>
    <body>
        <h2>Email Test Successful!</h2>
        <p>This is a test email from aleppogift.</p>
        <p>If you received this, email functionality is working correctly.</p>
        <p>Timestamp: " . date('Y-m-d H:i:s') . "</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    $headers .= "Cc: sales@aleppogift.com\r\n";
    
    return mail($test_email, $subject, $message, $headers);
}

/**
 * Generate HTML for cart items table
 */
function generateCartItemsHTML($order_items) {
    if (empty($order_items)) {
        return "<p>No items found for this order.</p>";
    }
    
    $html = "
    <div style='margin: 20px 0;'>
        <h3 style='color: #d4af37; margin-bottom: 15px;'>🛒 Your Order Items</h3>
        <table class='cart-table'>
            <thead>
                <tr>
                    <th style='text-align: left;'>Product</th>
                    <th style='text-align: center;'>Quantity</th>
                    <th style='text-align: right;'>Price</th>
                    <th style='text-align: right;'>Total</th>
                </tr>
            </thead>
            <tbody>";
    
    $subtotal = 0;
    
    foreach ($order_items as $item) {
        $product_name = $item['name_en'];
        $variation_name = '';
        
        // Build variation description from size and color
        $variation_parts = [];
        if (!empty($item['size'])) {
            $variation_parts[] = "Size: " . $item['size'];
        }
        if (!empty($item['color'])) {
            $variation_parts[] = "Color: " . $item['color'];
        }
        if (!empty($variation_parts)) {
            $variation_name = " - " . implode(', ', $variation_parts);
        }
        
        $item_total = $item['price'] * $item['quantity'];
        $subtotal += $item_total;
        
        $html .= "
                <tr>
                    <td>
                        <strong>" . htmlspecialchars($product_name) . "</strong>
                        " . htmlspecialchars($variation_name) . "
                    </td>
                    <td style='text-align: center;'>" . $item['quantity'] . "</td>
                    <td style='text-align: right;'>AED " . number_format($item['price'], 2) . "</td>
                    <td style='text-align: right;'><strong>AED " . number_format($item_total, 2) . "</strong></td>
                </tr>";
    }
    
    $html .= "
            </tbody>
        </table>
    </div>";
    
    return $html;
}

/**
 * Generate HTML for order summary
 */
function generateOrderSummaryHTML($order) {
    // Calculate pre-discount subtotal: total_amount = (cart_subtotal - discount) + shipping
    // So: cart_subtotal = total_amount - shipping + discount
    $discount = (float)($order['discount_amount'] ?? 0);
    $pre_discount_subtotal = $order['total_amount'] - $order['shipping_aed'] + $discount;
    
    $html = "
    <div class='summary-box'>
        <h3 style='margin-top: 0; color: #d4af37;'>💰 Order Summary</h3>
        <table style='width: 100%; border-collapse: collapse;'>
            <tr>
                <td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Subtotal:</strong></td>
                <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #ddd;'>AED " . number_format($pre_discount_subtotal, 2) . "</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Shipping:</strong></td>
                <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #ddd;'>AED " . number_format($order['shipping_aed'], 2) . "</td>
            </tr>";
    
    if ($discount > 0) {
        $html .= "
            <tr>
                <td style='padding: 8px 0; border-bottom: 1px solid #ddd; color: #28a745;'><strong>Discount:</strong></td>
                <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #ddd; color: #28a745;'>-AED " . number_format($discount, 2) . "</td>
            </tr>";
    }
    
    $html .= "
            <tr style='background: #f8f9fa;'>
                <td style='padding: 12px 0; font-size: 18px;'><strong>Total:</strong></td>
                <td style='padding: 12px 0; text-align: right; font-size: 18px; color: #d4af37;'><strong>AED " . number_format($order['total_amount'], 2) . "</strong></td>
            </tr>
        </table>
    </div>";
    
    return $html;
}

/**
 * Generate HTML for shipping details
 */
function generateShippingDetailsHTML($order, $customer_data = []) {
    $html = "
    <div class='shipping-box'>
        <h3 style='margin-top: 0; color: #d4af37;'>🚚 Shipping Information</h3>";
    
    if (!empty($customer_data)) {
        $html .= "
        <p><strong>Delivery Address:</strong></p>
        <address style='font-style: normal; line-height: 1.8;'>
            " . htmlspecialchars($customer_data['fullname'] ?? '') . "<br>
            " . htmlspecialchars($customer_data['address'] ?? '') . "<br>
            " . htmlspecialchars($customer_data['city'] ?? '') . ", " . htmlspecialchars($customer_data['country'] ?? '') . "<br>";
            
        if (!empty($customer_data['phone'])) {
            $html .= "📞 " . htmlspecialchars($customer_data['phone']) . "<br>";
        }
        
        $html .= "</address>";
    }
    
    $html .= "
        <p><strong>Shipping Cost:</strong> AED " . number_format($order['shipping_aed'], 2) . "</p>
        <p><strong>Total Weight:</strong> " . number_format($order['total_weight'], 2) . " kg</p>";
    
    if (!empty($order['note'])) {
        $html .= "<p><strong>Special Instructions:</strong> " . htmlspecialchars($order['note']) . "</p>";
    }
    
    $html .= "</div>";
    
    return $html;
}

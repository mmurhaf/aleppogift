<?php
/**
 * Simple Email Functions
 * Basic email functionality without external dependencies
 * 
 * @author aleppogift Development Team
 * @version 1.0
 * @date August 12, 2025
 */

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
        
        $subject = "Order Confirmation #$order_id - aleppogift";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Order Confirmation</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #d4af37;'>Thank you for your order!</h2>
                
                <p>Dear " . htmlspecialchars($customer_name) . ",</p>
                
                <p>Your order <strong>#$order_id</strong> has been received and is being processed.</p>
                
                <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                    <h3 style='margin-top: 0;'>What happens next?</h3>
                    <ul>
                        <li>📦 We'll prepare your order with care</li>
                        <li>🚚 You'll receive tracking information when it ships</li>
                        <li>💬 Our team is here if you need any help</li>
                    </ul>
                </div>
                
                <p>If you have any questions, please contact us:</p>
                <ul>
                    <li>📧 Email: " . EMAIL_FROM . "</li>
                    <li>📱 WhatsApp: +971 56 112 5320</li>
                    <li>🌐 Website: " . SITE_URL . "</li>
                </ul>
                
                <p>Thank you for choosing aleppogift!</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='color: #666; font-size: 12px;'>
                    aleppogift - Luxury Gifts & Home Decor<br>
                    " . SITE_URL . "
                </p>
            </div>
        </body>
        </html>
        ";
        
        error_log("📧 Email content prepared for Order #$order_id");
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
        $headers .= "Cc: sales@aleppogift.com\r\n";
        $headers .= "X-Mailer: aleppogift\r\n";
        
        error_log("📧 Email headers prepared for Order #$order_id");
        error_log("📧 Sending email via mail() function...");
        
        $success = mail($to, $subject, $message, $headers);
        
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
function sendEnhancedOrderEmail($to, $order_id, $customer_name = '', $customer_data = [], $pdf_path = null) {
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
        
        // Get order items
        $order_items = $db->query(
            "SELECT oi.*, p.name_en, p.name_ar, pv.size, pv.color, pv.additional_price 
             FROM order_items oi 
             JOIN products p ON oi.product_id = p.id 
             LEFT JOIN product_variations pv ON oi.variation_id = pv.id 
             WHERE oi.order_id = :order_id",
            ['order_id' => $order_id]
        )->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("✅ Email constants check passed");
        error_log("📧 Found " . count($order_items) . " items for order #$order_id");
        
        $subject = "Order Confirmation #$order_id - AleppoGift";
        
        // Generate cart items HTML
        $cart_items_html = generateCartItemsHTML($order_items);
        
        // Generate order summary HTML
        $order_summary_html = generateOrderSummaryHTML($order);
        
        // Generate shipping details HTML
        $shipping_html = generateShippingDetailsHTML($order, $customer_data);
        
        // Generate PDF download link
        $pdf_download_link = '';
        if ($pdf_path && file_exists($pdf_path)) {
            $filename = basename($pdf_path);
            $pdf_download_link = "
            <div style='background: #e3f2fd; padding: 15px; margin: 20px 0; border-radius: 5px; text-align: center;'>
                <h3 style='margin-top: 0; color: #1976d2;'>📄 Invoice Download</h3>
                <p>Your invoice is ready for download:</p>
                <a href='" . SITE_URL . "/download_invoice.php?id=$order_id' style='background: #1976d2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    📥 Download Invoice PDF
                </a>
                <p style='font-size: 12px; color: #666; margin-top: 10px;'>
                    Or right-click the link above and select 'Save link as...' to download
                </p>
            </div>";
        }
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Order Confirmation</title>
            <style>
                .email-container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #d4af37, #f4e5a1); padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { color: #fff; margin: 0; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
                .content { background: #fff; padding: 20px; }
                .order-info { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #d4af37; }
                .cart-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .cart-table th { background: #d4af37; color: white; padding: 12px; text-align: left; }
                .cart-table td { padding: 10px; border-bottom: 1px solid #ddd; }
                .cart-table tr:nth-child(even) { background: #f9f9f9; }
                .summary-box { background: #f0f8ff; padding: 15px; margin: 20px 0; border-radius: 8px; border: 2px solid #d4af37; }
                .shipping-box { background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 8px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; color: #666; }
                .btn { background: #d4af37; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
                .btn:hover { background: #b8941f; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🎉 Thank You for Your Order!</h1>
                </div>
                
                <div class='content'>
                    <p>Dear " . htmlspecialchars($customer_name) . ",</p>
                    
                    <p>Your order <strong>#$order_id</strong> has been received and is being processed.</p>
                    
                    <div class='order-info'>
                        <h3 style='margin-top: 0; color: #d4af37;'>📋 Order Information</h3>
                        <p><strong>Order Number:</strong> #$order_id</p>
                        <p><strong>Order Date:</strong> " . date('F j, Y g:i A', strtotime($order['order_date'])) . "</p>
                        <p><strong>Payment Method:</strong> " . htmlspecialchars($order['payment_method']) . "</p>
                        <p><strong>Payment Status:</strong> " . ucfirst($order['payment_status']) . "</p>
                    </div>
                    
                    $cart_items_html
                    
                    $order_summary_html
                    
                    $shipping_html
                    
                    $pdf_download_link
                    
                    <div style='background: #e8f5e8; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                        <h3 style='margin-top: 0; color: #2e7d32;'>🚀 What happens next?</h3>
                        <ul style='margin: 10px 0;'>
                            <li>📦 We'll carefully prepare your order</li>
                            <li>🏷️ Quality check and secure packaging</li>
                            <li>🚚 You'll receive tracking information when it ships</li>
                            <li>💬 Our team is here if you need any help</li>
                        </ul>
                    </div>
                    
                    <div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ffc107;'>
                        <h3 style='margin-top: 0; color: #856404;'>📞 Need Help?</h3>
                        <p>If you have any questions about your order, please contact us:</p>
                        <ul style='list-style: none; padding: 0;'>
                            <li>📧 <strong>Email:</strong> " . EMAIL_FROM . "</li>
                            <li>📱 <strong>WhatsApp:</strong> +971 56 112 5320</li>
                            <li>🌐 <strong>Website:</strong> " . SITE_URL . "</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . SITE_URL . "' class='btn'>🛍️ Continue Shopping</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p><strong>AleppoGift - Luxury Gifts & Home Decor</strong></p>
                    <p>" . SITE_URL . "</p>
                    <p style='font-size: 12px; margin-top: 15px;'>
                        Thank you for choosing AleppoGift. We appreciate your business!
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        error_log("📧 Email content prepared for Order #$order_id");
        
        // Prepare headers for multipart email (to support PDF attachment)
        $boundary = md5(time());
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
        $headers .= "Cc: sales@aleppogift.com\r\n";
        $headers .= "X-Mailer: AleppoGift\r\n";
        
        if ($pdf_path && file_exists($pdf_path)) {
            // Multipart email with PDF attachment
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            
            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $message . "\r\n";
            
            // Add PDF attachment
            $file_content = file_get_contents($pdf_path);
            $file_content = chunk_split(base64_encode($file_content));
            $filename = basename($pdf_path);
            
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
            $body .= $file_content . "\r\n";
            $body .= "--$boundary--";
            
            error_log("📎 PDF attachment prepared: $filename");
            
        } else {
            // Simple HTML email without attachment
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $body = $message;
            
            if ($pdf_path) {
                error_log("⚠️ PDF file not found: $pdf_path");
            }
        }
        
        error_log("📧 Email headers prepared for Order #$order_id");
        error_log("📧 Sending email via mail() function...");
        
        $success = mail($to, $subject, $body, $headers);
        
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
    $headers .= "From: " . htmlspecialchars($name) . " <" . $email . ">\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
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
    $subtotal = $order['total_amount'] - $order['shipping_aed'];
    
    $html = "
    <div class='summary-box'>
        <h3 style='margin-top: 0; color: #d4af37;'>💰 Order Summary</h3>
        <table style='width: 100%; border-collapse: collapse;'>
            <tr>
                <td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Subtotal:</strong></td>
                <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #ddd;'>AED " . number_format($subtotal, 2) . "</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; border-bottom: 1px solid #ddd;'><strong>Shipping:</strong></td>
                <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #ddd;'>AED " . number_format($order['shipping_aed'], 2) . "</td>
            </tr>";
    
    if (!empty($order['discount_amount']) && $order['discount_amount'] > 0) {
        $html .= "
            <tr>
                <td style='padding: 8px 0; border-bottom: 1px solid #ddd; color: #28a745;'><strong>Discount:</strong></td>
                <td style='padding: 8px 0; text-align: right; border-bottom: 1px solid #ddd; color: #28a745;'>-AED " . number_format($order['discount_amount'], 2) . "</td>
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

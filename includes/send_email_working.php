<?php
/**
 * Working Email Functions for aleppogift
 * Optimized for iPage hosting environment
 */

function sendOrderEmailWorking($to, $order_id, $customer_name = "") {
    error_log("📧 Sending order email for #$order_id to $to");
    
    $subject = "Order Confirmation #$order_id - AleppoGift";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\"UTF-8\">
        <title>Order Confirmation</title>
    </head>
    <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">
        <div style=\"max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;\">
            <div style=\"background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);\">
                <h2 style=\"color: #d4af37; text-align: center; margin-bottom: 30px;\">
                    🛍️ Thank you for your order!
                </h2>
                
                <p>Dear " . htmlspecialchars($customer_name) . ",</p>
                
                <div style=\"background: #f0f8ff; padding: 20px; border-radius: 5px; margin: 20px 0;\">
                    <h3 style=\"margin-top: 0; color: #1e90ff;\">Order #$order_id</h3>
                    <p style=\"margin: 0;\">Your order has been received and is being processed.</p>
                </div>
                
                <div style=\"background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;\">
                    <h4 style=\"margin-top: 0;\">What happens next?</h4>
                    <ul style=\"margin-bottom: 0;\">
                        <li>📦 We'll prepare your order with care</li>
                        <li>🚚 You'll receive tracking information when it ships</li>
                        <li>💬 Our team is here if you need any help</li>
                    </ul>
                </div>
                
                <div style=\"background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ffc107;\">
                    <h4 style=\"margin-top: 0;\">Contact Information</h4>
                    <ul style=\"margin-bottom: 0;\">
                        <li>📧 Email: sales@aleppogift.com</li>
                        <li>📱 WhatsApp: +971 56 112 5320</li>
                        <li>🌐 Website: https://aleppogift.com</li>
                    </ul>
                </div>
                
                <p style=\"text-align: center; margin-top: 30px;\">
                    <strong>Thank you for choosing AleppoGift!</strong>
                </p>
            </div>
            
            <p style=\"text-align: center; color: #666; font-size: 12px; margin-top: 20px;\">
                AleppoGift - Luxury Gifts & Home Decor<br>
                This email was sent from aleppogift.com
            </p>
        </div>
    </body>
    </html>
    ";
    
    // Use optimized headers for better delivery
    $headers = array();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=UTF-8";
    $headers[] = "From: AleppoGift <sales@aleppogift.com>";
    $headers[] = "Reply-To: sales@aleppogift.com";
    $headers[] = "Return-Path: sales@aleppogift.com";
    $headers[] = "X-Mailer: AleppoGift System";
    $headers[] = "X-Priority: 3";
    
    $header_string = implode("\r\n", $headers);
    
    // Add error handling
    $old_error_reporting = error_reporting(E_ALL);
    $old_display_errors = ini_get("display_errors");
    ini_set("display_errors", 0);
    
    $result = @mail($to, $subject, $message, $header_string);
    
    // Restore error settings
    error_reporting($old_error_reporting);
    ini_set("display_errors", $old_display_errors);
    
    if ($result) {
        error_log("✅ Order email sent successfully to $to for order #$order_id");
    } else {
        error_log("❌ Failed to send order email to $to for order #$order_id");
        
        // Log system information for debugging
        error_log("📊 System info - PHP version: " . phpversion());
        error_log("📊 Mail function available: " . (function_exists("mail") ? "yes" : "no"));
        error_log("📊 SendMail path: " . ini_get("sendmail_path"));
        error_log("📊 SMTP server: " . ini_get("SMTP"));
    }
    
    return $result;
}

/**
 * Send invoice email (alias for order email)
 */
function sendInvoiceEmailWorking($to, $order_id, $attachmentPath = null) {
    error_log("📧 Starting sendInvoiceEmail for Order #$order_id to $to");
    return sendOrderEmailWorking($to, $order_id);
}

/**
 * Wrapper functions for compatibility
 */
function sendOrderEmail($to, $order_id, $customer_name = "") {
    return sendOrderEmailWorking($to, $order_id, $customer_name);
}

function sendInvoiceEmail($to, $order_id, $attachmentPath = null) {
    return sendInvoiceEmailWorking($to, $order_id, $attachmentPath);
}

?>
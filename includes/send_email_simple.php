<?php
/**
 * Simple Email Functions
 * Basic email functionality without external dependencies
 * 
 * @author AleppoGift Development Team
 * @version 1.0
 * @date August 12, 2025
 */

/**
 * Send order confirmation email
 */
function sendOrderEmail($to, $order_id, $customer_name = '') {
    $subject = "Order Confirmation #$order_id - AleppoGift";
    
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
            
            <p>Thank you for choosing AleppoGift!</p>
            
            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>
                AleppoGift - Luxury Gifts & Home Decor<br>
                " . SITE_URL . "
            </p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
    $headers .= "X-Mailer: AleppoGift\r\n";
    
    $success = mail($to, $subject, $message, $headers);
    
    if ($success) {
        error_log("Order email sent successfully to $to for order #$order_id");
    } else {
        error_log("Failed to send order email to $to for order #$order_id");
    }
    
    return $success;
}

/**
 * Send invoice email (alias for order email)
 */
function sendInvoiceEmail($to, $order_id, $attachmentPath = null) {
    return sendOrderEmail($to, $order_id);
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
                Sent from AleppoGift contact form<br>
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
    $headers .= "X-Mailer: AleppoGift\r\n";
    
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
    
    $subject = "AleppoGift Email Test - " . date('Y-m-d H:i:s');
    $message = "
    <html>
    <body>
        <h2>Email Test Successful!</h2>
        <p>This is a test email from AleppoGift.</p>
        <p>If you received this, email functionality is working correctly.</p>
        <p>Timestamp: " . date('Y-m-d H:i:s') . "</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    
    return mail($test_email, $subject, $message, $headers);
}

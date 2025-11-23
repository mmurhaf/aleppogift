<?php
/**
 * PHPMailer Email Implementation for AleppoGift
 * Proper SMTP authentication with iPage
 */

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config/config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send order confirmation email using PHPMailer
 */
function sendOrderEmailPHPMailer($to, $order_id, $customer_name = '') {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to, $customer_name);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation #$order_id - AleppoGift";
        
        $mail->Body = "
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

        $mail->send();
        error_log("Order email sent successfully to $to for order #$order_id via PHPMailer");
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send order email to $to for order #$order_id: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send invoice email using PHPMailer
 */
function sendInvoiceEmailPHPMailer($to, $order_id, $attachmentPath = null) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);

        // Attachment
        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath, "invoice_$order_id.pdf");
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Invoice #$order_id - AleppoGift";
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Invoice</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #d4af37;'>Invoice for Order #$order_id</h2>
                
                <p>Thank you for your purchase!</p>
                
                <p>Please find your invoice attached to this email.</p>
                
                <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                    <h3 style='margin-top: 0;'>Order Details:</h3>
                    <p><strong>Order Number:</strong> #$order_id</p>
                    <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                
                <p>If you have any questions about your invoice, please contact us at " . EMAIL_FROM . "</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='color: #666; font-size: 12px;'>
                    AleppoGift - Luxury Gifts & Home Decor<br>
                    " . SITE_URL . "
                </p>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        error_log("Invoice email sent successfully to $to for order #$order_id via PHPMailer");
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send invoice email to $to for order #$order_id: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send contact form email using PHPMailer
 */
function sendContactEmailPHPMailer($name, $email, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress(EMAIL_FROM);
        $mail->addReplyTo($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Contact Form: " . htmlspecialchars($subject);
        
        $mail->Body = "
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

        $mail->send();
        error_log("Contact email sent successfully from $email via PHPMailer");
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send contact email from $email: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Test email function using PHPMailer
 */
function testEmailPHPMailer($to = null) {
    $test_email = $to ?? EMAIL_FROM;
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($test_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "AleppoGift Email Test - " . date('Y-m-d H:i:s');
        
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #d4af37;'>Email Test Successful! 🎉</h2>
            <p>This is a test email from AleppoGift using PHPMailer with iPage SMTP.</p>
            <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                <h3>Configuration Details:</h3>
                <ul>
                    <li><strong>SMTP Host:</strong> " . SMTP_HOST . "</li>
                    <li><strong>Port:</strong> " . SMTP_PORT . " (SSL)</li>
                    <li><strong>From:</strong> " . EMAIL_FROM . "</li>
                    <li><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</li>
                </ul>
            </div>
            <p>If you received this email, the AleppoGift email system is working perfectly!</p>
        </body>
        </html>
        ";

        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Test email failed: {$mail->ErrorInfo}");
        return false;
    }
}

// Debug function to test SMTP connection
function testSMTPConnection() {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPDebug  = 2; // Enable verbose debug output
        
        // Just test the connection without sending
        $mail->smtpConnect();
        $mail->smtpClose();
        
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}
?>

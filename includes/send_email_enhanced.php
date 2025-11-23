<?php
/**
 * Enhanced Email Functions with SMTP Testing
 * Includes fallback mechanisms and better error reporting
 */

/**
 * Test SMTP connection with multiple password attempts
 */
function testSMTPCredentials() {
    $passwords = ['Salem1972#i', 'Salem1972#a'];
    $working_password = null;
    
    foreach ($passwords as $password) {
        if (testSMTPConnection($password)) {
            $working_password = $password;
            break;
        }
    }
    
    return $working_password;
}

/**
 * Test SMTP connection with specific password
 */
function testSMTPConnection($password) {
    // Try basic socket connection first
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $socket = @stream_socket_client(
        'ssl://smtp.ipage.com:465',
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
        $context
    );
    
    if (!$socket) {
        error_log("❌ SMTP connection failed: $errstr ($errno)");
        return false;
    }
    
    // Read server greeting
    $response = fgets($socket);
    if (strpos($response, '220') !== 0) {
        fclose($socket);
        return false;
    }
    
    // Send EHLO
    fwrite($socket, "EHLO localhost\r\n");
    $response = fgets($socket);
    
    // Test AUTH LOGIN
    fwrite($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket);
    
    if (strpos($response, '334') === 0) {
        // Send username
        fwrite($socket, base64_encode('sales@aleppogift.com') . "\r\n");
        $response = fgets($socket);
        
        // Send password
        fwrite($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket);
        
        fclose($socket);
        
        // Check if authentication succeeded
        return strpos($response, '235') === 0;
    }
    
    fclose($socket);
    return false;
}

/**
 * Enhanced order email with SMTP testing
 */
function sendOrderEmailEnhanced($to, $order_id, $customer_name = '') {
    error_log("📧 Starting enhanced email send for Order #$order_id to $to");
    
    // First, test SMTP credentials
    $working_password = testSMTPCredentials();
    
    if ($working_password) {
        error_log("✅ Found working SMTP password ending in: " . substr($working_password, -2));
        
        // Try PHPMailer with working password
        if (sendViaPHPMailer($to, $order_id, $customer_name, $working_password)) {
            return true;
        }
    }
    
    // Fallback to basic mail
    error_log("⚠️ SMTP failed, falling back to basic mail()");
    return sendViaBasicMail($to, $order_id, $customer_name);
}

/**
 * Send via PHPMailer with specific password
 */
function sendViaPHPMailer($to, $order_id, $customer_name, $password) {
    // Check if PHPMailer is available
    $phpmailer_paths = [
        __DIR__ . '/../vendor/PHPMailer/src',
        __DIR__ . '/../vendor/phpmailer/phpmailer/src'
    ];
    
    $phpmailer_loaded = false;
    foreach ($phpmailer_paths as $path) {
        if (file_exists($path . '/PHPMailer.php')) {
            require_once $path . '/PHPMailer.php';
            require_once $path . '/SMTP.php';
            require_once $path . '/Exception.php';
            $phpmailer_loaded = true;
            break;
        }
    }
    
    if (!$phpmailer_loaded) {
        error_log("❌ PHPMailer not found");
        return false;
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation #$order_id - aleppogift";
        $mail->Body = generateEmailHTML($order_id, $customer_name);
        
        $mail->send();
        error_log("✅ PHPMailer email sent successfully for Order #$order_id");
        return true;
        
    } catch (Exception $e) {
        error_log("❌ PHPMailer failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send via basic mail() function
 */
function sendViaBasicMail($to, $order_id, $customer_name) {
    $subject = "Order Confirmation #$order_id - aleppogift";
    $message = generateEmailHTML($order_id, $customer_name);
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
    
    $result = mail($to, $subject, $message, $headers);
    
    if ($result) {
        error_log("✅ Basic mail() sent successfully for Order #$order_id");
    } else {
        error_log("❌ Basic mail() failed for Order #$order_id");
    }
    
    return $result;
}

/**
 * Generate email HTML content
 */
function generateEmailHTML($order_id, $customer_name) {
    return "
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
}

/**
 * Wrapper functions for compatibility
 */
function sendOrderEmail($to, $order_id, $customer_name = '') {
    return sendOrderEmailEnhanced($to, $order_id, $customer_name);
}

function sendInvoiceEmail($to, $order_id, $attachmentPath = null) {
    error_log("📧 Starting sendInvoiceEmail for Order #$order_id to $to");
    return sendOrderEmailEnhanced($to, $order_id);
}

?>

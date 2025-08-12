<?php
// Email functionality with fallback support
// Attempts to use PHPMailer if available, falls back to basic mail()

// Global flag for PHPMailer availability
$GLOBALS['phpmailer_available'] = false;

// Try to load PHPMailer from various possible locations
$phpmailer_paths = [
    __DIR__ . '/../vendor/PHPMailer/src',
    __DIR__ . '/../vendor/phpmailer/phpmailer/src',
    '../vendor/PHPMailer/src',
    '../vendor/phpmailer/phpmailer/src'
];

foreach ($phpmailer_paths as $path) {
    if (file_exists($path . '/Exception.php') && 
        file_exists($path . '/PHPMailer.php') && 
        file_exists($path . '/SMTP.php')) {
        
        require_once($path . '/Exception.php');
        require_once($path . '/PHPMailer.php');
        require_once($path . '/SMTP.php');
        
        $GLOBALS['phpmailer_available'] = true;
        break;
    }
}

// Import PHPMailer classes if available
if ($GLOBALS['phpmailer_available']) {
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
}

/**
 * Send basic email using PHP's mail() function
 */
function sendBasicEmail($to, $order_id, $attachmentPath = null) {
    $subject = "Your Order #$order_id - AleppoGift";
    $message = "
    <html>
    <body>
        <h2>Thank you for your order!</h2>
        <p>Your order #$order_id has been received and is being processed.</p>
        <p>We'll notify you when your order ships.</p>
        <br>
        <p>Best regards,<br>AleppoGift Team</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>' . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

function sendInvoiceEmail($to, $order_id, $attachmentPath) {
    if (!$GLOBALS['phpmailer_available']) {
        error_log("PHPMailer not available - using basic mail() function for order #$order_id");
        return sendBasicEmail($to, $order_id, $attachmentPath);
    }
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = 'ssl'; // Use 'ssl' for port 465
        $mail->Port = SMTP_PORT;

        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addAddress(EMAIL_FROM); // Admin copy
        $mail->addBCC(EMAIL_FROM);
        $mail->addAttachment($attachmentPath);

        $mail->isHTML(true);
        $mail->Subject = "Your AleppoGift Order Confirmation (Order #$order_id)";

        $mail->Body = '
        <div style="font-family: Arial, sans-serif; padding: 20px; background-color: #f7f7f7;">
            <h2 style="color: #1e90ff;">🛍️ Thank you for shopping with AleppoGift!</h2>
            <p>Your order <strong>#' . $order_id . '</strong> has been placed successfully.</p>
            <p>A PDF invoice is attached to this email.</p>

            <h3 style="margin-top: 30px;">What’s next?</h3>
            <ul>
                <li>📦 Your items will be prepared for shipping.</li>
                <li>📧 You will receive tracking info by email.</li>
                <li>💬 For help, contact us any time. WhatsApp or phone +971 56 112 5320 .</li>
            </ul>

            <p style="margin-top: 30px;">With love,</p>
            <p><strong>The AleppoGift Team</strong></p>
            <hr>
            <p style="font-size: 12px; color: #888;">This email was sent from AleppoGift.com</p>
        </div>';

        $mail->AltBody = "Your order #$order_id has been placed. A PDF invoice is attached.";
        $mail->send();
        return true;
        } catch (Exception $e) {
            $error = "❌ Email sending failed: " . $mail->ErrorInfo;
            error_log($error);
            echo $error; // show error on screen (for debugging only)
            return false;
        }

}

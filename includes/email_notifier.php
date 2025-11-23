<?php
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendOrderConfirmationEmail($toEmail, $toName, $order_id, $invoicePath) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = 'smtp.ipage.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sales@aleppogift.com';
        $mail->Password   = 'Salem1972#i';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('sales@aleppogift.com', 'aleppogift');
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo('sales@aleppogift.com', 'Support');
                $mail->addBCC('sales@aleppogift.com', 'aleppogift sales');

        // Attach invoice PDF if provided
        if ($invoicePath && file_exists($invoicePath)) {
            $mail->addAttachment($invoicePath);
        }

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation #$order_id - aleppogift";
        $invoiceLink = "https://aleppogift.com/download_invoice.php?id=$order_id";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif;'>
                <h3>Thank you for your order, $toName!</h3>
                <p>Your order <strong>#$order_id</strong> has been received and is being processed.</p>
                <p>You can download your invoice here: 
                <a href='$invoiceLink' target='_blank'>Download Invoice PDF</a></p>
                <br>
                <p>We appreciate your trust in aleppogift.</p>
                <p><a href='https://aleppogift.com' target='_blank'>Visit aleppogift.com</a></p>
            </div>
        ";
        $mail->AltBody = "Thank you for your order #$order_id. Invoice attached.";

        $mail->send();
        // WhatsApp Notification
$whatsappNumber = '971509687610'; // change to admin's full international number without '+' (e.g., UAE = 971xxxx)
$apiKey = '5574813';

$message = urlencode("📦 New Order Received\nOrder #$order_id\nCustomer: $toName\nEmail: $toEmail\nView invoice: $invoiceLink");

$waUrl = "https://api.callmebot.com/whatsapp.php?phone=$whatsappNumber&text=$message&apikey=$apiKey";

// Send WhatsApp
file_get_contents($waUrl);

        error_log("📧 Email sent successfully to $toEmail for Order #$order_id");
         // Optional: log or show success
         // echo "Email sent successfully!";
         // exit;
         
         // Return true to indicate success
        return true;
    } catch (Exception $e) {
        error_log("📧 Email failed: {$mail->ErrorInfo}");
        return false;
    }
}

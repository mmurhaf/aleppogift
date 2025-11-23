<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('../vendor/PHPMailer/src/Exception.php');
require_once('../vendor/PHPMailer/src/PHPMailer.php');
require_once('../vendor/PHPMailer/src/SMTP.php');

function sendInvoiceEmail($to, $order_id, $attachmentPath) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = 'ssl';
        $mail->Port = SMTP_PORT;

        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addAddress(EMAIL_FROM); // Admin copy
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
                <li>💬 For help, contact us any time.</li>
            </ul>

            <p style="margin-top: 30px;">With love,</p>
            <p><strong>The AleppoGift Team</strong></p>
            <hr>
            <p style="font-size: 12px; color: #888;">This email was sent from AleppoGift.com</p>
        </div>';

        $mail->AltBody = "Your order #$order_id has been placed. A PDF invoice is attached.";
        $mail->send();
    } catch (Exception $e) {
        // Optional: log or show error
    }
}

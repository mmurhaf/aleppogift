<?php
/**
 * Contact Form Handler
 * Processes contact form submissions and sends emails
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start output buffering to catch any errors
ob_start();

if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
    @session_start();
    session_write_close();
}
require_once __DIR__ . '/../includes/i18n.php';

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception(t('account.contact_invalid_method'));
    }
    
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validate required fields
    $required_fields = ['firstName', 'lastName', 'email', 'subject', 'message'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new Exception(t('account.contact_missing_field', ['field' => $field]));
        }
    }
    
    // Sanitize inputs
    $firstName = htmlspecialchars(trim($data['firstName']));
    $lastName = htmlspecialchars(trim($data['lastName']));
    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    $phone = isset($data['phone']) ? htmlspecialchars(trim($data['phone'])) : 'Not provided';
    $subject = htmlspecialchars(trim($data['subject']));
    $message = htmlspecialchars(trim($data['message']));
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception(t('account.contact_invalid_email'));
    }
    
    // Get subject name
    $subject_names = [
        'general' => 'General Inquiry',
        'order' => 'Order Status',
        'product' => 'Product Question',
        'shipping' => 'Shipping & Delivery',
        'return' => 'Returns & Refunds',
        'complaint' => 'Complaint',
        'other' => 'Other'
    ];
    $subject_name = isset($subject_names[$subject]) ? $subject_names[$subject] : $subject;
    
    // Prepare email content
    $to = 'info@aleppogift.com';
    $email_subject = "Contact Form: $subject_name - aleppogift.com";
    
    $email_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Contact Form Submission</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;'>
            <div style='background: #e67e22; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin: 0;'>📧 New Contact Form Submission</h2>
            </div>
            
            <div style='background: white; padding: 20px; margin-top: 20px; border-radius: 5px;'>
                <h3 style='color: #e67e22; border-bottom: 2px solid #e67e22; padding-bottom: 10px;'>
                    Contact Information
                </h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px 0; font-weight: bold; width: 150px;'>Name:</td>
                        <td style='padding: 10px 0;'>$firstName $lastName</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; font-weight: bold;'>Email:</td>
                        <td style='padding: 10px 0;'><a href='mailto:$email'>$email</a></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; font-weight: bold;'>Phone:</td>
                        <td style='padding: 10px 0;'>$phone</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; font-weight: bold;'>Subject:</td>
                        <td style='padding: 10px 0;'>$subject_name</td>
                    </tr>
                </table>
                
                <h3 style='color: #e67e22; border-bottom: 2px solid #e67e22; padding-bottom: 10px; margin-top: 20px;'>
                    Message
                </h3>
                <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #e67e22; margin-top: 10px;'>
                    " . nl2br($message) . "
                </div>
            </div>
            
            <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                <p>Submitted on: " . date('F j, Y \a\t g:i A') . " (GST)</p>
                <p>From: https://www.aleppogift.com/contact.html</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: aleppogift Contact Form <noreply@aleppogift.com>\r\n";
    $headers .= "Reply-To: $firstName $lastName <$email>\r\n";
    $headers .= "X-Mailer: aleppogift Contact Form\r\n";
    
    $messageId = 0;
    try {
        require_once __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/../includes/Database.php';
        $contactDb = new Database();
        $contactDb->query(
            'INSERT INTO contact_messages (name, email, phone, subject, message, ip, is_read, emailed) VALUES (?, ?, ?, ?, ?, ?, 0, 0)',
            [trim($firstName . ' ' . $lastName), $email, $phone, $subject_name, $message, $_SERVER['REMOTE_ADDR'] ?? '']
        );
        $messageId = (int)$contactDb->lastInsertId();
    } catch (Throwable $contactError) {
        error_log('Contact message save failed: ' . $contactError->getMessage());
    }

    // Send email
    $mail_sent = mail($to, $email_subject, $email_body, $headers);
    if ($messageId > 0 && isset($contactDb)) {
        $contactDb->query('UPDATE contact_messages SET emailed = ? WHERE id = ?', [$mail_sent ? 1 : 0, $messageId]);
    }
    
    if (!$mail_sent && $messageId <= 0) {
        throw new Exception(t('account.contact_send_failed', ['email' => 'info@aleppogift.com']));
    }
    
    // Log the submission (optional)
    $log_entry = date('Y-m-d H:i:s') . " - Contact form from: $firstName $lastName ($email) - Subject: $subject_name\n";
    @file_put_contents(__DIR__ . '/../../logs/contact_form.log', $log_entry, FILE_APPEND);
    
    // Clear output buffer
    ob_end_clean();
    
    // Send success response
    echo json_encode([
        'success' => true,
        'message' => t('account.contact_thanks')
    ]);
    
} catch (Exception $e) {
    // Clear output buffer
    ob_end_clean();
    
    // Log error
    error_log("Contact form error: " . $e->getMessage());
    
    // Send error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

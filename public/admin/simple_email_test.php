<?php
/**
 * Simple Email Test for Checkout
 * Quick test to verify email functionality
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load dependencies
require_once '../../includes/bootstrap.php';
require_once '../../includes/email_notifier.php';

echo "<h1>📧 Quick Email Test</h1>\n";

// Test configuration
$test_email = 'mmurhaf1@gmail.com';
$test_order_id = 'TEST_' . time();
$test_customer_name = 'Test Customer';

echo "<h2>Configuration</h2>\n";
echo "Test Email: $test_email<br>\n";
echo "Order ID: $test_order_id<br>\n";
echo "Customer: $test_customer_name<br>\n";

// Test 1: Check if sendOrderConfirmationEmail function exists
echo "<h2>Function Availability</h2>\n";
if (function_exists('sendOrderConfirmationEmail')) {
    echo "✅ sendOrderConfirmationEmail is available<br>\n";
    
    // Create a test invoice file
    $invoice_dir = __DIR__ . '/invoice';
    if (!is_dir($invoice_dir)) {
        mkdir($invoice_dir, 0755, true);
    }
    
    $test_invoice = $invoice_dir . '/test_invoice.pdf';
    file_put_contents($test_invoice, 'Test invoice content');
    
    echo "<h2>Sending Test Email</h2>\n";
    echo "Attempting to send email...<br>\n";
    
    $result = sendOrderConfirmationEmail($test_email, $test_customer_name, $test_order_id, $test_invoice);
    
    if ($result) {
        echo "✅ Email sent successfully!<br>\n";
    } else {
        echo "❌ Email failed to send<br>\n";
    }
    
    // Clean up
    if (file_exists($test_invoice)) {
        unlink($test_invoice);
    }
    
} else {
    echo "❌ sendOrderConfirmationEmail function not found<br>\n";
}

// Test 2: Check sendInvoiceEmail function
echo "<h2>Alternative Email Function</h2>\n";
if (function_exists('sendInvoiceEmail')) {
    echo "✅ sendInvoiceEmail is available<br>\n";
    
    // Create a test invoice file
    $invoice_dir = __DIR__ . '/invoice';
    if (!is_dir($invoice_dir)) {
        mkdir($invoice_dir, 0755, true);
    }
    
    $test_invoice = $invoice_dir . '/test_invoice2.pdf';
    file_put_contents($test_invoice, 'Test invoice content 2');
    
    echo "Attempting to send invoice email...<br>\n";
    
    $result2 = sendInvoiceEmail($test_email, $test_order_id, $test_invoice);
    
    if ($result2) {
        echo "✅ Invoice email sent successfully!<br>\n";
    } else {
        echo "❌ Invoice email failed to send<br>\n";
    }
    
    // Clean up
    if (file_exists($test_invoice)) {
        unlink($test_invoice);
    }
    
} else {
    echo "❌ sendInvoiceEmail function not found<br>\n";
}

// Test 3: Direct SMTP test if PHPMailer is available
echo "<h2>Direct SMTP Test</h2>\n";
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "✅ PHPMailer is available<br>\n";
    
    $mail = new PHPMailer(true);
    
    try {
        // Same settings as in email_notifier.php
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = 'smtp.ipage.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sales@aleppogift.com';
        $mail->Password = 'Salem1972#i';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->Timeout = 30;
        
        $mail->setFrom('sales@aleppogift.com', 'AleppoGift');
        $mail->addAddress($test_email, $test_customer_name);
        
        $mail->isHTML(true);
        $mail->Subject = "Direct SMTP Test - $test_order_id";
        $mail->Body = "This is a direct SMTP test email for order $test_order_id";
        
        $mail->send();
        echo "✅ Direct SMTP email sent successfully!<br>\n";
        
    } catch (Exception $e) {
        echo "❌ Direct SMTP test failed: " . $e->getMessage() . "<br>\n";
    }
} else {
    echo "❌ PHPMailer not available for direct SMTP test<br>\n";
}

echo "<h2>Test Complete</h2>\n";
echo "Check your email ($test_email) for test messages.<br>\n";
echo "Test completed at: " . date('Y-m-d H:i:s') . "<br>\n";
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Email Test - Confirmed SMTP Working</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { color: #007bff; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0; }
        input[type="email"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .test-result { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎉 SMTP Connection Confirmed Working!</h1>
        <p>Based on your test results, your SMTP configuration is working correctly. Let's test the actual order email system.</p>
        
        <div class="success">
            <h3>✅ Confirmed Working Configuration:</h3>
            <strong>Username:</strong> sales@aleppogift.com<br>
            <strong>Password:</strong> Salem1972#a<br>
            <strong>SMTP Host:</strong> smtp.ipage.com<br>
            <strong>SMTP Port:</strong> 465<br>
            <strong>Encryption:</strong> SSL
        </div>
        
        <?php
        require_once '../config/config.php';
        require_once '../includes/send_email_simple.php';
        
        // Test the actual order email system
        if (isset($_POST['test_order_email'])) {
            $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
            
            if ($test_email) {
                echo '<div class="info">🧪 Testing actual order email system with confirmed SMTP settings...</div>';
                
                $test_order_id = 'SMTP-CONFIRMED-' . date('YmdHis');
                
                try {
                    echo "<div class='info'>Sending order confirmation email using sendOrderEmail() function...</div>";
                    
                    $result = sendOrderEmail($test_email, $test_order_id, 'Test Customer');
                    
                    if ($result) {
                        echo '<div class="success">✅ <strong>Order Email Sent Successfully!</strong><br>';
                        echo '📧 Email sent to: ' . htmlspecialchars($test_email) . '<br>';
                        echo '🎯 Order ID: ' . $test_order_id . '<br>';
                        echo '🎉 Your checkout email system is working correctly!<br>';
                        echo '<br><strong>What this means:</strong><br>';
                        echo '• ✅ Customer order confirmations will be sent<br>';
                        echo '• ✅ SMTP authentication is working<br>';
                        echo '• ✅ Email delivery should be reliable<br>';
                        echo '• ✅ Your checkout process emails are functional</div>';
                    } else {
                        echo '<div class="error">❌ <strong>Order Email Failed</strong><br>';
                        echo 'Even though SMTP connection works, the sendOrderEmail() function failed.<br>';
                        echo 'This could be due to:<br>';
                        echo '• Mail server temporary issues<br>';
                        echo '• Email content formatting problems<br>';
                        echo '• Server mail() function configuration<br>';
                        echo '<br>Check the error logs for more details.</div>';
                    }
                    
                } catch (Exception $e) {
                    echo '<div class="error">❌ <strong>Exception occurred:</strong><br>';
                    echo htmlspecialchars($e->getMessage()) . '</div>';
                }
                
                // Also test PHPMailer direct SMTP
                echo '<div class="info">🔧 Testing PHPMailer direct SMTP with confirmed settings...</div>';
                
                try {
                    // Load PHPMailer
                    $phpmailer_path = '../vendor/PHPMailer/src';
                    require_once $phpmailer_path . '/PHPMailer.php';
                    require_once $phpmailer_path . '/SMTP.php';
                    require_once $phpmailer_path . '/Exception.php';
                    
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    
                    // SMTP configuration - using confirmed working settings
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USERNAME; // sales@aleppogift.com
                    $mail->Password = SMTP_PASSWORD; // Salem1972#a
                    $mail->SMTPSecure = 'ssl';
                    $mail->Port = SMTP_PORT;
                    $mail->SMTPDebug = 0;
                    
                    // Email content
                    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                    $mail->addAddress($test_email);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'PHPMailer Order Test - SMTP Confirmed Working';
                    $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h2 style="color: #28a745;">🎉 PHPMailer SMTP Test Successful!</h2>
                        <p>Great news! Your SMTP configuration is working perfectly with PHPMailer.</p>
                        
                        <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;">
                            <h3>✅ Confirmed Configuration:</h3>
                            <ul>
                                <li><strong>SMTP Host:</strong> ' . SMTP_HOST . '</li>
                                <li><strong>Port:</strong> ' . SMTP_PORT . '</li>
                                <li><strong>Username:</strong> ' . SMTP_USERNAME . '</li>
                                <li><strong>Password:</strong> Working (ending in #a)</li>
                                <li><strong>Encryption:</strong> SSL</li>
                                <li><strong>Test Time:</strong> ' . date('Y-m-d H:i:s') . '</li>
                            </ul>
                        </div>
                        
                        <div style="background: #d1ecf1; padding: 15px; border-radius: 5px;">
                            <h3>🚀 Recommendations:</h3>
                            <p>Since SMTP is working, consider upgrading your order email system to use PHPMailer for:</p>
                            <ul>
                                <li>✅ Better email deliverability</li>
                                <li>✅ Detailed error reporting</li>
                                <li>✅ Support for email attachments</li>
                                <li>✅ Professional email formatting</li>
                            </ul>
                        </div>
                        
                        <p style="text-align: center; color: #666;">
                            <strong>AleppoGift Order System</strong><br>
                            SMTP Test Completed Successfully
                        </p>
                    </div>';
                    
                    $mail->send();
                    echo '<div class="success">✅ <strong>PHPMailer SMTP Test Successful!</strong><br>';
                    echo '📧 PHPMailer email sent to: ' . htmlspecialchars($test_email) . '<br>';
                    echo '🔐 SMTP authentication confirmed working<br>';
                    echo '📡 Direct SMTP connection established<br>';
                    echo '<br><strong>Recommendation:</strong> Consider upgrading to PHPMailer for production use</div>';
                    
                } catch (Exception $e) {
                    echo '<div class="error">❌ <strong>PHPMailer SMTP Test Failed:</strong><br>';
                    echo htmlspecialchars($e->getMessage());
                    echo '<br><br>This is unexpected since SMTP connection was confirmed working.</div>';
                }
                
            } else {
                echo '<div class="error">❌ Please enter a valid email address.</div>';
            }
        }
        ?>
        
        <form method="post" style="margin-top: 30px;">
            <h3>🧪 Test Your Order Email System</h3>
            <p>Since SMTP connection is confirmed working, let's test your actual order email functions:</p>
            <input type="email" name="test_email" placeholder="your@email.com" required>
            <button type="submit" name="test_order_email">Test Order Email System</button>
        </form>
        
        <div class="test-result">
            <h3>📋 Your SMTP Test Results Summary:</h3>
            <p><strong>✅ Working:</strong> sales@aleppogift.com + Salem1972#a</p>
            <p><strong>❌ Failed:</strong> sales (without domain) + any password</p>
            <p><strong>❌ Failed:</strong> sales@aleppogift.com + Salem1972#i</p>
            <p><strong>🎯 Conclusion:</strong> Your current configuration in config/config.php is correct!</p>
        </div>
        
        <div class="info">
            <h3>🔧 Next Steps</h3>
            <ol>
                <li><strong>Test above ☝️</strong> - Verify your order email system works</li>
                <li><strong>Place test orders</strong> - Try the actual checkout process</li>
                <li><strong>Check customer emails</strong> - Ensure order confirmations are received</li>
                <li><strong>Consider PHPMailer upgrade</strong> - For better reliability if needed</li>
            </ol>
        </div>
        
        <div style="margin-top: 30px;">
            <h3>🔗 Related Tools</h3>
            <p><a href="complete_email_test.php">🔄 Complete Email Test</a> - Test both PHP mail() and PHPMailer</p>
            <p><a href="../checkout.php">🛒 Live Checkout Test</a> - Test actual order process</p>
            <p><a href="../thankyou.php">📧 Thank You Page</a> - Test invoice email functionality</p>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; text-align: center;">
            <small>AleppoGift SMTP Configuration Confirmed • Test Date: <?= date('Y-m-d H:i:s') ?></small>
        </div>
    </div>
</body>
</html>
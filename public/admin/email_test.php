<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test - AleppoGift</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { color: #007bff; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0; }
        input[type="email"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .config { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 AleppoGift Email Test</h1>
        <p>This page tests if the email system is working correctly on your production server.</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
            $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
            
            if (!$test_email) {
                echo '<div class="error">❌ Please enter a valid email address.</div>';
            } else {
                echo '<div class="info">🧪 Testing email system...</div>';
                
                // Include the configuration and email functions
                require_once '../../config/config.php';
                require_once '../../includes/send_email_working.php';
                
                // Display current configuration
                echo '<div class="config">';
                echo '<h3>Current Configuration:</h3>';
                echo 'SMTP Host: ' . SMTP_HOST . '<br>';
                echo 'SMTP Port: ' . SMTP_PORT . '<br>';
                echo 'SMTP Username: ' . SMTP_USERNAME . '<br>';
                echo 'Email From: ' . EMAIL_FROM . '<br>';
                echo 'Environment: ' . (defined('ENVIRONMENT') ? ENVIRONMENT : 'not set') . '<br>';
                echo '</div>';
                
                // Test email sending
                $test_order_id = 'EMAIL-TEST-' . date('YmdHis');
                
                echo '<div class="info">Sending test email to: ' . htmlspecialchars($test_email) . '</div>';
                echo '<div class="info">Test Order ID: ' . $test_order_id . '</div>';
                
                try {
                    $result = sendOrderEmailWorking($test_email, $test_order_id, 'Test Customer');
                    
                    if ($result) {
                        echo '<div class="success">✅ <strong>Email sent successfully!</strong><br>';
                        echo '📧 Check your inbox: ' . htmlspecialchars($test_email) . '<br>';
                        echo '🎉 Your checkout email system is working correctly!</div>';
                    } else {
                        echo '<div class="error">❌ <strong>Email failed to send.</strong><br>';
                        echo 'This could be due to SMTP configuration issues.<br>';
                        echo 'Check your error logs for more details.</div>';
                        
                        echo '<div class="info">';
                        echo '<h4>Troubleshooting Steps:</h4>';
                        echo '<ol>';
                        echo '<li>Verify email account "sales@aleppogift.com" exists in iPage control panel</li>';
                        echo '<li>Check if the password is correct: Salem1972#i</li>';
                        echo '<li>Try the alternative password: Salem1972#a</li>';
                        echo '<li>Contact iPage support for email configuration help</li>';
                        echo '</ol>';
                        echo '</div>';
                    }
                    
                } catch (Exception $e) {
                    echo '<div class="error">❌ <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
        ?>
        
        <form method="post" style="margin-top: 30px;">
            <h3>Test Email Sending</h3>
            <p>Enter your email address to receive a test email:</p>
            <input type="email" name="test_email" placeholder="your@email.com" required>
            <button type="submit">Send Test Email</button>
        </form>
        
        <div class="info" style="margin-top: 30px;">
            <h3>ℹ️ About This Test</h3>
            <p>This test uses the same email system as your checkout process. If this test works, your order confirmation emails should work too.</p>
            <p><strong>WhatsApp Status:</strong> ✅ Already working correctly</p>
            <p><strong>Email Status:</strong> Testing with this page</p>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; text-align: center;">
            <small>AleppoGift Email Test • Upload this file to test on production server</small>
        </div>
    </div>
</body>
</html>





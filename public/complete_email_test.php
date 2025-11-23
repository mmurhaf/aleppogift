<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Email System Test - AleppoGift</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { color: #007bff; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; }
        input[type="email"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .config { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .test-section { border: 1px solid #ddd; padding: 20px; margin: 15px 0; border-radius: 8px; }
        .test-section h3 { margin-top: 0; color: #333; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Complete Email System Test - AleppoGift</h1>
        <p>Test both PHP mail() function and PHPMailer SMTP functionality for your order confirmation emails.</p>

        <?php
        require_once '../config/config.php';
        require_once '../includes/send_email_simple.php';

        // Display current configuration
        echo '<div class="config">';
        echo '<h3>📋 Current Email Configuration:</h3>';
        echo '<strong>SMTP Host:</strong> ' . SMTP_HOST . '<br>';
        echo '<strong>SMTP Port:</strong> ' . SMTP_PORT . '<br>';
        echo '<strong>SMTP Username:</strong> ' . SMTP_USERNAME . '<br>';
        echo '<strong>Email From:</strong> ' . EMAIL_FROM . '<br>';
        echo '<strong>Site URL:</strong> ' . SITE_URL . '<br>';
        echo '</div>';

        // Check PHPMailer installation
        echo '<div class="test-section">';
        echo '<h3>🔧 PHPMailer Installation Status</h3>';

        $phpmailer_paths = [
            '../vendor/PHPMailer/src/PHPMailer.php',
            '../vendor/phpmailer/phpmailer/src/PHPMailer.php',
            '../vendor/autoload.php'
        ];

        $phpmailer_found = false;
        $phpmailer_path = '';

        foreach ($phpmailer_paths as $path) {
            if (file_exists($path)) {
                $phpmailer_found = true;
                $phpmailer_path = $path;
                echo "<div class='success'>✅ PHPMailer found at: $path</div>";
                break;
            }
        }

        if (!$phpmailer_found) {
            echo "<div class='error'>❌ PHPMailer not found in standard locations</div>";
        } else {
            // Test PHPMailer loading
            try {
                if (strpos($phpmailer_path, 'autoload.php') !== false) {
                    require_once $phpmailer_path;
                } else {
                    $base_path = dirname($phpmailer_path);
                    require_once $base_path . '/PHPMailer.php';
                    require_once $base_path . '/SMTP.php';
                    require_once $base_path . '/Exception.php';
                }

                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    echo "<div class='success'>✅ PHPMailer classes loaded successfully (namespaced)</div>";
                    $phpmailer_class = 'PHPMailer\\PHPMailer\\PHPMailer';
                } elseif (class_exists('PHPMailer')) {
                    echo "<div class='success'>✅ PHPMailer classes loaded successfully (legacy)</div>";
                    $phpmailer_class = 'PHPMailer';
                } else {
                    echo "<div class='error'>❌ PHPMailer classes not found after loading files</div>";
                    $phpmailer_found = false;
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error loading PHPMailer: " . htmlspecialchars($e->getMessage()) . "</div>";
                $phpmailer_found = false;
            }
        }
        echo '</div>';

        // Test 1: Current System (PHP mail() function)
        if (isset($_POST['test_current_system'])) {
            $test_email = filter_var($_POST['current_system_email'], FILTER_VALIDATE_EMAIL);
            
            if ($test_email) {
                echo '<div class="test-section">';
                echo '<h3>🧪 Testing Current System (PHP mail() + send_email_simple.php)</h3>';
                
                $test_order_id = 'CURRENT-TEST-' . date('YmdHis');
                
                try {
                    echo "<div class='info'>Testing sendOrderEmail() function...</div>";
                    $result = sendOrderEmail($test_email, $test_order_id, 'Test Customer');
                    
                    if ($result) {
                        echo "<div class='success'>✅ <strong>Current System Success!</strong><br>";
                        echo "📧 Order confirmation email sent to: " . htmlspecialchars($test_email) . "<br>";
                        echo "🎯 Order ID: $test_order_id<br>";
                        echo "💡 This uses PHP's mail() function</div>";
                    } else {
                        echo "<div class='error'>❌ <strong>Current System Failed</strong><br>";
                        echo "The sendOrderEmail() function returned false<br>";
                        echo "Check your server's mail configuration</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>❌ <strong>Exception:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                echo '</div>';
            } else {
                echo "<div class='error'>❌ Invalid email address</div>";
            }
        }

        // Test 2: PHPMailer SMTP
        if (isset($_POST['test_phpmailer_smtp']) && $phpmailer_found) {
            $test_email = filter_var($_POST['phpmailer_email'], FILTER_VALIDATE_EMAIL);
            
            if ($test_email) {
                echo '<div class="test-section">';
                echo '<h3>🧪 Testing PHPMailer SMTP</h3>';
                
                try {
                    $mail = new $phpmailer_class(true);
                    
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USERNAME;
                    $mail->Password = SMTP_PASSWORD;
                    $mail->SMTPSecure = 'ssl';
                    $mail->Port = SMTP_PORT;
                    $mail->SMTPDebug = 0;
                    
                    // Recipients
                    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                    $mail->addAddress($test_email);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'PHPMailer SMTP Test - AleppoGift Order System';
                    $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #d4af37;">✅ PHPMailer SMTP Test Successful!</h2>
                        <p>This email was sent using <strong>PHPMailer with SMTP</strong> from your AleppoGift order system.</p>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                            <h3>📋 Configuration Details:</h3>
                            <ul>
                                <li><strong>SMTP Host:</strong> ' . SMTP_HOST . '</li>
                                <li><strong>SMTP Port:</strong> ' . SMTP_PORT . '</li>
                                <li><strong>Username:</strong> ' . SMTP_USERNAME . '</li>
                                <li><strong>Encryption:</strong> SSL</li>
                                <li><strong>Test Time:</strong> ' . date('Y-m-d H:i:s') . '</li>
                            </ul>
                        </div>
                        
                        <div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;">
                            <h3>🎉 What this means:</h3>
                            <p>✅ Your SMTP credentials are correct<br>
                            ✅ PHPMailer is properly installed<br>
                            ✅ SSL connection to iPage is working<br>
                            ✅ You can use PHPMailer for more reliable email delivery</p>
                        </div>
                        
                        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                        <p style="color: #666; text-align: center;">
                            <strong>AleppoGift Email System Test</strong><br>
                            ' . SITE_URL . '
                        </p>
                    </div>';
                    
                    $mail->send();
                    echo "<div class='success'>✅ <strong>PHPMailer SMTP Success!</strong><br>";
                    echo "📧 Email sent successfully to: " . htmlspecialchars($test_email) . "<br>";
                    echo "🔐 SMTP authentication successful<br>";
                    echo "📡 Connected to: " . SMTP_HOST . ":" . SMTP_PORT . "<br>";
                    echo "💡 This method is more reliable than PHP mail()</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ <strong>PHPMailer SMTP Failed:</strong><br>";
                    echo "Error: " . htmlspecialchars($e->getMessage()) . "<br>";
                    
                    if (isset($mail->ErrorInfo)) {
                        echo "Mail Error: " . htmlspecialchars($mail->ErrorInfo) . "<br>";
                    }
                    
                    echo "<br><strong>Troubleshooting suggestions:</strong><br>";
                    echo "1. Check SMTP password in config/config.php<br>";
                    echo "2. Try alternative password: Salem1972#i<br>";
                    echo "3. Verify port 465 is not blocked<br>";
                    echo "4. Contact iPage support for SMTP settings</div>";
                }
                echo '</div>';
            } else {
                echo "<div class='error'>❌ Invalid email address</div>";
            }
        }

        // Test 3: Compare Both Methods
        if (isset($_POST['test_both_methods'])) {
            $test_email = filter_var($_POST['compare_email'], FILTER_VALIDATE_EMAIL);
            
            if ($test_email) {
                echo '<div class="test-section">';
                echo '<h3>🔄 Comparing Both Email Methods</h3>';
                
                $test_order_id = 'COMPARE-TEST-' . date('YmdHis');
                
                // Test current system first
                echo "<div class='info'><strong>Test 1: Current System (PHP mail())</strong></div>";
                try {
                    $current_result = sendOrderEmail($test_email, $test_order_id . '-CURRENT', 'Test Customer - Current System');
                    
                    if ($current_result) {
                        echo "<div class='success'>✅ PHP mail() method: SUCCESS</div>";
                    } else {
                        echo "<div class='error'>❌ PHP mail() method: FAILED</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>❌ PHP mail() method: EXCEPTION - " . htmlspecialchars($e->getMessage()) . "</div>";
                    $current_result = false;
                }
                
                // Test PHPMailer if available
                if ($phpmailer_found) {
                    echo "<div class='info'><strong>Test 2: PHPMailer SMTP</strong></div>";
                    try {
                        $mail = new $phpmailer_class(true);
                        
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = 'ssl';
                        $mail->Port = SMTP_PORT;
                        $mail->SMTPDebug = 0;
                        
                        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                        $mail->addAddress($test_email);
                        
                        $mail->isHTML(true);
                        $mail->Subject = 'PHPMailer Comparison Test - Order ' . $test_order_id . '-PHPMAILER';
                        $mail->Body = '<h2>PHPMailer SMTP Test</h2><p>This email was sent using PHPMailer SMTP for comparison.</p>';
                        
                        $mail->send();
                        echo "<div class='success'>✅ PHPMailer SMTP method: SUCCESS</div>";
                        $phpmailer_result = true;
                        
                    } catch (Exception $e) {
                        echo "<div class='error'>❌ PHPMailer SMTP method: FAILED - " . htmlspecialchars($e->getMessage()) . "</div>";
                        $phpmailer_result = false;
                    }
                } else {
                    echo "<div class='warning'>⚠️ PHPMailer not available for comparison</div>";
                    $phpmailer_result = null;
                }
                
                // Summary
                echo "<div class='info'><strong>📊 Comparison Summary:</strong><br>";
                if ($current_result && $phpmailer_result) {
                    echo "🎉 Both methods work! PHPMailer SMTP is recommended for production.<br>";
                } elseif ($current_result && !$phpmailer_result) {
                    echo "✅ PHP mail() works, PHPMailer SMTP has issues. Check SMTP settings.<br>";
                } elseif (!$current_result && $phpmailer_result) {
                    echo "✅ PHPMailer SMTP works, PHP mail() has issues. Use PHPMailer for reliability.<br>";
                } else {
                    echo "❌ Both methods failed. Check email server configuration.<br>";
                }
                echo "📧 Check your inbox: " . htmlspecialchars($test_email) . "</div>";
                
                echo '</div>';
            } else {
                echo "<div class='error'>❌ Invalid email address</div>";
            }
        }
        ?>

        <!-- Test Forms -->
        <div class="test-section">
            <h3>🧪 Email System Tests</h3>
            
            <form method="post" style="margin-bottom: 20px;">
                <h4>Test 1: Current System (PHP mail() function)</h4>
                <p>Tests the exact same function used in your checkout process (sendOrderEmail)</p>
                <input type="email" name="current_system_email" placeholder="your@email.com" required>
                <button type="submit" name="test_current_system">Test Current System</button>
            </form>

            <?php if ($phpmailer_found): ?>
            <form method="post" style="margin-bottom: 20px;">
                <h4>Test 2: PHPMailer SMTP</h4>
                <p>Tests direct SMTP connection using PHPMailer (more reliable)</p>
                <input type="email" name="phpmailer_email" placeholder="your@email.com" required>
                <button type="submit" name="test_phpmailer_smtp" style="background: #28a745;">Test PHPMailer SMTP</button>
            </form>

            <form method="post" style="margin-bottom: 20px;">
                <h4>Test 3: Compare Both Methods</h4>
                <p>Sends test emails using both methods for comparison</p>
                <input type="email" name="compare_email" placeholder="your@email.com" required>
                <button type="submit" name="test_both_methods" style="background: #fd7e14;">Compare Both Methods</button>
            </form>
            <?php else: ?>
            <div class="warning">
                <h4>⚠️ PHPMailer Not Available</h4>
                <p>PHPMailer is not installed. <a href="install_phpmailer.php">Install PHPMailer</a> to test SMTP functionality.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="info">
            <h3>📋 Recommendations</h3>
            <ul>
                <li><strong>Current System:</strong> Uses PHP's mail() function - simple but may be less reliable</li>
                <li><strong>PHPMailer SMTP:</strong> Direct SMTP connection - more reliable and professional</li>
                <li><strong>For Production:</strong> PHPMailer SMTP is recommended for better deliverability</li>
                <li><strong>Fallback:</strong> Your system can use PHP mail() if SMTP fails</li>
            </ul>
        </div>

        <div class="test-section">
            <h3>🔗 Related Tools</h3>
            <p><a href="production_email_test.php">📧 Simple Production Test</a> - User-friendly email testing</p>
            <p><a href="checkout_email_test.php">🛒 Checkout Email Test</a> - Advanced email testing</p>
            <p><a href="install_phpmailer.php">📦 PHPMailer Installation</a> - Install or update PHPMailer</p>
            <p><a href="../checkout.php">🛒 Live Checkout</a> - Test actual checkout process</p>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; text-align: center;">
            <small>AleppoGift Complete Email System Test • Version 1.0</small>
        </div>
    </div>
</body>
</html>
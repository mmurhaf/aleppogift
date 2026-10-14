<?php
/**
 * Test Order Email Functionality - Production Version
 * Simulates the checkout email sending process
 */

require_once '../config/config.php';
require_once '../includes/send_email_simple.php';

echo "<h2>🛒 Checkout Email Test</h2>\n";
echo "<style>body{font-family:monospace;background:#f5f5f5;padding:20px;}</style>\n";

echo "<h3>📋 Current Email Configuration</h3>\n";
echo "EMAIL_FROM: " . (defined('EMAIL_FROM') ? EMAIL_FROM : 'NOT DEFINED') . "\n";
echo "EMAIL_FROM_NAME: " . (defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'NOT DEFINED') . "\n";
echo "SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT DEFINED') . "\n";
echo "SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NOT DEFINED') . "\n";
echo "SMTP_USERNAME: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NOT DEFINED') . "\n";
echo "SMTP_PASSWORD: " . (defined('SMTP_PASSWORD') ? 'Set (ending in: ' . substr(SMTP_PASSWORD, -2) . ')' : 'NOT DEFINED') . "\n";
echo "SITE_URL: " . (defined('SITE_URL') ? SITE_URL : 'NOT DEFINED') . "\n\n";

// Test the actual function used in checkout
echo "<h3>🧪 Test Order Email Function</h3>\n";

if (isset($_POST['test_order_email'])) {
    $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    $test_order_id = 'TEST-' . date('YmdHis');
    
    if ($test_email) {
        echo "Testing sendOrderEmail function...\n";
        echo "To: $test_email\n";
        echo "Order ID: $test_order_id\n\n";
        
        try {
            $result = sendOrderEmail($test_email, $test_order_id, 'Test Customer');
            
            if ($result) {
                echo "✅ sendOrderEmail returned TRUE - Email should be sent\n";
            } else {
                echo "❌ sendOrderEmail returned FALSE - Email failed\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Exception in sendOrderEmail: " . $e->getMessage() . "\n";
        }
        
        echo "\n--- Testing sendInvoiceEmail function ---\n";
        try {
            $invoice_result = sendInvoiceEmail($test_email, $test_order_id, null);
            
            if ($invoice_result) {
                echo "✅ sendInvoiceEmail returned TRUE - Email should be sent\n";
            } else {
                echo "❌ sendInvoiceEmail returned FALSE - Email failed\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Exception in sendInvoiceEmail: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Invalid email address\n";
    }
}

echo "<form method='post'>";
echo "<h4>Test Order Email:</h4>";
echo "<input type='email' name='test_email' placeholder='your@email.com' required style='padding:8px;margin:5px;'>";
echo "<button type='submit' name='test_order_email' style='padding:8px 15px;margin:5px;background:#28a745;color:white;border:none;'>Test Order Email</button>";
echo "</form>\n";

echo "\n<h3>📋 Next Steps if Email Fails</h3>\n";
echo "1. Check error logs during order submission\n";
echo "2. Test both passwords: ******** and ********\n";
echo "3. Verify email account exists in iPage hosting control panel\n";
echo "4. Test SMTP connection directly\n";
echo "5. Try updating the SMTP password in the configuration\n\n";

echo "<h3>🔧 Update Email Password</h3>\n";
echo "To update the email password, edit config/config.php and change:\n";
echo "<code>define('SMTP_PASSWORD', '********');</code>\n";
echo "or\n";
echo "<code>define('SMTP_PASSWORD', '********');</code>\n\n";

echo "<h3>� PHPMailer Installation Test</h3>\n";

// Test PHPMailer installation
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
        echo "✅ PHPMailer found at: $path\n";
        break;
    }
}

if (!$phpmailer_found) {
    echo "❌ PHPMailer NOT FOUND in standard locations\n";
    echo "\n<h4>📦 PHPMailer Installation Instructions:</h4>\n";
    echo "<pre>\n";
    echo "Option 1 - Using Composer (Recommended):\n";
    echo "cd " . dirname(__DIR__) . "\n";
    echo "composer require phpmailer/phpmailer\n\n";
    
    echo "Option 2 - Manual Download:\n";
    echo "1. Download PHPMailer from: https://github.com/PHPMailer/PHPMailer/releases\n";
    echo "2. Extract to: vendor/PHPMailer/\n";
    echo "3. Or extract to: vendor/phpmailer/phpmailer/\n\n";
    
    echo "Option 3 - Quick Manual Setup:\n";
    echo "mkdir -p ../vendor/PHPMailer/src\n";
    echo "# Then download PHPMailer.php, SMTP.php, Exception.php to that folder\n";
    echo "</pre>\n";
} else {
    echo "✅ PHPMailer library is installed\n";
    
    // Try to load and test PHPMailer
    echo "\n<h4>🧪 Testing PHPMailer Loading:</h4>\n";
    
    try {
        if (strpos($phpmailer_path, 'autoload.php') !== false) {
            require_once $phpmailer_path;
            echo "✅ Composer autoloader loaded\n";
        } else {
            $base_path = dirname($phpmailer_path);
            require_once $base_path . '/PHPMailer.php';
            require_once $base_path . '/SMTP.php';
            require_once $base_path . '/Exception.php';
            echo "✅ PHPMailer files loaded manually\n";
        }
        
        // Check if PHPMailer classes are available
        $phpmailer_class = '';
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $phpmailer_class = 'PHPMailer\\PHPMailer\\PHPMailer';
            echo "✅ PHPMailer namespaced class found\n";
        } elseif (class_exists('PHPMailer')) {
            $phpmailer_class = 'PHPMailer';
            echo "✅ PHPMailer legacy class found\n";
        } else {
            throw new Exception("PHPMailer class not found after loading files");
        }
        
        echo "📧 PHPMailer is ready to use with class: $phpmailer_class\n";
        
        // Test PHPMailer email sending if form submitted
        if (isset($_POST['test_phpmailer_direct'])) {
            $test_email = filter_var($_POST['phpmailer_test_email'], FILTER_VALIDATE_EMAIL);
            
            if ($test_email) {
                echo "\n--- Testing PHPMailer Direct Send ---\n";
                
                // Create PHPMailer instance dynamically
                $mail = new $phpmailer_class(true);
                
                try {
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USERNAME;
                    $mail->Password = SMTP_PASSWORD;
                    $mail->SMTPSecure = 'ssl';  // Use string instead of constant for compatibility
                    $mail->Port = SMTP_PORT;
                    $mail->SMTPDebug = 0;
                    
                    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
                    $mail->addAddress($test_email);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'PHPMailer Test - AleppoGift';
                    $mail->Body = '
                    <h2>✅ PHPMailer Test Successful!</h2>
                    <p>This email was sent directly using PHPMailer with your SMTP configuration.</p>
                    <p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
                    <p><strong>Configuration:</strong></p>
                    <ul>
                        <li>Host: ' . SMTP_HOST . '</li>
                        <li>Port: ' . SMTP_PORT . '</li>
                        <li>Username: ' . SMTP_USERNAME . '</li>
                        <li>Encryption: SSL</li>
                    </ul>
                    <p>Your PHPMailer installation is working correctly!</p>
                    ';
                    
                    $mail->send();
                    echo "✅ PHPMailer email sent successfully to $test_email!\n";
                    echo "📧 Check your inbox for the test email\n";
                    
                } catch (Exception $e) {
                    echo "❌ PHPMailer SMTP failed: " . $e->getMessage() . "\n";
                    if (isset($mail->ErrorInfo)) {
                        echo "Mail Error Info: " . $mail->ErrorInfo . "\n";
                    }
                    
                    // Suggest troubleshooting
                    echo "\n<h4>🔍 PHPMailer Troubleshooting:</h4>\n";
                    echo "1. Check SMTP credentials in config/config.php\n";
                    echo "2. Verify port 465 is not blocked by firewall\n";
                    echo "3. Try alternative password: ********\n";
                    echo "4. Contact iPage support for SMTP settings\n";
                }
            } else {
                echo "❌ Invalid email address for PHPMailer test\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error loading PHPMailer: " . $e->getMessage() . "\n";
        echo "This usually means PHPMailer files are missing or corrupted.\n";
    } catch (Error $e) {
        echo "❌ PHP Error loading PHPMailer: " . $e->getMessage() . "\n";
        echo "This usually means PHPMailer files have syntax errors or missing dependencies.\n";
    }
}

// Add PHPMailer test form if library is found
if ($phpmailer_found) {
    echo "\n<form method='post'>";
    echo "<h4>Test PHPMailer Direct:</h4>";
    echo "<input type='email' name='phpmailer_test_email' placeholder='your@email.com' required style='padding:8px;margin:5px;'>";
    echo "<button type='submit' name='test_phpmailer_direct' style='padding:8px 15px;margin:5px;background:#007bff;color:white;border:none;'>Test PHPMailer Direct</button>";
    echo "</form>\n";
}

echo "\n<h3>📋 Current Email System Status</h3>\n";
echo "✅ Config files: Loaded\n";
echo ($phpmailer_found ? "✅" : "❌") . " PHPMailer: " . ($phpmailer_found ? "Installed" : "NOT INSTALLED") . "\n";
echo "📧 Fallback: PHP mail() function available\n";

echo "\n<h3>💡 Quick Fix for Email Issues</h3>\n";
echo "1. If PHPMailer is missing, install it using the instructions above\n";
echo "2. If SMTP fails, the system will fall back to PHP's basic mail() function\n";
echo "3. This is configured in the send_email_simple.php file\n";
echo "4. For production, PHPMailer with SMTP is recommended for reliability\n";

?>

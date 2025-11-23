<?php
/**
 * Comprehensive Email SMTP Test for aleppogift - Production Version
 * Tests both password options and multiple SMTP configurations
 */

// Include configuration
require_once '../config/config.php';

echo "<h2>🔧 SMTP Email Diagnostic Test - aleppogift</h2>\n";
echo "<style>body{font-family:monospace;background:#f5f5f5;padding:20px;}</style>\n";

// Test both password options
$passwords = ['Salem1972#a', 'Salem1972#i'];
$username_options = ['sales@aleppogift.com', 'sales'];

echo "<h3>📋 Current Configuration</h3>\n";
echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP Encryption: " . SMTP_ENCRYPTION . "\n";
echo "Current Username: " . SMTP_USERNAME . "\n";
echo "Current Password: " . SMTP_PASSWORD . "\n\n";

// Test if PHPMailer is available
$phpmailer_paths = [
    __DIR__ . '/../vendor/PHPMailer/src',
    __DIR__ . '/../vendor/phpmailer/phpmailer/src',
    '../vendor/PHPMailer/src',
    '../vendor/phpmailer/phpmailer/src'
];

$phpmailer_available = false;
foreach ($phpmailer_paths as $path) {
    if (file_exists($path . '/Exception.php') && 
        file_exists($path . '/PHPMailer.php') && 
        file_exists($path . '/SMTP.php')) {
        
        require_once($path . '/Exception.php');
        require_once($path . '/PHPMailer.php');
        require_once($path . '/SMTP.php');
        
        $phpmailer_available = true;
        echo "✅ PHPMailer found at: $path\n";
        break;
    }
}

if (!$phpmailer_available) {
    echo "❌ PHPMailer not found, will test with basic mail()\n";
}

echo "\n<h3>🧪 Testing SMTP Connection with Different Settings</h3>\n";

// Test function for PHPMailer
function testSMTPConnection($host, $port, $username, $password, $encryption = 'ssl') {
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return ['success' => false, 'error' => 'PHPMailer not available'];
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Enable verbose debug output
        $mail->SMTPDebug = 0; // Set to 2 for full debug
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $encryption;
        $mail->Port = $port;
        $mail->Timeout = 10;
        
        // Test connection without sending
        $result = $mail->smtpConnect();
        $mail->smtpClose();
        
        return ['success' => $result, 'error' => $result ? null : 'Connection failed'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Test function for sending actual email
function testEmailSend($to, $username, $password) {
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return testBasicMail($to);
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.ipage.com';
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        
        $mail->setFrom('sales@aleppogift.com', 'AleppoGift Test');
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = 'SMTP Test Email - ' . date('Y-m-d H:i:s');
        $mail->Body = '
        <h3>✅ SMTP Email Test Successful!</h3>
        <p>This email was sent successfully using PHPMailer with SMTP.</p>
        <p><strong>Test Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
        <p><strong>From:</strong> aleppogift SMTP Test</p>
        ';
        
        $result = $mail->send();
        return ['success' => $result, 'method' => 'PHPMailer SMTP'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'method' => 'PHPMailer SMTP'];
    }
}

// Test basic PHP mail() function
function testBasicMail($to) {
    $subject = 'Basic Mail Test - ' . date('Y-m-d H:i:s');
    $message = '
    <html>
    <body>
        <h3>📧 Basic Mail Test</h3>
        <p>This email was sent using PHP\'s basic mail() function.</p>
        <p><strong>Test Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
    </body>
    </html>
    ';
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: AleppoGift <sales@aleppogift.com>\r\n";
    
    $result = mail($to, $subject, $message, $headers);
    return ['success' => $result, 'method' => 'Basic mail()'];
}

// 1. Test SMTP connections with different credentials
if ($phpmailer_available) {
    echo "Testing SMTP connections...\n";
    
    foreach ($passwords as $password) {
        foreach ($username_options as $username) {
            echo "\nTesting: $username with password ending in '" . substr($password, -2) . "'...\n";
            
            $result = testSMTPConnection('smtp.ipage.com', 465, $username, $password, 'ssl');
            
            if ($result['success']) {
                echo "✅ SMTP Connection successful with $username and password ending in '" . substr($password, -2) . "'\n";
            } else {
                echo "❌ SMTP Connection failed: " . $result['error'] . "\n";
            }
        }
    }
}

echo "\n<h3>📤 Email Send Test</h3>\n";
echo "Enter a test email address to receive a test email:\n";
echo "<form method='post'>";
echo "<input type='email' name='test_email' placeholder='your@email.com' required style='padding:8px;margin:5px;'>";
echo "<button type='submit' name='send_test' style='padding:8px 15px;margin:5px;background:#007bff;color:white;border:none;'>Send Test Email</button>";
echo "</form>\n";

if (isset($_POST['send_test']) && !empty($_POST['test_email'])) {
    $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    
    if ($test_email) {
        echo "\n<h4>🧪 Testing email send to: $test_email</h4>\n";
        
        // Test with both passwords
        foreach ($passwords as $password) {
            echo "\nTesting with password ending in '" . substr($password, -2) . "'...\n";
            
            $result = testEmailSend($test_email, 'sales@aleppogift.com', $password);
            
            if ($result['success']) {
                echo "✅ Email sent successfully using " . $result['method'] . " with password ending in '" . substr($password, -2) . "'\n";
                echo "📧 Check your inbox: $test_email\n";
                break; // Stop on first success
            } else {
                echo "❌ Email failed with " . $result['method'] . ": " . ($result['error'] ?? 'Unknown error') . "\n";
            }
        }
        
        // Also test basic mail function
        echo "\nTesting basic mail() function...\n";
        $basic_result = testBasicMail($test_email);
        if ($basic_result['success']) {
            echo "✅ Basic mail() function worked\n";
        } else {
            echo "❌ Basic mail() function failed\n";
        }
    } else {
        echo "❌ Invalid email address provided\n";
    }
}

echo "\n<h3>🔧 Troubleshooting Steps</h3>\n";
echo "1. ✅ Check if email account 'sales@aleppogift.com' exists in iPage control panel\n";
echo "2. ✅ Verify the password is correct (try both: Salem1972#a and Salem1972#i)\n";
echo "3. ✅ Ensure SMTP settings: smtp.ipage.com, port 465, SSL\n";
echo "4. ✅ Check if domain aleppogift.com is properly configured for email\n";
echo "5. ✅ Test if PHPMailer library is properly installed\n\n";

echo "<h3>📝 Recommended Actions</h3>\n";
echo "• If connection tests fail: Update email password in iPage control panel\n";
echo "• If PHPMailer not found: Install it via composer or manual download\n";
echo "• If basic mail() works but SMTP doesn't: Use mail() as fallback\n";
echo "• Check hosting provider's email documentation for specific settings\n\n";

echo "<h3>🔄 Update Configuration</h3>\n";
echo "To update the working password in config, edit config/config.php:\n";
echo "<code>define('SMTP_PASSWORD', 'Salem1972#i'); // or Salem1972#a</code>\n";

?>

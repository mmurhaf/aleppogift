<?php
/**
 * Checkout Diagnostic Tool
 * Use this to diagnose issues with the checkout page
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>🔧 Checkout Diagnostic</h2>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;}</style>";

echo "<h3>1. Testing File Includes</h3>";

try {
    echo "Testing config include...<br>";
    require_once('../config/config.php');
    echo "✅ Config loaded successfully<br>";
    
    echo "Testing email include...<br>";
    require_once('../includes/send_email_simple.php');
    echo "✅ Email functions loaded successfully<br>";
    
    echo "Testing WhatsApp include...<br>";
    require_once('../includes/whatsapp_notify.php');
    echo "✅ WhatsApp functions loaded successfully<br>";
    
} catch (Exception $e) {
    echo "❌ Include Error: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "❌ Fatal Include Error: " . $e->getMessage() . "<br>";
}

echo "<h3>2. Testing Function Availability</h3>";

if (function_exists('sendInvoiceEmail')) {
    echo "✅ sendInvoiceEmail function exists<br>";
} else {
    echo "❌ sendInvoiceEmail function not found<br>";
}

if (function_exists('sendAdminWhatsApp')) {
    echo "✅ sendAdminWhatsApp function exists<br>";
} else {
    echo "❌ sendAdminWhatsApp function not found<br>";
}

echo "<h3>3. Testing Database Connection</h3>";

try {
    if (defined('DB_HOST')) {
        echo "✅ Database constants defined<br>";
        echo "DB Host: " . DB_HOST . "<br>";
        echo "DB Name: " . DB_NAME . "<br>";
        
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        echo "✅ Database connection successful<br>";
    } else {
        echo "❌ Database constants not defined<br>";
    }
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Testing Email Configuration</h3>";

if (defined('EMAIL_FROM')) {
    echo "✅ Email constants defined<br>";
    echo "Email From: " . EMAIL_FROM . "<br>";
    echo "SMTP Host: " . (defined('SMTP_HOST') ? SMTP_HOST : 'Not defined') . "<br>";
} else {
    echo "❌ Email constants not defined<br>";
}

echo "<h3>5. File Permissions Check</h3>";

$files_to_check = [
    '../config/config.php',
    '../includes/send_email_simple.php',
    '../includes/whatsapp_notify.php',
    'checkout.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "✅ $file - readable<br>";
        } else {
            echo "❌ $file - not readable<br>";
        }
    } else {
        echo "❌ $file - not found<br>";
    }
}

echo "<h3>6. PHP Info</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

echo "<h3>✅ Diagnostic Complete</h3>";
echo "If all checks pass, the checkout page should be working.<br>";
echo "If you see any ❌ errors above, those need to be fixed.<br>";

?>

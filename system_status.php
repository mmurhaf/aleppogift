<?php
/**
 * System Status Check
 * Tests all security components and core functionality
 */

require_once(__DIR__ . '/includes/bootstrap.php');

echo "<h1>AleppoGift System Status</h1>";

// Test database connection
try {
    $db = new Database();
    echo "<p>✅ Database connection: SUCCESS</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection: FAILED - " . $e->getMessage() . "</p>";
}

// Test environment variables
echo "<p>✅ Environment: " . ENVIRONMENT . "</p>";
echo "<p>✅ Site URL: " . SITE_URL . "</p>";

// Test security features
echo "<p>✅ CSRF Token: " . Security::generateCSRFToken() . "</p>";
echo "<p>✅ Session ID: " . session_id() . "</p>";

// Test email functionality
if (function_exists('sendOrderEmail')) {
    echo "<p>✅ Email functions: LOADED</p>";
} else {
    echo "<p>❌ Email functions: NOT LOADED</p>";
}

// Test rate limiting
if (Security::checkRateLimit('test', 5, 300)) {
    echo "<p>✅ Rate limiting: WORKING</p>";
} else {
    echo "<p>❌ Rate limiting: FAILED</p>";
}

// Test file permissions
$logs_writable = is_writable(__DIR__ . '/logs');
echo "<p>" . ($logs_writable ? "✅" : "❌") . " Logs directory: " . ($logs_writable ? "WRITABLE" : "NOT WRITABLE") . "</p>";

// Test encryption
try {
    $test_data = "test encryption";
    $encrypted = Security::encrypt($test_data);
    $decrypted = Security::decrypt($encrypted);
    
    if ($decrypted === $test_data) {
        echo "<p>✅ Encryption: WORKING</p>";
    } else {
        echo "<p>❌ Encryption: FAILED - Data mismatch</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Encryption: FAILED - " . $e->getMessage() . "</p>";
}

echo "<h2>Configuration Summary</h2>";
echo "<ul>";
echo "<li><strong>Environment:</strong> " . ENVIRONMENT . "</li>";
echo "<li><strong>Debug Mode:</strong> " . (env_bool('DEBUG_MODE') ? 'ON' : 'OFF') . "</li>";
echo "<li><strong>Database:</strong> " . DB_HOST . "/" . DB_NAME . "</li>";
echo "<li><strong>Email From:</strong> " . EMAIL_FROM . "</li>";
echo "<li><strong>SMTP Host:</strong> " . SMTP_HOST . "</li>";
echo "<li><strong>Currency:</strong> " . CURRENCY . " (" . CURRENCY_SYMBOL . ")</li>";
echo "<li><strong>Ziina Test Mode:</strong> " . (ZIINA_TEST_MODE ? 'ON' : 'OFF') . "</li>";
echo "</ul>";

echo "<p><a href='public/index.php'>Go to Main Site</a></p>";
?>

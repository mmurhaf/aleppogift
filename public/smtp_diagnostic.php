<?php
/**
 * SMTP Authentication Diagnostic Tool
 * Help troubleshoot the authentication issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SMTP Authentication Diagnostic ===\n\n";

require_once('../config/config.php');

echo "Current SMTP Configuration:\n";
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_USERNAME: " . SMTP_USERNAME . "\n";
echo "SMTP_PASSWORD: " . (strlen(SMTP_PASSWORD) > 0 ? str_repeat('*', strlen(SMTP_PASSWORD)) : 'NOT SET') . "\n";
echo "EMAIL_FROM: " . EMAIL_FROM . "\n\n";

echo "=== Diagnostic Information ===\n";
echo "1. Password Length: " . strlen(SMTP_PASSWORD) . " characters\n";
echo "2. Password Characters: " . (preg_match('/^[a-zA-Z0-9#@!$%^&*()_+\-=\[\]{}|;:,.<>?]+$/', SMTP_PASSWORD) ? 'Valid characters' : 'Contains special characters') . "\n";
echo "3. Username Format: " . (filter_var(SMTP_USERNAME, FILTER_VALIDATE_EMAIL) ? 'Valid email format' : 'Invalid email format') . "\n\n";

echo "=== Common iPage SMTP Issues & Solutions ===\n";
echo "1. Email Account Missing:\n";
echo "   - Make sure 'sales@aleppogift.com' exists in your iPage hosting control panel\n";
echo "   - Go to Email Accounts section and create the email if needed\n\n";

echo "2. Password Issues:\n";
echo "   - Current password: " . SMTP_PASSWORD . "\n";
echo "   - Make sure this matches exactly what you set in iPage\n";
echo "   - Try resetting the email password in iPage control panel\n\n";

echo "3. Username Format:\n";
echo "   - Some servers need just 'sales' instead of 'sales@aleppogift.com'\n";
echo "   - Some need the full email address\n";
echo "   - Current: " . SMTP_USERNAME . "\n\n";

echo "4. Alternative SMTP Settings to Try:\n";
echo "   Option A (current): smtp.ipage.com:465 (SSL)\n";
echo "   Option B: mail.aleppogift.com:465 (SSL)\n";
echo "   Option C: smtp.ipage.com:465 (ssl)\n\n";

echo "=== Manual Test Instructions ===\n";
echo "You can test the email credentials manually:\n";
echo "1. Go to iPage control panel\n";
echo "2. Check if 'sales@aleppogift.com' email exists\n";
echo "3. If not, create it with password: Salem1972#i\n";
echo "4. If it exists, reset the password to: Salem1972#i\n";
echo "5. Make sure the domain 'aleppogift.com' is properly configured\n\n";

echo "=== Testing Different Username Formats ===\n";

// Test with just username part
$username_only = 'sales';
echo "Testing with username only: '$username_only'\n";

// Test with full email
$full_email = 'sales@aleppogift.com';
echo "Testing with full email: '$full_email'\n\n";

echo "=== Quick SMTP Connection Test ===\n";

// Test raw SMTP connection
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$socket = @stream_socket_client(
    'ssl://smtp.ipage.com:465',
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT,
    $context
);

if ($socket) {
    echo "✅ Raw SSL connection to smtp.ipage.com:465 successful\n";
    
    // Read server greeting
    $response = fgets($socket);
    echo "Server greeting: " . trim($response) . "\n";
    
    // Send EHLO
    fwrite($socket, "EHLO localhost\r\n");
    $response = '';
    while (($line = fgets($socket)) !== false) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    echo "EHLO response: " . trim($response) . "\n";
    
    fclose($socket);
} else {
    echo "❌ Raw SSL connection failed: $errstr ($errno)\n";
}

echo "\n=== Recommended Actions ===\n";
echo "1. 🔍 Verify email account exists in iPage control panel\n";
echo "2. 🔑 Reset email password to match configuration\n";
echo "3. 🌐 Check domain DNS settings for aleppogift.com\n";
echo "4. 📧 Try creating a test email account first\n";
echo "5. 🔄 Test from production server instead of localhost\n\n";

echo "=== Alternative Test ===\n";
echo "Try creating 'test@aleppogift.com' with a simple password like 'test123'\n";
echo "And update the config temporarily to test basic functionality.\n\n";

echo "=== Diagnostic Complete ===\n";
?>

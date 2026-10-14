<?php
// Very basic email test
echo "Starting email test...\n";

// Check if files exist
$bootstrap_path = 'includes/bootstrap.php';
$email_notifier_path = 'includes/email_notifier.php';

echo "Checking file existence:\n";
echo "Bootstrap: " . (file_exists($bootstrap_path) ? "EXISTS" : "MISSING") . "\n";
echo "Email notifier: " . (file_exists($email_notifier_path) ? "EXISTS" : "MISSING") . "\n";

try {
    echo "Loading bootstrap...\n";
    require_once $bootstrap_path;
    echo "Bootstrap loaded successfully\n";
} catch (Exception $e) {
    echo "Bootstrap error: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "Loading email notifier...\n";
    require_once $email_notifier_path;
    echo "Email notifier loaded successfully\n";
} catch (Exception $e) {
    echo "Email notifier error: " . $e->getMessage() . "\n";
}

// Check functions
echo "Checking email functions:\n";
echo "sendOrderConfirmationEmail: " . (function_exists('sendOrderConfirmationEmail') ? "EXISTS" : "MISSING") . "\n";
echo "sendInvoiceEmail: " . (function_exists('sendInvoiceEmail') ? "EXISTS" : "MISSING") . "\n";

// Test basic email if functions exist
if (function_exists('sendOrderConfirmationEmail')) {
    echo "Testing sendOrderConfirmationEmail...\n";
    
    $test_result = sendOrderConfirmationEmail(
        'mmurhaf1@gmail.com',
        'Test Customer',
        'TEST_' . time(),
        null // No invoice file for this test
    );
    
    echo "Email test result: " . ($test_result ? "SUCCESS" : "FAILED") . "\n";
}

echo "Test completed.\n";
?>





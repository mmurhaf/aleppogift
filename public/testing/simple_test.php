<?php
// Simple bootstrap test
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting bootstrap test...\n";

try {
    echo "Loading bootstrap...\n";
    require_once(__DIR__ . '/../../includes/bootstrap.php');
    echo "Bootstrap loaded successfully!\n";
    
    echo "Testing database connection...\n";
    $db = new Database();
    echo "Database connected successfully!\n";
    
    echo "Testing functions...\n";
    if (function_exists('getCartTotalAndWeight')) {
        echo "getCartTotalAndWeight function: EXISTS\n";
    } else {
        echo "getCartTotalAndWeight function: MISSING\n";
    }
    
    if (function_exists('calculateShippingCost')) {
        echo "calculateShippingCost function: EXISTS\n";
    } else {
        echo "calculateShippingCost function: MISSING\n";
    }
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>

<?php
// Minimal checkout test with error handling
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- Starting minimal checkout test -->\n";

try {
    // Step 1: Test bootstrap loading
    echo "<!-- Loading bootstrap... -->\n";
    require_once('../includes/bootstrap.php');
    echo "<!-- Bootstrap loaded successfully -->\n";
    
    // Step 2: Test session cart
    echo "<!-- Checking cart session... -->\n";
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        echo "<!-- Cart is empty, redirecting to cart.php -->\n";
        header("Location: cart.php");
        exit;
    }
    echo "<!-- Cart has " . count($_SESSION['cart']) . " items -->\n";
    
    // Step 3: Test database
    echo "<!-- Testing database connection... -->\n";
    $db = new Database();
    echo "<!-- Database connected successfully -->\n";
    
    // Step 4: Test functions
    echo "<!-- Testing helper functions... -->\n";
    if (!function_exists('getCartTotalAndWeight')) {
        throw new Exception('getCartTotalAndWeight function not found');
    }
    if (!function_exists('calculateShippingCost')) {
        throw new Exception('calculateShippingCost function not found');
    }
    echo "<!-- All functions found -->\n";
    
    // Step 5: Test function calls
    echo "<!-- Testing function calls... -->\n";
    $cart = $_SESSION['cart'];
    list($cartTotal, $totalWeight) = getCartTotalAndWeight($db, $cart);
    echo "<!-- Cart total: $cartTotal, Weight: $totalWeight -->\n";
    
    $shippingAED = calculateShippingCost('United Arab Emirates', 'Dubai', $totalWeight);
    echo "<!-- Shipping cost: $shippingAED -->\n";
    
    echo "<!-- All tests passed! -->\n";
    
} catch (Throwable $e) {
    echo "<!-- ERROR: " . htmlspecialchars($e->getMessage()) . " -->\n";
    echo "<!-- File: " . htmlspecialchars($e->getFile()) . " -->\n";
    echo "<!-- Line: " . $e->getLine() . " -->\n";
    die();
}

// If we get here, everything is working
echo "SUCCESS: All dependencies loaded correctly\n";
echo "Cart Total: $cartTotal AED\n";
echo "Total Weight: $totalWeight kg\n";
echo "Shipping Cost: $shippingAED AED\n";
?>

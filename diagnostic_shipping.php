<?php
echo "<h1>Shipping System Diagnostic</h1>";

// Check 1: PHP Configuration
echo "<h2>1. PHP Configuration</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Error Reporting: " . error_reporting() . "<br>";
echo "Display Errors: " . ini_get('display_errors') . "<br>";

// Check 2: File Existence
echo "<h2>2. File Checks</h2>";
$files = [
    'includes/shipping.php',
    'public/ajax/calculate_shipping.php',
    'config/config.php',
    'includes/Database.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

// Check 3: Function Availability
echo "<h2>3. Function Availability</h2>";
try {
    require_once('includes/shipping.php');
    
    if (function_exists('calculateShippingCost')) {
        echo "✅ calculateShippingCost function available<br>";
        
        // Test the function
        $test1 = calculateShippingCost('United Arab Emirates', 'Dubai', 2.5);
        echo "Test UAE Dubai 2.5kg: AED $test1<br>";
        
        $test2 = calculateShippingCost('Oman', 'Muscat', 7);
        echo "Test Oman Muscat 7kg: AED $test2<br>";
        
    } else {
        echo "❌ calculateShippingCost function not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error loading shipping.php: " . $e->getMessage() . "<br>";
}

// Check 4: AJAX Endpoint Test
echo "<h2>4. AJAX Endpoint Test</h2>";
echo "<div id='ajax-test-results'></div>";

// Check 5: Direct POST Simulation
echo "<h2>5. Direct POST Simulation</h2>";
$_POST['country'] = 'United Arab Emirates';
$_POST['city'] = 'Dubai';
$_POST['totalWeight'] = 2.5;

ob_start();
try {
    include 'public/ajax/calculate_shipping.php';
    $result = ob_get_contents();
    echo "Direct POST result: <pre>" . htmlspecialchars($result) . "</pre>";
} catch (Exception $e) {
    echo "Error in direct POST: " . $e->getMessage();
}
ob_end_clean();

?>

<script>
// AJAX Test
console.log('Starting AJAX diagnostic test...');
fetch('public/ajax/calculate_shipping.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'country=United Arab Emirates&city=Dubai&totalWeight=2.5'
})
.then(response => {
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    return response.text();
})
.then(text => {
    console.log('Raw response:', text);
    document.getElementById('ajax-test-results').innerHTML = 
        '<strong>AJAX Response:</strong><pre>' + text + '</pre>';
    
    try {
        const data = JSON.parse(text);
        console.log('Parsed data:', data);
        document.getElementById('ajax-test-results').innerHTML += 
            '<strong>Parsed JSON:</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    } catch (e) {
        document.getElementById('ajax-test-results').innerHTML += 
            '<strong style="color:red;">JSON Parse Error:</strong> ' + e.message;
    }
})
.catch(error => {
    console.error('AJAX Error:', error);
    document.getElementById('ajax-test-results').innerHTML = 
        '<strong style="color:red;">AJAX Error:</strong> ' + error.message;
});
</script>

<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    h1, h2 { color: #333; }
    pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
</style>
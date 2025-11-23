<?php
/**
 * Quick Add to Cart Test - Production Server
 * Test this on https://aleppogift.com/quick_cart_test.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Quick Add to Cart Test</h1>";
echo "<p>Testing on: " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<hr>";

// Test 1: Basic file existence
echo "<h3>1. File Existence Check</h3>";
$files_to_check = [
    'config/config.php',
    'includes/Database.php', 
    '../ajax/add_to_cart.php',
    '../ajax/add_to_cart_debug_verbose.php'
];

foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    echo "$file: " . ($exists ? '✅ EXISTS' : '❌ MISSING') . "<br>";
}

// Test 2: Direct AJAX call simulation
echo "<h3>2. Direct AJAX Call Test</h3>";

echo '<div id="test-results"></div>';
echo '<button onclick="testAddToCart()">Test Add to Cart (Product ID: 1)</button><br><br>';
echo '<button onclick="testAddToCartDebug()">Test Debug Version</button>';

?>

<script>
function testAddToCart() {
    document.getElementById('test-results').innerHTML = 'Testing original add_to_cart.php...';
    
    const formData = new FormData();
    formData.append('product_id', '1');
    formData.append('quantity', '1');
    
    fetch('./public/ajax/add_to_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('test-results').innerHTML = 
            '<h4>Original add_to_cart.php Response:</h4><pre style="background:#f5f5f5;padding:10px;white-space:pre-wrap;">' + 
            data + '</pre>';
    })
    .catch(error => {
        document.getElementById('test-results').innerHTML = 
            '<h4>Error:</h4><span style="color: red;">' + error + '</span>';
    });
}

function testAddToCartDebug() {
    document.getElementById('test-results').innerHTML = 'Testing debug version...';
    
    const formData = new FormData();
    formData.append('product_id', '1');
    formData.append('quantity', '1');
    
    fetch('./public/ajax/add_to_cart_debug_verbose.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('test-results').innerHTML = 
            '<h4>Debug Version Response:</h4><pre style="background:#f0f8ff;padding:10px;white-space:pre-wrap;">' + 
            data + '</pre>';
    })
    .catch(error => {
        document.getElementById('test-results').innerHTML = 
            '<h4>Error:</h4><span style="color: red;">' + error + '</span>';
    });
}
</script>

<h3>3. Manual Database Test</h3>
<?php
// Test 3: Manual database connection like add_to_cart.php does
echo "Testing database connection exactly like add_to_cart.php does...<br>";

try {
    // Change to the ajax directory perspective
    $old_cwd = getcwd();
    
    // Simulate being in public/ajax/ directory
    if (file_exists('../ajax/')) {
        chdir('../ajax/');
        echo "Changed to ajax directory: " . getcwd() . "<br>";
        
        // Now try the exact same includes
        if (file_exists('../../config/config.php')) {
            require_once('../../config/config.php');
            echo "✅ Config loaded from ajax perspective<br>";
            
            if (file_exists('../../includes/Database.php')) {
                require_once('../../includes/Database.php');
                echo "✅ Database class loaded from ajax perspective<br>";
                
                // Try to instantiate Database
                $db = new Database();
                echo "✅ Database object created successfully<br>";
                
                // Test a query
                $result = $db->query("SELECT COUNT(*) as count FROM products")->fetch(PDO::FETCH_ASSOC);
                echo "✅ Test query successful - Products count: " . $result['count'] . "<br>";
                
            } else {
                echo "❌ Database class file not found from ajax perspective<br>";
            }
        } else {
            echo "❌ Config file not found from ajax perspective<br>";
        }
        
        chdir($old_cwd);
    } else {
        echo "❌ public/ajax directory not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error during manual test: " . $e->getMessage() . "<br>";
    chdir($old_cwd);
}

?>

<h3>4. Instructions</h3>
<ol>
<li>Upload this file to your server as <code>quick_cart_test.php</code></li>
<li>Visit <code>https://aleppogift.com/quick_cart_test.php</code></li>
<li>Click both test buttons and check the results</li>
<li>Compare the responses to see what's different</li>
</ol>

<p><strong>Expected outcome:</strong> This will show us exactly where the database connection error is occurring and whether it's a file version issue or something else.</p>





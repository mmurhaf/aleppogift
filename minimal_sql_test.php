<?php
// Minimal test to reproduce the exact error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Minimal SQL Parameter Test</h2>";

$root_dir = dirname(__DIR__);
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

$db = new Database();

// Test different parameter formats that might cause the issue
$test_cases = [
    'Case 1: Integer array' => [94],
    'Case 2: String array' => ['94'],
    'Case 3: Mixed array' => [intval('94')],
    'Case 4: Empty array' => [],
];

foreach ($test_cases as $label => $params) {
    echo "<h3>$label</h3>";
    echo "Parameters: " . json_encode($params) . "<br>";
    
    try {
        if (empty($params)) {
            // Test without WHERE clause
            $stmt = $db->query("SELECT COUNT(*) as count FROM orders");
        } else {
            // Test with WHERE clause
            $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE id = ?", $params);
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Success: " . $result['count'] . " results<br>";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
        
        // Debug the parameter binding
        echo "Debug info:<br>";
        echo "- Parameters type: " . gettype($params) . "<br>";
        echo "- Parameters count: " . count($params) . "<br>";
        if (!empty($params)) {
            reset($params);
            $first_key = key($params);
            echo "- First key: " . var_export($first_key, true) . " (type: " . gettype($first_key) . ")<br>";
            echo "- Is integer key: " . (is_int($first_key) ? 'Yes' : 'No') . "<br>";
        }
    }
    echo "<hr>";
}

// Test the exact InvoiceGenerator scenario
echo "<h3>InvoiceGenerator Scenario Test</h3>";
try {
    $order_id = 94;
    $order_id_int = (int)$order_id;
    echo "order_id: $order_id (type: " . gettype($order_id) . ")<br>";
    echo "order_id_int: $order_id_int (type: " . gettype($order_id_int) . ")<br>";
    
    $params = [$order_id_int];
    echo "params: " . json_encode($params) . "<br>";
    
    $order = $db->query("SELECT * FROM orders WHERE id = ?", $params)->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "✅ InvoiceGenerator scenario successful: Order " . $order['id'] . " found<br>";
    } else {
        echo "❌ Order not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ InvoiceGenerator scenario failed: " . $e->getMessage() . "<br>";
}
?>
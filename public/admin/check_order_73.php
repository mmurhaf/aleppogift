<?php
require_once('../../includes/bootstrap.php');

try {
    $db = new Database();
    
    // Check if order 73 exists
    $order73 = $db->query("SELECT * FROM orders WHERE id = 73")->fetch(PDO::FETCH_ASSOC);
    
    if ($order73) {
        echo "Order 73 exists!\n";
        print_r($order73);
    } else {
        echo "Order 73 does not exist.\n";
    }
    
    // Let's check all orders around that ID
    echo "\nOrders around ID 73:\n";
    $orders = $db->query("SELECT id, customer_id FROM orders WHERE id BETWEEN 70 AND 80 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orders as $order) {
        echo "Order ID: " . $order['id'] . " | Customer ID: " . $order['customer_id'] . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>





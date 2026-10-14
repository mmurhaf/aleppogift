<?php
require_once('../../includes/bootstrap.php');

try {
    $db = new Database();
    
    // Check orders table structure
    echo "Orders table structure:\n";
    $columns = $db->query("DESCRIBE orders")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " | " . $col['Type'] . "\n";
    }
    
    echo "\n\nCustomers table structure:\n";
    $columns = $db->query("DESCRIBE customers")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " | " . $col['Type'] . "\n";
    }
    
    // Fetch recent orders
    echo "\n\nRecent orders:\n";
    $orders = $db->query("
        SELECT 
            o.id,
            c.fullname AS customer_name,
            c.email AS customer_email
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        ORDER BY o.id DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as $order) {
        echo "Order ID: " . $order['id'] . " | Customer: " . $order['customer_name'] . " | Email: " . $order['customer_email'] . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>





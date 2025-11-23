<?php
require_once('../../includes/bootstrap.php');

try {
    $db = new Database();
    
    // Fetch recent orders
    $orders = $db->query("
        SELECT 
            o.id,
            o.created_at,
            c.fullname AS customer_name,
            c.email AS customer_email
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        ORDER BY o.id DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "Recent orders:\n";
    foreach ($orders as $order) {
        echo "Order ID: " . $order['id'] . " | Customer: " . $order['customer_name'] . " | Email: " . $order['customer_email'] . " | Date: " . $order['created_at'] . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>

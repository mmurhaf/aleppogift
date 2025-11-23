<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

header('Content-Type: text/plain');

try {
    $db = new Database();
    
    // Check if products table exists
    $tables = $db->query("SHOW TABLES LIKE 'products'")->fetchAll();
    echo "Products table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n";
    
    if (count($tables) > 0) {
        // Get product count
        $count = $db->query("SELECT COUNT(*) as count FROM products")->fetch(PDO::FETCH_ASSOC);
        echo "Total products: " . $count['count'] . "\n\n";
        
        // Get first 5 products
        $products = $db->query("SELECT id, name, status FROM products LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        echo "First 5 products:\n";
        foreach($products as $product) {
            echo "ID: " . $product['id'] . ", Name: " . $product['name'] . ", Status: " . $product['status'] . "\n";
        }
        
        // Get active products
        $active = $db->query("SELECT COUNT(*) as count FROM products WHERE status = 1")->fetch(PDO::FETCH_ASSOC);
        echo "\nActive products (status=1): " . $active['count'] . "\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

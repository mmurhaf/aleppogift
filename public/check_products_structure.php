<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

header('Content-Type: text/plain');

try {
    $db = new Database();
    
    // Get table structure
    echo "Products table structure:\n";
    $columns = $db->query("DESCRIBE products")->fetchAll(PDO::FETCH_ASSOC);
    foreach($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\nFirst 3 products:\n";
    $products = $db->query("SELECT * FROM products LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    foreach($products as $i => $product) {
        echo "Product " . ($i+1) . ":\n";
        foreach($product as $key => $value) {
            echo "  $key: $value\n";
        }
        echo "\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

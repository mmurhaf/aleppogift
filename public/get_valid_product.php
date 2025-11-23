<?php
require_once '../config/config.php';
require_once '../includes/Database.php';

header('Content-Type: text/plain');

try {
    $db = new Database();
    
    // Get first active product
    echo "Looking for active products:\n";
    $products = $db->query("SELECT id, name_en, name_ar, status FROM products WHERE status = 1 LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        echo "Found " . count($products) . " active products:\n";
        foreach($products as $product) {
            echo "ID: " . $product['id'] . ", English: " . $product['name_en'] . ", Arabic: " . $product['name_ar'] . ", Status: " . $product['status'] . "\n";
        }
    } else {
        echo "No active products found. Checking all products:\n";
        $all_products = $db->query("SELECT id, name_en, name_ar, status FROM products LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        foreach($all_products as $product) {
            echo "ID: " . $product['id'] . ", English: " . $product['name_en'] . ", Arabic: " . $product['name_ar'] . ", Status: " . $product['status'] . "\n";
        }
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

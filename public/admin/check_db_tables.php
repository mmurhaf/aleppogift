<?php
require_once('../../includes/bootstrap.php');

global $db;
$db = new Database();

try {
    echo "=== DATABASE STRUCTURE ANALYSIS ===\n\n";
    
    // Check if order_items table exists
    echo "1. Checking order_items table:\n";
    try {
        $columns = $db->query("DESCRIBE order_items")->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ order_items table exists with columns:\n";
        foreach ($columns as $col) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "❌ order_items table error: " . $e->getMessage() . "\n";
    }
    
    echo "\n2. Checking products table:\n";
    try {
        $columns = $db->query("DESCRIBE products")->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ products table exists with columns:\n";
        foreach ($columns as $col) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "❌ products table error: " . $e->getMessage() . "\n";
    }
    
    echo "\n3. Checking product_variations table:\n";
    try {
        $columns = $db->query("DESCRIBE product_variations")->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ product_variations table exists with columns:\n";
        foreach ($columns as $col) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "❌ product_variations table error: " . $e->getMessage() . "\n";
    }
    
    echo "\n4. Checking existing order items for order 83:\n";
    try {
        $items = $db->query("SELECT * FROM order_items WHERE order_id = 83")->fetchAll(PDO::FETCH_ASSOC);
        echo "Order 83 has " . count($items) . " items:\n";
        foreach ($items as $item) {
            echo "  - " . json_encode($item) . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Error fetching order items: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}
?>





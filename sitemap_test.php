<?php
/**
 * Sitemap Test - Check actual query output
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/includes/bootstrap.php');

if (!class_exists('Database')) {
    require_once(__DIR__ . '/includes/Database.php');
}

$db = new Database();

echo "Testing query execution:\n\n";

// Test categories
echo "Categories:\n";
try {
    $result = $db->query("SELECT id, name_en FROM categories WHERE status = 1 LIMIT 3");
    $categories = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($categories) . "\n";
    print_r($categories);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n\nBrands:\n";
try {
    $result = $db->query("SELECT id, name_en FROM brands WHERE status = 1 LIMIT 3");
    $brands = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($brands) . "\n";
    print_r($brands);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n\nProducts:\n";
try {
    $result = $db->query("SELECT id, name_en FROM products WHERE status = 1 LIMIT 3");
    $products = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($products) . "\n";
    print_r($products);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

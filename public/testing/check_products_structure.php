<?php
require_once('../../config/config.php');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
    
    echo "Products table structure:\n";
    $stmt = $pdo->query('DESCRIBE products');
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\nFirst 5 products:\n";
    $stmt = $pdo->query('SELECT * FROM products LIMIT 5');
    $products = $stmt->fetchAll();
    foreach ($products as $product) {
        echo "ID: " . $product['id'];
        if (isset($product['title'])) echo " - " . $product['title'];
        if (isset($product['product_name'])) echo " - " . $product['product_name'];
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

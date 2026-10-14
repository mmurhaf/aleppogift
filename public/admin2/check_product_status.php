<?php
require_once('config/config.php');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
    
    echo "Products with their status:\n";
    $stmt = $pdo->query('SELECT id, name_en, status FROM products WHERE id IN (164, 166, 167, 168, 169)');
    while($row = $stmt->fetch()) {
        echo "ID: " . $row['id'] . " - " . ($row['name_en'] ?? 'No English name') . " - Status: " . $row['status'] . "\n";
    }
    
    echo "\nProducts with status = 1:\n";
    $stmt = $pdo->query('SELECT id, name_en, status FROM products WHERE status = 1 LIMIT 5');
    while($row = $stmt->fetch()) {
        echo "ID: " . $row['id'] . " - " . ($row['name_en'] ?? 'No English name') . " - Status: " . $row['status'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>





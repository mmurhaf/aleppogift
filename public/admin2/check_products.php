<?php
require_once('config/config.php');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
    $stmt = $pdo->query('SELECT id, name FROM products LIMIT 5');
    echo "Available products:\n";
    while($row = $stmt->fetch()) {
        echo 'ID: ' . $row['id'] . ' - ' . $row['name'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>





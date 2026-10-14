<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing simple connection...\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=aleppogift", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Connection successful!\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Query successful: Found " . $result['count'] . " products\n";
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}
?>





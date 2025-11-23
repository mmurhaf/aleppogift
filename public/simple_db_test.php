<?php
// Simple database test for web server
echo "Testing database connection...\n";

try {
    // Test basic connection
    $pdo = new PDO("mysql:host=localhost;dbname=aleppogift;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SUCCESS: Database connected!\n";
    
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Test query result: " . $result['test'] . "\n";
    
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>

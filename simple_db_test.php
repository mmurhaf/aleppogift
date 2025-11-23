<?php
/**
 * Simple Database Connection Test for PHP 8.4
 */

echo "=== PHP 8.4 Database Driver Check ===\n\n";

// Check PHP version
echo "PHP Version: " . phpversion() . "\n";
echo "PHP ini file: " . php_ini_loaded_file() . "\n\n";

// Check if PDO MySQL driver is available
echo "=== PDO Driver Status ===\n";
if (class_exists('PDO')) {
    $drivers = PDO::getAvailableDrivers();
    echo "Available PDO Drivers: " . implode(', ', $drivers) . "\n";
    
    if (in_array('mysql', $drivers)) {
        echo "✅ PDO MySQL driver: AVAILABLE\n";
    } else {
        echo "❌ PDO MySQL driver: NOT AVAILABLE\n";
    }
} else {
    echo "❌ PDO class: NOT AVAILABLE\n";
}

echo "\n=== MySQL Extensions ===\n";
$extensions = ['pdo_mysql', 'mysqli', 'mysqlnd'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext: Loaded\n";
    } else {
        echo "❌ $ext: NOT Loaded\n";
    }
}

// Test database connection
echo "\n=== Database Connection Test ===\n";
try {
    require_once __DIR__ . '/config/config.php';
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    echo "Attempting connection to: $dsn\n";
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ Database connection: SUCCESS\n";
    
    // Simple test query
    $stmt = $pdo->prepare("SELECT 1 as test");
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "✅ Query test: SUCCESS (result: " . $result['test'] . ")\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'could not find driver') !== false) {
        echo "\n🔧 SOLUTION NEEDED: MySQL PDO driver not found!\n";
    }
} catch (Exception $e) {
    echo "❌ Configuration error: " . $e->getMessage() . "\n";
}

echo "\n=== PHP 8.4 Upgrade Checklist ===\n";
echo "1. Ensure php.ini has these extensions enabled:\n";
echo "   extension=pdo\n";
echo "   extension=pdo_mysql\n";
echo "   extension=mysqli\n";
echo "   extension=mysqlnd\n\n";

echo "2. After upgrading to PHP 8.4:\n";
echo "   - Check php.ini location: php --ini\n";
echo "   - Verify extensions: php -m | grep -i mysql\n";
echo "   - Restart web server\n";
echo "   - Run this test again\n";

?>
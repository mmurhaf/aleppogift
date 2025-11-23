<?php
// Check PHP configuration and caching
echo "<h2>PHP Configuration Check</h2>";

echo "<h3>PHP Version & Extensions</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO Available: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "<br>";

echo "<h3>Caching Configuration</h3>";
echo "OPcache Enabled: " . (ini_get('opcache.enable') ? 'Yes' : 'No') . "<br>";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    echo "OPcache Status: " . ($status ? 'Active' : 'Inactive') . "<br>";
    if ($status) {
        echo "Cache Full: " . ($status['cache_full'] ? 'Yes' : 'No') . "<br>";
        echo "Cached Files: " . $status['opcache_statistics']['num_cached_scripts'] . "<br>";
    }
}

echo "<h3>File Modification Times</h3>";
$files = [
    '/includes/Database.php',
    '/includes/generate_invoice.php',
    '/admin/generate_invoice.php'
];

foreach ($files as $file) {
    $full_path = __DIR__ . $file;
    if (file_exists($full_path)) {
        echo "$file: " . date('Y-m-d H:i:s', filemtime($full_path)) . "<br>";
    } else {
        echo "$file: NOT FOUND<br>";
    }
}

echo "<h3>Manual Database Test</h3>";
try {
    $root_dir = dirname(__DIR__);
    require_once($root_dir . '/config/config.php');
    
    // Direct PDO connection without our Database class
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Direct PDO connection successful<br>";
    
    // Test direct prepared statement
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([94]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "✅ Direct PDO query successful: Order " . $order['id'] . " found<br>";
    } else {
        echo "❌ Order 94 not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Direct PDO error: " . $e->getMessage() . "<br>";
}

// Clear opcache if possible
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<br>🔄 OPcache cleared<br>";
}
?>
<?php
/**
 * Database Connection Diagnostic Tool
 * 
 * This script helps identify database connection issues.
 * Visit: https://aleppogift.com/database-diagnostic.php
 * 
 * IMPORTANT: Delete this file after troubleshooting in production!
 */

// Prevent accidental exposure of sensitive information
$allowed_ips = ['127.0.0.1', '::1']; // Only localhost
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// In production, restrict access
if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    if (!in_array($client_ip, $allowed_ips)) {
        http_response_code(403);
        die("Access denied");
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Database Diagnostic</title><style>";
echo "body { font-family: monospace; margin: 20px; }";
echo ".success { color: green; } .error { color: red; } .warning { color: orange; }";
echo "pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }";
echo "</style></head><body>";
echo "<h1>AleppoGift Database Diagnostic</h1>";

// Load configuration
require_once(__DIR__ . '/includes/env_loader.php');
EnvLoader::load();

require_once(__DIR__ . '/config/config.php');

echo "<h2>1. Environment Variables</h2>";
echo "<pre>";
echo "ENVIRONMENT: " . (defined('IS_PRODUCTION') && IS_PRODUCTION ? "PRODUCTION" : "DEVELOPMENT") . "\n";
echo "DB_HOST: " . EnvLoader::get('DB_HOST', 'NOT SET') . "\n";
echo "DB_PORT: " . (EnvLoader::get('DB_PORT', '3306')) . "\n";
echo "DB_NAME: " . EnvLoader::get('DB_NAME', 'NOT SET') . "\n";
echo "DB_USER: " . EnvLoader::get('DB_USER', 'NOT SET') . "\n";
echo "DB_PASS: " . (strlen(EnvLoader::get('DB_PASS', '')) ? "***SET***" : "NOT SET") . "\n";
echo "</pre>";

// Test basic connectivity
echo "<h2>2. Basic Connectivity Test</h2>";
$host = DB_HOST;
$port = EnvLoader::get('DB_PORT', '3306');
$errno = 0;
$errstr = "";

echo "<pre>";
if (@fsockopen($host, $port, $errno, $errstr, 5)) {
    echo "<span class='success'>✓ TCP connection to {$host}:{$port} successful</span>\n";
} else {
    echo "<span class='error'>✗ TCP connection to {$host}:{$port} FAILED</span>\n";
    echo "Error: {$errstr} ({$errno})\n";
}
echo "</pre>";

// Test PDO connection
echo "<h2>3. PDO Connection Test</h2>";
echo "<pre>";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . EnvLoader::get('DB_PORT', '3306') . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );
    echo "<span class='success'>✓ PDO Connection successful!</span>\n";
    echo "MySQL Server Info: " . $pdo->getAttribute(PDO::ATTR_SERVER_INFO) . "\n";
    
    // Test a simple query
    $result = $pdo->query("SELECT 1 as test");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "Test Query: " . json_encode($row) . "\n";
    
} catch (PDOException $e) {
    echo "<span class='error'>✗ PDO Connection FAILED</span>\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Error Message: " . $e->getMessage() . "\n";
    
    // Provide debugging hints
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "\n<span class='warning'>Hint: Wrong username or password</span>\n";
        echo "Check credentials in:\n";
        echo "  - .env file\n";
        echo "  - config/config.php\n";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "\n<span class='warning'>Hint: Database name does not exist</span>\n";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "\n<span class='warning'>Hint: MySQL server not running or wrong host/port</span>\n";
    }
}
echo "</pre>";

// Check .env file
echo "<h2>4. Configuration Files</h2>";
echo "<pre>";
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    echo "<span class='success'>✓ .env file exists</span>\n";
    echo "Location: " . $env_path . "\n";
} else {
    echo "<span class='error'>✗ .env file NOT found</span>\n";
    echo "Expected location: " . $env_path . "\n";
}

$config_path = __DIR__ . '/config/config.php';
if (file_exists($config_path)) {
    echo "<span class='success'>✓ config.php exists</span>\n";
} else {
    echo "<span class='error'>✗ config.php NOT found</span>\n";
}
echo "</pre>";

// Log file status
echo "<h2>5. Log Files</h2>";
echo "<pre>";
$log_dir = __DIR__ . '/logs';
if (is_writable($log_dir)) {
    echo "<span class='success'>✓ Logs directory is writable</span>\n";
} else {
    echo "<span class='error'>✗ Logs directory NOT writable</span>\n";
}

$error_log = $log_dir . '/error.log';
if (file_exists($error_log)) {
    $tail_lines = 20;
    $content = array_slice(file($error_log), -$tail_lines);
    echo "\nRecent error log entries (last {$tail_lines} lines):\n";
    echo implode("", $content);
} else {
    echo "No error.log found yet (will be created on first error)\n";
}
echo "</pre>";

echo "<h2>Next Steps</h2>";
echo "<ul>";
echo "<li>Delete this file: <code>rm " . __FILE__ . "</code></li>";
echo "<li>Check the .env file for correct database credentials</li>";
echo "<li>Verify MySQL server is running and accessible</li>";
echo "<li>Check firewall rules if MySQL is on a remote host</li>";
echo "</ul>";

echo "</body></html>";
?>

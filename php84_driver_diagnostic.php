<?php
/**
 * PHP 8.4 Database Driver Diagnostic Tool
 * Helps diagnose "could not find driver" errors
 */

echo "<h1>PHP Database Driver Diagnostic</h1>\n";
echo "<div style='font-family: monospace;'>\n";

// Basic PHP info
echo "<h2>1. PHP Environment</h2>\n";
echo "PHP Version: " . phpversion() . "\n<br>";
echo "PHP SAPI: " . php_sapi_name() . "\n<br>";
echo "PHP Configuration File: " . php_ini_loaded_file() . "\n<br>";

$additional_inis = php_ini_scanned_files();
if ($additional_inis) {
    echo "Additional INI files: " . $additional_inis . "\n<br>";
}
echo "\n<br>";

// Check PDO availability
echo "<h2>2. PDO Status</h2>\n";
if (class_exists('PDO')) {
    echo "✅ PDO Class: Available\n<br>";
    
    // Get available PDO drivers
    $drivers = PDO::getAvailableDrivers();
    echo "Available PDO Drivers: " . implode(', ', $drivers) . "\n<br>";
    
    // Check specific drivers
    $required_drivers = ['mysql', 'sqlite'];
    foreach ($required_drivers as $driver) {
        if (in_array($driver, $drivers)) {
            echo "✅ PDO $driver: Available\n<br>";
        } else {
            echo "❌ PDO $driver: NOT Available\n<br>";
        }
    }
} else {
    echo "❌ PDO Class: NOT Available\n<br>";
}
echo "\n<br>";

// Check MySQL extensions
echo "<h2>3. MySQL Extensions</h2>\n";
$mysql_extensions = ['mysqli', 'pdo_mysql', 'mysqlnd'];
foreach ($mysql_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext: Loaded\n<br>";
        if ($ext === 'mysqli') {
            echo "   - MySQLi Version: " . mysqli_get_client_info() . "\n<br>";
        }
    } else {
        echo "❌ $ext: NOT Loaded\n<br>";
    }
}
echo "\n<br>";

// Test database connection with detailed error reporting
echo "<h2>4. Database Connection Test</h2>\n";

// Test if config file exists
$config_file = __DIR__ . '/config/config.php';
if (!file_exists($config_file)) {
    echo "❌ Config file not found: $config_file\n<br>";
} else {
    echo "✅ Config file found\n<br>";
    
    try {
        require_once $config_file;
        
        echo "Database Configuration:\n<br>";
        echo "- Host: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n<br>";
        echo "- Database: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n<br>";
        echo "- User: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n<br>";
        echo "- Password: " . (defined('DB_PASS') ? '[SET]' : 'NOT DEFINED') . "\n<br>";
        echo "\n<br>";
        
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
            // Test connection with different DSN formats
            $dsn_formats = [
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME
            ];
            
            foreach ($dsn_formats as $i => $dsn) {
                try {
                    echo "Testing DSN format " . ($i + 1) . ": $dsn\n<br>";
                    
                    $pdo = new PDO(
                        $dsn,
                        DB_USER,
                        DB_PASS,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_EMULATE_PREPARES => false,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]
                    );
                    
                    // Test a simple query
                    $stmt = $pdo->query("SELECT VERSION() as version, NOW() as current_time");
                    $result = $stmt->fetch();
                    
                    echo "✅ Connection successful!\n<br>";
                    echo "   - MySQL Version: " . $result['version'] . "\n<br>";
                    echo "   - Current Time: " . $result['current_time'] . "\n<br>";
                    
                    $pdo = null; // Close connection
                    break;
                    
                } catch (PDOException $e) {
                    echo "❌ Connection failed: " . $e->getMessage() . "\n<br>";
                    
                    // Provide specific error analysis
                    $error_msg = $e->getMessage();
                    if (strpos($error_msg, 'could not find driver') !== false) {
                        echo "   🔍 ANALYSIS: MySQL PDO driver is not installed/enabled\n<br>";
                    } elseif (strpos($error_msg, 'Access denied') !== false) {
                        echo "   🔍 ANALYSIS: Authentication error - check credentials\n<br>";
                    } elseif (strpos($error_msg, 'Unknown database') !== false) {
                        echo "   🔍 ANALYSIS: Database does not exist\n<br>";
                    } elseif (strpos($error_msg, 'Connection refused') !== false) {
                        echo "   🔍 ANALYSIS: Cannot connect to MySQL server\n<br>";
                    }
                }
                echo "\n<br>";
            }
        } else {
            echo "❌ Database configuration incomplete\n<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error loading config: " . $e->getMessage() . "\n<br>";
    }
}

// PHP.ini recommendations
echo "<h2>5. PHP.ini Recommendations for PHP 8.4</h2>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
echo "; Required extensions for AleppoGift\n";
echo "extension=pdo\n";
echo "extension=pdo_mysql\n";
echo "extension=mysqli\n";
echo "extension=mysqlnd\n";
echo "\n";
echo "; Additional recommended extensions\n";
echo "extension=mbstring\n";
echo "extension=openssl\n";
echo "extension=curl\n";
echo "extension=gd\n";
echo "extension=zip\n";
echo "extension=fileinfo\n";
echo "extension=json\n";
echo "\n";
echo "; Memory and execution settings\n";
echo "memory_limit = 256M\n";
echo "max_execution_time = 300\n";
echo "upload_max_filesize = 64M\n";
echo "post_max_size = 64M\n";
echo "</pre>\n";

echo "<h2>6. Troubleshooting Steps</h2>\n";
echo "<ol>\n";
echo "<li><strong>Check php.ini location:</strong> " . php_ini_loaded_file() . "</li>\n";
echo "<li><strong>Enable extensions:</strong> Uncomment the required extension lines</li>\n";
echo "<li><strong>Restart web server:</strong> Apache/Nginx needs restart after php.ini changes</li>\n";
echo "<li><strong>Verify installation:</strong> Run this script again after changes</li>\n";
echo "</ol>\n";

echo "</div>\n";
?>
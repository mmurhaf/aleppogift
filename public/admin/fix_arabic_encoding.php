<?php
/**
 * Arabic Character Encoding Fix Script
 * This script fixes double-encoded UTF-8 Arabic characters in the aleppogift database
 * 
 * IMPORTANT: Make a backup of your database before running this script!
 * 
 * Usage: Upload this file to your server and run it via web browser
 * Example: https://yourdomain.com/fix_arabic_encoding.php
 */

// Try multiple possible config file locations
$possible_config_files = [
    'config/config.php',
    'includes/config.php',
    'inc/config.php',
    'config/database.php',
    'db_config.php'
];

$config_file = null;
foreach ($possible_config_files as $file) {
    if (file_exists($file)) {
        $config_file = $file;
        break;
    }
}

if (!$config_file) {
    die('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif;">
        <h3>❌ Configuration File Missing</h3>
        <p>No configuration file found. Tried the following locations:</p>
        <ul><li>' . implode('</li><li>', $possible_config_files) . '</li></ul>
        <p>Please ensure one of these files exists and contains database connection constants.</p>
        <h4>Manual Configuration</h4>
        <p>If you prefer, you can set the database credentials directly below:</p>
        <form method="post" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;">
            <label>Host: <input type="text" name="db_host" value="localhost" style="margin: 5px; padding: 5px;"></label><br>
            <label>Database: <input type="text" name="db_name" placeholder="aleppogift" style="margin: 5px; padding: 5px;"></label><br>
            <label>Username: <input type="text" name="db_user" placeholder="root" style="margin: 5px; padding: 5px;"></label><br>
            <label>Password: <input type="password" name="db_pass" style="margin: 5px; padding: 5px;"></label><br>
            <button type="submit" name="manual_config" style="background: #007bff; color: white; padding: 8px 15px; border: none; border-radius: 3px; margin: 5px;">Connect</button>
        </form>
    </div>');
}

// Include database configuration
require_once $config_file;

// Handle manual configuration (only if automatic detection fails)
if (isset($_POST['manual_config'])) {
    // Only define if not already defined
    if (!defined('DB_HOST')) define('DB_HOST', $_POST['db_host']);
    if (!defined('DB_NAME')) define('DB_NAME', $_POST['db_name']);
    if (!defined('DB_USER')) define('DB_USER', $_POST['db_user']);
    if (!defined('DB_PASS')) define('DB_PASS', $_POST['db_pass']);
}

// Check for different possible constant names
$db_host = null;
$db_name = null;
$db_user = null;
$db_pass = null;

// Try different constant naming conventions
if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    $db_host = DB_HOST;
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = defined('DB_PASS') ? DB_PASS : '';
} elseif (defined('DATABASE_HOST') && defined('DATABASE_NAME') && defined('DATABASE_USER')) {
    $db_host = DATABASE_HOST;
    $db_name = DATABASE_NAME;
    $db_user = DATABASE_USER;
    $db_pass = defined('DATABASE_PASSWORD') ? constant('DATABASE_PASSWORD') : '';
} elseif (defined('MYSQL_HOST') && defined('MYSQL_DATABASE') && defined('MYSQL_USER')) {
    $db_host = MYSQL_HOST;
    $db_name = MYSQL_DATABASE;
    $db_user = MYSQL_USER;
    $db_pass = defined('MYSQL_PASSWORD') ? constant('MYSQL_PASSWORD') : '';
} elseif (isset($host) && isset($dbname) && isset($username)) {
    // Check for variables instead of constants
    $db_host = $host;
    $db_name = $dbname;
    $db_user = $username;
    $db_pass = isset($password) ? $password : '';
}

if (empty($db_host) || empty($db_name) || empty($db_user)) {
    die('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif;">
        <h3>❌ Database Constants Not Found</h3>
        <p>Could not find database configuration in <code>' . $config_file . '</code></p>
        <p>Looking for one of these patterns:</p>
        <ul>
            <li>DB_HOST, DB_NAME, DB_USER, DB_PASS</li>
            <li>DATABASE_HOST, DATABASE_NAME, DATABASE_USER, DATABASE_PASSWORD</li>
            <li>MYSQL_HOST, MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD</li>
            <li>$host, $dbname, $username, $password variables</li>
        </ul>
    </div>');
}

// Set proper headers for UTF-8
header('Content-Type: text/html; charset=utf-8');

// Check if the script should run (safety measure)
$run_fix = isset($_GET['run']) && $_GET['run'] === 'yes';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arabic Encoding Fix - AleppoGift</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .code { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; margin: 10px 0; border-radius: 3px; font-family: monospace; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px 5px; }
        button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Arabic Character Encoding Fix Tool</h1>
    
    <!-- Display dependency information -->
    <div class="info">
        <h4>📋 Configuration Status</h4>
        <ul>
            <li>Configuration file: <code><?php echo $config_file; ?></code> ✅</li>
            <li>Database host: <code><?php echo $db_host; ?></code></li>
            <li>Database name: <code><?php echo $db_name; ?></code></li>
            <li>Database user: <code><?php echo $db_user; ?></code></li>
        </ul>
    </div>
    
    <?php if (!$run_fix): ?>
        <div class="warning">
            <h3>⚠️ WARNING</h3>
            <p>This script will modify your database to fix Arabic character encoding issues.</p>
            <p><strong>IMPORTANT:</strong> Make a backup of your database before proceeding!</p>
            <ul>
                <li>This will change all tables to use UTF-8 character set</li>
                <li>It will attempt to fix double-encoded Arabic text</li>
                <li>The process is mostly reversible, but a backup is recommended</li>
            </ul>
        </div>
        
        <h3>Current Database Status</h3>
        <?php
        try {
            $dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $db = new PDO($dsn, $db_user, $db_pass, $options);
            
            echo "<div class='success'>✅ Database connection successful</div>";
            
            // Check if required tables exist
            $required_tables = ['admin', 'brands', 'categories', 'coupons', 'customers', 'orders', 'order_items', 'products', 'product_images', 'product_variations', 'visitors'];
            $stmt = $db->query("SHOW TABLES");
            $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $missing_tables = array_diff($required_tables, $existing_tables);
            if (!empty($missing_tables)) {
                echo "<div class='warning'>";
                echo "<h4>⚠️ Missing Tables</h4>";
                echo "<p>The following tables are expected but not found:</p>";
                echo "<ul><li>" . implode('</li><li>', $missing_tables) . "</li></ul>";
                echo "<p>The script will only process existing tables.</p>";
                echo "</div>";
            } else {
                echo "<div class='success'>✅ All required tables found</div>";
            }
            
            // Check current database charset
            $stmt = $db->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$db_name]);
            $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<div class='code'>";
            echo "Database Character Set: " . ($dbInfo['DEFAULT_CHARACTER_SET_NAME'] ?? 'Unknown') . "<br>";
            echo "Database Collation: " . ($dbInfo['DEFAULT_COLLATION_NAME'] ?? 'Unknown') . "<br>";
            echo "</div>";
            
            // Check for problematic Arabic text (only if products table exists)
            if (in_array('products', $existing_tables)) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND name_ar REGEXP '[Ã]'");
                $problemCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                echo "<div class='code'>";
                echo "Products with potentially corrupted Arabic text: " . $problemCount . "<br>";
                echo "</div>";
                
                // Show sample of problematic entries
                if ($problemCount > 0) {
                    echo "<h4>Sample of Corrupted Entries:</h4>";
                    $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND name_ar REGEXP '[Ã]' LIMIT 3");
                    echo "<div class='code'>";
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "ID: " . $row['id'] . "<br>";
                        echo "Arabic: " . htmlspecialchars($row['name_ar']) . "<br>";
                        echo "English: " . htmlspecialchars($row['name_en']) . "<br><br>";
                    }
                    echo "</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Database connection error: " . $e->getMessage() . "</div>";
            echo "<div class='info'>";
            echo "<h4>Troubleshooting:</h4>";
            echo "<ul>";
            echo "<li>Check if MySQL server is running</li>";
            echo "<li>Verify database credentials in <code>" . $config_file . "</code></li>";
            echo "<li>Ensure database '" . $db_name . "' exists</li>";
            echo "<li>Check if user '" . $db_user . "' has proper permissions</li>";
            echo "<li>Try connecting manually with these credentials</li>";
            echo "</ul>";
            echo "</div>";
        }
        ?>
        
        <h3>Run the Fix</h3>
        <p>Click the button below to start the fixing process:</p>
        <a href="?run=yes"><button class="danger">🔧 Run Arabic Encoding Fix</button></a>
        
    <?php else: ?>
        <!-- Run the actual fix -->
        <?php
        try {
            $dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $db = new PDO($dsn, $db_user, $db_pass, $options);
            
            echo "<div class='success'><h3>✅ Starting Fix Process</h3></div>";
            
            // Set proper character sets for session
            $db->exec("SET NAMES utf8mb4");
            $db->exec("SET CHARACTER_SET_CLIENT = utf8mb4");
            $db->exec("SET CHARACTER_SET_RESULTS = utf8mb4");
            $db->exec("SET COLLATION_CONNECTION = utf8mb4_unicode_ci");
            echo "<p>✓ Set session character sets</p>";
            
            // Change database collation
            $db->exec("ALTER DATABASE `" . $db_name . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "<p>✓ Updated database character set</p>";
            
            // Get existing tables
            $stmt = $db->query("SHOW TABLES");
            $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // List of tables to convert (only existing ones)
            $tables = ['admin', 'brands', 'categories', 'coupons', 'customers', 'orders', 'order_items', 'products', 'product_images', 'product_variations', 'visitors'];
            $tables_to_convert = array_intersect($tables, $existing_tables);
            
            foreach ($tables_to_convert as $table) {
                try {
                    $db->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    echo "<p>✓ Converted table: $table</p>";
                } catch (Exception $e) {
                    echo "<p>⚠️ Warning: Could not convert table $table - " . $e->getMessage() . "</p>";
                }
            }
            
            // Fix corrupted Arabic text in specific tables
            echo "<h4>Fixing Corrupted Arabic Text:</h4>";
            
            // Only process tables that exist and have Arabic columns
            if (in_array('brands', $existing_tables)) {
                $stmt = $db->exec("UPDATE brands SET name_ar = CONVERT(CONVERT(name_ar USING latin1) USING utf8mb4) WHERE name_ar IS NOT NULL AND name_ar REGEXP '[Ã]'");
                echo "<p>✓ Fixed brands table - $stmt rows updated</p>";
            }
            
            if (in_array('categories', $existing_tables)) {
                $stmt = $db->exec("UPDATE categories SET name_ar = CONVERT(CONVERT(name_ar USING latin1) USING utf8mb4) WHERE name_ar IS NOT NULL AND name_ar REGEXP '[Ã]'");
                echo "<p>✓ Fixed categories table - $stmt rows updated</p>";
            }
            
            if (in_array('products', $existing_tables)) {
                // Fix products name_ar
                $stmt = $db->exec("UPDATE products SET name_ar = CONVERT(CONVERT(name_ar USING latin1) USING utf8mb4) WHERE name_ar IS NOT NULL AND name_ar REGEXP '[Ã]'");
                echo "<p>✓ Fixed products name_ar - $stmt rows updated</p>";
                
                // Fix products description_ar
                $stmt = $db->exec("UPDATE products SET description_ar = CONVERT(CONVERT(description_ar USING latin1) USING utf8mb4) WHERE description_ar IS NOT NULL AND description_ar REGEXP '[Ã]'");
                echo "<p>✓ Fixed products description_ar - $stmt rows updated</p>";
                
                // Additional specific replacements
                $replacements = [
                    'ÃÂÃÂ·ÃÂÃ¢ÂÂÃÂÃ¢ÂÂ¦' => 'سوار',
                    'ÃÂÃÂ¹ÃÂÃÂ±ÃÂÃÂ¨ÃÂÃÂ' => 'عربي',
                    'ÃÂÃ¢ÂÂ¦ÃÂÃ¢ÂÂ' => 'من',
                    'ÃÂÃÂ³ÃÂÃÂÃÂÃÂ§ÃÂÃÂ±ÃÂÃÂÃÂÃÂÃÂÃÂ³ÃÂÃÂÃÂÃÂ' => 'سواروفسكي'
                ];
                
                foreach ($replacements as $find => $replace) {
                    $stmt = $db->prepare("UPDATE products SET name_ar = REPLACE(name_ar, ?, ?) WHERE name_ar LIKE ?");
                    $stmt->execute([$find, $replace, "%$find%"]);
                    echo "<p>✓ Applied specific replacement for: $find</p>";
                }
            }
            
            echo "<div class='success'><h3>✅ Fix Process Completed Successfully!</h3></div>";
            
            // Show final status
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND name_ar REGEXP '[Ã]'");
            $remainingProblems = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<h4>Final Status:</h4>";
            echo "<div class='code'>";
            echo "Remaining products with encoding issues: " . $remainingProblems . "<br>";
            echo "</div>";
            
            if ($remainingProblems > 0) {
                echo "<div class='warning'>Some entries may still need manual correction. Check the sample below:</div>";
                $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND name_ar REGEXP '[Ã]' LIMIT 5");
                echo "<div class='code'>";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "ID: " . $row['id'] . " - " . htmlspecialchars($row['name_ar']) . "<br>";
                }
                echo "</div>";
            } else {
                echo "<div class='success'>All Arabic text appears to be properly encoded now!</div>";
            }
            
            // Show sample of corrected entries
            echo "<h4>Sample of Corrected Entries:</h4>";
            $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND name_ar != '' LIMIT 5");
            echo "<div class='code'>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "ID: " . $row['id'] . "<br>";
                echo "Arabic: " . htmlspecialchars($row['name_ar']) . "<br>";
                echo "English: " . htmlspecialchars($row['name_en']) . "<br><br>";
            }
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'><h3>❌ Error during fix process:</h3>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "<p><strong>Error details:</strong> " . $e->getFile() . " on line " . $e->getLine() . "</p>";
            echo "<p><strong>Possible solutions:</strong></p>";
            echo "<ul>";
            echo "<li>Check database connection</li>";
            echo "<li>Ensure user has ALTER privileges</li>";
            echo "<li>Verify table structure matches expectations</li>";
            echo "<li>Check if database supports utf8mb4</li>";
            echo "</ul>";
            echo "</div>";
        }
        ?>
        
        <p><a href="?"><button>🔄 Check Status Again</button></a></p>
        
    <?php endif; ?>
    
    <hr>
    <p><small>Arabic Encoding Fix Tool for AleppoGift - <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>





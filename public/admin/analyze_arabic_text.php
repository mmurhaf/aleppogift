<?php
/**
 * Arabic Text Analysis Script
 * This script analyzes the current state of Arabic text in your database
 * without making any changes
 */

// Database configuration - MODIFY THESE VALUES OR THEY WILL BE AUTO-DETECTED
$db_host = 'localhost';
$db_name = 'aleppogift'; // Change to your actual database name
$db_user = 'root'; // Change to your actual username
$db_pass = ''; // Change to your actual password

// Try to auto-detect configuration from config files
$possible_config_files = [
    'config/config.php',
    'includes/config.php',
    'inc/config.php',
    'config/database.php',
    'db_config.php',
    'config_production.php'
];

$config_file = null;
foreach ($possible_config_files as $file) {
    if (file_exists($file)) {
        $config_file = $file;
        break;
    }
}

// If config file found, use it instead of manual values
if ($config_file) {
    require_once $config_file;
    
    // Override manual values with config file values if available
    if (defined('DB_HOST')) $db_host = DB_HOST;
    if (defined('DB_NAME')) $db_name = DB_NAME;
    if (defined('DB_USER')) $db_user = DB_USER;
    if (defined('DB_PASS')) $db_pass = DB_PASS;
}

// Set proper headers for UTF-8
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arabic Text Analysis</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .code { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; margin: 10px 0; border-radius: 3px; font-family: monospace; white-space: pre-wrap; }
        .arabic { font-family: Arial, 'Times New Roman', serif; font-size: 16px; direction: rtl; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        .corrupted { background-color: #ffe6e6; }
        .good { background-color: #e6ffe6; }
        .suspicious { background-color: #fff3cd; }
    </style>
</head>
<body>
    <h1>Arabic Text Analysis</h1>
    
    <div class="info">
        <h4>📋 About This Analysis</h4>
        <p>This script analyzes Arabic text in your database to identify:</p>
        <ul>
            <li>🔴 Corrupted text (contains Ã, Â, or other encoding markers)</li>
            <li>🟡 Suspicious text (might need attention)</li>
            <li>🟢 Good text (appears to be properly encoded)</li>
        </ul>
        <p><strong>No changes will be made to your database.</strong></p>
    </div>
    
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
        
        // Check database charset
        $stmt = $db->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$db_name]);
        $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='info'>";
        echo "<h4>Database Configuration</h4>";
        echo "<div class='code'>";
        echo "Database Character Set: " . ($dbInfo['DEFAULT_CHARACTER_SET_NAME'] ?? 'Unknown') . "\n";
        echo "Database Collation: " . ($dbInfo['DEFAULT_COLLATION_NAME'] ?? 'Unknown') . "\n";
        echo "</div>";
        echo "</div>";
        
        // Function to classify text
        function classifyText($text) {
            if (empty($text)) return 'empty';
            if (preg_match('/[ÃÂ]/', $text)) return 'corrupted';
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) return 'good';
            if (preg_match('/[a-zA-Z]/', $text)) return 'english';
            return 'suspicious';
        }
        
        // Analyze products table
        echo "<h3>Products Table Analysis</h3>";
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE name_ar IS NOT NULL AND name_ar != ''");
        $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND name_ar REGEXP '[ÃÂ]'");
        $corrupted_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND name_ar REGEXP '[\u{0600}-\u{06FF}]'");
        $arabic_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "<div class='code'>";
        echo "Total products with Arabic names: $total_products\n";
        echo "Products with corrupted text: $corrupted_count\n";
        echo "Products with Arabic characters: $arabic_count\n";
        echo "Products possibly good: " . ($total_products - $corrupted_count) . "\n";
        echo "</div>";
        
        // Show detailed analysis
        echo "<h4>Detailed Analysis of First 20 Products:</h4>";
        $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND name_ar != '' LIMIT 20");
        echo "<table>";
        echo "<tr><th>ID</th><th>Status</th><th>Arabic Name</th><th>English Name</th></tr>";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classification = classifyText($row['name_ar']);
            $class = '';
            $status = '';
            
            switch ($classification) {
                case 'corrupted':
                    $class = 'corrupted';
                    $status = '🔴 Corrupted';
                    break;
                case 'good':
                    $class = 'good';
                    $status = '🟢 Good Arabic';
                    break;
                case 'english':
                    $class = 'suspicious';
                    $status = '🟡 English only';
                    break;
                case 'empty':
                    $class = 'suspicious';
                    $status = '⚪ Empty';
                    break;
                default:
                    $class = 'suspicious';
                    $status = '🟡 Suspicious';
            }
            
            echo "<tr class='$class'>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $status . "</td>";
            echo "<td>";
            if ($classification === 'good') {
                echo "<span class='arabic'>" . htmlspecialchars($row['name_ar']) . "</span>";
            } else {
                echo htmlspecialchars($row['name_ar']);
            }
            echo "</td>";
            echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show examples of corrupted text
        if ($corrupted_count > 0) {
            echo "<h4>Examples of Corrupted Text:</h4>";
            $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND name_ar REGEXP '[ÃÂ]' LIMIT 10");
            echo "<table>";
            echo "<tr><th>ID</th><th>Corrupted Arabic</th><th>English</th><th>Corruption Pattern</th></tr>";
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr class='corrupted'>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['name_ar']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
                
                // Identify corruption patterns
                $patterns = [];
                if (strpos($row['name_ar'], 'Ã') !== false) $patterns[] = 'Ã character';
                if (strpos($row['name_ar'], 'Â') !== false) $patterns[] = 'Â character';
                if (strpos($row['name_ar'], 'ÃÂ') !== false) $patterns[] = 'ÃÂ sequence';
                if (strpos($row['name_ar'], 'Ã¢ÂÂ') !== false) $patterns[] = 'Ã¢ÂÂ sequence';
                
                echo "<td>" . implode(', ', $patterns) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Check other tables
        $tables_to_check = ['brands', 'categories'];
        foreach ($tables_to_check as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<h4>" . ucfirst($table) . " Table:</h4>";
                
                try {
                    $stmt = $db->query("SELECT COUNT(*) as total FROM $table WHERE name_ar IS NOT NULL AND name_ar != ''");
                    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    $stmt = $db->query("SELECT COUNT(*) as count FROM $table WHERE name_ar IS NOT NULL AND name_ar REGEXP '[ÃÂ]'");
                    $corrupted = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    echo "<div class='code'>";
                    echo "Total $table with Arabic names: $total\n";
                    echo "Corrupted $table: $corrupted\n";
                    echo "</div>";
                    
                    if ($corrupted > 0) {
                        $stmt = $db->query("SELECT id, name_ar, name_en FROM $table WHERE name_ar IS NOT NULL AND name_ar REGEXP '[ÃÂ]' LIMIT 5");
                        echo "<table>";
                        echo "<tr><th>ID</th><th>Corrupted Arabic</th><th>English</th></tr>";
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr class='corrupted'>";
                            echo "<td>" . $row['id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['name_ar']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['name_en'] ?? 'N/A') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                } catch (Exception $e) {
                    echo "<div class='warning'>Could not analyze $table table: " . $e->getMessage() . "</div>";
                }
            }
        }
        
        // Summary and recommendations
        echo "<h3>Summary and Recommendations</h3>";
        
        if ($corrupted_count > 0) {
            echo "<div class='warning'>";
            echo "<h4>⚠️ Action Required</h4>";
            echo "<p>Found $corrupted_count products with corrupted Arabic text.</p>";
            echo "<p><strong>Recommendations:</strong></p>";
            echo "<ul>";
            echo "<li>Use the <code>restore_arabic_manual.php</code> script to attempt automatic restoration</li>";
            echo "<li>Make a database backup before running any fix scripts</li>";
            echo "<li>Review the results and manually correct any remaining issues</li>";
            echo "<li>Consider re-entering Arabic text for severely corrupted entries</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "<h4>✅ Good News!</h4>";
            echo "<p>No corrupted Arabic text detected in the analyzed tables.</p>";
            echo "<p>Your Arabic encoding appears to be working correctly.</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Database connection error: " . $e->getMessage() . "</div>";
        echo "<div class='warning'>";
        echo "<h4>Please check:</h4>";
        echo "<ul>";
        echo "<li>Database credentials in this script are correct</li>";
        echo "<li>Database server is running</li>";
        echo "<li>Database name exists</li>";
        echo "<li>User has proper permissions</li>";
        echo "</ul>";
        echo "</div>";
    }
    ?>
    
    <hr>
    <p><small>Arabic Text Analysis Tool - <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>





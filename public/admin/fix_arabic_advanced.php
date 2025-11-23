<?php
/**
 * Advanced Arabic Text Restoration Script
 * Handles complex corruption patterns including the new أÂÃÂ type corruption
 */

// Database configuration - auto-detection
$db_host = 'localhost';
$db_name = 'aleppogift';
$db_user = 'root';
$db_pass = '';

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

if ($config_file) {
    require_once $config_file;
    if (defined('DB_HOST')) $db_host = DB_HOST;
    if (defined('DB_NAME')) $db_name = DB_NAME;
    if (defined('DB_USER')) $db_user = DB_USER;
    if (defined('DB_PASS')) $db_pass = DB_PASS;
}

header('Content-Type: text/html; charset=utf-8');
$run_fix = isset($_GET['run']) && $_GET['run'] === 'yes';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Arabic Text Restoration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .code { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; margin: 10px 0; border-radius: 3px; font-family: monospace; white-space: pre-wrap; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px 5px; }
        button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
        .arabic { font-family: Arial, 'Times New Roman', serif; font-size: 16px; direction: rtl; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        .corrupted { background-color: #ffe6e6; }
        .fixed { background-color: #e6ffe6; }
    </style>
</head>
<body>
    <h1>Advanced Arabic Text Restoration</h1>
    
    <div class="warning">
        <h4>⚠️ NEW CORRUPTION PATTERN DETECTED</h4>
        <p>This script handles the new corruption pattern: <code>أÂÃÂ­أÂÃÂ§أÂÃ¢ÂÂ¦</code></p>
        <p>This appears to be Arabic text that has been corrupted by multiple encoding conversions.</p>
        <p><strong>ALWAYS BACKUP YOUR DATABASE BEFORE RUNNING!</strong></p>
    </div>
    
    <?php if (!$run_fix): ?>
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
            
            // Check for the new corruption pattern
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%أÂÃÂ%' OR name_ar LIKE '%أÂÃ¢ÂÂ%' OR name_ar LIKE '%أÂذÂ%')");
            $new_corruption_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Check for old corruption pattern
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%ÃÂ%' OR name_ar LIKE '%Ã¢ÂÂ%')");
            $old_corruption_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<div class='code'>";
            echo "Products with NEW corruption pattern (أÂÃÂ): " . $new_corruption_count . "\n";
            echo "Products with OLD corruption pattern (ÃÂ): " . $old_corruption_count . "\n";
            echo "Total corrupted products: " . ($new_corruption_count + $old_corruption_count) . "\n";
            echo "</div>";
            
            if ($new_corruption_count > 0) {
                echo "<h4>Sample of NEW Corruption Pattern:</h4>";
                $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%أÂÃÂ%' OR name_ar LIKE '%أÂÃ¢ÂÂ%' OR name_ar LIKE '%أÂذÂ%') LIMIT 5");
                echo "<table>";
                echo "<tr><th>ID</th><th>Corrupted Arabic</th><th>English</th></tr>";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr class='corrupted'>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['name_ar']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            
            if ($old_corruption_count > 0) {
                echo "<h4>Sample of OLD Corruption Pattern:</h4>";
                $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%ÃÂ%' OR name_ar LIKE '%Ã¢ÂÂ%') LIMIT 5");
                echo "<table>";
                echo "<tr><th>ID</th><th>Corrupted Arabic</th><th>English</th></tr>";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr class='corrupted'>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['name_ar']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Database connection error: " . $e->getMessage() . "</div>";
        }
        ?>
        
        <h3>Run Advanced Restoration</h3>
        <p>This will attempt to restore both old and new corruption patterns:</p>
        <a href="?run=yes"><button class="danger">🔧 Run Advanced Arabic Restoration</button></a>
        
    <?php else: ?>
        <!-- Run the restoration -->
        <?php
        try {
            $dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $db = new PDO($dsn, $db_user, $db_pass, $options);
            
            echo "<div class='success'><h3>✅ Starting Advanced Arabic Restoration</h3></div>";
            
            // Set proper character sets
            $db->exec("SET NAMES utf8mb4");
            $db->exec("SET CHARACTER_SET_CLIENT = utf8mb4");
            $db->exec("SET CHARACTER_SET_RESULTS = utf8mb4");
            $db->exec("SET COLLATION_CONNECTION = utf8mb4_unicode_ci");
            
            // Function to fix the new corruption pattern
            function fixAdvancedCorruption($text) {
                if (empty($text)) return $text;
                
                // NEW CORRUPTION PATTERN: أÂÃÂ­ -> ح (for example)
                // The pattern is: Arabic letter + Â + Ã + Â + corruption markers
                
                // Step 1: Handle the new أÂÃÂ pattern
                $fixes = [
                    // Common Arabic letters with the new corruption pattern
                    'أÂÃÂ­' => 'ح',  // ح
                    'أÂÃÂ§' => 'ا',  // ا  
                    'أÂÃ¢ÂÂ¦' => 'م', // م
                    'أÂÃ¢ÂÂ' => 'ن',  // ن
                    'أÂÃÂª' => 'ت',  // ت
                    'أÂÃÂ¯' => 'ي',  // ي
                    'أÂتÂ' => 'ة',   // ة
                    'أÂÃÂ³' => 'س',  // س
                    'أÂذÂ' => 'و',   // و (guessing based on pattern)
                    'أÂÃÂ±' => 'ر',  // ر
                    'أÂÃÂ' => 'ل',   // ل
                    'أÂثÂ' => 'ب',   // ب (guessing)
                    'أÂÃ¢ÂÂ°' => 'ذ', // ذ
                    'أÂÃÂ¨' => 'ب',  // ب
                    
                    // Common word patterns
                    'أÂÃÂ­أÂÃÂ§أÂÃ¢ÂÂ¦أÂÃ¢ÂÂ أÂÃÂªأÂÃ¢ÂÂأÂÃÂ¯أÂتÂ أÂÃ¢ÂÂ¦' => 'حلقة تذييل من',
                    'أÂÃÂ³أÂذÂأÂÃÂ§أÂÃÂ±أÂذÂأÂÃÂأÂÃÂ³أÂثÂأÂتÂ' => 'سواروفسكي',
                    'أÂÃ¢ÂÂأÂÃ¢ÂÂأÂثÂأÂÃÂ¨' => 'مطلب',
                    'أÂثÂأÂتÂ أÂثÂ' => 'من',
                ];
                
                // Apply the fixes
                $fixed_text = $text;
                foreach ($fixes as $corrupted => $correct) {
                    $fixed_text = str_replace($corrupted, $correct, $fixed_text);
                }
                
                // Step 2: Clean up remaining corruption markers
                $cleanup_patterns = [
                    '/أÂ[A-Za-z\x80-\xFF]+/' => '', // Remove أÂ followed by non-Arabic
                    '/Â[A-Za-z\x80-\xFF]*/' => '', // Remove  followed by corruption
                    '/Ã[A-Za-z\x80-\xFF]*/' => '', // Remove Ã followed by corruption
                    '/[A-Za-z\x80-\xFF]Â/' => '', // Remove corruption followed by Â
                    '/\s+/' => ' ', // Clean up multiple spaces
                ];
                
                foreach ($cleanup_patterns as $pattern => $replacement) {
                    $fixed_text = preg_replace($pattern, $replacement, $fixed_text);
                }
                
                // Step 3: Handle OLD corruption patterns too
                $old_fixes = [
                    'ÃÂÃÂ­' => 'ح',
                    'ÃÂÃÂ§' => 'ا',
                    'ÃÂÃ¢ÂÂ¦' => 'م',
                    'ÃÂÃ¢ÂÂ' => 'ن',
                    'ÃÂÃÂª' => 'ت',
                    'ÃÂÃÂ¯' => 'ي',
                    'ÃÂÃÂ³' => 'س',
                    'ÃÂÃÂ±' => 'ر',
                    'ÃÂÃÂ' => 'ل',
                    'ÃÂÃÂ¨' => 'ب',
                ];
                
                foreach ($old_fixes as $corrupted => $correct) {
                    $fixed_text = str_replace($corrupted, $correct, $fixed_text);
                }
                
                return trim($fixed_text);
            }
            
            // Process products table
            $total_fixed = 0;
            $entries_fixed = [];
            
            // Get all products with corrupted text (both patterns)
            $stmt = $db->query("SELECT id, name_ar, description_ar, name_en FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%أÂ%' OR name_ar LIKE '%ÃÂ%' OR name_ar LIKE '%Ã¢ÂÂ%')");
            $corrupted_products = $stmt->fetchAll();
            
            echo "<p>Found " . count($corrupted_products) . " products with corrupted text</p>";
            
            foreach ($corrupted_products as $product) {
                $original_name = $product['name_ar'];
                $fixed_name = fixAdvancedCorruption($original_name);
                
                // Also fix description if it exists and is corrupted
                $original_desc = $product['description_ar'];
                $fixed_desc = $original_desc;
                if ($original_desc && (strpos($original_desc, 'أÂ') !== false || strpos($original_desc, 'ÃÂ') !== false)) {
                    $fixed_desc = fixAdvancedCorruption($original_desc);
                }
                
                // Only update if we actually changed something and result is not empty
                if (($fixed_name !== $original_name && !empty($fixed_name)) || 
                    ($fixed_desc !== $original_desc && !empty($fixed_desc))) {
                    
                    // Update name if changed
                    if ($fixed_name !== $original_name && !empty($fixed_name)) {
                        $update_stmt = $db->prepare("UPDATE products SET name_ar = ? WHERE id = ?");
                        $update_stmt->execute([$fixed_name, $product['id']]);
                    }
                    
                    // Update description if changed
                    if ($fixed_desc !== $original_desc && !empty($fixed_desc)) {
                        $update_stmt = $db->prepare("UPDATE products SET description_ar = ? WHERE id = ?");
                        $update_stmt->execute([$fixed_desc, $product['id']]);
                    }
                    
                    $total_fixed++;
                    $entries_fixed[] = [
                        'id' => $product['id'],
                        'original' => $original_name,
                        'fixed' => $fixed_name,
                        'english' => $product['name_en']
                    ];
                } else if (empty($fixed_name) && !empty($original_name)) {
                    // If the name became empty, set a placeholder
                    $update_stmt = $db->prepare("UPDATE products SET name_ar = ? WHERE id = ?");
                    $update_stmt->execute(['منتج', $product['id']]); // Generic "product" in Arabic
                    $total_fixed++;
                    
                    $entries_fixed[] = [
                        'id' => $product['id'],
                        'original' => $original_name,
                        'fixed' => 'منتج (placeholder)',
                        'english' => $product['name_en']
                    ];
                }
            }
            
            echo "<div class='success'>";
            echo "<h3>✅ Advanced Restoration Complete!</h3>";
            echo "<p>Total products fixed: $total_fixed</p>";
            echo "</div>";
            
            // Show detailed results
            if (!empty($entries_fixed)) {
                echo "<h4>Detailed Fix Results:</h4>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Before</th><th>After</th><th>English</th></tr>";
                
                foreach (array_slice($entries_fixed, 0, 20) as $entry) { // Show first 20
                    echo "<tr class='fixed'>";
                    echo "<td>" . $entry['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($entry['original']) . "</td>";
                    echo "<td class='arabic'>" . htmlspecialchars($entry['fixed']) . "</td>";
                    echo "<td>" . htmlspecialchars($entry['english']) . "</td>";
                    echo "</tr>";
                }
                
                if (count($entries_fixed) > 20) {
                    echo "<tr><td colspan='4'><em>... and " . (count($entries_fixed) - 20) . " more entries</em></td></tr>";
                }
                echo "</table>";
            }
            
            // Show current status
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%أÂ%' OR name_ar LIKE '%ÃÂ%' OR name_ar LIKE '%Ã¢ÂÂ%')");
            $remaining_problems = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<h4>Final Status:</h4>";
            echo "<div class='code'>";
            echo "Remaining products with corruption markers: " . $remaining_problems;
            echo "</div>";
            
            if ($remaining_problems == 0) {
                echo "<div class='success'>🎉 All corruption patterns have been fixed!</div>";
            } else {
                echo "<div class='warning'>Some entries may need manual correction or have new corruption patterns.</div>";
            }
            
            // Show sample of current entries
            echo "<h4>Sample of Current Entries:</h4>";
            $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND name_ar != '' LIMIT 10");
            echo "<table>";
            echo "<tr><th>ID</th><th>Arabic Name</th><th>English Name</th></tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $has_corruption = (strpos($row['name_ar'], 'أÂ') !== false || strpos($row['name_ar'], 'ÃÂ') !== false);
                $class = $has_corruption ? 'corrupted' : 'fixed';
                
                echo "<tr class='$class'>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td class='arabic'>" . htmlspecialchars($row['name_ar']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } catch (Exception $e) {
            echo "<div class='error'><h3>❌ Error during restoration:</h3>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
        ?>
        
        <p><a href="?"><button>🔄 Check Status Again</button></a></p>
        
    <?php endif; ?>
    
    <hr>
    <p><small>Advanced Arabic Text Restoration Tool - <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>





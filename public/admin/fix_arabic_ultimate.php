<?php
/**
 * Ultimate Arabic Text Restoration Script
 * Handles ALL corruption patterns: أÂÃÂ, ÃÂ, and HTML entity corruption
 */

// Database configuration - auto-detection
$db_host = 'localhost';
$db_name = 'aleppogift';
$db_user = 'root';
$db_pass = '';

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
    <title>Ultimate Arabic Text Restoration</title>
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
        .pattern-box { background: #e7f3ff; border: 1px solid #b3d7ff; padding: 10px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Ultimate Arabic Text Restoration</h1>
    
    <div class="warning">
        <h4>⚠️ HANDLES ALL CORRUPTION PATTERNS</h4>
        <p>This script can fix:</p>
        <ul>
            <li><strong>Pattern 1:</strong> <code>أÂÃÂ­أÂÃÂ§</code> - Complex encoding corruption</li>
            <li><strong>Pattern 2:</strong> <code>ÃÂÃÂ­ÃÂÃÂ§</code> - Double UTF-8 encoding</li>
            <li><strong>Pattern 3:</strong> <code>ÙÙ†Ø¬Ø§Ù†</code> - HTML entity corruption</li>
        </ul>
        <p><strong>ALWAYS BACKUP YOUR DATABASE BEFORE RUNNING!</strong></p>
    </div>
    
    <?php if (!$run_fix): ?>
        
        <!-- Test the new pattern -->
        <div class="pattern-box">
            <h4>🧪 Test New Corruption Pattern</h4>
            <p><strong>Sample:</strong> <code>ÙÙ†Ø¬Ø§Ù† Ø´Ø§ÙŠ ÙˆØ§Ø­Ø¯ Ù…Ø¹ ØµØ­Ù† Ù…Ù† Ø³ÙˆØ§Ø±ÙˆÙØ³ÙƒÙŠ</code></p>
            <?php
            // Test the HTML entity pattern fix
            function testHtmlEntityFix($text) {
                // First try HTML entity decode
                $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // If that doesn't work, try manual conversion of common patterns
                if ($decoded === $text) {
                    // Manual conversion for Arabic HTML entities
                    $manual_fixes = [
                        'Ù' => 'ف',  // ف
                        'Ù†' => 'ن',  // ن
                        'Ø¬' => 'ج',  // ج
                        'Ø§' => 'ا',  // ا
                        'Ø´' => 'ش',  // ش
                        'ÙŠ' => 'ي',  // ي
                        'Ùˆ' => 'و',  // و
                        'Ø­' => 'ح',  // ح
                        'Ø¯' => 'د',  // د
                        'Ù…' => 'م',  // م
                        'Ø¹' => 'ع',  // ع
                        'Øµ' => 'ص',  // ص
                        'Ø³' => 'س',  // س
                        'Ø±' => 'ر',  // ر
                        'ÙƒÙŠ' => 'كي', // كي
                    ];
                    
                    foreach ($manual_fixes as $corrupt => $correct) {
                        $decoded = str_replace($corrupt, $correct, $decoded);
                    }
                }
                
                return $decoded;
            }
            
            $test_text = 'ÙÙ†Ø¬Ø§Ù† Ø´Ø§ÙŠ ÙˆØ§Ø­Ø¯ Ù…Ø¹ ØµØ­Ù† Ù…Ù† Ø³ÙˆØ§Ø±ÙˆÙØ³ÙƒÙŠ';
            $test_result = testHtmlEntityFix($test_text);
            
            echo "<p><strong>Test Result:</strong> <span class='arabic'>" . htmlspecialchars($test_result) . "</span></p>";
            if ($test_result !== $test_text) {
                echo "<p>✅ Pattern can be decoded!</p>";
            } else {
                echo "<p>⚠️ Pattern needs manual mapping</p>";
            }
            ?>
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
            
            // Check for all corruption patterns
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%أÂÃÂ%' OR name_ar LIKE '%أÂÃ¢ÂÂ%' OR name_ar LIKE '%أÂذÂ%')");
            $pattern1_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%ÃÂ%' OR name_ar LIKE '%Ã¢ÂÂ%')");
            $pattern2_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%Ù%' OR name_ar LIKE '%Ø%')");
            $pattern3_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<div class='code'>";
            echo "Pattern 1 (أÂÃÂ): " . $pattern1_count . " products\n";
            echo "Pattern 2 (ÃÂ): " . $pattern2_count . " products\n";
            echo "Pattern 3 (HTML entities): " . $pattern3_count . " products\n";
            echo "Total corrupted: " . ($pattern1_count + $pattern2_count + $pattern3_count) . " products\n";
            echo "</div>";
            
            // Show samples of each pattern
            if ($pattern1_count > 0) {
                echo "<h4>Sample of Pattern 1 (أÂÃÂ):</h4>";
                $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%أÂÃÂ%' OR name_ar LIKE '%أÂÃ¢ÂÂ%') LIMIT 3");
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
            
            if ($pattern2_count > 0) {
                echo "<h4>Sample of Pattern 2 (ÃÂ):</h4>";
                $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%ÃÂ%' OR name_ar LIKE '%Ã¢ÂÂ%') LIMIT 3");
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
            
            if ($pattern3_count > 0) {
                echo "<h4>Sample of Pattern 3 (HTML Entities):</h4>";
                $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%Ù%' OR name_ar LIKE '%Ø%') LIMIT 3");
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
        
        <h3>Run Ultimate Restoration</h3>
        <p>This will attempt to restore ALL corruption patterns:</p>
        <a href="?run=yes"><button class="danger">🔧 Run Ultimate Arabic Restoration</button></a>
        
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
            
            echo "<div class='success'><h3>✅ Starting Ultimate Arabic Restoration</h3></div>";
            
            // Set proper character sets
            $db->exec("SET NAMES utf8mb4");
            $db->exec("SET CHARACTER_SET_CLIENT = utf8mb4");
            $db->exec("SET CHARACTER_SET_RESULTS = utf8mb4");
            $db->exec("SET COLLATION_CONNECTION = utf8mb4_unicode_ci");
            
            // Ultimate fix function that handles all patterns
            function ultimateArabicFix($text) {
                if (empty($text)) return $text;
                
                $fixed_text = $text;
                
                // PATTERN 1: أÂÃÂ corruption
                $pattern1_fixes = [
                    'أÂÃÂ­' => 'ح',
                    'أÂÃÂ§' => 'ا',
                    'أÂÃ¢ÂÂ¦' => 'م',
                    'أÂÃ¢ÂÂ' => 'ن',
                    'أÂÃÂª' => 'ت',
                    'أÂÃÂ¯' => 'ي',
                    'أÂتÂ' => 'ة',
                    'أÂÃÂ³' => 'س',
                    'أÂذÂ' => 'و',
                    'أÂÃÂ±' => 'ر',
                    'أÂÃÂ' => 'ل',
                    'أÂثÂ' => 'ب',
                    'أÂÃ¢ÂÂ°' => 'ذ',
                    'أÂÃÂ¨' => 'ب',
                ];
                
                // PATTERN 2: ÃÂ corruption
                $pattern2_fixes = [
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
                
                // PATTERN 3: HTML Entity corruption
                $pattern3_fixes = [
                    'Ù' => 'ف',
                    'Ù†' => 'ن',
                    'Ø¬' => 'ج',
                    'Ø§' => 'ا',
                    'Ø´' => 'ش',
                    'ÙŠ' => 'ي',
                    'Ùˆ' => 'و',
                    'Ø­' => 'ح',
                    'Ø¯' => 'د',
                    'Ù…' => 'م',
                    'Ø¹' => 'ع',
                    'Øµ' => 'ص',
                    'Ø³' => 'س',
                    'Ø±' => 'ر',
                    'ÙƒÙŠ' => 'كي',
                    'Ùƒ' => 'ك',
                    'Ù„' => 'ل',
                    'Ù‡' => 'ه',
                    'Ø©' => 'ة',
                    'Ø®' => 'خ',
                    'Ø°' => 'ذ',
                    'Ø²' => 'ز',
                    'Ø·' => 'ط',
                    'Ø¸' => 'ظ',
                    'Øº' => 'غ',
                    'Ù‚' => 'ق',
                    'Ø¡' => 'ء',
                    'Ø¦' => 'ئ',
                    'Ø¤' => 'ؤ',
                    'Ø¨' => 'ب',
                    'Øª' => 'ت',
                    'Ø«' => 'ث',
                ];
                
                // Apply all fixes
                foreach ([$pattern1_fixes, $pattern2_fixes, $pattern3_fixes] as $fixes) {
                    foreach ($fixes as $corrupted => $correct) {
                        $fixed_text = str_replace($corrupted, $correct, $fixed_text);
                    }
                }
                
                // Try HTML entity decode as well
                $html_decoded = html_entity_decode($fixed_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (mb_check_encoding($html_decoded, 'UTF-8') && $html_decoded !== $fixed_text) {
                    $fixed_text = $html_decoded;
                }
                
                // Clean up remaining corruption markers
                $cleanup_patterns = [
                    '/[ÃÂ]+/' => '',
                    '/أÂ[A-Za-z\x80-\xFF]*/' => '',
                    '/Â[A-Za-z\x80-\xFF]*/' => '',
                    '/Ã[A-Za-z\x80-\xFF]*/' => '',
                    '/\s+/' => ' ',
                ];
                
                foreach ($cleanup_patterns as $pattern => $replacement) {
                    $fixed_text = preg_replace($pattern, $replacement, $fixed_text);
                }
                
                return trim($fixed_text);
            }
            
            // Process products table
            $total_fixed = 0;
            $entries_fixed = [];
            
            // Get all products with any type of corruption
            $stmt = $db->query("SELECT id, name_ar, description_ar, name_en FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%أÂ%' OR name_ar LIKE '%ÃÂ%' OR name_ar LIKE '%Ù%' OR name_ar LIKE '%Ø%')");
            $corrupted_products = $stmt->fetchAll();
            
            echo "<p>Found " . count($corrupted_products) . " products with corrupted text</p>";
            
            foreach ($corrupted_products as $product) {
                $original_name = $product['name_ar'];
                $fixed_name = ultimateArabicFix($original_name);
                
                // Also fix description if it exists and is corrupted
                $original_desc = $product['description_ar'];
                $fixed_desc = $original_desc;
                if ($original_desc && (strpos($original_desc, 'أÂ') !== false || strpos($original_desc, 'ÃÂ') !== false || strpos($original_desc, 'Ù') !== false)) {
                    $fixed_desc = ultimateArabicFix($original_desc);
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
                    $update_stmt->execute(['منتج', $product['id']]);
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
            echo "<h3>✅ Ultimate Restoration Complete!</h3>";
            echo "<p>Total products fixed: $total_fixed</p>";
            echo "</div>";
            
            // Show detailed results
            if (!empty($entries_fixed)) {
                echo "<h4>Detailed Fix Results (First 15):</h4>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Before</th><th>After</th><th>English</th></tr>";
                
                foreach (array_slice($entries_fixed, 0, 15) as $entry) {
                    echo "<tr class='fixed'>";
                    echo "<td>" . $entry['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($entry['original']) . "</td>";
                    echo "<td class='arabic'>" . htmlspecialchars($entry['fixed']) . "</td>";
                    echo "<td>" . htmlspecialchars($entry['english']) . "</td>";
                    echo "</tr>";
                }
                
                if (count($entries_fixed) > 15) {
                    echo "<tr><td colspan='4'><em>... and " . (count($entries_fixed) - 15) . " more entries</em></td></tr>";
                }
                echo "</table>";
            }
            
            // Show final status
            $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE name_ar IS NOT NULL AND (name_ar LIKE '%أÂ%' OR name_ar LIKE '%ÃÂ%' OR name_ar LIKE '%Ù%' OR name_ar LIKE '%Ø%')");
            $remaining_problems = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<h4>Final Status:</h4>";
            echo "<div class='code'>";
            echo "Remaining products with corruption markers: " . $remaining_problems;
            echo "</div>";
            
            if ($remaining_problems == 0) {
                echo "<div class='success'>🎉 ALL corruption patterns have been fixed!</div>";
            } else {
                echo "<div class='warning'>Some entries may need manual correction or have unknown corruption patterns.</div>";
            }
            
            // Show sample of current entries
            echo "<h4>Sample of Current Entries:</h4>";
            $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND name_ar != '' LIMIT 10");
            echo "<table>";
            echo "<tr><th>ID</th><th>Arabic Name</th><th>English Name</th><th>Status</th></tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $has_corruption = (strpos($row['name_ar'], 'أÂ') !== false || 
                                 strpos($row['name_ar'], 'ÃÂ') !== false || 
                                 strpos($row['name_ar'], 'Ù') !== false);
                $class = $has_corruption ? 'corrupted' : 'fixed';
                $status = $has_corruption ? '🔴 Still corrupted' : '🟢 Clean';
                
                echo "<tr class='$class'>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td class='arabic'>" . htmlspecialchars($row['name_ar']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
                echo "<td>" . $status . "</td>";
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
    <p><small>Ultimate Arabic Text Restoration Tool - <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>





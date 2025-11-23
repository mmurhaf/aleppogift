<?php
/**
 * Arabic Corruption Pattern Analyzer
 * Analyzes the specific corruption patterns in your Arabic text
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arabic Corruption Pattern Analyzer</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .code { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; margin: 10px 0; border-radius: 3px; font-family: monospace; white-space: pre-wrap; }
        .pattern { background: #fff3cd; padding: 10px; margin: 5px 0; border-radius: 3px; border-left: 4px solid #ffc107; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        .hex { font-family: monospace; font-size: 12px; background: #f8f9fa; padding: 2px; }
    </style>
</head>
<body>
    <h1>Arabic Corruption Pattern Analyzer</h1>
    
    <div class="info">
        <h4>📋 Pattern Analysis</h4>
        <p>This tool analyzes the exact corruption patterns in your Arabic text to help create better restoration rules.</p>
        <p>Based on your sample: <code>أÂÃÂ­أÂÃÂ§أÂÃ¢ÂÂ¦أÂÃ¢ÂÂ أÂÃÂªأÂÃ¢ÂÂأÂÃÂ¯أÂتÂ أÂÃ¢ÂÂ¦</code></p>
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
        
        // Analyze your specific sample
        $sample_text = 'أÂÃÂ­أÂÃÂ§أÂÃ¢ÂÂ¦أÂÃ¢ÂÂ أÂÃÂªأÂÃ¢ÂÂأÂÃÂ¯أÂتÂ أÂÃ¢ÂÂ¦ أÂÃÂ­أÂÃ¢ÂÂأÂذÂأÂÃ¢ÂÂ° أÂÃÂ³أÂذÂأÂÃÂ§أÂÃÂ±أÂذÂأÂÃÂأÂÃÂ³أÂثÂأÂتÂ  أÂÃ¢ÂÂأÂÃ¢ÂÂأÂثÂأÂÃÂ¨ أÂثÂأÂتÂ أÂثÂ';
        
        echo "<h3>Analysis of Your Sample Text</h3>";
        echo "<div class='code'>";
        echo "Sample: " . htmlspecialchars($sample_text) . "\n";
        echo "Length: " . strlen($sample_text) . " bytes\n";
        echo "Characters: " . mb_strlen($sample_text, 'UTF-8') . " Unicode characters\n";
        echo "</div>";
        
        // Break down into patterns
        echo "<h4>Detected Patterns:</h4>";
        
        // Function to analyze patterns
        function analyzePatterns($text) {
            $patterns = [];
            
            // Look for أÂ followed by various combinations
            if (preg_match_all('/أÂ[^أ\s]*/', $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $patterns[] = [
                        'pattern' => $match,
                        'type' => 'أÂ sequence',
                        'hex' => bin2hex($match),
                        'length' => strlen($match)
                    ];
                }
            }
            
            return array_unique($patterns, SORT_REGULAR);
        }
        
        $detected_patterns = analyzePatterns($sample_text);
        
        echo "<table>";
        echo "<tr><th>Pattern</th><th>Type</th><th>Hex</th><th>Likely Arabic</th></tr>";
        
        // Manual mapping based on common Arabic letters
        $likely_mappings = [
            'أÂÃÂ­' => 'ح',
            'أÂÃÂ§' => 'ا',
            'أÂÃ¢ÂÂ¦' => 'م',
            'أÂÃ¢ÂÂ' => 'ن',
            'أÂÃÂª' => 'ت',
            'أÂÃÂ¯' => 'ي',
            'أÂتÂ' => 'ة',
            'أÂذÂ' => 'و',
            'أÂÃ¢ÂÂ°' => 'ذ',
            'أÂÃÂ³' => 'س',
            'أÂÃÂ±' => 'ر',
            'أÂÃÂ' => 'ل',
            'أÂثÂ' => 'ب',
            'أÂÃÂ¨' => 'ب'
        ];
        
        foreach ($detected_patterns as $pattern) {
            $likely = isset($likely_mappings[$pattern['pattern']]) ? $likely_mappings[$pattern['pattern']] : '?';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($pattern['pattern']) . "</td>";
            echo "<td>" . $pattern['type'] . "</td>";
            echo "<td class='hex'>" . $pattern['hex'] . "</td>";
            echo "<td style='font-size:18px;'>" . $likely . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Try to reconstruct the original text
        echo "<h4>Attempted Reconstruction:</h4>";
        $reconstructed = $sample_text;
        foreach ($likely_mappings as $corrupted => $correct) {
            $reconstructed = str_replace($corrupted, $correct, $reconstructed);
        }
        
        // Clean up remaining corruption
        $reconstructed = preg_replace('/[ÃÂ]+/', '', $reconstructed);
        $reconstructed = preg_replace('/\s+/', ' ', $reconstructed);
        $reconstructed = trim($reconstructed);
        
        echo "<div class='code'>";
        echo "Original corrupted: " . htmlspecialchars($sample_text) . "\n";
        echo "Reconstructed:      " . htmlspecialchars($reconstructed) . "\n";
        echo "</div>";
        
        // Now analyze actual database entries
        echo "<h3>Database Pattern Analysis</h3>";
        
        $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL AND name_ar LIKE '%أÂ%' LIMIT 10");
        $db_patterns = [];
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Corrupted Text</th><th>English</th><th>Unique Patterns</th></tr>";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $patterns_in_text = analyzePatterns($row['name_ar']);
            $unique_patterns = array_unique(array_column($patterns_in_text, 'pattern'));
            
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['name_ar']) . "</td>";
            echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
            echo "<td>" . implode(', ', array_map('htmlspecialchars', $unique_patterns)) . "</td>";
            echo "</tr>";
            
            // Collect all patterns for frequency analysis
            foreach ($unique_patterns as $pattern) {
                if (!isset($db_patterns[$pattern])) {
                    $db_patterns[$pattern] = 0;
                }
                $db_patterns[$pattern]++;
            }
        }
        echo "</table>";
        
        // Show pattern frequency
        if (!empty($db_patterns)) {
            arsort($db_patterns);
            echo "<h4>Pattern Frequency in Database:</h4>";
            echo "<table>";
            echo "<tr><th>Pattern</th><th>Frequency</th><th>Suggested Mapping</th></tr>";
            
            foreach ($db_patterns as $pattern => $count) {
                $suggested = isset($likely_mappings[$pattern]) ? $likely_mappings[$pattern] : '?';
                echo "<tr>";
                echo "<td>" . htmlspecialchars($pattern) . "</td>";
                echo "<td>" . $count . "</td>";
                echo "<td style='font-size:18px;'>" . $suggested . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Generate restoration rules
        echo "<h3>Suggested Restoration Rules</h3>";
        echo "<div class='pattern'>";
        echo "<h4>PHP Array for Restoration Script:</h4>";
        echo "<pre>";
        echo "\$corruption_fixes = [\n";
        foreach ($likely_mappings as $corrupted => $correct) {
            if (isset($db_patterns[$corrupted])) {
                echo "    '" . addslashes($corrupted) . "' => '" . $correct . "', // Found " . $db_patterns[$corrupted] . " times\n";
            }
        }
        echo "];\n";
        echo "</pre>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Database connection error: " . $e->getMessage() . "</div>";
    }
    ?>
    
    <div class="info">
        <h4>📋 Next Steps</h4>
        <p>Use the <code>fix_arabic_advanced.php</code> script with the patterns identified above.</p>
        <p>The script has been designed to handle these specific corruption patterns.</p>
    </div>
    
    <hr>
    <p><small>Arabic Corruption Pattern Analyzer - <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>





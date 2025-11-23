<?php
/**
 * Command Line Arabic Text Corruption Fix
 * Run this script to fix your database: php fix_arabic_cli.php
 */

// Set UTF-8 encoding
mb_internal_encoding('UTF-8');

echo "=== Ultimate Arabic Corruption Fix CLI ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

/**
 * Fix Arabic text corruption based on pattern analysis
 */
function fixArabicCorruption($text) {
    if (empty($text)) return $text;
    
    $corruption_map = [
        // Multi-character patterns (apply these first)
        'ف‡فŠرف…فŠس' => 'هيرميس',      // Hermes
        'طف‚ف… ف‚ف‡فˆة' => 'قهوة',     // Coffee (combined pattern)
        'طف‚ف…' => 'قهوة',           // Coffee (qahwa)
        'ف‚ف‡فˆة' => 'قهوة',         // Coffee (alternative pattern)
        'عربفŠة' => 'عربية',        // Arabic
        'ف‚طع' => 'قطع',           // Pieces
        
        // Two-character patterns
        'ف‡' => 'ه',               // Arabic Heh
        'فŠ' => 'ي',               // Arabic Yeh  
        'ف…' => 'م',               // Arabic Meem
        'ف‚' => 'ق',               // Arabic Qaf
        'فˆ' => 'و',               // Arabic Waw
        'طف‚' => 'قه',             // Part of coffee
        
        // Single character substitutions
        '‡' => 'ه',                // Double dagger -> Heh
        'Š' => 'ي',                // S with caron -> Yeh
        '…' => 'م',                // Horizontal ellipsis -> Meem
        '‚' => 'ق',                // Single low-9 quotation -> Qaf
        'ˆ' => 'و',                // Circumflex accent -> Waw
        
        // Additional common corruptions
        'Ã' => 'أ',   'Â' => '',    'Ø' => '',    '¡' => 'ا',   '©' => 'ة',
        '²' => 'ر',   '³' => 'س',   'µ' => 'ن',   '¹' => 'ل',   'º' => 'ك',
        '»' => 'ج',   '¼' => 'ت',   '½' => 'د',   '¾' => 'ذ',   '¿' => 'ز',
        'À' => 'ش',   'Á' => 'ص',   'Æ' => 'ض',   'Ç' => 'ط',   'È' => 'ظ',
        'É' => 'ع',   'Ê' => 'غ',   'Ë' => 'ف',   'Ì' => 'ق',   'Í' => 'ك',
        'Î' => 'ل',   'Ï' => 'م',   'Ð' => 'ن',   'Ñ' => 'ه',   'Ò' => 'و',
        'Ó' => 'ي',   'Õ' => 'ى',   'Ö' => 'ة',
    ];
    
    $fixed_text = $text;
    
    // Sort patterns by length (longest first)
    $patterns = array_keys($corruption_map);
    usort($patterns, function($a, $b) {
        return strlen($b) - strlen($a);
    });
    
    // Apply fixes in order of pattern length
    foreach ($patterns as $pattern) {
        $replacement = $corruption_map[$pattern];
        $fixed_text = str_replace($pattern, $replacement, $fixed_text);
    }
    
    // Additional cleanup
    $fixed_text = preg_replace('/[^\p{Arabic}\p{L}\p{N}\p{P}\p{Z}]/u', '', $fixed_text);
    $fixed_text = preg_replace('/\s+/', ' ', trim($fixed_text));
    
    // Remove duplicate consecutive words
    $words = explode(' ', $fixed_text);
    $cleaned_words = [];
    $prev_word = '';
    foreach ($words as $word) {
        if ($word !== $prev_word || empty($prev_word)) {
            $cleaned_words[] = $word;
        }
        $prev_word = $word;
    }
    $fixed_text = implode(' ', $cleaned_words);
    
    return $fixed_text;
}

// Test the fix first
$test_corrupted = "ف‡فŠرف…فŠس طف‚ف… ف‚ف‡فˆة عربفŠة 6 ف‚طع";
$test_fixed = fixArabicCorruption($test_corrupted);

echo "Testing fix function:\n";
echo "Original: $test_corrupted\n";
echo "Fixed:    $test_fixed\n";
echo "Expected: هيرميس قهوة عربية 6 قطع\n";
echo "Status:   " . ($test_fixed === 'هيرميس قهوة عربية 6 قطع' ? '✅ Perfect!' : '⚠️ Needs adjustment') . "\n\n";

// Connect to database
echo "Connecting to database...\n";

$config_files = ['config_arabic_fix.php', 'config_production.php', 'config/config.php', 'includes/config.php'];
$db = null;

foreach ($config_files as $config_file) {
    if (file_exists($config_file)) {
        try {
            require_once $config_file;
            
            $host = defined('DB_HOST') ? DB_HOST : (defined('DATABASE_HOST') ? constant('DATABASE_HOST') : null);
            $dbname = defined('DB_NAME') ? DB_NAME : (defined('DATABASE_NAME') ? constant('DATABASE_NAME') : null);
            $username = defined('DB_USER') ? DB_USER : (defined('DATABASE_USER') ? constant('DATABASE_USER') : null);
            $password = defined('DB_PASS') ? DB_PASS : (defined('DATABASE_PASSWORD') ? constant('DATABASE_PASSWORD') : '');
            
            if ($host && $dbname && $username) {
                // Enhanced PDO options for remote connection
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                    PDO::ATTR_TIMEOUT => 30,
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                ];
                
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                $db = new PDO($dsn, $username, $password, $options);
                echo "✅ Connected to database: $dbname (using $config_file)\n";
                break;
            }
        } catch (Exception $e) {
            echo "❌ Connection failed with $config_file: " . $e->getMessage() . "\n";
        }
    }
}

if (!$db) {
    echo "❌ Could not connect to database. Check your configuration.\n";
    exit(1);
}

// Ask for confirmation
echo "\n⚠️  IMPORTANT: This will modify your database!\n";
echo "Make sure you have a backup before proceeding.\n";
echo "Continue? (y/N): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'y') {
    echo "Aborted.\n";
    exit(0);
}

// Tables and columns to fix
$tables_to_fix = [
    'products' => ['name_ar', 'description_ar', 'details_ar'],
    'categories' => ['name_ar', 'description_ar'],
    'brands' => ['name_ar', 'description_ar'],
    'coupons' => ['name_ar', 'description_ar'],
];

$total_fixed = 0;

foreach ($tables_to_fix as $table => $columns) {
    // Check if table exists
    $stmt = $db->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() == 0) {
        echo "⚠️  Table '$table' not found, skipping...\n";
        continue;
    }
    
    // Get table structure
    $stmt = $db->query("DESCRIBE $table");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($columns as $column) {
        if (!in_array($column, $existing_columns)) {
            echo "⚠️  Column '$column' not found in table '$table', skipping...\n";
            continue;
        }
        
        echo "🔍 Processing $table.$column...\n";
        
        // Get all rows with potentially corrupted text
        $stmt = $db->prepare("SELECT id, $column FROM $table WHERE $column IS NOT NULL AND $column != ''");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $fixed_count = 0;
        foreach ($rows as $row) {
            $original = $row[$column];
            $fixed = fixArabicCorruption($original);
            
            if ($fixed !== $original && !empty($fixed)) {
                $update_stmt = $db->prepare("UPDATE $table SET $column = ? WHERE id = ?");
                $update_stmt->execute([$fixed, $row['id']]);
                $fixed_count++;
                
                // Show example of fix (first 3 only)
                if ($fixed_count <= 3) {
                    echo "   ID {$row['id']}: " . substr($original, 0, 40) . " → " . substr($fixed, 0, 40) . "\n";
                }
            }
        }
        
        echo "✅ Fixed $fixed_count rows in $table.$column\n";
        $total_fixed += $fixed_count;
    }
}

echo "\n🎉 Fix completed!\n";
echo "Total rows fixed: $total_fixed\n";
echo "Your Arabic text should now display correctly!\n";
?>





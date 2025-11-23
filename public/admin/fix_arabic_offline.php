<?php
/**
 * Offline Arabic Text Correction Script
 * This script can be used when database connection is not available
 * It generates SQL statements that you can run manually
 */

// Set UTF-8 encoding
mb_internal_encoding('UTF-8');

echo "=== Offline Arabic Text Correction Script ===\n";
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
        '‡' => 'ه',   'Š' => 'ي',   '…' => 'م',   '‚' => 'ق',   'ˆ' => 'و',
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

// Test the fix function
$test_corrupted = "ف‡فŠرف…فŠس طف‚ف… ف‚ف‡فˆة عربفŠة 6 ف‚طع";
$test_fixed = fixArabicCorruption($test_corrupted);

echo "Testing fix function:\n";
echo "Original: $test_corrupted\n";
echo "Fixed:    $test_fixed\n";
echo "Expected: هيرميس قهوة عربية 6 قطع\n";
echo "Status:   " . ($test_fixed === 'هيرميس قهوة عربية 6 قطع' ? '✅ Perfect!' : '⚠️ Needs adjustment') . "\n\n";

// Generate SQL statements for manual execution
echo "=== SQL STATEMENTS FOR MANUAL EXECUTION ===\n\n";

// Common corrupted patterns to find and replace
$common_corruptions = [
    'ف‡فŠرف…فŠس' => 'هيرميس',
    'طف‚ف…' => 'قهوة',
    'ف‚ف‡فˆة' => 'قهوة',
    'عربفŠة' => 'عربية',
    'ف‚طع' => 'قطع',
    'ف‡' => 'ه',
    'فŠ' => 'ي',
    'ف…' => 'م',
    'ف‚' => 'ق',
    'فˆ' => 'و',
];

// Tables to process
$tables = ['products', 'categories', 'brands', 'coupons'];
$arabic_columns = ['name_ar', 'description_ar', 'details_ar'];

echo "-- Step 1: Backup your database first!\n";
echo "-- mysqldump -u username -p aleppogift > backup_before_arabic_fix.sql\n\n";

echo "-- Step 2: Set proper charset for the session\n";
echo "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\n\n";

foreach ($tables as $table) {
    echo "-- Fixing table: $table\n";
    
    foreach ($arabic_columns as $column) {
        echo "-- Processing column: $table.$column\n";
        
        foreach ($common_corruptions as $corrupted => $fixed) {
            $safe_corrupted = addslashes($corrupted);
            $safe_fixed = addslashes($fixed);
            
            echo "UPDATE `$table` SET `$column` = REPLACE(`$column`, '$safe_corrupted', '$safe_fixed') WHERE `$column` LIKE '%$safe_corrupted%';\n";
        }
        
        echo "\n";
    }
}

echo "-- Step 3: Verify the changes\n";
foreach ($tables as $table) {
    echo "SELECT id, name_ar FROM `$table` WHERE name_ar IS NOT NULL LIMIT 5;\n";
}

echo "\n-- Step 4: If you need to rollback, restore from backup:\n";
echo "-- mysql -u username -p aleppogift < backup_before_arabic_fix.sql\n\n";

echo "=== END OF SQL STATEMENTS ===\n\n";

echo "INSTRUCTIONS:\n";
echo "1. Copy the SQL statements above\n";
echo "2. Login to your hosting control panel (cPanel/phpMyAdmin)\n";
echo "3. Navigate to MySQL databases > phpMyAdmin\n";
echo "4. Select your 'aleppogift' database\n";
echo "5. Click on 'SQL' tab\n";
echo "6. Paste and execute the SQL statements\n";
echo "7. Check the results in your website\n\n";

echo "ALTERNATIVE - Export/Import Method:\n";
echo "1. Export your database from hosting control panel\n";
echo "2. Download the .sql file\n";
echo "3. Import it to local XAMPP MySQL\n";
echo "4. Run the Arabic fix script locally\n";
echo "5. Export the fixed database\n";
echo "6. Import it back to your hosting\n\n";

?>





<?php
/**
 * Detailed Analysis of Arabic Text Corruption
 * This script analyzes the specific corruption pattern in your example
 */

// Set UTF-8 encoding
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>Arabic Corruption Analysis</title>";
echo "<style>body{font-family:Arial;margin:40px;} .box{background:#f8f9fa;border:1px solid #ddd;padding:15px;margin:10px 0;border-radius:5px;} .corrupted{background:#ffebee;} .fixed{background:#e8f5e8;}</style>";
echo "</head><body>";

echo "<h1>Detailed Arabic Corruption Analysis</h1>";

// Your example
$corrupted_text = "ف‡فŠرف…فŠس طف‚ف… ف‚ف‡فˆة عربفŠة 6 ف‚طع";
$expected_english = "Hermes Arabic coffee set of 6";

echo "<div class='box corrupted'>";
echo "<h3>Corrupted Text Example:</h3>";
echo "<p><strong>Corrupted:</strong> " . htmlspecialchars($corrupted_text) . "</p>";
echo "<p><strong>Expected English:</strong> " . htmlspecialchars($expected_english) . "</p>";
echo "</div>";

// Analyze character by character
echo "<div class='box'>";
echo "<h3>Character-by-Character Analysis:</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>Position</th><th>Character</th><th>Unicode</th><th>Hex</th><th>Decimal</th></tr>";

$chars = mb_str_split($corrupted_text, 1, 'UTF-8');
foreach ($chars as $index => $char) {
    $unicode = mb_ord($char, 'UTF-8');
    $hex = sprintf("U+%04X", $unicode);
    echo "<tr>";
    echo "<td>$index</td>";
    echo "<td>" . htmlspecialchars($char) . "</td>";
    echo "<td>$hex</td>";
    echo "<td>0x" . dechex($unicode) . "</td>";
    echo "<td>$unicode</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// Try different decoding approaches
echo "<div class='box'>";
echo "<h3>Decoding Attempts:</h3>";

// Method 1: Try different character set conversions
$methods = [
    'ISO-8859-1 to UTF-8' => function($text) {
        return @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    },
    'Windows-1256 to UTF-8' => function($text) {
        return @mb_convert_encoding($text, 'UTF-8', 'Windows-1256');
    },
    'UTF-8 decode' => function($text) {
        return @utf8_decode($text);
    },
    'HTML entities decode' => function($text) {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    },
    'Double UTF-8 decode' => function($text) {
        $decoded = @utf8_decode($text);
        if ($decoded !== false) {
            return @utf8_decode($decoded);
        }
        return $text;
    },
    'Iconv ISO-8859-1' => function($text) {
        return @iconv('ISO-8859-1', 'UTF-8//IGNORE', $text);
    },
    'Iconv Windows-1256' => function($text) {
        return @iconv('Windows-1256', 'UTF-8//IGNORE', $text);
    }
];

foreach ($methods as $method_name => $method_func) {
    $result = $method_func($corrupted_text);
    echo "<p><strong>$method_name:</strong> " . htmlspecialchars($result) . "</p>";
}
echo "</div>";

// Try to detect the corruption pattern
echo "<div class='box'>";
echo "<h3>Corruption Pattern Analysis:</h3>";

// Look for common corruption patterns
$patterns = [
    '/ف‡/' => 'ه', // This might be ه (Arabic letter Heh)
    '/فŠ/' => 'ي', // This might be ي (Arabic letter Yeh)
    '/ف…/' => 'م', // This might be م (Arabic letter Meem)
    '/طف‚/' => 'قط', // This might be قط
    '/ف‚/' => 'ق', // This might be ق (Arabic letter Qaf)
    '/فˆ/' => 'و', // This might be و (Arabic letter Waw)
    '/عرب/' => 'عرب', // Arabic already correct
];

$test_fix = $corrupted_text;
foreach ($patterns as $pattern => $replacement) {
    $test_fix = preg_replace($pattern, $replacement, $test_fix);
}

echo "<p><strong>Pattern-based fix attempt:</strong> " . htmlspecialchars($test_fix) . "</p>";
echo "</div>";

// Try to reverse engineer from expected text
echo "<div class='box'>";
echo "<h3>Reverse Engineering:</h3>";
echo "<p>Based on 'Hermes Arabic coffee set of 6', the Arabic should be something like:</p>";
echo "<p>هيرميس قهوة عربية 6 قطع</p>";
echo "<p>Let's see if we can map the corruption:</p>";

$expected_arabic = "هيرميس قهوة عربية 6 قطع";
$expected_chars = mb_str_split($expected_arabic, 1, 'UTF-8');
$corrupted_chars = mb_str_split($corrupted_text, 1, 'UTF-8');

echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>Position</th><th>Expected</th><th>Corrupted</th><th>Unicode Expected</th><th>Unicode Corrupted</th></tr>";

$max_length = max(count($expected_chars), count($corrupted_chars));
for ($i = 0; $i < $max_length; $i++) {
    $exp_char = $i < count($expected_chars) ? $expected_chars[$i] : '';
    $corr_char = $i < count($corrupted_chars) ? $corrupted_chars[$i] : '';
    
    $exp_unicode = $exp_char ? sprintf("U+%04X", mb_ord($exp_char, 'UTF-8')) : '';
    $corr_unicode = $corr_char ? sprintf("U+%04X", mb_ord($corr_char, 'UTF-8')) : '';
    
    echo "<tr>";
    echo "<td>$i</td>";
    echo "<td>" . htmlspecialchars($exp_char) . "</td>";
    echo "<td>" . htmlspecialchars($corr_char) . "</td>";
    echo "<td>$exp_unicode</td>";
    echo "<td>$corr_unicode</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// Check database connection and analyze real data
echo "<div class='box'>";
echo "<h3>Database Analysis:</h3>";

// Try to connect to database
$config_files = ['config_production.php', 'config/config.php', 'includes/config.php'];
$db = null;

foreach ($config_files as $config_file) {
    if (file_exists($config_file)) {
        try {
            require_once $config_file;
            
            // Try different constant names
            $host = defined('DB_HOST') ? DB_HOST : (defined('DATABASE_HOST') ? constant('DATABASE_HOST') : null);
            $dbname = defined('DB_NAME') ? DB_NAME : (defined('DATABASE_NAME') ? constant('DATABASE_NAME') : null);
            $username = defined('DB_USER') ? DB_USER : (defined('DATABASE_USER') ? constant('DATABASE_USER') : null);
            $password = defined('DB_PASS') ? DB_PASS : (defined('DATABASE_PASSWORD') ? constant('DATABASE_PASSWORD') : '');
            
            if ($host && $dbname && $username) {
                $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo "<p>✅ Connected to database: $dbname</p>";
                break;
            }
        } catch (Exception $e) {
            echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
        }
    }
}

if ($db) {
    try {
        // Check for products with corrupted Arabic
        $stmt = $db->query("SELECT id, name_ar, name_en FROM products WHERE name_ar IS NOT NULL LIMIT 5");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Sample Products from Database:</h4>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Arabic Name</th><th>English Name</th><th>Analysis</th></tr>";
        
        foreach ($products as $product) {
            $arabic = $product['name_ar'];
            $english = $product['name_en'];
            
            // Analyze the text
            $has_corruption = false;
            $corruption_indicators = ['ف‡', 'فŠ', 'ف…', 'طف‚', 'ف‚', 'فˆ'];
            foreach ($corruption_indicators as $indicator) {
                if (strpos($arabic, $indicator) !== false) {
                    $has_corruption = true;
                    break;
                }
            }
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($product['id']) . "</td>";
            echo "<td>" . htmlspecialchars($arabic) . "</td>";
            echo "<td>" . htmlspecialchars($english) . "</td>";
            echo "<td>" . ($has_corruption ? "🔴 Corrupted" : "✅ OK") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p>❌ Database query failed: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ Could not connect to database</p>";
}

echo "</div>";

echo "</body></html>";
?>





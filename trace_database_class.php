<?php
// Trace include paths and Database class
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Include Path Tracing</h2>";

$root_dir = dirname(dirname(__DIR__));
echo "Root directory: $root_dir<br>";

echo "<h3>File Existence Check</h3>";
$database_file = $root_dir . '/includes/Database.php';
echo "Database.php path: $database_file<br>";
echo "File exists: " . (file_exists($database_file) ? 'Yes' : 'No') . "<br>";
echo "File size: " . filesize($database_file) . " bytes<br>";
echo "Last modified: " . date('Y-m-d H:i:s', filemtime($database_file)) . "<br>";

echo "<h3>Include and Test Database Class</h3>";
require_once($root_dir . '/config/config.php');

// Include Database.php and show the loaded file
require_once($database_file);

// Get reflection info about the Database class
$reflection = new ReflectionClass('Database');
echo "Database class file: " . $reflection->getFileName() . "<br>";

// Check the query method
$method = $reflection->getMethod('query');
echo "Query method exists: Yes<br>";

// Read the actual query method code from file
$file_contents = file_get_contents($reflection->getFileName());
$start_line = $method->getStartLine() - 1;
$end_line = $method->getEndLine() - 1;
$file_lines = explode("\n", $file_contents);
$method_code = array_slice($file_lines, $start_line, $end_line - $start_line + 1);

echo "<h3>Current Database::query() Method Code</h3>";
echo "<pre>" . htmlspecialchars(implode("\n", $method_code)) . "</pre>";

echo "<h3>Test Database Query Method</h3>";
try {
    $db = new Database();
    
    // Test the specific failing case
    echo "Testing: SELECT * FROM orders WHERE id = ? with [94]<br>";
    $result = $db->query("SELECT * FROM orders WHERE id = ?", [94]);
    $order = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "✅ Query successful: Order " . $order['id'] . " found<br>";
    } else {
        echo "❌ Query returned no results<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    
    // Show the exact line that failed
    if ($e->getFile() == $database_file) {
        $error_line = $e->getLine();
        $file_lines = file($database_file);
        echo "<h4>Error occurred at line $error_line:</h4>";
        echo "<pre>";
        for ($i = max(0, $error_line - 3); $i < min(count($file_lines), $error_line + 2); $i++) {
            $marker = ($i + 1 == $error_line) ? '>>> ' : '    ';
            echo $marker . ($i + 1) . ': ' . htmlspecialchars($file_lines[$i]);
        }
        echo "</pre>";
    }
}
?>
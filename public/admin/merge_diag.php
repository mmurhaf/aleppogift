<?php
// Temporary diagnostic - DELETE AFTER USE
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo '<pre>';
echo 'PHP version: ' . PHP_VERSION . "\n";
echo 'Script: ' . __FILE__ . "\n\n";

// Check for function redeclaration by listing defined functions before/after
$before = get_defined_functions()['user'];

// Try including just the config/DB stack (same as header.php does)
$rootDir = dirname(dirname(__DIR__));
echo 'rootDir: ' . $rootDir . "\n\n";

$files_to_check = [
    $rootDir . '/config/config.php',
    $rootDir . '/includes/session_helper.php',
    $rootDir . '/includes/functions.php',
    $rootDir . '/includes/Database.php',
];
foreach ($files_to_check as $f) {
    echo (file_exists($f) ? 'EXISTS' : 'MISSING') . ': ' . $f . "\n";
}

echo "\nDefined user functions before includes:\n";
echo implode(', ', array_filter($before, fn($n) => str_contains($n, 'merge'))) . "\n";

// Try loading the includes manually
foreach ($files_to_check as $f) {
    if (file_exists($f)) {
        try {
            require_once $f;
            echo "OK: $f\n";
        } catch (Throwable $e) {
            echo "FAIL: $f => " . $e->getMessage() . "\n";
        }
    }
}

$after = get_defined_functions()['user'];
echo "\nUser functions containing 'merge' after includes:\n";
echo implode(', ', array_filter($after, fn($n) => str_contains($n, 'merge'))) . "\n";

// Check what functions.php defines that could conflict
echo "\nAll user functions defined (sorted):\n";
sort($after);
echo implode("\n", $after) . "\n";

echo "\nAll clear — no fatal before functions.php loaded.\n";
echo '</pre>';

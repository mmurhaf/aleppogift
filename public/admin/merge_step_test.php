<?php
declare(strict_types=1);
// Minimal replication of merge-products.php structure - DELETE AFTER USE

ini_set('display_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Output JSON to bypass any HTML already sent
        header_remove();
        echo "\n\n<!-- SHUTDOWN ERROR: " . json_encode($err) . " -->\n";
    }
});

echo "STEP 1: declare(strict_types=1) OK\n";

$pageTitle = 'Merge Test';
echo "STEP 2: pageTitle set OK\n";

// Test the function declarations that merge-products.php has
function mergeProductsEscape(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
echo "STEP 3: mergeProductsEscape declared OK\n";

// Simulate a simple typed function call
$result = mergeProductsEscape("test <b>value</b>");
echo "STEP 4: mergeProductsEscape() called OK => $result\n";

// Test the rootDir / includes load that header.php does
$rootDir = dirname(dirname(__DIR__));
echo "STEP 5: rootDir = $rootDir\n";

require_once $rootDir . '/config/config.php';
echo "STEP 6: config.php loaded OK\n";

require_once $rootDir . '/includes/session_helper.php';
echo "STEP 7: session_helper.php loaded OK\n";

require_once $rootDir . '/includes/functions.php';
echo "STEP 8: functions.php loaded OK\n";

require_once $rootDir . '/includes/Database.php';
echo "STEP 9: Database.php loaded OK\n";

// Test require_admin_login - this may redirect
echo "STEP 10: About to call require_admin_login()...\n";
ob_start();
require_admin_login();
$buffered = ob_get_clean();
echo "STEP 11: require_admin_login() returned (did not exit) — session is active\n";

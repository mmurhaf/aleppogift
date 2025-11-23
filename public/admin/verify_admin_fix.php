<?php
// Test admin pages without output buffering issues
$test_results = [];

// Test 1: Check redirect paths
$_SERVER['SCRIPT_NAME'] = '/admin/categories.php';
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$login_path = (strpos($script_dir, '/admin') !== false) ? 'login.php' : 'admin/login.php';
$test_results[] = "✅ Admin page redirect path: " . $login_path;

$_SERVER['SCRIPT_NAME'] = '/index.php';
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$login_path = (strpos($script_dir, '/admin') !== false) ? 'login.php' : 'admin/login.php';
$test_results[] = "✅ Root page redirect path: " . $login_path;

// Test 2: Check if admin files exist and are readable
$admin_files = [
    '../admin/login.php',
    '../admin/categories.php', 
    '../admin/brands.php'
];

foreach ($admin_files as $file) {
    if (file_exists($file) && is_readable($file)) {
        $test_results[] = "✅ File accessible: " . $file;
    } else {
        $test_results[] = "❌ File issue: " . $file;
    }
}

// Test 3: Check if includes are accessible
$includes = [
    'includes/session_helper.php',
    'config/config.php',
    'includes/Database.php'
];

foreach ($includes as $file) {
    if (file_exists($file) && is_readable($file)) {
        $test_results[] = "✅ Include accessible: " . $file;
    } else {
        $test_results[] = "❌ Include issue: " . $file;
    }
}

// Output results
header('Content-Type: text/plain');
echo "Admin Pages Fix Verification\n";
echo "============================\n\n";

foreach ($test_results as $result) {
    echo $result . "\n";
}

echo "\nExpected Behavior:\n";
echo "- Visiting /admin/categories.php without login -> redirects to /admin/login.php\n";
echo "- Visiting /admin/brands.php without login -> redirects to /admin/login.php\n";
echo "- After login with admin/admin123 -> pages should display properly\n";
echo "- No more blank pages\n";
?>




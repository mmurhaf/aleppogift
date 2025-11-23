<?php
// Debug version of brands.php to test authentication
// Temporarily enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "Starting brands debug...<br>";

$root_dir = dirname(dirname(__DIR__));
echo "Root directory: " . $root_dir . "<br>";

try {
    require_once($root_dir . '/includes/session_helper.php');
    echo "Session helper loaded successfully<br>";
    
    require_once($root_dir . '/config/config.php');
    echo "Config loaded successfully<br>";
    
    require_once($root_dir . '/includes/Database.php');
    echo "Database class loaded successfully<br>";

    echo "About to call require_admin_login()...<br>";
    require_admin_login();
    echo "Admin login check passed!<br>";

    $db = new Database();
    echo "Database connection successful<br>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "Debug complete!";
?>
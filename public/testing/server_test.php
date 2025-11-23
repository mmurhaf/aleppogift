<?php
// Simple server test file
echo "PHP is working! " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n";

// Test basic connectivity
if (function_exists('mysqli_connect')) {
    echo "MySQL functions available\n";
} else {
    echo "MySQL functions NOT available\n";
}

// Server info
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
?>

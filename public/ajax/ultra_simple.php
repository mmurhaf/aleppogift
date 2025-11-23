<?php
// Ultra simple test - no includes, no sessions
echo "Hello from AJAX test!";
echo "\nPHP Version: " . PHP_VERSION;
echo "\nWorking Directory: " . getcwd();
echo "\nDocument Root: " . $_SERVER['DOCUMENT_ROOT'];
echo "\nScript Name: " . $_SERVER['SCRIPT_NAME'];
?>

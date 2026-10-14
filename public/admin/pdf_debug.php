<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(__DIR__); if (!file_exists($root_dir . '/config/config.php')) { $root_dir = dirname(__DIR__); if (!file_exists($root_dir . '/config/config.php')) { $root_dir = dirname(dirname(__DIR__)); } }
echo "root_dir: " . $root_dir . "<br>";
echo "__DIR__: " . __DIR__ . "<br>";
echo "session_helper: " . $root_dir . '/includes/session_helper.php' . " - " . (file_exists($root_dir . '/includes/session_helper.php') ? 'EXISTS' : 'MISSING') . "<br>";
echo "config: " . $root_dir . '/config/config.php' . " - " . (file_exists($root_dir . '/config/config.php') ? 'EXISTS' : 'MISSING') . "<br>";
echo "Database: " . $root_dir . '/includes/Database.php' . " - " . (file_exists($root_dir . '/includes/Database.php') ? 'EXISTS' : 'MISSING') . "<br>";
echo "autoload: " . $root_dir . '/vendor/autoload.php' . " - " . (file_exists($root_dir . '/vendor/autoload.php') ? 'EXISTS' : 'MISSING') . "<br>";
echo "Dompdf class: " . (class_exists('Dompdf\Dompdf') || (@include_once($root_dir . '/vendor/autoload.php') && class_exists('Dompdf\Dompdf')) ? 'OK' : 'MISSING') . "<br>";

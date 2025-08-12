<?php
echo "DEBUG: PHP is working!<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "PHP version: " . phpversion() . "<br>";
echo "Current directory: " . __DIR__ . "<br>";

if (file_exists(__DIR__ . '/../includes/bootstrap.php')) {
    echo "Bootstrap file exists<br>";
    try {
        require_once(__DIR__ . '/../includes/bootstrap.php');
        echo "Bootstrap loaded successfully<br>";
        
        if (class_exists('Database')) {
            echo "Database class available<br>";
            try {
                $db = new Database();
                echo "Database connection successful<br>";
            } catch (Exception $e) {
                echo "Database error: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "Database class not found<br>";
        }
    } catch (Exception $e) {
        echo "Bootstrap error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Bootstrap file not found<br>";
}
?>

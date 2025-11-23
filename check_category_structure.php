<?php
require_once('config/config.php');
require_once('includes/Database.php');

$db = new Database();

try {
    // Check categories table structure
    echo "<h2>Categories Table Structure:</h2>";
    $structure = $db->query("DESCRIBE categories")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($structure);
    echo "</pre>";
    
    // Check existing categories
    echo "<h2>Existing Categories:</h2>";
    $categories = $db->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($categories);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
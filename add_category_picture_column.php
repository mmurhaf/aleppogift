<?php
require_once('config/config.php');
require_once('includes/Database.php');

$db = new Database();

try {
    // Add picture column to categories table
    $sql = "ALTER TABLE categories ADD COLUMN picture VARCHAR(500) NULL";
    $db->query($sql);
    echo "✅ Successfully added 'picture' column to categories table!<br>";
    
    // Verify the new structure
    echo "<h3>Updated Categories Table Structure:</h3>";
    $structure = $db->query("DESCRIBE categories")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($structure as $col) {
        echo $col['Field'] . " - " . $col['Type'] . " - " . ($col['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    echo "</pre>";
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️ Column 'picture' already exists in categories table.<br>";
        
        // Show current structure
        echo "<h3>Current Categories Table Structure:</h3>";
        $structure = $db->query("DESCRIBE categories")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($structure as $col) {
            echo $col['Field'] . " - " . $col['Type'] . " - " . ($col['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
        echo "</pre>";
    } else {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
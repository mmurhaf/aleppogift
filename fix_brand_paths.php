<?php
/**
 * Fix Brand Logo Paths
 * This script fixes the duplicated path issue in the brands table
 * where logo paths are stored as "uploads/brands/uploads/brands/filename.jpg"
 * instead of just "filename.jpg"
 * 
 * NOTE: Place this file in the public folder and access it via:
 * https://www.aleppogift.com/fix_brand_paths.php
 */

require_once('../config/config.php');
require_once('../includes/Database.php');

$db = new Database();

echo "<h2>Brand Logo Path Fix Script</h2>";
echo "<p>Starting fix process...</p>";

// Fetch all brands
$brands = $db->query("SELECT id, logo FROM brands WHERE logo IS NOT NULL AND logo != ''")->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Found " . count($brands) . " brands with logos.</p>";

$fixed_count = 0;
$already_correct = 0;

foreach ($brands as $brand) {
    $current_logo = $brand['logo'];
    $new_logo = $current_logo;
    
    // Check if the path contains the duplicated "uploads/brands/" prefix
    if (strpos($current_logo, 'uploads/brands/') !== false) {
        // Extract just the filename from the path
        // This handles cases like "uploads/brands/filename.jpg" or "uploads/brands/uploads/brands/filename.jpg"
        $new_logo = basename($current_logo);
        
        // Update the database
        $db->query("UPDATE brands SET logo = :new_logo WHERE id = :id", [
            'new_logo' => $new_logo,
            'id' => $brand['id']
        ]);
        
        echo "<p style='color: green;'>✓ Fixed Brand ID {$brand['id']}: <br>";
        echo "&nbsp;&nbsp;Old: {$current_logo}<br>";
        echo "&nbsp;&nbsp;New: {$new_logo}</p>";
        
        $fixed_count++;
    } else {
        echo "<p style='color: blue;'>✓ Brand ID {$brand['id']} already correct: {$current_logo}</p>";
        $already_correct++;
    }
}

echo "<hr>";
echo "<h3>Summary:</h3>";
echo "<ul>";
echo "<li>Total brands processed: " . count($brands) . "</li>";
echo "<li>Brands fixed: " . $fixed_count . "</li>";
echo "<li>Brands already correct: " . $already_correct . "</li>";
echo "</ul>";

echo "<p><strong>Fix complete! You can now safely delete this file.</strong></p>";
echo "<p><a href='admin/brands.php'>Go to Brands Management</a></p>";
?>

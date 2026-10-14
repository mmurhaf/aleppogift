<?php
// File: fix_variant_stock.php
// Updates existing auto-created variants to inherit parent product stock
// Run this once to fix variants that were created with 0 stock

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(__DIR__);
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

// Simple password protection
$setup_password = defined('SETUP_PASSWORD') ? SETUP_PASSWORD : '';
if ($setup_password === '' || !isset($_GET['password']) || !hash_equals($setup_password, (string)$_GET['password'])) {
    die("Access denied. Use: fix_variant_stock.php?password=YOUR_PASSWORD");
}

$db = new Database();
$updated_count = 0;
$skipped_count = 0;

try {
    echo "<h2>Fixing Variant Stock Levels</h2>";
    echo "<pre>";
    
    // Get all variants with 0 stock that have image_id (auto-created ones)
    $variants = $db->query("
        SELECT v.id, v.product_id, v.stock as variant_stock, p.stock as product_stock, p.name_en
        FROM product_variations v
        INNER JOIN products p ON v.product_id = p.id
        WHERE v.image_id IS NOT NULL
        AND v.stock = 0
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($variants) . " variants with 0 stock\n\n";
    
    foreach ($variants as $variant) {
        $product_stock = (int)$variant['product_stock'];
        
        if ($product_stock > 0) {
            // Update variant stock to match product stock
            $db->query("
                UPDATE product_variations 
                SET stock = :stock 
                WHERE id = :id
            ", [
                'stock' => $product_stock,
                'id' => $variant['id']
            ]);
            
            echo "✓ Updated variant {$variant['id']} for product '{$variant['name_en']}': 0 → {$product_stock}\n";
            $updated_count++;
        } else {
            echo "○ Skipped variant {$variant['id']} for product '{$variant['name_en']}': parent stock is also 0\n";
            $skipped_count++;
        }
    }
    
    echo "\n";
    echo "=====================================\n";
    echo "Summary:\n";
    echo "  Updated: {$updated_count} variants\n";
    echo "  Skipped: {$skipped_count} variants (parent stock = 0)\n";
    echo "=====================================\n";
    echo "\n";
    echo "✓ Fix completed successfully!\n";
    echo "</pre>";
    
    echo "<br><a href='admin/products.php'>Go to Products</a>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    error_log("Fix variant stock error: " . $e->getMessage());
}
?>

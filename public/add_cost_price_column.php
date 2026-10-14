<?php
/**
 * Database Migration: Add cost_price column to products table
 * Run this script once to add the cost_price column if it doesn't exist
 */

require_once '../config/config.php';
require_once '../includes/Database.php';

// Check if update query should be executed
$updateQuery = isset($_GET['update_cost_price']);

if ($updateQuery) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== Updating cost_price for existing products ===\n\n";
    
    try {
        $db = new Database();
        
        echo "Executing UPDATE query...\n";
        echo "UPDATE products SET cost_price = price * 0.80 WHERE cost_price IS NULL OR cost_price = 0\n\n";
        
        $stmt = $db->query("UPDATE products SET cost_price = price * 0.80 WHERE cost_price IS NULL OR cost_price = 0");
        $rowCount = $stmt->rowCount();
        
        echo "✓ Successfully updated {$rowCount} product(s)\n";
        echo "\nUpdate completed successfully!\n";
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n<br><a href='" . $_SERVER['PHP_SELF'] . "'>Back to Migration Page</a>";
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - cost_price Column</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .button { display: inline-block; padding: 12px 24px; margin: 10px 5px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
        .button:hover { background: #45a049; }
        .button.warning { background: #ff9800; }
        .button.warning:hover { background: #e68900; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #4CAF50; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Migration: cost_price Column</h1>
        
        <div class="section">
            <h2>Step 1: Run Migration</h2>
            <p>Click the button below to run the migration script that adds the cost_price column.</p>
            <a href="?run_migration=1" class="button">Run Migration</a>
        </div>
        
        <div class="section">
            <h2>Step 2: Update Existing Products</h2>
            <p>After migration, click this button to update cost_price for existing products (set to 80% of price).</p>
            <p><strong>Query:</strong></p>
            <pre>UPDATE products 
SET cost_price = price * 0.80 
WHERE cost_price IS NULL OR cost_price = 0;</pre>
            <a href="?update_cost_price=1" class="button warning" onclick="return confirm('Are you sure you want to update cost_price for all products?');">Update Cost Prices</a>
        </div>
    </div>
</body>
</html>
<?php
exit;

// Run migration if requested
if (isset($_GET['run_migration'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== Database Migration: Add cost_price Column ===\n\n";

try {
    $db = new Database();
    
    // Check if cost_price column already exists
    echo "1. Checking if cost_price column exists...\n";
    $columns = $db->query("SHOW COLUMNS FROM products LIKE 'cost_price'")->fetchAll();
    
    if (!empty($columns)) {
        echo "   ✓ cost_price column already exists\n";
        echo "\nNo migration needed. The database is up to date.\n";
    } else {
        echo "   ✗ cost_price column does not exist\n\n";
        
        echo "2. Adding cost_price column...\n";
        $db->query("ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT NULL AFTER price");
        echo "   ✓ Successfully added cost_price column\n\n";
        
        echo "3. Modifying cost_price to be a generated column (price * 0.80)...\n";
        $db->query("ALTER TABLE products MODIFY COLUMN cost_price DECIMAL(10,2) GENERATED ALWAYS AS (price * 0.80) STORED");
        echo "   ✓ Successfully modified cost_price as generated column\n\n";
        
        echo "4. Verifying the change...\n";
        $verify = $db->query("SHOW COLUMNS FROM products LIKE 'cost_price'")->fetchAll();
        if (!empty($verify)) {
            echo "   ✓ Verification successful\n\n";
            echo "Migration completed successfully!\n";
            echo "The cost_price column has been added to the products table.\n";
        } else {
            echo "   ✗ Verification failed\n\n";
            echo "Warning: The column was not added successfully. Please check your database permissions.\n";
        }
    }
    
    echo "\n5. Current products table structure:\n";
    $structure = $db->query("DESCRIBE products")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($structure as $column) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\nPlease check your database configuration and try again.\n";
}

echo "\n=== Migration Script Complete ===\n";
echo "\n<br><a href='" . $_SERVER['PHP_SELF'] . "'>Back to Migration Page</a>";
}
?>

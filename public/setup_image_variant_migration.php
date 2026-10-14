<?php
// File: setup_image_variant_migration.php
// Run this file once to add image_id column to product_variations table

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(__DIR__);
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

// Simple password protection
$setup_password = defined('SETUP_PASSWORD') ? SETUP_PASSWORD : '';
if ($setup_password === '' || !isset($_GET['password']) || !hash_equals($setup_password, (string)$_GET['password'])) {
    die("Access denied. Use: setup_image_variant_migration.php?password=YOUR_PASSWORD");
}

$db = new Database();
$messages = [];
$errors = [];

try {
    // Check if column already exists
    $check = $db->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'product_variations' 
          AND COLUMN_NAME = 'image_id'
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($check) {
        $messages[] = "✓ Column 'image_id' already exists in product_variations table";
    } else {
        // Add the column
        $db->query("
            ALTER TABLE product_variations 
            ADD COLUMN image_id INT(11) DEFAULT NULL 
            AFTER product_id
        ");
        $messages[] = "✓ Successfully added 'image_id' column to product_variations table";
    }
    
    // Add index
    $check_index = $db->query("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'product_variations' 
          AND INDEX_NAME = 'idx_variation_image'
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($check_index) {
        $messages[] = "✓ Index 'idx_variation_image' already exists";
    } else {
        $db->query("
            CREATE INDEX idx_variation_image 
            ON product_variations(image_id)
        ");
        $messages[] = "✓ Successfully created index 'idx_variation_image'";
    }
    
    // Add foreign key (may fail if already exists, that's okay)
    try {
        $db->query("
            ALTER TABLE product_variations
            ADD CONSTRAINT fk_variation_image 
            FOREIGN KEY (image_id) REFERENCES product_images(id) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
        ");
        $messages[] = "✓ Successfully added foreign key constraint 'fk_variation_image'";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $messages[] = "✓ Foreign key constraint already exists";
        } else {
            throw $e;
        }
    }
    
    // Get statistics
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_variations,
            COUNT(image_id) as variations_with_images,
            COUNT(*) - COUNT(image_id) as variations_without_images
        FROM product_variations
    ")->fetch(PDO::FETCH_ASSOC);
    
    $messages[] = "<br><strong>Database Statistics:</strong>";
    $messages[] = "Total variations: " . $stats['total_variations'];
    $messages[] = "Variations with images: " . $stats['variations_with_images'];
    $messages[] = "Variations without images: " . $stats['variations_without_images'];
    
    // Success message
    $messages[] = "<br><strong>✓ Migration completed successfully!</strong>";
    $messages[] = "You can now use the auto-create variants feature.";
    $messages[] = "<br><strong>Next steps:</strong>";
    $messages[] = "1. Go to Admin Panel → Edit any product";
    $messages[] = "2. Click 'Auto-Create Variants for Images' button";
    $messages[] = "3. Or add a new product with images (variants will be auto-created)";
    
} catch (Exception $e) {
    $errors[] = "Error: " . $e->getMessage();
    error_log("Migration error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Variant Migration Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .success-msg {
            color: #28a745;
            padding: 5px 0;
        }
        .error-msg {
            color: #dc3545;
            padding: 5px 0;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Auto-Create Variants Migration</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h4>Errors:</h4>
                <?php foreach ($errors as $error): ?>
                    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($messages)): ?>
            <div class="alert alert-success">
                <?php foreach ($messages as $message): ?>
                    <div class="success-msg"><?php echo $message; ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <h3>About This Feature</h3>
            <p>This migration adds support for automatically creating product variants linked to product images.</p>
            
            <h4>What was added:</h4>
            <ul>
                <li><code>image_id</code> column in <code>product_variations</code> table</li>
                <li>Database index for performance</li>
                <li>Foreign key constraint for data integrity</li>
            </ul>
            
            <h4>How to use:</h4>
            <ol>
                <li>In Admin Panel, edit any product</li>
                <li>Click "Auto-Create Variants for Images" button</li>
                <li>Or add new products with images (variants auto-created if none specified)</li>
            </ol>
            
            <div class="alert alert-info mt-3">
                <strong>Note:</strong> You can safely delete this file after running the migration.
            </div>
        </div>
        
        <div class="mt-4">
            <a href="admin/products.php" class="btn btn-primary">Go to Products</a>
            <a href="admin/add_product.php" class="btn btn-success">Add New Product</a>
        </div>
    </div>
</body>
</html>

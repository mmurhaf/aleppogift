<?php
// File: admin/set_main_image.php - Set Main Image Handler
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

// Require admin authentication
require_admin_login();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "error: Invalid request method";
    exit;
}

$image_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($image_id <= 0 || $product_id <= 0) {
    echo "error: Invalid parameters";
    exit;
}

try {
    $db = new Database();
    
    // Verify the image belongs to the product
    $image = $db->query("SELECT id FROM product_images WHERE id = :id AND product_id = :product_id", [
        'id' => $image_id,
        'product_id' => $product_id
    ])->fetch(PDO::FETCH_ASSOC);
    
    if (!$image) {
        echo "error: Image not found";
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Remove main status from all images of this product
    $db->query("UPDATE product_images SET is_main = 0 WHERE product_id = :product_id", [
        'product_id' => $product_id
    ]);
    
    // Set the selected image as main
    $db->query("UPDATE product_images SET is_main = 1 WHERE id = :id", [
        'id' => $image_id
    ]);
    
    // Commit transaction
    $db->commit();
    
    echo "success: Main image updated successfully";
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    echo "error: Error setting main image: " . $e->getMessage();
}
?>
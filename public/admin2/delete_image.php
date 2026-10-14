<?php
// File: admin/delete_image.php - Enhanced Image Deletion Handler
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

// Require admin authentication
require_admin_login();

// Handle both GET and POST requests for backward compatibility
$image_id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : (isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0);

if ($image_id <= 0 || $product_id <= 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['id'])) {
        echo "error: Invalid parameters";
    } else {
        header("Location: products.php");
    }
    exit;
}

try {
    $db = new Database();
    
    // Get image details before deletion
    $image = $db->query("SELECT * FROM product_images WHERE id = :id AND product_id = :product_id", [
        'id' => $image_id,
        'product_id' => $product_id
    ])->fetch(PDO::FETCH_ASSOC);
    
    if (!$image) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['id'])) {
            echo "error: Image not found";
        } else {
            header("Location: edit_product.php?id=" . $product_id . "&error=image_not_found");
        }
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Delete the image record from database
    $db->query("DELETE FROM product_images WHERE id = :id", ['id' => $image_id]);
    
    // Delete the physical file
    $file_path = '../' . $image['image_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // If this was the main image, set another image as main (if any exist)
    if ($image['is_main'] == 1) {
        $db->query("UPDATE product_images SET is_main = 1 WHERE product_id = :product_id ORDER BY display_order ASC LIMIT 1", [
            'product_id' => $product_id
        ]);
    }
    
    // Update display order for remaining images
    $remaining_images = $db->query("SELECT id FROM product_images WHERE product_id = :product_id ORDER BY display_order ASC", [
        'product_id' => $product_id
    ])->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($remaining_images as $index => $img) {
        $db->query("UPDATE product_images SET display_order = :order WHERE id = :id", [
            'order' => $index + 1,
            'id' => $img['id']
        ]);
    }
    
    // Commit transaction
    $db->commit();
    
    // Handle different request types
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['id'])) {
        // AJAX request
        echo "success: Image deleted successfully";
    } else {
        // Regular GET request - redirect back
        header("Location: edit_product.php?id=" . $product_id . "&success=image_deleted");
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['id'])) {
        // AJAX request
        echo "error: Error deleting image: " . $e->getMessage();
    } else {
        // Regular GET request - redirect back with error
        header("Location: edit_product.php?id=" . $product_id . "&error=delete_failed");
    }
}

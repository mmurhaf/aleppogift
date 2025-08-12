<?php
// File: admin/delete_image.php
require_once('../../config/config.php');
require_once('../../includes/Database.php');

$db = new Database();

if (!isset($_GET['id']) || !isset($_GET['product_id'])) {
    header("Location: products.php");
    exit;
}

$image_id = (int)$_GET['id'];
$product_id = (int)$_GET['product_id'];

// Get image path
$image = $db->query("SELECT image_path FROM product_images WHERE id = :id", ['id' => $image_id])->fetch(PDO::FETCH_ASSOC);
if ($image) {
    // Delete file from disk
    $path = $image['image_path'];
    if (file_exists($path)) {
        unlink($path);
    }

    // Delete from DB
    $db->query("DELETE FROM product_images WHERE id = :id", ['id' => $image_id]);
}

header("Location: edit_product.php?id=$product_id");
exit;

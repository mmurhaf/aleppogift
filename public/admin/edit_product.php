<?php
// File: admin/edit_product.php
require_once('../../config/config.php');
require_once('../../includes/Database.php');

$db = new Database();

if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$product_id = (int)$_GET['id'];

$product = $db->query("SELECT * FROM products WHERE id = :id", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header("Location: products.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_en = $_POST['name_en'];
    $name_ar = $_POST['name_ar'];
    $category_id = $_POST['category_id'];
    $brand_id = $_POST['brand_id'];
    $description_en = $_POST['description_en'];
    $description_ar = $_POST['description_ar'];
    $price = $_POST['price'];
    $weight = $_POST['weight'];
    $status = $_POST['status'];

    $db->query("UPDATE products SET name_en = :name_en, name_ar = :name_ar, category_id = :category_id, brand_id = :brand_id, description_en = :description_en, description_ar = :description_ar, price = :price, weight = :weight, status = :status WHERE id = :id", [
        'name_en' => $name_en,
        'name_ar' => $name_ar,
        'category_id' => $category_id,
        'brand_id' => $brand_id,
        'description_en' => $description_en,
        'description_ar' => $description_ar,
        'price' => $price,
        'weight' => $weight,
        'status' => $status,
        'id' => $product_id
    ]);

    // Upload new images
    $upload_dir = '../uploads/products/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!empty($_FILES['images']['name'][0])) {
        $existingImages = $db->query("SELECT COUNT(*) FROM product_images WHERE product_id = :id", ['id' => $product_id])->fetchColumn();

        foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
            $fileName = time() . '_' . basename($_FILES['images']['name'][$index]);
            $targetPath = $upload_dir . $fileName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $is_main = ($existingImages == 0 && $index == 0) ? 1 : 0;
                $display_order = $existingImages + $index;

                $db->query("INSERT INTO product_images (product_id, image_path, is_main, display_order)
                            VALUES (:product_id, :image_path, :is_main, :display_order)", [
                    'product_id' => $product_id,
                    'image_path' => $targetPath,
                    'is_main' => $is_main,
                    'display_order' => $display_order
                ]);
            }
        }
    }

    header("Location: products.php");
    exit;
}

$images = $db->query("SELECT * FROM product_images WHERE product_id = :id ORDER BY display_order", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product</title>
</head>
<body>
<h2>Edit Product</h2>
<form method="post" enctype="multipart/form-data">
    Name (EN): <input type="text" name="name_en" value="<?php echo htmlspecialchars($product['name_en']); ?>" required><br><br>
    Name (AR): <input type="text" name="name_ar" value="<?php echo htmlspecialchars($product['name_ar']); ?>" required><br><br>
    Category ID: <input type="number" name="category_id" value="<?php echo $product['category_id']; ?>" required><br><br>
    Brand ID: <input type="number" name="brand_id" value="<?php echo $product['brand_id']; ?>"><br><br>
    Description (EN): <textarea name="description_en"><?php echo htmlspecialchars($product['description_en']); ?></textarea><br><br>
    Description (AR): <textarea name="description_ar"><?php echo htmlspecialchars($product['description_ar']); ?></textarea><br><br>
    Price: <input type="number" step="0.01" name="price" value="<?php echo $product['price']; ?>" required><br><br>
    Weight: <input type="number" name="weight" value="<?php echo $product['weight']; ?>"><br><br>
    Status: <select name="status">
        <option value="1" <?php if ($product['status'] == 1) echo 'selected'; ?>>Active</option>
        <option value="0" <?php if ($product['status'] == 0) echo 'selected'; ?>>Inactive</option>
    </select><br><br>

    Upload New Product Images:<br>
    <input type="file" name="images[]" multiple accept="image/*"><br><br>

    <input type="submit" value="Update Product">
</form>

<h3>Existing Images</h3>
<ul>
    <?php foreach ($images as $img): ?>
        <li>
            <img src="<?php echo str_replace('../', '', $img['image_path']); ?>" width="100">
            (Main: <?php echo $img['is_main']; ?> | Order: <?php echo $img['display_order']; ?>)
            <a href="delete_image.php?id=<?php echo $img['id']; ?>&product_id=<?php echo $product_id; ?>" onclick="return confirm('Delete this image?')">🗑 Delete</a>
        </li>
    <?php endforeach; ?>
</ul>

<p><a href="products.php">&larr; Back to Products</a></p>
</body>
</html>
<?php
// File: admin/edit_product.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$root_dir = dirname(dirname(__DIR__));

// Check if required files exist before including
$required_files = [
    $root_dir . '/includes/session_helper.php',
    $root_dir . '/config/config.php',
    $root_dir . '/includes/Database.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Required file not found: " . $file);
    }
}

require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

try {
    require_admin_login();
    
    $db = new Database();
    $errors = [];
    $success = false;
    $has_cost_price = false;

    try {
        $has_cost_price = (bool) $db->query("SHOW COLUMNS FROM products LIKE 'cost_price'")->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $has_cost_price = false;
    }

    // Get product ID
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($product_id <= 0) {
        header("Location: products.php");
        exit;
    }

    // Fetch product data
    try {
        $product = $db->query("SELECT * FROM products WHERE id = :id", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error fetching product: " . $e->getMessage());
        die("Database error: " . htmlspecialchars($e->getMessage()));
    }

    if (!$product) {
        header("Location: products.php");
        exit;
    }

    // Fetch categories
    try {
        $categories = $db->query("SELECT id, name_en, name_ar FROM categories ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error fetching categories: " . $e->getMessage());
        $categories = [];
    }

    // Fetch brands
    try {
        $brands = $db->query("SELECT id, name_en, name_ar FROM brands ORDER BY name_en ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error fetching brands: " . $e->getMessage());
        $brands = [];
    }

    // Fetch existing images
    try {
        $images = $db->query("SELECT * FROM product_images WHERE product_id = :id ORDER BY display_order ASC, id ASC", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error fetching images: " . $e->getMessage());
        $images = [];
    }

    // Fetch existing variations
    try {
        $variations = $db->query(
            "SELECT pv.*, pi.image_path, pi.is_main
             FROM product_variations pv
             LEFT JOIN product_images pi ON pv.image_id = pi.id
             WHERE pv.product_id = :id
             ORDER BY pv.id ASC",
            ['id' => $product_id]
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error fetching variations: " . $e->getMessage());
        $variations = [];
    }

    $normalize_variation = static function (array $variation, array $valid_image_ids): array {
        $image_id = isset($variation['image_id']) ? (int)$variation['image_id'] : 0;
        if ($image_id <= 0 || !in_array($image_id, $valid_image_ids, true)) {
            $image_id = null;
        }

        $additional_price_input = trim((string)($variation['additional_price'] ?? '0'));
        $stock_input = trim((string)($variation['stock'] ?? '0'));

        return [
            'size' => ($size = trim((string)($variation['size'] ?? ''))) !== '' ? $size : null,
            'color' => ($color = trim((string)($variation['color'] ?? ''))) !== '' ? $color : null,
            'additional_price' => $additional_price_input === '' ? 0.00 : (float)$additional_price_input,
            'stock' => max(0, $stock_input === '' ? 0 : (int)$stock_input),
            'image_id' => $image_id
        ];
    };

    $variation_has_content = static function (array $variation): bool {
        return !empty($variation['size'])
            || !empty($variation['color'])
            || !empty($variation['image_id'])
            || (float)$variation['additional_price'] != 0.0
            || (int)$variation['stock'] > 0;
    };

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate input
        $name_en = trim($_POST['name_en'] ?? '');
        $name_ar = trim($_POST['name_ar'] ?? '');
        $description_en = trim($_POST['description_en'] ?? '');
        $description_ar = trim($_POST['description_ar'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $cost_price_input = trim($_POST['cost_price'] ?? '');
        $cost_price = ($cost_price_input === '') ? null : floatval($cost_price_input);
        $stock = intval($_POST['stock'] ?? 0);
        $weight = floatval($_POST['weight'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $brand_id = intval($_POST['brand_id'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;

        // Validation
        if (empty($name_en)) $errors[] = "English name is required";
        if (empty($name_ar)) $errors[] = "Arabic name is required";
        if ($price <= 0) $errors[] = "Price must be greater than 0";
        if (!is_null($cost_price) && $cost_price < 0) $errors[] = "Cost price cannot be negative";
        if ($stock < 0) $errors[] = "Stock cannot be negative";
        if ($weight < 0) $errors[] = "Weight cannot be negative";
        if ($category_id <= 0) $errors[] = "Please select a category";
        if ($brand_id <= 0) $errors[] = "Please select a brand";

        if (empty($errors)) {
            try {
                // Update product
                $update_fields = [
                    'name_en = :name_en',
                    'name_ar = :name_ar',
                    'description_en = :description_en',
                    'description_ar = :description_ar',
                    'price = :price',
                    'stock = :stock',
                    'weight = :weight',
                    'category_id = :category_id',
                    'brand_id = :brand_id',
                    'status = :status',
                    'featured = :featured'
                ];

                $update_params = [
                    'name_en' => $name_en,
                    'name_ar' => $name_ar,
                    'description_en' => $description_en,
                    'description_ar' => $description_ar,
                    'price' => $price,
                    'stock' => $stock,
                    'weight' => $weight,
                    'category_id' => $category_id,
                    'brand_id' => $brand_id,
                    'status' => $status,
                    'featured' => $featured,
                    'id' => $product_id
                ];

                if ($has_cost_price) {
                    $update_fields[] = 'cost_price = :cost_price';
                    $update_params['cost_price'] = $cost_price;
                }

                $db->query(
                    "UPDATE products SET " . implode(', ', $update_fields) . " WHERE id = :id",
                    $update_params
                );

                // Handle image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $upload_dir = $root_dir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR;
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Check if there are any existing images
                    $existing_images_count = $db->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = :id", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC)['count'];
                    $has_main_image = $db->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = :id AND is_main = TRUE", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                    
                    $uploaded_count = 0;
                    foreach ($_FILES['images']['name'] as $key => $filename) {
                        if (!empty($filename)) {
                            $file_tmp = $_FILES['images']['tmp_name'][$key];
                            $file_size = $_FILES['images']['size'][$key];
                            $file_error = $_FILES['images']['error'][$key];

                            if ($file_error === UPLOAD_ERR_OK) {
                                $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                                if (in_array($file_ext, $allowed_types) && $file_size <= 5000000) {
                                    $new_filename = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                                    $upload_path = $upload_dir . $new_filename;

                                    if (move_uploaded_file($file_tmp, $upload_path)) {
                                        try {
                                            // Determine if this should be the main image
                                            $is_main = (!$has_main_image && $existing_images_count == 0 && $uploaded_count == 0);
                                            
                                            // Get the next display order
                                            $max_order = $db->query("SELECT COALESCE(MAX(display_order), 0) as max_order FROM product_images WHERE product_id = :id", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC)['max_order'];
                                            $display_order = $max_order + $uploaded_count + 1;
                                            
                                            $db->query("
                                                INSERT INTO product_images (product_id, image_path, is_main, display_order) 
                                                VALUES (:product_id, :image_path, :is_main, :display_order)
                                            ", [
                                                'product_id' => $product_id,
                                                'image_path' => 'uploads/products/' . $new_filename,
                                                'is_main' => $is_main,
                                                'display_order' => $display_order
                                            ]);
                                            
                                            $uploaded_count++;
                                            if ($is_main) {
                                                $has_main_image = true;
                                            }
                                        } catch (Exception $e) {
                                            error_log("Database error inserting image: " . $e->getMessage());
                                            // Delete uploaded file if database insert fails
                                            if (file_exists($upload_path)) {
                                                unlink($upload_path);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Handle main image selection
                if (!empty($_POST['main_image'])) {
                    $main_image_id = (int)$_POST['main_image'];
                    
                    try {
                        // First, remove main flag from all images of this product
                        $db->query("UPDATE product_images SET is_main = FALSE WHERE product_id = :product_id", ['product_id' => $product_id]);
                        
                        // Then set the selected image as main
                        $db->query("UPDATE product_images SET is_main = TRUE WHERE id = :image_id AND product_id = :product_id", [
                            'image_id' => $main_image_id,
                            'product_id' => $product_id
                        ]);
                    } catch (Exception $e) {
                        error_log("Error updating main image: " . $e->getMessage());
                    }
                }

                // Handle image deletions
                if (!empty($_POST['delete_images'])) {
                    foreach ($_POST['delete_images'] as $image_id) {
                        $image_id = (int)$image_id;
                        
                        try {
                            // Get image path and check if it's main before deleting
                            $image_data = $db->query("SELECT image_path, is_main FROM product_images WHERE id = :id", ['id' => $image_id])->fetch(PDO::FETCH_ASSOC);
                            
                            if ($image_data) {
                                // Remove image link from any product variants first
                                $db->query(
                                    "UPDATE product_variations SET image_id = NULL WHERE product_id = :product_id AND image_id = :image_id",
                                    [
                                        'product_id' => $product_id,
                                        'image_id' => $image_id
                                    ]
                                );

                                // Delete from database
                                $db->query("DELETE FROM product_images WHERE id = :id", ['id' => $image_id]);
                                
                                // Delete physical file
                                $file_path = $root_dir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $image_data['image_path'];
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                                
                                // If we deleted the main image, set the first remaining image as main
                                if ($image_data['is_main']) {
                                    $remaining_image = $db->query("SELECT id FROM product_images WHERE product_id = :product_id ORDER BY display_order ASC, id ASC LIMIT 1", ['product_id' => $product_id])->fetch(PDO::FETCH_ASSOC);
                                    if ($remaining_image) {
                                        $db->query("UPDATE product_images SET is_main = TRUE WHERE id = :id", ['id' => $remaining_image['id']]);
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error deleting image: " . $e->getMessage());
                        }
                    }
                }

                // Handle product variations (existing + new)
                $valid_image_ids = array_map(
                    'intval',
                    array_column(
                        $db->query("SELECT id FROM product_images WHERE product_id = :id", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC),
                        'id'
                    )
                );
                $delete_variation_ids = array_map('intval', $_POST['delete_variations'] ?? []);

                if (!empty($delete_variation_ids)) {
                    foreach ($delete_variation_ids as $variation_id) {
                        if ($variation_id > 0) {
                            $db->query(
                                "DELETE FROM product_variations WHERE id = :id AND product_id = :product_id",
                                [
                                    'id' => $variation_id,
                                    'product_id' => $product_id
                                ]
                            );
                        }
                    }
                }

                if (!empty($_POST['variations_existing'])) {
                    foreach ($_POST['variations_existing'] as $variation_id => $variation_data) {
                        $variation_id = (int)$variation_id;
                        if ($variation_id <= 0 || in_array($variation_id, $delete_variation_ids, true)) {
                            continue;
                        }

                        $normalized_variation = $normalize_variation((array)$variation_data, $valid_image_ids);

                        if ($variation_has_content($normalized_variation)) {
                            $db->query(
                                "UPDATE product_variations
                                 SET image_id = :image_id,
                                     size = :size,
                                     color = :color,
                                     additional_price = :additional_price,
                                     stock = :stock
                                 WHERE id = :id AND product_id = :product_id",
                                [
                                    'image_id' => $normalized_variation['image_id'],
                                    'size' => $normalized_variation['size'],
                                    'color' => $normalized_variation['color'],
                                    'additional_price' => $normalized_variation['additional_price'],
                                    'stock' => $normalized_variation['stock'],
                                    'id' => $variation_id,
                                    'product_id' => $product_id
                                ]
                            );
                        } else {
                            $db->query(
                                "DELETE FROM product_variations WHERE id = :id AND product_id = :product_id",
                                [
                                    'id' => $variation_id,
                                    'product_id' => $product_id
                                ]
                            );
                        }
                    }
                }

                if (!empty($_POST['variations_new'])) {
                    foreach ($_POST['variations_new'] as $variation_data) {
                        $normalized_variation = $normalize_variation((array)$variation_data, $valid_image_ids);

                        if (!$variation_has_content($normalized_variation)) {
                            continue;
                        }

                        $db->query(
                            "INSERT INTO product_variations (product_id, image_id, size, color, additional_price, stock)
                             VALUES (:product_id, :image_id, :size, :color, :additional_price, :stock)",
                            [
                                'product_id' => $product_id,
                                'image_id' => $normalized_variation['image_id'],
                                'size' => $normalized_variation['size'],
                                'color' => $normalized_variation['color'],
                                'additional_price' => $normalized_variation['additional_price'],
                                'stock' => $normalized_variation['stock']
                            ]
                        );
                    }
                }

                $success = true;
                
                // Refresh product data, images, and variations
                try {
                    $product = $db->query("SELECT * FROM products WHERE id = :id", ['id' => $product_id])->fetch(PDO::FETCH_ASSOC);
                    $images = $db->query("SELECT * FROM product_images WHERE product_id = :id ORDER BY display_order ASC, id ASC", ['id' => $product_id])->fetchAll(PDO::FETCH_ASSOC);
                    $variations = $db->query(
                        "SELECT pv.*, pi.image_path, pi.is_main
                         FROM product_variations pv
                         LEFT JOIN product_images pi ON pv.image_id = pi.id
                         WHERE pv.product_id = :id
                         ORDER BY pv.id ASC",
                        ['id' => $product_id]
                    )->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    error_log("Error refreshing data: " . $e->getMessage());
                }
                
            } catch (Exception $e) {
                error_log("Error updating product: " . $e->getMessage());
                $errors[] = "Error updating product: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Fatal error in edit_product.php: " . $e->getMessage());
    die("An error occurred: " . htmlspecialchars($e->getMessage()) . "<br><br>Check error logs for details.");
}
?>
<?php
$page_title = 'Edit Product';
require_once __DIR__ . '/includes/header.php';
?>
<style>
        .edit-product-page { background-color: #f8f9fc; }
        .form-container { background: white; border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); padding: 2rem; }
        .image-preview { width: 100px; height: 100px; object-fit: cover; border-radius: 4px; }
        .image-item { position: relative; display: inline-block; margin: 5px; }
        .image-item.main-image { border: 3px solid #28a745; border-radius: 8px; padding: 2px; }
        .delete-image { position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; cursor: pointer; }
        .main-image-badge { position: absolute; top: -8px; left: -8px; background: #28a745; color: white; border-radius: 50%; width: 24px; height: 24px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .main-image-radio { position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); }
        .image-controls { margin-top: 10px; text-align: center; }
        .variation-card {
            border: 1px solid #dfe3e8;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fafbff;
        }
        .variation-card.is-new {
            border-style: dashed;
            border-color: #86b7fe;
            background: #f8fbff;
        }
        .variation-card__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        .variation-preview {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.75rem;
            padding: 0.5rem 0.75rem;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .variation-preview img {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 8px;
        }
        .variation-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 1rem;
            background: #fff;
            color: #6c757d;
        }
    </style>
    <div class="edit-product-page container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Edit Product</h2>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Products
                        </a>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        Product updated successfully!
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name_en" class="form-label">Product Name (English) *</label>
                                <input type="text" class="form-control" id="name_en" name="name_en" 
                                       value="<?php echo htmlspecialchars($product['name_en']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="name_ar" class="form-label">Product Name (Arabic) *</label>
                                <input type="text" class="form-control" id="name_ar" name="name_ar" 
                                       value="<?php echo htmlspecialchars($product['name_ar']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name_en']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="brand_id" class="form-label">Brand *</label>
                                <select class="form-select" id="brand_id" name="brand_id" required>
                                    <option value="">Select Brand</option>
                                    <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand['id']; ?>" 
                                            <?php echo $product['brand_id'] == $brand['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($brand['name_en']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="price" class="form-label">Price (AED) *</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo $product['price']; ?>" step="0.01" min="0" required>
                            </div>
                            <?php if ($has_cost_price): ?>
                            <div class="col-md-4 mb-3">
                                <label for="cost_price" class="form-label">Cost Price (AED) <small class="text-muted">(Optional)</small></label>
                                <input type="number" class="form-control" id="cost_price" name="cost_price"
                                       value="<?php echo isset($product['cost_price']) && $product['cost_price'] !== null ? htmlspecialchars($product['cost_price']) : ''; ?>"
                                       step="0.01" min="0" placeholder="Internal cost">
                            </div>
                            <?php endif; ?>
                            <div class="col-md-4 mb-3">
                                <label for="stock" class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                       value="<?php echo $product['stock']; ?>" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="weight" class="form-label">Weight (kg)</label>
                                <input type="number" class="form-control" id="weight" name="weight" 
                                       value="<?php echo $product['weight'] ?? 0; ?>" step="0.001" min="0" 
                                       placeholder="0.000">
                                <div class="form-text">Product weight in kilograms (for shipping calculations)</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description (English)</label>
                            <textarea class="form-control" id="description_en" name="description_en" rows="4"><?php echo htmlspecialchars($product['description_en']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="description_ar" class="form-label">Description (Arabic)</label>
                            <textarea class="form-control" id="description_ar" name="description_ar" rows="4"><?php echo htmlspecialchars($product['description_ar']); ?></textarea>
                        </div>

                        <!-- Existing Images -->
                        <?php if (!empty($images)): ?>
                        <div class="mb-3">
                            <label class="form-label">Current Images</label>
                            <div class="existing-images">
                                <?php foreach ($images as $image): ?>
                                <div class="image-item <?php echo $image['is_main'] ? 'main-image' : ''; ?>">
                                    <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         alt="Product Image" class="image-preview">
                                    
                                    <?php if ($image['is_main']): ?>
                                    <div class="main-image-badge" title="Main Image">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="delete-image" 
                                            onclick="markForDeletion(<?php echo $image['id']; ?>, this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    
                                    <div class="main-image-radio">
                                        <input type="radio" name="main_image" value="<?php echo $image['id']; ?>" 
                                               <?php echo $image['is_main'] ? 'checked' : ''; ?>
                                               title="Set as main image">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="image-controls">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Select a radio button to set the main image. The main image is highlighted with a green border and star.
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="images" class="form-label">Add New Images</label>
                            <input type="file" class="form-control" id="images" name="images[]" 
                                   accept="image/*" multiple>
                            <div class="form-text">Select multiple images. Max 5MB each. Supported: JPG, PNG, GIF, WebP</div>
                        </div>

                        <!-- Product Variations Section -->
                        <div class="mb-4">
                            <label class="form-label">Product Variations</label>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Link Images to Variations:</strong> Each variant can have its own size, color/name, stock, extra price, and linked image.
                                You can auto-generate variants from images, then edit them here.
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button type="button" class="btn btn-primary" id="autoCreateVariantsBtn">
                                    <i class="fas fa-magic me-2"></i>Auto-Generate Variants from Images
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="addVariationBtn">
                                    <i class="fas fa-plus me-2"></i>Add Manual Variant
                                </button>
                            </div>

                            <div id="variantCreationResult" class="mt-2"></div>

                            <div class="variation-editor">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Current Variants (<?php echo count($variations); ?>)</h6>
                                    <small class="text-muted">Changes are saved when you click <strong>Update Product</strong>.</small>
                                </div>

                                <?php if (!empty($variations)): ?>
                                    <div id="existingVariationsContainer">
                                        <?php foreach ($variations as $variation): ?>
                                            <div class="variation-card" data-existing-variation="<?php echo (int)$variation['id']; ?>">
                                                <div class="variation-card__header">
                                                    <span class="badge bg-primary">Variant #<?php echo (int)$variation['id']; ?></span>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="markVariationForDeletion(<?php echo (int)$variation['id']; ?>, this)">
                                                        <i class="fas fa-trash me-1"></i>Remove
                                                    </button>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-md-2">
                                                        <label class="form-label">Size</label>
                                                        <input type="text" class="form-control" name="variations_existing[<?php echo (int)$variation['id']; ?>][size]"
                                                               value="<?php echo htmlspecialchars($variation['size'] ?? ''); ?>" placeholder="S, M, L">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Color / Name</label>
                                                        <input type="text" class="form-control" name="variations_existing[<?php echo (int)$variation['id']; ?>][color]"
                                                               value="<?php echo htmlspecialchars($variation['color'] ?? ''); ?>" placeholder="Red / Gold / Main Image Variant">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Extra Price</label>
                                                        <input type="number" step="0.01" class="form-control" name="variations_existing[<?php echo (int)$variation['id']; ?>][additional_price]"
                                                               value="<?php echo htmlspecialchars((string)($variation['additional_price'] ?? '0.00')); ?>">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">Variant Stock</label>
                                                        <input type="number" min="0" class="form-control" name="variations_existing[<?php echo (int)$variation['id']; ?>][stock]"
                                                               value="<?php echo htmlspecialchars((string)($variation['stock'] ?? '0')); ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Linked Image</label>
                                                        <select class="form-select variant-image-select" name="variations_existing[<?php echo (int)$variation['id']; ?>][image_id]">
                                                            <option value="">No linked image</option>
                                                            <?php foreach ($images as $image_option): ?>
                                                                <option value="<?php echo (int)$image_option['id']; ?>" <?php echo ((int)($variation['image_id'] ?? 0) === (int)$image_option['id']) ? 'selected' : ''; ?>>
                                                                    Image #<?php echo (int)$image_option['id']; ?><?php echo !empty($image_option['is_main']) ? ' (Main)' : ''; ?> - <?php echo htmlspecialchars(basename($image_option['image_path'])); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <?php if (!empty($variation['image_path'])): ?>
                                                    <div class="variation-preview">
                                                        <img src="../<?php echo htmlspecialchars($variation['image_path']); ?>" alt="Variant image preview">
                                                        <div>
                                                            <div class="fw-semibold">Currently linked image<?php echo !empty($variation['is_main']) ? ' (Main)' : ''; ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars(basename($variation['image_path'])); ?></small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="variation-empty mb-3">
                                        <i class="fas fa-layer-group me-2"></i>No variants created yet. Use <strong>Auto-Generate</strong> or <strong>Add Manual Variant</strong>.
                                    </div>
                                <?php endif; ?>

                                <div id="newVariationsContainer"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="status" name="status" 
                                           <?php echo $product['status'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status">
                                        Active (Visible to customers)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="featured" name="featured" 
                                           <?php echo $product['featured'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="featured">
                                        Featured Product
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const variationImages = <?php echo json_encode(array_map(static function ($image) {
            return [
                'id' => (int)$image['id'],
                'label' => 'Image #' . (int)$image['id'] . (!empty($image['is_main']) ? ' (Main)' : '') . ' - ' . basename((string)$image['image_path'])
            ];
        }, $images), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        let newVariationCount = 0;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(char) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
            });
        }

        function buildVariationImageOptions(selectedId = '') {
            const normalizedSelected = String(selectedId || '');
            let options = '<option value="">No linked image</option>';

            variationImages.forEach(function(image) {
                const isSelected = String(image.id) === normalizedSelected ? 'selected' : '';
                options += `<option value="${image.id}" ${isSelected}>${escapeHtml(image.label)}</option>`;
            });

            return options;
        }

        function addVariationRow(defaults = {}) {
            newVariationCount += 1;
            const container = document.getElementById('newVariationsContainer');
            if (!container) {
                return;
            }

            const row = document.createElement('div');
            row.className = 'variation-card is-new';
            row.innerHTML = `
                <div class="variation-card__header">
                    <span class="badge bg-info text-dark">New Variant</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeNewVariation(this)">
                        <i class="fas fa-times me-1"></i>Remove
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Size</label>
                        <input type="text" class="form-control" name="variations_new[${newVariationCount}][size]" value="${escapeHtml(defaults.size || '')}" placeholder="S, M, L">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Color / Name</label>
                        <input type="text" class="form-control" name="variations_new[${newVariationCount}][color]" value="${escapeHtml(defaults.color || '')}" placeholder="Red / Gold">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Extra Price</label>
                        <input type="number" step="0.01" class="form-control" name="variations_new[${newVariationCount}][additional_price]" value="${escapeHtml(defaults.additional_price || '0.00')}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Variant Stock</label>
                        <input type="number" min="0" class="form-control" name="variations_new[${newVariationCount}][stock]" value="${escapeHtml(defaults.stock || '<?php echo (int)$product['stock']; ?>')}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Linked Image</label>
                        <select class="form-select variant-image-select" name="variations_new[${newVariationCount}][image_id]">
                            ${buildVariationImageOptions(defaults.image_id || '')}
                        </select>
                    </div>
                </div>
            `;

            container.appendChild(row);
        }

        function removeNewVariation(button) {
            button.closest('.variation-card')?.remove();
        }

        function markVariationForDeletion(variationId, button) {
            if (!confirm('Remove this variant when you save the product?')) {
                return;
            }

            const form = document.querySelector('form');
            if (form && !form.querySelector(`input[name="delete_variations[]"][value="${variationId}"]`)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_variations[]';
                input.value = variationId;
                form.appendChild(input);
            }

            const card = button.closest('[data-existing-variation]');
            if (card) {
                card.style.display = 'none';
            }
        }

        document.getElementById('addVariationBtn')?.addEventListener('click', function() {
            addVariationRow();
        });

        // Auto-create variants button handler
        document.getElementById('autoCreateVariantsBtn')?.addEventListener('click', function() {
            const btn = this;
            const resultDiv = document.getElementById('variantCreationResult');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating variants...';
            resultDiv.innerHTML = '';
            
            fetch('ajax/auto_create_variants.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=<?php echo $product_id; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `<div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>${data.message}<br><small>Reloading the page to show the variants...</small>
                    </div>`;
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>${data.message}
                    </div>`;
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic me-2"></i>Auto-Generate Variants from Images';
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Error: ${error.message}
                </div>`;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic me-2"></i>Auto-Generate Variants from Images';
            });
        });

        function markForDeletion(imageId, button) {
            if (confirm('Are you sure you want to delete this image?')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_images[]';
                input.value = imageId;
                document.querySelector('form').appendChild(input);
                
                const imageItem = button.parentElement;
                imageItem.style.opacity = '0.3';
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.disabled = true;
                button.title = 'Marked for deletion';

                document.querySelectorAll('.variant-image-select').forEach(function(select) {
                    if (select.value === String(imageId)) {
                        select.value = '';
                    }
                });
                
                // Disable the main image radio button for this image
                const radioButton = imageItem.querySelector('input[type="radio"]');
                if (radioButton) {
                    radioButton.disabled = true;
                    
                    // If this was the main image, suggest selecting another one
                    if (radioButton.checked) {
                        alert('You are deleting the main image. Please select another image as the main image before saving.');
                        
                        // Try to select the first non-deleted image as main
                        const otherRadios = document.querySelectorAll('input[name="main_image"]:not(:disabled)');
                        if (otherRadios.length > 0) {
                            otherRadios[0].checked = true;
                        }
                    }
                }
            }
        }

        // Ensure at least one main image is selected
        document.addEventListener('DOMContentLoaded', function() {
            const mainImageRadios = document.querySelectorAll('input[name="main_image"]');
            const form = document.querySelector('form');
            
            if (form && mainImageRadios.length > 0) {
                form.addEventListener('submit', function(e) {
                    const checkedRadio = document.querySelector('input[name="main_image"]:checked:not(:disabled)');
                    if (!checkedRadio && mainImageRadios.length > 0) {
                        e.preventDefault();
                        alert('Please select a main image before saving.');
                        return false;
                    }
                });
            }
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
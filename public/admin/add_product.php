<?php
// File: admin/add_product.php
require_once('../../config/config.php');
require_once('../../includes/Database.php');

$db = new Database();

// Fetch categories and brands for dropdowns
$categories = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY name_en")->fetchAll(PDO::FETCH_ASSOC);
$brands = $db->query("SELECT * FROM brands WHERE status = 1 ORDER BY name_en")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_en = $_POST['name_en'];
    $name_ar = $_POST['name_ar'];
    $category_id = $_POST['category_id'];
    $brand_id = !empty($_POST['brand_id']) ? $_POST['brand_id'] : null;
    $description_en = $_POST['description_en'];
    $description_ar = $_POST['description_ar'];
    $price = $_POST['price'];
    $stock = !empty($_POST['stock']) ? $_POST['stock'] : 100;
    $weight = $_POST['weight'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = $_POST['status'];

    $db->query("INSERT INTO products (name_en, name_ar, category_id, brand_id, description_en, description_ar, price, stock, weight, featured, status) 
                VALUES (:name_en, :name_ar, :category_id, :brand_id, :description_en, :description_ar, :price, :stock, :weight, :featured, :status)", [
        'name_en' => $name_en,
        'name_ar' => $name_ar,
        'category_id' => $category_id,
        'brand_id' => $brand_id,
        'description_en' => $description_en,
        'description_ar' => $description_ar,
        'price' => $price,
        'stock' => $stock,
        'weight' => $weight,
        'featured' => $featured,
        'status' => $status
    ]);

    $product_id = $db->lastInsertId();

    // Upload images
    $upload_dir = '../uploads/products/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
            if (!empty($tmpName)) {
                $fileName = time() . '_' . $index . '_' . basename($_FILES['images']['name'][$index]);
                $targetPath = $upload_dir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $is_main = ($index == 0) ? 1 : 0;
                    $db->query("INSERT INTO product_images (product_id, image_path, is_main, display_order)
                                VALUES (:product_id, :image_path, :is_main, :display_order)", [
                        'product_id' => $product_id,
                        'image_path' => 'uploads/products/' . $fileName,
                        'is_main' => $is_main,
                        'display_order' => $index
                    ]);
                }
            }
        }
    }

    header("Location: products.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            max-width: 800px;
            margin: 0 auto;
        }
        .form-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .form-section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 1.1rem;
            color: #495057;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .language-tabs {
            margin-bottom: 15px;
        }
        .language-content {
            display: none;
        }
        .language-content.active {
            display: block;
        }
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .image-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h2 class="mb-0">Add New Product</h2>
        </div>

        <form method="post" enctype="multipart/form-data">
            <!-- Basic Information Section -->
            <div class="form-section">
                <h5 class="section-title">Basic Information</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="" disabled selected>Select category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name_en']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="brand_id" class="form-label">Brand (optional)</label>
                        <select class="form-select" id="brand_id" name="brand_id">
                            <option value="">No brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name_en']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="price" class="form-label">Price *</label>
                        <div class="input-group">
                            <span class="input-group-text">AED</span>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="stock" class="form-label">Stock Quantity</label>
                        <input type="number" class="form-control" id="stock" name="stock" value="100" min="0">
                    </div>
                    <div class="col-md-4">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="number" step="0.01" class="form-control" id="weight" name="weight" value="1">
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Featured Product</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="featured" name="featured">
                            <label class="form-check-label" for="featured">Mark as featured</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multilingual Content Section -->
            <div class="form-section">
                <h5 class="section-title">Product Details</h5>
                
                <ul class="nav nav-tabs language-tabs" id="languageTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="en-tab" data-bs-toggle="tab" data-bs-target="#en-content" type="button" role="tab">English</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ar-tab" data-bs-toggle="tab" data-bs-target="#ar-content" type="button" role="tab">العربية</button>
                    </li>
                </ul>
                
                <div class="tab-content p-3 border border-top-0 rounded-bottom">
                    <div class="tab-pane fade show active" id="en-content" role="tabpanel">
                        <div class="mb-3">
                            <label for="name_en" class="form-label">Product Name *</label>
                            <input type="text" class="form-control" id="name_en" name="name_en" required>
                        </div>
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description</label>
                            <textarea class="form-control" id="description_en" name="description_en" rows="4"></textarea>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="ar-content" role="tabpanel">
                        <div class="mb-3">
                            <label for="name_ar" class="form-label">اسم المنتج *</label>
                            <input type="text" class="form-control text-end" id="name_ar" name="name_ar" required dir="rtl">
                        </div>
                        <div class="mb-3">
                            <label for="description_ar" class="form-label">الوصف</label>
                            <textarea class="form-control text-end" id="description_ar" name="description_ar" rows="4" dir="rtl"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Images Section -->
            <div class="form-section">
                <h5 class="section-title">Product Images</h5>
                <div class="mb-3">
                    <label for="images" class="form-label">Upload Images (Multiple selection allowed)</label>
                    <input class="form-control" type="file" id="images" name="images[]" multiple accept="image/*">
                    <div class="form-text">Upload high-quality product images. First image will be used as main image.</div>
                </div>
                <div class="image-preview-container" id="imagePreviewContainer">
                    <!-- Image previews will appear here -->
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-outline-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview functionality
        document.getElementById('images').addEventListener('change', function(event) {
            const previewContainer = document.getElementById('imagePreviewContainer');
            previewContainer.innerHTML = '';
            
            if (this.files) {
                Array.from(this.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.classList.add('image-preview');
                        previewContainer.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                });
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name_en = document.getElementById('name_en').value.trim();
            const nameAr = document.getElementById('name_ar').value.trim();
            const price = document.getElementById('price').value;
            
            if (!name_en || !nameAr) {
                e.preventDefault();
                alert('Please fill in product names in both languages');
                return;
            }
            
            if (!price || price <= 0) {
                e.preventDefault();
                alert('Please enter a valid price');
                return;
            }
        });
    </script>
</body>
</html>
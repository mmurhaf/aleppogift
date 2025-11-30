<?php
// File: admin/add_product.php
require_once('../../config/config.php');
require_once('../../includes/Database.php');

$db = new Database();
$error_message = '';
$success_message = '';

// Load categories and brands for form
try {
    $categories = $db->query("SELECT id, name_en, name_ar FROM categories WHERE status = 1 ORDER BY name_en ASC")->fetchAll();
    $brands = $db->query("SELECT id, name_en, name_ar FROM brands WHERE status = 1 ORDER BY name_en ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $categories = [];
    $brands = [];
    $error_message = "Error loading form data. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $name_en = trim($_POST['name_en']);
        $name_ar = trim($_POST['name_ar']);
        $category_id = $_POST['category_id'];
        $brand_id = !empty($_POST['brand_id']) ? $_POST['brand_id'] : null;
        $description_en = trim($_POST['description_en']);
        $description_ar = trim($_POST['description_ar']);
        $price = $_POST['price'];
        $weight = $_POST['weight'];
        $stock = $_POST['stock'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        $status = $_POST['status'];

        // Validation
        if (empty($name_en) || empty($name_ar) || empty($category_id) || empty($price)) {
            throw new Exception("Please fill all required fields.");
        }

        if (!is_numeric($price) || $price <= 0) {
            throw new Exception("Price must be a positive number.");
        }

        if (!is_numeric($weight) || $weight <= 0) {
            throw new Exception("Weight must be a positive number.");
        }

        if (!is_numeric($stock) || $stock < 0) {
            throw new Exception("Stock must be a non-negative number.");
        }

        // Insert product
        $product_id = $db->query("INSERT INTO products (name_en, name_ar, category_id, brand_id, description_en, description_ar, price, weight, stock, featured, status) 
                    VALUES (:name_en, :name_ar, :category_id, :brand_id, :description_en, :description_ar, :price, :weight, :stock, :featured, :status)", [
            'name_en' => $name_en,
            'name_ar' => $name_ar,
            'category_id' => $category_id,
            'brand_id' => $brand_id,
            'description_en' => $description_en,
            'description_ar' => $description_ar,
            'price' => $price,
            'weight' => $weight,
            'stock' => $stock,
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
                if (!empty($tmpName) && is_uploaded_file($tmpName)) {
                    $fileName = time() . '_' . $index . '_' . basename($_FILES['images']['name'][$index]);
                    $targetPath = $upload_dir . $fileName;

                    // Validate image file
                    $imageInfo = getimagesize($tmpName);
                    if ($imageInfo === false) {
                        throw new Exception("File " . $_FILES['images']['name'][$index] . " is not a valid image.");
                    }

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $is_main = ($index == 0) ? 1 : 0;
                        $display_order = $index;

                        // Store relative path from web root
                        $relative_path = 'uploads/products/' . $fileName;

                        $db->query("INSERT INTO product_images (product_id, image_path, is_main, display_order)
                                    VALUES (:product_id, :image_path, :is_main, :display_order)", [
                            'product_id' => $product_id,
                            'image_path' => $relative_path,
                            'is_main' => $is_main,
                            'display_order' => $display_order
                        ]);
                    } else {
                        throw new Exception("Failed to upload image: " . $_FILES['images']['name'][$index]);
                    }
                }
            }
        }

        // Handle product variations (colors, sizes)
        if (!empty($_POST['variations'])) {
            foreach ($_POST['variations'] as $variation) {
                $size = !empty($variation['size']) ? trim($variation['size']) : null;
                $color = !empty($variation['color']) ? trim($variation['color']) : null;
                $additional_price = !empty($variation['additional_price']) ? floatval($variation['additional_price']) : 0.00;
                $variation_stock = !empty($variation['stock']) ? intval($variation['stock']) : 0;

                // Only insert if at least size or color is provided
                if ($size || $color) {
                    $db->query("INSERT INTO product_variations (product_id, size, color, additional_price, stock)
                                VALUES (:product_id, :size, :color, :additional_price, :stock)", [
                        'product_id' => $product_id,
                        'size' => $size,
                        'color' => $color,
                        'additional_price' => $additional_price,
                        'stock' => $variation_stock
                    ]);
                }
            }
        }

        $success_message = "Product added successfully!";
        // Redirect after 2 seconds
        header("refresh:2;url=products.php");
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Product creation error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .variation-row {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid #0d6efd;
        }
        .variation-row:hover {
            background: #e9ecef;
        }
        .remove-variation {
            cursor: pointer;
            color: #dc3545;
        }
        .remove-variation:hover {
            color: #bb2d3b;
        }
        .add-variation-btn {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h2 class="mb-0">Add New Product</h2>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                <br><small>Redirecting to products page...</small>
            </div>
        <?php endif; ?>

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
                                <option value="<?= htmlspecialchars($category['id']) ?>">
                                    <?= htmlspecialchars($category['name_en']) ?> (<?= htmlspecialchars($category['name_ar']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="brand_id" class="form-label">Brand (optional)</label>
                        <select class="form-select" id="brand_id" name="brand_id">
                            <option value="" selected>No brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= htmlspecialchars($brand['id']) ?>">
                                    <?= htmlspecialchars($brand['name_en']) ?> (<?= htmlspecialchars($brand['name_ar']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="price" class="form-label">Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control" id="weight" name="weight" value="2">
                    </div>
                    <div class="col-md-6">
                        <label for="stock" class="form-label">Stock Quantity</label>
                        <input type="number" class="form-control" id="stock" name="stock" value="100" min="0">
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1">
                            <label class="form-check-label" for="featured">
                                Featured Product
                            </label>
                            <div class="form-text">Mark this product as featured to highlight it on the website</div>
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
                            <label for="name_en" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="name_en" name="name_en" required>
                        </div>
                        <div class="mb-3">
                            <label for="description_en" class="form-label">Description</label>
                            <textarea class="form-control" id="description_en" name="description_en" rows="4"></textarea>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="ar-content" role="tabpanel">
                        <div class="mb-3">
                            <label for="name_ar" class="form-label">اسم المنتج</label>
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
                    <label for="productImages" class="form-label">Upload Images (Multiple selection allowed)</label>
                    <input class="form-control" type="file" id="productImages" name="images[]" multiple accept="image/*">
                    <div class="form-text">Upload high-quality product images. First image will be used as main image.</div>
                </div>
                <div class="image-preview-container" id="imagePreviewContainer">
                    <!-- Image previews will appear here -->
                </div>
            </div>

            <!-- Product Variations Section -->
            <div class="form-section">
                <h5 class="section-title">Product Variations (Optional)</h5>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Add variations for products that come in different sizes, colors, or combinations. The variant ID is automatically generated.
                </div>
                
                <div id="variationsContainer">
                    <!-- Variation rows will be added here -->
                </div>
                
                <button type="button" class="btn btn-outline-primary add-variation-btn" id="addVariationBtn">
                    <i class="fas fa-plus me-2"></i>Add Variation
                </button>
            </div>

            <!-- Form Actions -->
            <div class="d-flex justify-content-between mt-4">
                <a href="products.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Products
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Product
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let variationCount = 0;

        // Add variation row
        document.getElementById('addVariationBtn').addEventListener('click', function() {
            variationCount++;
            const variationsContainer = document.getElementById('variationsContainer');
            
            const variationRow = document.createElement('div');
            variationRow.className = 'variation-row';
            variationRow.id = `variation-${variationCount}`;
            variationRow.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Variation #${variationCount}</h6>
                    <i class="fas fa-times remove-variation" onclick="removeVariation(${variationCount})"></i>
                </div>
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Size</label>
                        <input type="text" class="form-control" name="variations[${variationCount}][size]" placeholder="e.g., S, M, L, XL">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Color</label>
                        <input type="text" class="form-control" name="variations[${variationCount}][color]" placeholder="e.g., Red, Blue">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Additional Price ($)</label>
                        <input type="number" step="0.01" class="form-control" name="variations[${variationCount}][additional_price]" value="0.00" placeholder="0.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Stock</label>
                        <input type="number" class="form-control" name="variations[${variationCount}][stock]" value="0" min="0">
                    </div>
                </div>
            `;
            
            variationsContainer.appendChild(variationRow);
        });

        // Remove variation row
        function removeVariation(id) {
            const element = document.getElementById(`variation-${id}`);
            if (element) {
                element.remove();
            }
        }

        // Image preview functionality
        document.getElementById('productImages').addEventListener('change', function(event) {
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

        // Enhanced form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const nameEn = document.getElementById('name_en').value.trim();
            const nameAr = document.getElementById('name_ar').value.trim();
            const categoryId = document.getElementById('category_id').value;
            const price = document.getElementById('price').value;
            const weight = document.getElementById('weight').value;
            const stock = document.getElementById('stock').value;
            
            let errors = [];
            
            if (!nameEn) errors.push('English product name is required');
            if (!nameAr) errors.push('Arabic product name is required');
            if (!categoryId) errors.push('Category selection is required');
            if (!price || parseFloat(price) <= 0) errors.push('Valid price is required');
            if (!weight || parseFloat(weight) <= 0) errors.push('Valid weight is required');
            if (!stock || parseInt(stock) < 0) errors.push('Valid stock quantity is required');
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n• ' + errors.join('\n• '));
            }
        });
    </script>
</body>
</html>
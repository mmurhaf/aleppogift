<?php
/**
 * Bulk Image Upload - Quick Product Creation
 * Dubai Gift - Master Copy Brands E-commerce
 * Upload multiple product images and create products quickly
 */

$pageTitle = "Bulk Image Upload";
include 'header.php';

$uploadSuccess = [];
$uploadErrors = [];
$uploadSkipped = [];
$totalUploaded = 0;

// CSRF token (generate once per session)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    
    // Check if files were uploaded
    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        
        $files = $_FILES['product_images'];
        $fileCount = count($files['name']);
        
        // Upload directory
        $uploadDir = __DIR__ . '/../uploads/products/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Get the highest product ID for naming
        $lastProduct = $db->fetch("SELECT MAX(id) as max_id FROM products");
        $startNumber = ($lastProduct['max_id'] ?? 0) + 1;
        
        // Process each uploaded file
        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $files['name'][$i];
            $fileTmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileError = $files['error'][$i];
            
            // Skip if no file or error
            if ($fileError !== UPLOAD_ERR_OK || empty($fileName)) {
                if ($fileError !== UPLOAD_ERR_OK && $fileError !== UPLOAD_ERR_NO_FILE) {
                    $uploadErrors[] = "Error uploading '$fileName': Upload error code $fileError";
                }
                continue;
            }
            
            // Validate file type
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $uploadErrors[] = "File '$fileName' has invalid extension. Only JPG, PNG, GIF, WEBP allowed.";
                continue;
            }
            
            // Validate file size (max 5MB)
            if ($fileSize > 5 * 1024 * 1024) {
                $uploadErrors[] = "File '$fileName' is too large. Maximum size is 5MB.";
                continue;
            }
            
            // Validate image
            $imageInfo = @getimagesize($fileTmpName);
            if ($imageInfo === false) {
                $uploadErrors[] = "File '$fileName' is not a valid image.";
                continue;
            }
            
            // Check for duplicate image by comparing file hash with existing images
            $fileHash = md5_file($fileTmpName);
            
            // Get all existing product images to check for duplicates
            $existingImages = $db->fetchAll("SELECT id, product_id, image_path FROM product_images");
            $duplicateFound = false;
            $duplicateProduct = null;
            
            foreach ($existingImages as $existingImg) {
                $existingFilePath = __DIR__ . '/../' . $existingImg['image_path'];
                
                // Check if existing file exists and compare hashes
                if (file_exists($existingFilePath)) {
                    $existingHash = md5_file($existingFilePath);
                    
                    if ($existingHash === $fileHash) {
                        // Duplicate found - get product details
                        $duplicateProduct = $db->fetch(
                            "SELECT id, name_en FROM products WHERE id = ?",
                            [$existingImg['product_id']]
                        );
                        $duplicateFound = true;
                        break;
                    }
                }
            }
            
            // Skip if duplicate found
            if ($duplicateFound) {
                $uploadSkipped[] = [
                    'file' => $fileName,
                    'reason' => 'Duplicate image content',
                    'existing_product' => $duplicateProduct['name_en'] ?? 'Unknown',
                    'product_id' => $duplicateProduct['id'] ?? 0
                ];
                continue;
            }
            
            try {
                // Generate unique filename
                $newFileName = 'product_' . time() . '_' . $startNumber . '.' . $fileExtension;
                $targetPath = $uploadDir . $newFileName;
                $dbImagePath = 'uploads/products/' . $newFileName;
                
                // Move uploaded file
                if (!move_uploaded_file($fileTmpName, $targetPath)) {
                    $uploadErrors[] = "Failed to move file '$fileName' to upload directory.";
                    continue;
                }
                
                // Create product record
                $db->beginTransaction();
                
                // Generate SKU
                $sku = 'SKU' . str_pad($startNumber, 6, '0', STR_PAD_LEFT);
                
                // Product default values
                $productName = "New Product #" . $startNumber;
                $productDescription = "Product uploaded on " . date('Y-m-d H:i:s') . ". Please update details.";
                
                // Insert product
                $productSql = "INSERT INTO products (
                    name_en, 
                    name_ar, 
                    description_en, 
                    description_ar, 
                    price, 
                    sku,
                    stock,
                    category_id,
                    brand_id,
                    featured, 
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $productParams = [
                    $productName,                           // name_en
                    $productName,                           // name_ar (same as English for now)
                    $productDescription,                     // description_en
                    $productDescription,                     // description_ar
                    499.00,                                 // price (default as user specified)
                    $sku,                                   // sku
                    10,                                     // stock (default)
                    NULL,                                   // category_id (to be set later)
                    NULL,                                   // brand_id (to be set later)
                    0,                                      // featured (not featured by default)
                    1,                                      // status (active - visible to customers)
                ];
                
                // Insert and get product ID
                $db->query($productSql, $productParams);
                $productId = $db->lastInsertId();
                
                if (!$productId) {
                    throw new Exception("Database error: Unable to insert product");
                }
                
                // Insert into product_images table
                $imageSql = "INSERT INTO product_images (
                    product_id, 
                    image_path, 
                    is_main, 
                    display_order
                ) VALUES (?, ?, ?, ?)";
                
                $imageParams = [
                    $productId,
                    $dbImagePath,
                    1,  // is_main (first and only image is main)
                    1   // display_order
                ];
                
                $db->query($imageSql, $imageParams);
                $imageId = $db->lastInsertId();
                
                if (!$imageId) {
                    throw new Exception("Database error: Unable to insert product image");
                }
                
                $db->commit();
                
                // Success!
                $uploadSuccess[] = [
                    'id' => $productId,
                    'name' => $productName,
                    'sku' => $sku,
                    'image' => $newFileName,
                    'original' => $fileName
                ];
                
                $totalUploaded++;
                $startNumber++;
                
            } catch (Exception $e) {
                $db->rollback();
                $uploadErrors[] = "Failed to create product for '$fileName': " . $e->getMessage();
                
                // Delete uploaded file if product creation failed
                if (file_exists($targetPath)) {
                    unlink($targetPath);
                }
            }
        }
        
        // Set messages
        if ($totalUploaded > 0) {
            $success_message = "Successfully uploaded $totalUploaded product(s)!";
        }
        if (!empty($uploadSkipped)) {
            $skippedCount = count($uploadSkipped);
            $warning_message = "Skipped $skippedCount duplicate image(s).";
        }
        if (!empty($uploadErrors)) {
            $error_message = implode('<br>', $uploadErrors);
        }
    } else {
        $error_message = 'Please select at least one image to upload.';
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-cloud-upload-alt me-2"></i>
                        Bulk Image Upload - Quick Product Creation
                    </h5>
                    <div>
                        <a href="product-consolidate.php#productsMenu" class="btn btn-warning btn-sm me-2">
                            <i class="fas fa-object-group me-1"></i> Merge Products
                        </a>
                        <a href="products.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Products
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    
                    <!-- Alert Messages -->
                    <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($warning_message)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $warning_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Instructions -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="fas fa-info-circle me-2"></i>How it works:
                        </h6>
                        <ul class="mb-0">
                            <li>Upload multiple product images at once</li>
                            <li>Each image automatically creates a new product</li>
                            <li>Products are <strong>immediately visible</strong> to customers</li>
                            <li>Default price is set to <strong>AED 499</strong></li>
                            <li>You must edit each product later to add: name, description, category, brand, and actual price</li>
                            <li>Customers can click WhatsApp to inquire about products</li>
                            <li><strong>Pro Tip:</strong> If multiple images belong to the same product, use the 
                                <a href="product-consolidate.php#productsMenu" class="alert-link">Merge Products</a> tool to combine them!</li>
                        </ul>
                    </div>

                    <!-- Upload Results -->
                    <?php if (!empty($uploadSuccess)): ?>
                    <div class="alert alert-success">
                        <h6 class="alert-heading">
                            <i class="fas fa-check-circle me-2"></i>
                            Successfully Uploaded <?php echo $totalUploaded; ?> Product(s)!
                        </h6>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Name</th>
                                        <th>SKU</th>
                                        <th>Image</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($uploadSuccess as $product): ?>
                                    <tr>
                                        <td><?php echo $product['id']; ?></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                        <td>
                                            <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 style="max-width: 80px; max-height: 80px;"
                                                 class="img-thumbnail">
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['original']); ?></small>
                                        </td>
                                        <td>
                                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Edit Now
                                            </a>
                                            <a href="../product.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-info" 
                                               target="_blank">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Skipped Duplicates -->
                    <?php if (!empty($uploadSkipped)): ?>
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Skipped <?php echo count($uploadSkipped); ?> Duplicate Image(s)
                        </h6>
                        <p class="mb-2">The following images were not uploaded because they already exist in the database:</p>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Filename</th>
                                        <th>Reason</th>
                                        <th>Existing Product</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($uploadSkipped as $skipped): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($skipped['file']); ?></td>
                                        <td><span class="badge bg-warning"><?php echo htmlspecialchars($skipped['reason']); ?></span></td>
                                        <td><?php echo htmlspecialchars($skipped['existing_product']); ?></td>
                                        <td>
                                            <?php if ($skipped['product_id'] > 0): ?>
                                            <a href="edit_product.php?id=<?php echo $skipped['product_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View Existing
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" id="bulkUploadForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="mb-4">
                            <label for="product_images" class="form-label fw-bold">
                                <i class="fas fa-images me-2"></i>Select Product Images
                            </label>
                            <input type="file" 
                                   class="form-control form-control-lg" 
                                   id="product_images" 
                                   name="product_images[]" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                   multiple 
                                   required>
                            <div class="form-text">
                                <i class="fas fa-check-circle text-success me-1"></i>
                                Select multiple images (JPG, PNG, GIF, WEBP). Max 5MB per image.
                            </div>
                        </div>

                        <!-- Image Preview -->
                        <div id="imagePreview" class="row g-3 mb-4" style="display: none;">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2">Selected Images Preview:</h6>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-cloud-upload-alt me-2"></i>
                                Upload Images & Create Products
                            </button>
                        </div>
                    </form>

                    <!-- Recent Products (Need Editing) -->
                    <?php
                    $recentProducts = $db->fetchAll(
                        "SELECT p.*, pi.image_path 
                         FROM products p 
                         LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
                         WHERE p.category_id IS NULL OR p.brand_id IS NULL
                         ORDER BY p.created_at DESC 
                         LIMIT 10"
                    );
                    
                    if (!empty($recentProducts)):
                    ?>
                    <div class="mt-5">
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            Products Awaiting Details (<?php echo count($recentProducts); ?>)
                        </h6>
                        <div class="alert alert-warning">
                            <small>These products need category, brand, and other details updated.</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>SKU</th>
                                        <th>Price</th>
                                        <th>Category</th>
                                        <th>Brand</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentProducts as $product): ?>
                                    <tr>
                                        <td><?php echo $product['id']; ?></td>
                                        <td>
                                            <?php if (!empty($product['image_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" 
                                                 alt="Product" 
                                                 style="max-width: 80px; max-height: 80px;"
                                                 class="img-thumbnail">
                                            <?php else: ?>
                                            <span class="text-muted">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['name_en']); ?></td>
                                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                        <td>AED <?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <?php if (empty($product['category_id'])): ?>
                                            <span class="badge bg-danger">Not Set</span>
                                            <?php else: ?>
                                            <span class="badge bg-success">Set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (empty($product['brand_id'])): ?>
                                            <span class="badge bg-danger">Not Set</span>
                                            <?php else: ?>
                                            <span class="badge bg-success">Set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($product['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Script -->
<script>
document.getElementById('product_images').addEventListener('change', function(e) {
    const previewDiv = document.getElementById('imagePreview');
    const files = e.target.files;
    
    // Clear previous previews
    previewDiv.innerHTML = '<div class="col-12"><h6 class="border-bottom pb-2">Selected Images Preview (' + files.length + '):</h6></div>';
    
    if (files.length > 0) {
        previewDiv.style.display = 'flex';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const col = document.createElement('div');
                col.className = 'col-md-3 col-sm-4 col-6';
                col.innerHTML = `
                    <div class="card">
                        <img src="${e.target.result}" class="card-img-top" style="height: 150px; object-fit: cover;" alt="Preview">
                        <div class="card-body p-2">
                            <small class="text-muted d-block text-truncate">${file.name}</small>
                            <small class="text-muted">${(file.size / 1024).toFixed(2)} KB</small>
                        </div>
                    </div>
                `;
                previewDiv.appendChild(col);
            };
            
            reader.readAsDataURL(file);
        }
    } else {
        previewDiv.style.display = 'none';
    }
});

// Form submission confirmation
document.getElementById('bulkUploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('product_images');
    const fileCount = fileInput.files.length;
    
    if (fileCount > 0) {
        const confirmed = confirm(`You are about to upload ${fileCount} image(s) and create ${fileCount} product(s). Continue?`);
        if (!confirmed) {
            e.preventDefault();
        }
    }
});
</script>

<style>
.img-thumbnail {
    border: 2px solid #dee2e6;
    width: 80px;
    height: 80px;
    object-fit: cover;
}

.table-responsive {
    max-height: 500px;
    overflow-y: auto;
}

#imagePreview .card {
    transition: transform 0.2s;
}

#imagePreview .card:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
</style>

<?php include 'includes/footer.php'; ?>

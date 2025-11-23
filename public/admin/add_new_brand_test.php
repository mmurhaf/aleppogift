<?php
/**
 * Add New Brand Test File
 * Simplified interface to test brand addition functionality
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../config/config.php');
require_once('../../includes/Database.php');
require_once('../../includes/session_helper.php');

// Comment out the admin login requirement for testing
// require_admin_login();

$db = new Database();
$message = "";
$error = "";
$success = false;

// Handle Add Brand
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_brand'])) {
    try {
        $name_ar = trim($_POST['name_ar']);
        $name_en = trim($_POST['name_en']);
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
        $logo_filename = null;

        // Validate inputs
        if (empty($name_en)) {
            $error = "English name is required.";
        } elseif (empty($name_ar)) {
            $error = "Arabic name is required.";
        }

        // Handle logo upload
        if (empty($error) && isset($_FILES['brand_logo']) && $_FILES['brand_logo']['error'] == 0) {
            $upload_dir = '../uploads/brands/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $error = "Failed to create upload directory.";
                }
            }

            if (empty($error)) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
                $file_type = $_FILES['brand_logo']['type'];
                
                if (in_array($file_type, $allowed_types)) {
                    $file_extension = pathinfo($_FILES['brand_logo']['name'], PATHINFO_EXTENSION);
                    $logo_filename = 'brand_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $logo_filename;
                    
                    if (!move_uploaded_file($_FILES['brand_logo']['tmp_name'], $upload_path)) {
                        $error = "Error uploading logo file. Please check permissions.";
                        $logo_filename = null;
                    }
                } else {
                    $error = "Invalid file type. Please upload JPG, PNG, GIF, SVG, or WEBP images only. Received: " . $file_type;
                }
            }
        }

        // Insert into database if no errors
        if (empty($error)) {
            $sql = "INSERT INTO brands (name_ar, name_en, status, logo) VALUES (:name_ar, :name_en, :status, :logo)";
            $result = $db->query($sql, [
                'name_ar' => $name_ar,
                'name_en' => $name_en,
                'status' => $status,
                'logo' => $logo_filename
            ]);

            if ($result) {
                $success = true;
                $message = "Brand added successfully! Brand ID: " . $db->getPdo()->lastInsertId();
            } else {
                $error = "Failed to insert brand into database.";
            }
        }
    } catch (Exception $e) {
        $error = "Exception occurred: " . $e->getMessage();
    }
}

// Fetch existing brands
try {
    $brands = $db->query("SELECT * FROM brands ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $brands = [];
    $error .= " | Error fetching brands: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Brand - Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .test-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
        }
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        .page-header h1 {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .page-header .badge {
            font-size: 14px;
            padding: 8px 15px;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .form-section h3 {
            color: #667eea;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .rtl-text {
            direction: rtl;
            text-align: right;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: 600;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .alert-custom {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border: none;
        }
        .alert-success-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .alert-danger-custom {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .brand-preview {
            background: white;
            border-radius: 10px;
            padding: 20px;
        }
        .brand-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .brand-item:last-child {
            border-bottom: none;
        }
        .brand-logo-preview {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 8px;
            background: #f8f9fa;
            padding: 5px;
            margin-right: 15px;
        }
        .brand-info {
            flex: 1;
        }
        .brand-info h6 {
            margin: 0 0 5px 0;
            color: #495057;
            font-weight: 600;
        }
        .brand-info p {
            margin: 0;
            font-size: 13px;
            color: #6c757d;
        }
        .debug-section {
            background: #fff3cd;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        .debug-section h4 {
            color: #856404;
            margin-bottom: 15px;
        }
        .file-upload-wrapper {
            position: relative;
        }
        .file-upload-preview {
            margin-top: 15px;
            text-align: center;
        }
        .file-upload-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-plus-circle"></i> Add New Brand Test</h1>
                <span class="badge bg-info">Testing Environment</span>
                <p class="text-muted mt-2">Simplified interface to test brand addition functionality</p>
            </div>

            <!-- Success Message -->
            <?php if($success && $message): ?>
            <div class="alert alert-success-custom alert-custom">
                <h5><i class="fas fa-check-circle me-2"></i>Success!</h5>
                <p class="mb-0"><?php echo htmlspecialchars($message); ?></p>
                <a href="brands.php" class="btn btn-light btn-sm mt-3">View All Brands</a>
            </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if(!empty($error)): ?>
            <div class="alert alert-danger-custom alert-custom">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Error!</h5>
                <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <!-- Add Brand Form -->
            <div class="form-section">
                <h3><i class="fas fa-tags me-2"></i>Brand Information</h3>
                <form method="post" enctype="multipart/form-data" id="brandForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name_en" class="form-label">
                                <i class="fas fa-font me-1"></i>Brand Name (English) *
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name_en" 
                                   name="name_en" 
                                   placeholder="e.g., Samsung"
                                   required
                                   value="<?php echo isset($_POST['name_en']) ? htmlspecialchars($_POST['name_en']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="name_ar" class="form-label">
                                <i class="fas fa-font me-1"></i>اسم العلامة التجارية (Arabic) *
                            </label>
                            <input type="text" 
                                   class="form-control rtl-text" 
                                   id="name_ar" 
                                   name="name_ar" 
                                   placeholder="مثال: سامسونج"
                                   required
                                   value="<?php echo isset($_POST['name_ar']) ? htmlspecialchars($_POST['name_ar']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="brand_logo" class="form-label">
                                <i class="fas fa-image me-1"></i>Brand Logo
                            </label>
                            <div class="file-upload-wrapper">
                                <input type="file" 
                                       class="form-control" 
                                       id="brand_logo" 
                                       name="brand_logo" 
                                       accept="image/*"
                                       onchange="previewImage(this)">
                                <div class="form-text">Supported: JPG, PNG, GIF, SVG, WEBP (Max 5MB)</div>
                                <div class="file-upload-preview" id="imagePreview"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">
                                <i class="fas fa-toggle-on me-1"></i>Status
                            </label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="1" selected>Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <div class="form-text">Active brands will be visible on the website</div>
                        </div>
                        
                        <div class="col-12">
                            <hr class="my-4">
                            <button type="submit" name="add_brand" class="btn btn-primary btn-submit">
                                <i class="fas fa-save me-2"></i>Add Brand
                            </button>
                            <a href="brands.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Recent Brands Preview -->
            <?php if (!empty($brands)): ?>
            <div class="form-section">
                <h3><i class="fas fa-list me-2"></i>Recently Added Brands (Last 10)</h3>
                <div class="brand-preview">
                    <?php foreach ($brands as $brand): ?>
                    <div class="brand-item">
                    <?php if (!empty($brand['logo'])): ?>
                            <img src="../uploads/brands/<?php echo htmlspecialchars($brand['logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($brand['name_en']); ?>" 
                                 class="brand-logo-preview">
                    <?php else: ?>
                            <div class="brand-logo-preview d-flex align-items-center justify-content-center">
                                <i class="fas fa-image text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div class="brand-info">
                            <h6><?php echo htmlspecialchars($brand['name_en']); ?> - <?php echo htmlspecialchars($brand['name_ar']); ?></h6>
                            <p>ID: #<?php echo $brand['id']; ?> | Status: 
                                <span class="badge <?php echo $brand['status'] == 1 ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo $brand['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Debug Information -->
            <div class="debug-section">
                <h4><i class="fas fa-bug me-2"></i>Debug Information</h4>
                <ul class="list-unstyled mb-0">
                    <li><strong>Database Connected:</strong> <span class="badge bg-success">Yes</span></li>
                    <li><strong>Upload Directory:</strong> <code>../uploads/brands/</code></li>
                    <li><strong>Upload Directory Exists:</strong> 
                        <span class="badge <?php echo is_dir('../uploads/brands/') ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo is_dir('../uploads/brands/') ? 'Yes' : 'No'; ?>
                        </span>
                    </li>
                    <li><strong>Upload Directory Writable:</strong> 
                        <span class="badge <?php echo is_writable('../uploads/brands/') ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo is_writable('../uploads/brands/') ? 'Yes' : 'No'; ?>
                        </span>
                    </li>
                    <li><strong>Total Brands in Database:</strong> 
                        <span class="badge bg-info"><?php echo count($brands); ?></span>
                    </li>
                    <li><strong>PHP Upload Max Filesize:</strong> 
                        <span class="badge bg-info"><?php echo ini_get('upload_max_filesize'); ?></span>
                    </li>
                    <li><strong>PHP Post Max Size:</strong> 
                        <span class="badge bg-info"><?php echo ini_get('post_max_size'); ?></span>
                    </li>
                </ul>
            </div>

            <!-- Navigation Links -->
            <div class="text-center mt-4">
                <a href="brands.php" class="btn btn-outline-primary">
                    <i class="fas fa-list me-2"></i>View All Brands
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                    
                    // Show file info
                    const info = document.createElement('p');
                    info.className = 'text-muted mt-2 mb-0';
                    info.textContent = `File: ${input.files[0].name} (${(input.files[0].size / 1024).toFixed(2)} KB)`;
                    preview.appendChild(info);
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Form validation
        document.getElementById('brandForm').addEventListener('submit', function(e) {
            const nameEn = document.getElementById('name_en').value.trim();
            const nameAr = document.getElementById('name_ar').value.trim();
            
            if (!nameEn || !nameAr) {
                e.preventDefault();
                alert('Please fill in both English and Arabic names.');
                return false;
            }
        });

        // Auto-hide success message after 5 seconds
        <?php if($success): ?>
        setTimeout(() => {
            const alert = document.querySelector('.alert-success-custom');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>

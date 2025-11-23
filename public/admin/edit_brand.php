<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();
$message = "";

// Validate brand ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: brands.php");
    exit;
}

$brand_id = (int)$_GET['id'];

// Fetch brand
$brand = $db->query("SELECT * FROM brands WHERE id = :id", ['id' => $brand_id])->fetch(PDO::FETCH_ASSOC);
if (!$brand) {
    header("Location: brands.php");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_brand'])) {
    $name_ar = trim($_POST['name_ar']);
    $name_en = trim($_POST['name_en']);
    $status = isset($_POST['status']) ? 1 : 0;
    $logo_filename = $brand['logo'] ?? null; // Keep existing logo by default

    // Handle image upload
    if (isset($_FILES['brand_image']) && $_FILES['brand_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/brands/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['brand_image']['tmp_name'];
        $file_name = $_FILES['brand_image']['name'];
        $file_size = $_FILES['brand_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $message = "Error: Only JPG, JPEG, PNG, GIF, and WebP files are allowed.";
        } elseif ($file_size > $max_size) {
            $message = "Error: File size must be less than 5MB.";
        } else {
            // Generate unique filename
            $new_filename = 'brand_' . $brand_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old logo if exists
                if (($brand['logo'] ?? null) && file_exists($upload_dir . $brand['logo'])) {
                    unlink($upload_dir . $brand['logo']);
                }
                $logo_filename = $new_filename;
            } else {
                $message = "Error: Failed to upload image.";
            }
        }
    }

    // Handle logo removal
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if (($brand['logo'] ?? null) && file_exists('../uploads/brands/' . $brand['logo'])) {
            unlink('../uploads/brands/' . $brand['logo']);
        }
        $logo_filename = null;
    }

    // Update DB only if no error occurred
    if (empty($message) || strpos($message, 'Error:') === false) {
        $db->query("UPDATE brands SET name_ar = :name_ar, name_en = :name_en, status = :status, logo = :logo WHERE id = :id", [
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'status' => $status,
            'logo' => $logo_filename,
            'id' => $brand_id
        ]);

        if (empty($message)) {
            $message = "Brand updated successfully!";
        }
        
        // Refresh brand data
        $brand = $db->query("SELECT * FROM brands WHERE id = :id", ['id' => $brand_id])->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Brand - AleppoGift Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --light-bg: #f8f9fa;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-color);
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .container {
            margin-top: 2rem;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }

        .card-title {
            margin-bottom: 0;
            font-weight: 500;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #138496);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #495057);
            border: none;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #545b62, #343a40);
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .form-check-input {
            border-radius: 4px;
        }

        .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 1.5rem;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            text-decoration: underline;
        }

        .breadcrumb-item.active {
            color: var(--secondary-color);
        }

        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 0.5rem;
        }

        .current-image {
            border: 2px solid var(--primary-color);
            padding: 0.5rem;
            border-radius: 8px;
            background-color: white;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            background: linear-gradient(135deg, var(--secondary-color), #495057);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.9rem;
        }

        .file-input-label:hover {
            background: linear-gradient(135deg, #545b62, #343a40);
            transform: translateY(-1px);
        }

        .image-container {
            position: relative;
            display: inline-block;
        }

        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-gift me-2"></i>AleppoGift Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="brands.php">Brands</a></li>
                <li class="breadcrumb-item active">Edit Brand</li>
            </ol>
        </nav>

        <!-- Success/Error Message -->
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error:') === 0 ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                <i class="fas <?php echo strpos($message, 'Error:') === 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?> me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Edit Brand Form -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fas fa-edit me-2"></i>Edit Brand: <?php echo htmlspecialchars($brand['name_en']); ?>
                </h4>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name_ar" class="form-label">
                                    <i class="fas fa-font me-1"></i>Brand Name (Arabic)
                                </label>
                                <input type="text" class="form-control" id="name_ar" name="name_ar" 
                                       value="<?php echo htmlspecialchars($brand['name_ar']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name_en" class="form-label">
                                    <i class="fas fa-font me-1"></i>Brand Name (English)
                                </label>
                                <input type="text" class="form-control" id="name_en" name="name_en" 
                                       value="<?php echo htmlspecialchars($brand['name_en']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="status" name="status" 
                                           <?php echo $brand['status'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status">
                                        <i class="fas fa-toggle-on me-1"></i>Active Brand
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="brand_image" class="form-label">
                                    <i class="fas fa-image me-1"></i>Brand Logo
                                </label>
                                
                                <!-- Current Logo Display -->
                                <?php if (!empty($brand['logo'])): ?>
                                    <div class="current-image mb-2">
                                        <div class="image-container">
                                            <img src="../uploads/brands/<?php echo htmlspecialchars($brand['logo']); ?>" 
                                                 alt="Current brand logo" class="image-preview" id="currentImage">
                                            <button type="button" class="remove-image" onclick="removeCurrentImage()" title="Remove current logo">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted d-block mt-1">Current logo</small>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- File Input -->
                                <div class="file-input-wrapper">
                                    <input type="file" id="brand_image" name="brand_image" accept="image/*" onchange="previewImage(this)">
                                    <label for="brand_image" class="file-input-label">
                                        <i class="fas fa-upload me-1"></i>Choose New Logo
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">Supported formats: JPG, JPEG, PNG, GIF, WebP (Max: 5MB)</small>
                                
                                <!-- Logo Preview -->
                                <div id="imagePreview" style="display: none;">
                                    <img id="previewImg" class="image-preview mt-2" alt="Logo preview">
                                    <small class="text-muted d-block mt-1">New logo preview</small>
                                </div>
                                
                                <!-- Hidden input to track logo removal -->
                                <input type="hidden" id="remove_image" name="remove_image" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name_en" class="form-label">
                                    <i class="fas fa-font me-1"></i>Brand Name (English)
                                </label>
                                <input type="text" class="form-control" id="name_en" name="name_en" 
                                       value="<?php echo htmlspecialchars($brand['name_en']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="update_brand" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Brand
                        </button>
                        <a href="brands.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        function removeCurrentImage() {
            const currentImage = document.getElementById('currentImage');
            const removeInput = document.getElementById('remove_image');
            
            if (confirm('Are you sure you want to remove the current logo?')) {
                currentImage.parentElement.parentElement.style.display = 'none';
                removeInput.value = '1';
            }
        }
    </script>
</body>
</html>

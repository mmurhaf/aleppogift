<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();
$message = "";

// Validate category ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: categories.php");
    exit;
}

$category_id = (int)$_GET['id'];

// Fetch category
$category = $db->query("SELECT * FROM categories WHERE id = :id", ['id' => $category_id])->fetch(PDO::FETCH_ASSOC);
if (!$category) {
    header("Location: categories.php");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_category'])) {
    $name_ar = trim($_POST['name_ar']);
    $name_en = trim($_POST['name_en']);
    $status = isset($_POST['status']) ? 1 : 0;
    $picture_path = $category['picture'];

    // Handle image upload
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../../uploads/categories/";
        
        // Create folder if doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['picture']['tmp_name'];
        $file_name = $_FILES['picture']['name'];
        $file_size = $_FILES['picture']['size'];
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
            $new_filename = 'category_' . $category_id . '_' . time() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $target_file)) {
                // Delete old picture if it exists
                if (!empty($category['picture']) && file_exists('../../' . $category['picture'])) {
                    unlink('../../' . $category['picture']);
                }
                $picture_path = 'uploads/categories/' . $new_filename;
            } else {
                $message = "Error: Failed to upload image.";
            }
        }
    }

    // Handle image removal
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if (!empty($category['picture']) && file_exists('../../' . $category['picture'])) {
            unlink('../../' . $category['picture']);
        }
        $picture_path = null;
    }

    // Update DB only if no error occurred
    if (empty($message) || strpos($message, 'Error:') === false) {
        $db->query("UPDATE categories SET name_ar = :name_ar, name_en = :name_en, status = :status, picture = :picture WHERE id = :id", [
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'status' => $status,
            'picture' => $picture_path,
            'id' => $category_id
        ]);

        if (empty($message)) {
            $message = "Category updated successfully!";
        }
        
        // Refresh category data
        $category = $db->query("SELECT * FROM categories WHERE id = :id", ['id' => $category_id])->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - AleppoGift Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin-theme.css">
    <style>
        body { background-color: #f8f9fc; }
        .edit-card { background: #fff; border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem rgba(58,59,69,.15); padding: 2rem; }
        .rtl-text { direction: rtl; text-align: right; }
        .preview-img { 
            max-width: 150px; 
            max-height: 150px; 
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            object-fit: cover;
        }
        .current-image {
            border: 2px solid #4e73df;
            padding: 0.5rem;
            border-radius: 8px;
            background-color: white;
            display: inline-block;
        }
        .image-container {
            position: relative;
            display: inline-block;
        }
        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .remove-image:hover {
            background: #c82333;
        }
        .message-alert {
            position: fixed;
            top: 20px; right: 20px;
            animation: fadeIn 0.3s, fadeOut 0.5s 2.5s;
            z-index: 999;
        }
        @keyframes fadeIn { from {opacity: 0;} to {opacity: 1;} }
        @keyframes fadeOut { from {opacity: 1;} to {opacity: 0;} }
        .dashboard-header {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.35rem;
        }
        .admin-nav {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 0.35rem;
        }
        .admin-nav a {
            color: #4e73df;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border-radius: 0.35rem;
            transition: all 0.3s;
            display: inline-block;
        }
        .admin-nav a:hover {
            background-color: #4e73df;
            color: white;
        }
        .admin-nav a.active {
            background-color: #ff7f00;
            color: white;
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-gifts"></i> <span>AleppoGift</span></h2>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
        <li><a href="products.php"><i class="fas fa-box-open"></i> <span>Products</span></a></li>
        <li><a href="categories.php" class="active"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
        <li><a href="brands.php"><i class="fas fa-copyright"></i> <span>Brands</span></a></li>
        <li><a href="coupons.php"><i class="fas fa-ticket-alt"></i> <span>Coupons</span></a></li>
        <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
        <li><a href="regenerate_invoice.php"><i class="fas fa-file-invoice-dollar"></i> <span>Regenerate Invoice</span></a></li>
        <li><a href="customers.php"><i class="fas fa-users"></i> <span>Customers</span></a></li>
        <li><a href="../testing/" target="_blank"><i class="fas fa-flask"></i> <span>Testing Dashboard</span></a></li>
    </ul>
    <button class="logout-btn" onclick="window.location.href='logout.php'">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </button>
</div>

<!-- Main Content -->
<div class="main-content">

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Edit Category</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="categories.php">Categories</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Category</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="categories.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Categories
                </a>
            </div>
        </div>
    </div>

<div class="container-fluid py-4">

    <?php if ($message): ?>
        <div class="alert alert-success message-alert" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="edit-card">
        <form method="post" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name_en" class="form-label">Category Name (English)</label>
                    <input type="text" class="form-control" id="name_en" name="name_en" required value="<?= htmlspecialchars($category['name_en']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="name_ar" class="form-label">اسم الفئة (Arabic)</label>
                    <input type="text" class="form-control rtl-text" id="name_ar" name="name_ar" required value="<?= htmlspecialchars($category['name_ar']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" id="status" name="status" <?= $category['status'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status">Active</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="picture" class="form-label">
                        <i class="fas fa-image me-1"></i>Category Picture
                    </label>
                    
                    <!-- Current Picture Display -->
                    <?php if (!empty($category['picture'])): ?>
                        <div class="current-image mb-2">
                            <div class="image-container">
                                <img src="../../<?= htmlspecialchars($category['picture']) ?>" 
                                     alt="Current category picture" class="preview-img" id="currentImage">
                                <button type="button" class="remove-image" onclick="removeCurrentImage()" title="Remove current picture">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">Current picture</small>
                        </div>
                    <?php endif; ?>
                    
                    <!-- File Input -->
                    <input class="form-control" type="file" name="picture" id="picture" accept="image/*" onchange="previewImage(this)">
                    <small class="text-muted d-block mt-1">Supported formats: JPG, JPEG, PNG, GIF, WebP (Max: 5MB)</small>
                    
                    <!-- Picture Preview -->
                    <div id="imagePreview" style="display: none;">
                        <img id="previewImg" class="preview-img mt-2 rounded" alt="Picture preview">
                        <small class="text-muted d-block mt-1">New picture preview</small>
                    </div>
                    
                    <!-- Hidden input to track picture removal -->
                    <input type="hidden" id="remove_image" name="remove_image" value="0">
                </div>

                <div class="col-12 mt-3">
                    <button type="submit" name="update_category" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Update Category
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
</div>

<script>
    setTimeout(() => {
        const alert = document.querySelector('.message-alert');
        if (alert) {
            alert.style.animation = 'fadeOut 0.5s';
            setTimeout(() => alert.remove(), 500);
        }
    }, 3000);
    
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
        
        if (confirm('Are you sure you want to remove the current picture?')) {
            currentImage.parentElement.parentElement.style.display = 'none';
            removeInput.value = '1';
        }
    }
</script>

</body>
</html>

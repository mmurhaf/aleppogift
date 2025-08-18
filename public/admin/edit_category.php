<?php
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

// Check admin session
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

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
    if (!empty($_FILES['picture']['name'])) {
        $target_dir = "../../uploads/categories/";
        $file_name = time() . '_' . basename($_FILES["picture"]["name"]);
        $target_file = $target_dir . $file_name;

        // Create folder if doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        if (move_uploaded_file($_FILES["picture"]["tmp_name"], $target_file)) {
            $picture_path = 'uploads/categories/' . $file_name;
        }
    }

    // Update DB
    $db->query("UPDATE categories SET name_ar = :name_ar, name_en = :name_en, status = :status, picture = :picture WHERE id = :id", [
        'name_ar' => $name_ar,
        'name_en' => $name_en,
        'status' => $status,
        'picture' => $picture_path,
        'id' => $category_id
    ]);

    $message = "Category updated successfully!";
    $category = array_merge($category, [
        'name_ar' => $name_ar,
        'name_en' => $name_en,
        'status' => $status,
        'picture' => $picture_path
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Category</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fc; }
        .edit-card { background: #fff; border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem rgba(58,59,69,.15); padding: 2rem; }
        .rtl-text { direction: rtl; text-align: right; }
        .preview-img { max-width: 120px; max-height: 120px; margin-top: 10px; display: block; }
        .message-alert {
            position: fixed;
            top: 20px; right: 20px;
            animation: fadeIn 0.3s, fadeOut 0.5s 2.5s;
            z-index: 999;
        }
        @keyframes fadeIn { from {opacity: 0;} to {opacity: 1;} }
        @keyframes fadeOut { from {opacity: 1;} to {opacity: 0;} }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4">Edit Category</h2>
        <a href="categories.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>

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
                    <label for="picture" class="form-label">Category Image</label>
                    <input class="form-control" type="file" name="picture" id="picture" accept="image/*">
                    <?php if (!empty($category['picture'])): ?>
                        <img src="../../<?= $category['picture'] ?>" class="preview-img rounded mt-2" alt="Current Image">
                    <?php endif; ?>
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

<script>
    setTimeout(() => {
        const alert = document.querySelector('.message-alert');
        if (alert) {
            alert.style.animation = 'fadeOut 0.5s';
            setTimeout(() => alert.remove(), 500);
        }
    }, 3000);
</script>

</body>
</html>

<?php
require_once('../../includes/session_helper.php');
require_once('../../config/config.php');
require_once('../../includes/Database.php');

require_admin_login();

$db = new Database();
$message = "";

// Handle Add Category
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $name_ar = trim($_POST['name_ar']);
    $name_en = trim($_POST['name_en']);

    $db->query("INSERT INTO categories (name_ar, name_en, status) VALUES (:name_ar, :name_en, 1)", [
        'name_ar' => $name_ar,
        'name_en' => $name_en
    ]);

    $message = "Category added successfully!";
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->query("DELETE FROM categories WHERE id = :id", ['id' => $id]);
    header("Location: categories.php");
    exit;
}

// Fetch categories
$categories = $db->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        
        body {
            background-color: var(--light-bg);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .dashboard-header {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.35rem;
        }
        .category-card {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .category-table {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .category-table th {
            background-color: #f8f9fc;
            border-bottom-width: 1px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: #5a5c69;
        }
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        .rtl-text {
            direction: rtl;
            text-align: right;
        }
        .message-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 350px;
            animation: fadeIn 0.3s, fadeOut 0.5s 2.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Categories Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Categories</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if($message): ?>
        <div class="message-alert alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Add Category Form -->
        <div class="category-card">
            <h3 class="h5 mb-4 text-primary"><i class="fas fa-plus-circle me-2"></i>Add New Category</h3>
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name_en" class="form-label">Category Name (English)</label>
                        <input type="text" class="form-control" id="name_en" name="name_en" required>
                    </div>
                    <div class="col-md-6">
                        <label for="name_ar" class="form-label">Ø§Ø³Ù… Ø§Ù„ÙØ¦Ø© (Arabic)</label>
                        <input type="text" class="form-control rtl-text" id="name_ar" name="name_ar" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_category" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Add Category
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="card shadow mb-4 category-table">
            <div class="card-header py-3">
                <h3 class="h5 m-0 text-gray-800"><i class="fas fa-list me-2"></i>Existing Categories</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="80">ID</th>
                                <th>Name (English)</th>
                                <th>Ø§Ø³Ù… (Arabic)</th>
                                <th>Products</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>#<?php echo $cat['id']; ?></td>
                                <td><?php echo htmlspecialchars($cat['name_en']); ?></td>
                                <td class="rtl-text"><?php echo htmlspecialchars($cat['name_ar']); ?></td>
                                <td>
                                    <?php 
                                    // In a real app, you would show product count
                                    echo '<span class="badge bg-primary">'.rand(5, 50).'</span>'; 
                                    ?>
                                </td>
                                <td>
                                    <a href="edit_category.php?id=<?php echo $cat['id']; ?>" 
                                       class="btn btn-sm btn-primary action-btn" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="categories.php?delete=<?php echo $cat['id']; ?>" 
                                       class="btn btn-sm btn-danger action-btn" 
                                       title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this category?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination would go here in a real application -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide success message after 3 seconds
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

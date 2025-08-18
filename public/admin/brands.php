<?php
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$message = "";

// Handle Add Brand
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_brand'])) {
    $name_en = trim($_POST['name_en']);
    $name_ar = trim($_POST['name_ar']);
    
    // Handle logo upload
    $logo_path = null;
    if (!empty($_FILES['logo']['name'])) {
        $target_dir = "../../uploads/brands/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $file_name = time() . '_' . basename($_FILES["logo"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            $logo_path = 'uploads/brands/' . $file_name;
        }
    }

    $db->query("INSERT INTO brands (name_en, name_ar, logo, status) VALUES (:name_en, :name_ar, :logo, 1)", [
        'name_en' => $name_en,
        'name_ar' => $name_ar,
        'logo' => $logo_path
    ]);

    $message = "Brand added successfully!";
}

// Handle Delete Brand
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->query("DELETE FROM brands WHERE id = :id", ['id' => $id]);
    header("Location: brands.php");
    exit;
}

// Fetch brands
$brands = $db->query("SELECT * FROM brands ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brands Management - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --light-bg: #f8f9fc;
        }
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
        .brand-card {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .brand-table {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .brand-table th {
            background-color: #f8f9fc;
            border-bottom-width: 1px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: #5a5c69;
        }
        .brand-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 4px;
            background-color: #f8f9fa;
            padding: 5px;
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
                    <h1 class="h3 mb-0 text-gray-800">Brands Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Brands</li>
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

        <!-- Add Brand Form -->
        <div class="brand-card">
            <h3 class="h5 mb-4 text-primary"><i class="fas fa-plus-circle me-2"></i>Add New Brand</h3>
            <form method="post" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name_en" class="form-label">Brand Name (English)</label>
                        <input type="text" class="form-control" id="name_en" name="name_en" required>
                    </div>
                    <div class="col-md-6">
                        <label for="name_ar" class="form-label">اسم العلامة التجارية (Arabic)</label>
                        <input type="text" class="form-control rtl-text" id="name_ar" name="name_ar" required>
                    </div>
                    <div class="col-12">
                        <label for="logo" class="form-label">Brand Logo</label>
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                        <div class="form-text">Upload a high-quality logo (PNG, JPG, SVG)</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_brand" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Add Brand
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Brands Table -->
        <div class="card shadow mb-4 brand-table">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h3 class="h5 m-0 text-gray-800"><i class="fas fa-tags me-2"></i>Existing Brands</h3>
                <div class="input-group" style="max-width: 300px;">
                    <input type="text" class="form-control" placeholder="Search brands...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="80">Logo</th>
                                <th>ID</th>
                                <th>Name (English)</th>
                                <th>اسم (Arabic)</th>
                                <th>Products</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($brands as $brand): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($brand['logo'])): ?>
                                        <img src="../../<?= $brand['logo'] ?>" alt="<?= htmlspecialchars($brand['name_en']) ?>" class="brand-logo">
                                    <?php else: ?>
                                        <div class="brand-logo d-flex align-items-center justify-content-center bg-light">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>#<?php echo $brand['id']; ?></td>
                                <td><?php echo htmlspecialchars($brand['name_en']); ?></td>
                                <td class="rtl-text"><?php echo htmlspecialchars($brand['name_ar']); ?></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php /* Product count would come from database */ ?>
                                        <?php echo isset($brand['product_count']) ? $brand['product_count'] : '0'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit_brand.php?id=<?php echo $brand['id']; ?>" 
                                       class="btn btn-sm btn-primary action-btn" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="brands.php?delete=<?php echo $brand['id']; ?>" 
                                       class="btn btn-sm btn-danger action-btn" 
                                       title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this brand? All associated products will be kept but their brand will be set to none.')">
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
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                </li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item">
                    <a class="page-link" href="#">Next</a>
                </li>
            </ul>
        </nav>
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

        // Enhanced delete confirmation
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this brand? All associated products will be kept but their brand will be set to none.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
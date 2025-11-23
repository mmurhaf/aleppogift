<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

// Require admin authentication
require_admin_login();

$db = new Database();
$message = "";

// Add new coupon
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_coupon'])) {
    $db->query("INSERT INTO coupons (code, discount_type, discount_value, start_date, end_date, max_usage, status) 
        VALUES (:code, :type, :value, :start, :end, :max, :status)", [
        'code'   => $_POST['code'],
        'type'   => $_POST['discount_type'],
        'value'  => $_POST['discount_value'],
        'start'  => $_POST['start_date'],
        'end'    => $_POST['end_date'],
        'max'    => $_POST['max_usage'],
        'status' => $_POST['status'],
    ]);
    $message = "✅ Coupon added successfully.";
}

// Delete coupon
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id > 0) {
        $db->query("DELETE FROM coupons WHERE id = :id", ['id' => $delete_id]);
    }
    header("Location: coupons.php");
    exit;
}

// Edit coupon
if (isset($_GET['edit']) && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_coupon'])) {
    $db->query("UPDATE coupons SET code = :code, discount_type = :type, discount_value = :value,
        start_date = :start, end_date = :end, max_usage = :max, status = :status 
        WHERE id = :id", [
        'code'   => $_POST['code'],
        'type'   => $_POST['discount_type'],
        'value'  => $_POST['discount_value'],
        'start'  => $_POST['start_date'],
        'end'    => $_POST['end_date'],
        'max'    => $_POST['max_usage'],
        'status' => $_POST['status'],
        'id'     => $_GET['edit']
    ]);
    $message = "✅ Coupon updated successfully.";
}

// Fetch coupons
$coupons = $db->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Load data for editing
$edit_coupon = null;
if (isset($_GET['edit'])) {
    $edit_coupon = $db->query("SELECT * FROM coupons WHERE id = :id", ['id' => $_GET['edit']])->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupon Management - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
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
        .coupon-card {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .coupon-table {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .coupon-table th {
            background-color: #f8f9fc;
            border-bottom-width: 1px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: #5a5c69;
        }
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
            text-transform: uppercase;
        }
        .badge-active {
            background-color: var(--success-color);
            color: white;
        }
        .badge-inactive {
            background-color: #6c757d;
            color: white;
        }
        .badge-expired {
            background-color: var(--danger-color);
            color: white;
        }
        .badge-fixed {
            background-color: var(--primary-color);
            color: white;
        }
        .badge-percent {
            background-color: var(--warning-color);
            color: #000;
        }
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        .coupon-code {
            font-family: monospace;
            font-weight: bold;
            letter-spacing: 1px;
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
                    <h1 class="h3 mb-0 text-gray-800">Coupon Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Coupons</li>
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
        <?php if ($message): ?>
        <div class="message-alert alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Add/Edit Coupon Form -->
        <div class="coupon-card">
            <h3 class="h5 mb-4 text-primary">
                <i class="fas <?php echo $edit_coupon ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                <?php echo $edit_coupon ? "Edit Coupon" : "Add New Coupon"; ?>
            </h3>
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="code" class="form-label">Coupon Code</label>
                        <input type="text" class="form-control coupon-code" id="code" name="code" 
                               value="<?php echo $edit_coupon['code'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="discount_type" class="form-label">Discount Type</label>
                        <select class="form-select" id="discount_type" name="discount_type" required>
                            <option value="fixed" <?php if (($edit_coupon['discount_type'] ?? '') == 'fixed') echo 'selected'; ?>>Fixed Amount</option>
                            <option value="percent" <?php if (($edit_coupon['discount_type'] ?? '') == 'percent') echo 'selected'; ?>>Percentage</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="discount_value" class="form-label">Discount Value</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <?php echo (($edit_coupon['discount_type'] ?? '') == 'percent') ? '%' : 'AED'; ?>
                            </span>
                            <input type="number" step="0.01" class="form-control" id="discount_value" 
                                   name="discount_value" value="<?php echo $edit_coupon['discount_value'] ?? ''; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="max_usage" class="form-label">Maximum Usage</label>
                        <input type="number" class="form-control" id="max_usage" name="max_usage" 
                               value="<?php echo $edit_coupon['max_usage'] ?? 1; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $edit_coupon['start_date'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $edit_coupon['end_date'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php if (($edit_coupon['status'] ?? '') == 'active') echo 'selected'; ?>>Active</option>
                            <option value="inactive" <?php if (($edit_coupon['status'] ?? '') == 'inactive') echo 'selected'; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 mt-3">
                        <div class="d-flex justify-content-between">
                            <?php if ($edit_coupon): ?>
                                <a href="coupons.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" name="update_coupon" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Coupon
                                </button>
                            <?php else: ?>
                                <button type="submit" name="add_coupon" class="btn btn-primary ms-auto">
                                    <i class="fas fa-plus me-1"></i> Add Coupon
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Coupons Table -->
        <div class="card shadow mb-4 coupon-table">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h3 class="h5 m-0 text-gray-800"><i class="fas fa-tags me-2"></i>Existing Coupons</h3>
                <div class="input-group" style="max-width: 300px;">
                    <input type="text" class="form-control" placeholder="Search coupons...">
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
                                <th>Code</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Validity</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $coupon): 
                                $isExpired = strtotime($coupon['end_date']) < time();
                                $statusClass = $isExpired ? 'expired' : $coupon['status'];
                            ?>
                            <tr>
                                <td>
                                    <span class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></span>
                                    <div class="text-muted small">ID: <?php echo $coupon['id']; ?></div>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?php echo $coupon['discount_type']; ?>">
                                        <?php echo ucfirst($coupon['discount_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $coupon['discount_type'] == 'percent' ? $coupon['discount_value'].'%' : 'AED '.$coupon['discount_value']; ?>
                                </td>
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($coupon['start_date'])); ?> - <?php echo date('M j, Y', strtotime($coupon['end_date'])); ?></div>
                                    <div class="small text-muted">
                                        <?php echo $isExpired ? 'Expired' : (strtotime($coupon['start_date']) > time() ? 'Starts soon' : 'Active'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?php echo ($coupon['used_count'] >= $coupon['max_usage']) ? 'bg-danger' : 'bg-success'; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo min(100, ($coupon['used_count'] / $coupon['max_usage']) * 100); ?>%" 
                                             aria-valuenow="<?php echo $coupon['used_count']; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="<?php echo $coupon['max_usage']; ?>">
                                        </div>
                                    </div>
                                    <small><?php echo $coupon['used_count']; ?> of <?php echo $coupon['max_usage']; ?> used</small>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?php echo $statusClass; ?>">
                                        <?php echo $isExpired ? 'Expired' : ucfirst($coupon['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="coupons.php?edit=<?php echo $coupon['id']; ?>" 
                                       class="btn btn-sm btn-primary action-btn" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="coupons.php?delete=<?php echo $coupon['id']; ?>" 
                                       class="btn btn-sm btn-danger action-btn" 
                                       title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this coupon?')">
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

        <!-- Pagination -->
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

        // Update currency symbol based on discount type
        document.getElementById('discount_type').addEventListener('change', function() {
            const type = this.value;
            const currencySpan = document.querySelector('#discount_value').previousElementSibling;
            currencySpan.textContent = type === 'percent' ? '%' : 'AED';
        });

        // Enhanced delete confirmation
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this coupon?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
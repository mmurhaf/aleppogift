<?php
// File: admin/regenerate_invoice.php
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

// (Optional) Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$message = '';
$order = null;
$order_id = 0;

// Step 1: If form submitted with order ID, process
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $new_note = trim($_POST['note'] ?? '');
    $new_remarks = trim($_POST['remarks'] ?? '');

    // Check if the order exists
    $order = $db->query("SELECT * FROM orders WHERE id = :id", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $message = "❌ Order #$order_id not found.";
    } else {
        // Update note and remarks
        $db->query("
            UPDATE orders 
            SET note = :note, remarks = :remarks
            WHERE id = :id
        ", [
            'note' => $new_note,
            'remarks' => $new_remarks,
            'id' => $order_id
        ]);

        // Regenerate invoice
        require_once('../../includes/generate_invoice.php');
        $message = "✅ Invoice for order #$order_id regenerated successfully!";
    }
}
// Step 2: If order_id was passed via GET (e.g. from orders.php?id=123)
elseif (isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    $order = $db->query("SELECT * FROM orders WHERE id = :id", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);
    $customer = $db->query("SELECT * FROM customers WHERE id = :id", ['id' => $order['customer_id']])->fetch(PDO::FETCH_ASSOC);

    $order_items = $db->query("
        SELECT oi.*, p.name_en 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
    ", ['order_id' => $order_id])->fetchAll(PDO::FETCH_ASSOC);



    if (!$order) {
        $message = "❌ Order #$order_id not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regenerate Invoice - AleppoGift</title>
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
        .order-card {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .items-table {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .items-table th {
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
        .badge-paid {
            background-color: var(--success-color);
            color: white;
        }
        .badge-pending {
            background-color: #f6c23e;
            color: #000;
        }
        .badge-failed {
            background-color: var(--danger-color);
            color: white;
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
        .detail-card {
            border-left: 4px solid var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Regenerate Invoice</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Regenerate Invoice</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
        <div class="message-alert alert <?= strpos($message, '✅') !== false ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
            <i class="fas <?= strpos($message, '✅') !== false ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (!$order): ?>
            <!-- Order Lookup Form -->
            <div class="order-card">
                <h3 class="h5 mb-4"><i class="fas fa-search me-2"></i>Find Order</h3>
                <form method="get" class="row g-3">
                    <div class="col-md-6">
                        <label for="id" class="form-label">Enter Order ID</label>
                        <div class="input-group">
                            <span class="input-group-text">#</span>
                            <input type="number" class="form-control" name="id" id="id" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Load Order
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Order Details -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100 detail-card">
                        <div class="card-body">
                            <h3 class="h5 card-title text-primary">
                                <i class="fas fa-receipt me-2"></i>Order Details
                            </h3>
                            <ul class="list-unstyled">
                                <li class="mb-2"><strong>Order ID:</strong> #<?= $order['id'] ?></li>
                                <li class="mb-2"><strong>Date:</strong> <?= date('M j, Y h:i A', strtotime($order['updated_at'])) ?></li>
                                <li class="mb-2"><strong>Total:</strong> AED <?= number_format($order['total_amount'], 2) ?></li>
                                <li class="mb-2"><strong>Payment Method:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?></li>
                                <li>
                                    <strong>Status:</strong> 
                                    <span class="status-badge badge-<?= $order['payment_status'] ?>">
                                        <?= ucfirst($order['payment_status']) ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 detail-card">
                        <div class="card-body">
                            <h3 class="h5 card-title text-primary">
                                <i class="fas fa-user me-2"></i>Customer Details
                            </h3>
                            <ul class="list-unstyled">
                                <li class="mb-2"><strong>Full Name:</strong> <?= htmlspecialchars($customer['fullname']) ?></li>
                                <li class="mb-2"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($customer['email']) ?>"><?= htmlspecialchars($customer['email']) ?></a></li>
                                <li class="mb-2"><strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($customer['phone']) ?>"><?= htmlspecialchars($customer['phone']) ?></a></li>
                                <li class="mb-2"><strong>Address:</strong> <?= nl2br(htmlspecialchars($customer['address'])) ?></li>
                                <li class="mb-2"><strong>City:</strong> <?= htmlspecialchars($customer['city']) ?></li>
                                <li class="mb-2"><strong>Country:</strong> <?= htmlspecialchars($customer['country']) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card shadow mb-4 items-table">
                <div class="card-header py-3">
                    <h3 class="h5 m-0 text-gray-800"><i class="fas fa-boxes me-2"></i>Order Items</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name_en']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>AED <?= number_format($item['price'], 2) ?></td>
                                    <td>AED <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><strong>AED <?= number_format($order['total_amount'], 2) ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Invoice Regeneration Form -->
            <div class="order-card">
                <h3 class="h5 mb-4 text-primary"><i class="fas fa-file-invoice me-2"></i>Invoice Details</h3>
                <form method="POST">
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="note" class="form-label">Customer Note (visible on invoice)</label>
                            <textarea class="form-control" id="note" name="note" rows="4"><?= htmlspecialchars($order['note']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="remarks" class="form-label">Internal Remarks (admin only)</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="4"><?= htmlspecialchars($order['remarks']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="orders.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sync-alt me-1"></i> Regenerate Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
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
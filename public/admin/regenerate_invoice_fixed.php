<?php
// File: admin/regenerate_invoice.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/includes/generate_invoice_pdf.php');

require_admin_login();

$db = new Database();
$message = '';
$order = null;
$customer = null;
$order_items = [];
$order_id = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $new_note = trim($_POST['note'] ?? '');
    $new_remarks = trim($_POST['remarks'] ?? '');

    // Get order
    $order = $db->query("SELECT * FROM orders WHERE id = ?", [$order_id])->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $message = "Order #$order_id not found.";
    } else {
        try {
            // Update order notes
            $db->query("UPDATE orders SET note = ?, remarks = ? WHERE id = ?", [
                $new_note, $new_remarks, $order_id
            ]);

            $message = "Invoice for order #$order_id regenerated successfully! <a href='generate_invoice.php?id=$order_id' target='_blank' class='alert-link'>View Invoice</a>";
            
            // Reload data
            $order = $db->query("SELECT * FROM orders WHERE id = ?", [$order_id])->fetch(PDO::FETCH_ASSOC);
            if ($order) {
                $customer = $db->query("SELECT * FROM customers WHERE id = ?", [$order['customer_id']])->fetch(PDO::FETCH_ASSOC);
                $order_items = $db->query("
                    SELECT oi.*, p.name_en 
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ", [$order_id])->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}
// Handle GET request
elseif (isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    
    $order = $db->query("SELECT * FROM orders WHERE id = ?", [$order_id])->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $customer = $db->query("SELECT * FROM customers WHERE id = ?", [$order['customer_id']])->fetch(PDO::FETCH_ASSOC);
        $order_items = $db->query("
            SELECT oi.*, p.name_en 
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ", [$order_id])->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $message = "Order #$order_id not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regenerate Invoice - AleppoGift Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fc; }
        .card { box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); border: none; }
        .card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
        .alert { border: none; }
        .badge { font-size: 0.75em; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Regenerate Invoice</h1>
            <a href="orders.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Orders
            </a>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
        <div class="alert <?= strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show">
            <?php if (strpos($message, 'successfully') !== false): ?>
                <?= $message ?>
            <?php else: ?>
                <?= htmlspecialchars($message) ?>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!$order): ?>
            <!-- Order Search -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>Find Order</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-6">
                            <label for="id" class="form-label">Enter Order ID</label>
                            <div class="input-group">
                                <span class="input-group-text">#</span>
                                <input type="number" class="form-control" name="id" id="id" required>
                                <button type="submit" class="btn btn-primary">Load Order</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Order Details -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Details</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Order ID:</strong> #<?= $order['id'] ?></p>
                            <p><strong>Date:</strong> <?= date('M j, Y h:i A', strtotime($order['created_at'] ?? 'now')) ?></p>
                            <p><strong>Total:</strong> AED <?= number_format($order['total_amount'], 2) ?></p>
                            <p><strong>Payment Method:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')) ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?= ($order['payment_status'] ?? 'secondary') === 'paid' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($order['payment_status'] ?? 'Unknown') ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Customer Details</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($customer): ?>
                                <p><strong>Name:</strong> <?= htmlspecialchars($customer['fullname']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($customer['phone']) ?></p>
                                <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($customer['address'])) ?></p>
                                <p><strong>City:</strong> <?= htmlspecialchars($customer['city']) ?></p>
                                <p><strong>Country:</strong> <?= htmlspecialchars($customer['country']) ?></p>
                            <?php else: ?>
                                <p class="text-muted">Customer information not available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <?php if (!empty($order_items)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Order Items</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name_en'] ?? 'Unknown Product') ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>AED <?= number_format($item['price'], 2) ?></td>
                                    <td>AED <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong>AED <?= number_format($order['total_amount'], 2) ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Regenerate Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Invoice Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="note" class="form-label">Customer Note (visible on invoice)</label>
                                <textarea class="form-control" id="note" name="note" rows="4"><?= htmlspecialchars($order['note'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="remarks" class="form-label">Internal Remarks (admin only)</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="4"><?= htmlspecialchars($order['remarks'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="orders.php" class="btn btn-outline-secondary">Cancel</a>
                            <div>
                                <a href="generate_invoice.php?id=<?= $order_id ?>" target="_blank" class="btn btn-success me-2">
                                    <i class="fas fa-eye me-1"></i> View Invoice
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sync-alt me-1"></i> Update & Regenerate
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
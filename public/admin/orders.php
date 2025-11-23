<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();
$message = "";

// Handle bulk delete
if (isset($_POST['bulk_delete']) && !empty($_POST['selected_orders'])) {
    $selectedOrders = $_POST['selected_orders'];
    $deletedCount = 0;
    
    foreach ($selectedOrders as $orderId) {
        $orderId = (int)$orderId;
        
        // Get order details for file cleanup
        $order = $db->query("SELECT invoice_pdf FROM orders WHERE id = :id", ['id' => $orderId])->fetch(PDO::FETCH_ASSOC);
        
        // Delete invoice PDF file if exists
        if ($order && !empty($order['invoice_pdf'])) {
            $filePath = "../../invoice/" . $order['invoice_pdf'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Delete order items first (foreign key constraint)
        $db->query("DELETE FROM order_items WHERE order_id = :id", ['id' => $orderId]);
        
        // Delete the order
        $db->query("DELETE FROM orders WHERE id = :id", ['id' => $orderId]);
        $deletedCount++;
    }
    
    $message = "Successfully deleted $deletedCount order(s) and their related data.";
}

// Handle status update
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['payment_status'];
    $db->query("UPDATE orders SET payment_status = :status WHERE id = :id", [
        'status' => $status,
        'id' => $order_id
    ]);
    $message = "Order payment status updated successfully!";
}

// Fetch orders with customer info
$sql = "SELECT o.*, c.fullname, c.email FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        ORDER BY o.id DESC";
$orders = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - AleppoGift</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin-theme.css">
    <style>
        /* Orders page specific styles */
        body {
            background-color: var(--light-color);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .dashboard-header {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.35rem;
        }
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        .badge-pending {
            background-color: var(--warning-color);
            color: #000;
        }
        .badge-paid {
            background-color: var(--success-color);
            color: white;
        }
        .badge-failed {
            background-color: var(--danger-color);
            color: white;
        }
        .order-table {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .order-table th {
            background-color: #f8f9fc;
            border-bottom-width: 1px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: #5a5c69;
        }
        .action-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .invoice-link {
            display: inline-block;
            margin-top: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
        }
        .invoice-link:hover {
            text-decoration: underline;
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
        .bulk-actions {
            gap: 10px;
            align-items: center;
        }
        .selected-count {
            font-size: 0.9rem;
            margin-left: 10px;
        }
        .order-checkbox {
            cursor: pointer;
        }
        #selectAll {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Orders Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Orders</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
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

        <!-- Orders Table -->
        <div class="card shadow mb-4 order-table">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Orders Management</h6>
                <div class="bulk-actions" style="display: none;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <span class="selected-count text-muted">0 selected</span>
                </div>
            </div>
            <div class="card-body p-0">
                <form id="bulkDeleteForm" method="post">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                                    </th>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_orders[]" value="<?php echo $order['id']; ?>" class="order-checkbox" onchange="updateBulkActions()">
                                </td>
                                <td>#<a href="order_detail.php?id=<?php echo $order['id']; ?>"><?php echo $order['id']; ?></a></td>
                                <td><?php echo htmlspecialchars($order['fullname'] ?? 'Guest'); ?></td>
                                <td>
                                    <?php if (!empty($order['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>"><?php echo htmlspecialchars($order['email']); ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">No email</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                <td>AED <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></td>
                                <td><code><?php echo $order['payment_reference']; ?></code></td>
                                <td>
                                    <span class="status-badge badge-<?php echo $order['payment_status']; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" class="action-form">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="payment_status" class="form-select form-select-sm" style="width: 100px;">
                                            <option value="pending" <?php if($order['payment_status']=='pending') echo "selected"; ?>>Pending</option>
                                            <option value="paid" <?php if($order['payment_status']=='paid') echo "selected"; ?>>Paid</option>
                                            <option value="failed" <?php if($order['payment_status']=='failed') echo "selected"; ?>>Failed</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </form>

                                    <?php if ($order['payment_status'] == 'paid'): ?>
                                    <a href="../download_invoice.php?id=<?php echo $order['id']; ?>" target="_blank" class="invoice-link">
                                        <i class="fas fa-file-invoice me-1"></i>Invoice
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                </div>
            </div>
        </div>
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

        // Bulk actions functionality
        function toggleAll(selectAllCheckbox) {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const bulkActions = document.querySelector('.bulk-actions');
            const selectedCount = document.querySelector('.selected-count');
            
            if (checkboxes.length > 0) {
                bulkActions.style.display = 'flex';
                selectedCount.textContent = `${checkboxes.length} selected`;
            } else {
                bulkActions.style.display = 'none';
            }
        }

        function bulkDelete() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select orders to delete.');
                return;
            }

            if (confirm(`Are you sure you want to delete ${checkboxes.length} order(s)? This action cannot be undone and will also delete related order items and invoice files.`)) {
                const form = document.getElementById('bulkDeleteForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulk_delete';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        }
    </script>
</body>
</html>
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

// Generate CSRF token for AJAX requests
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();
$message = '';
$order = null;
$customer = null;
$order_items = [];
$order_id = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    
    // Collect all form data
    $form_data = [
        'note' => trim($_POST['note'] ?? ''),
        'remarks' => trim($_POST['remarks'] ?? ''),
        'tracking_number' => trim($_POST['tracking_number'] ?? ''),
        'carrier_name' => trim($_POST['carrier_name'] ?? ''),
        'shipping_method' => trim($_POST['shipping_method'] ?? ''),
        'shipment_status' => $_POST['shipment_status'] ?? 'pending',
        'shipped_date' => $_POST['shipped_date'] ? $_POST['shipped_date'] : null,
        'shipping_aed' => floatval($_POST['shipping_aed'] ?? 0),
        'total_amount' => floatval($_POST['total_amount'] ?? 0),
        'payment_method' => trim($_POST['payment_method'] ?? ''),
        'payment_status' => $_POST['payment_status'] ?? 'pending',
        'coupon_code' => trim($_POST['coupon_code'] ?? ''),
        'discount_type' => !empty($_POST['discount_type']) ? $_POST['discount_type'] : null,
        'discount_value' => floatval($_POST['discount_value'] ?? 0),
        'discount_amount' => floatval($_POST['discount_amount'] ?? 0)
    ];

    // Customer data
    $customer_data = [
        'fullname' => trim($_POST['customer_name'] ?? ''),
        'email' => trim($_POST['customer_email'] ?? ''),
        'phone' => trim($_POST['customer_phone'] ?? ''),
        'address' => trim($_POST['customer_address'] ?? ''),
        'city' => trim($_POST['customer_city'] ?? ''),
        'country' => trim($_POST['customer_country'] ?? '')
    ];

    // Get order
    $order = $db->query("SELECT * FROM orders WHERE id = ?", [$order_id])->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $message = "Order #$order_id not found.";
    } else {
        try {
            // Update order details
            $db->query("UPDATE orders SET 
                note = ?, remarks = ?, tracking_number = ?, carrier_name = ?, 
                shipping_method = ?, shipment_status = ?, shipped_date = ?, 
                shipping_aed = ?, total_amount = ?, payment_method = ?, payment_status = ?,
                coupon_code = ?, discount_type = ?, discount_value = ?, discount_amount = ?
                WHERE id = ?", [
                $form_data['note'], $form_data['remarks'], $form_data['tracking_number'], 
                $form_data['carrier_name'], $form_data['shipping_method'], $form_data['shipment_status'], 
                $form_data['shipped_date'], $form_data['shipping_aed'], $form_data['total_amount'], 
                $form_data['payment_method'], $form_data['payment_status'],
                $form_data['coupon_code'], $form_data['discount_type'], $form_data['discount_value'], 
                $form_data['discount_amount'], $order_id
            ]);

            // Update customer details if customer exists
            if ($order['customer_id']) {
                $db->query("UPDATE customers SET 
                    fullname = ?, email = ?, phone = ?, address = ?, city = ?, country = ?
                    WHERE id = ?", [
                    $customer_data['fullname'], $customer_data['email'], $customer_data['phone'],
                    $customer_data['address'], $customer_data['city'], $customer_data['country'],
                    $order['customer_id']
                ]);
            }

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
        
        /* Product Management Styles */
        #order-items-table tbody tr {
            transition: background-color 0.3s ease;
        }
        
        #order-items-table tbody tr:hover:not(#add-product-row) {
            background-color: #f8f9fa;
        }
        
        .input-group-sm .btn {
            padding: 0.25rem 0.5rem;
        }
        
        .input-group-sm input[type="number"] {
            appearance: textfield;
            -moz-appearance: textfield;
        }
        
        .input-group-sm input[type="number"]::-webkit-outer-spin-button,
        .input-group-sm input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .btn-sm {
            transition: all 0.2s ease;
        }
        
        .btn-sm:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-sm:active {
            transform: translateY(0);
        }
        
        .btn-danger:hover {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        .btn-success:hover {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        #add-product-row {
            background-color: #fff3cd;
        }
        
        #add-product-row td {
            padding: 1rem 0.75rem;
        }
        
        .table tfoot tr {
            font-size: 1rem;
        }
        
        .table tfoot .table-success td {
            background-color: #d1e7dd !important;
        }
        
        /* Toast customizations */
        .toast {
            min-width: 300px;
        }
        
        .toast-header {
            font-weight: 600;
        }
        
        /* Loading spinner */
        .fa-spinner {
            animation: fa-spin 1s infinite linear;
        }
        
        @keyframes fa-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Highlight animations */
        @keyframes highlight {
            0% { background-color: #fff3cd; }
            100% { background-color: transparent; }
        }
        
        .item-updated {
            animation: highlight 1s ease;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .input-group {
                width: 100% !important;
            }
            
            #order-items-table {
                font-size: 0.875rem;
            }
        }
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
            <!-- Display Current Data for Reference -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Details</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Order ID:</strong> #<?= $order['id'] ?></p>
                            <p><strong>Date:</strong> <?= date('M j, Y h:i A', strtotime($order['order_date'] ?? 'now')) ?></p>
                            <p><strong>Current Total:</strong> AED <?= number_format($order['total_amount'], 2) ?></p>
                            <p><strong>Current Shipping:</strong> AED <?= number_format($order['shipping_aed'] ?? 0, 2) ?></p>
                            <p><strong>Payment Status:</strong> 
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
                            <h5 class="mb-0"><i class="fas fa-shipping-fast me-2"></i>Shipment Status</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Tracking:</strong> <?= htmlspecialchars($order['tracking_number'] ?? 'Not set') ?></p>
                            <p><strong>Carrier:</strong> <?= htmlspecialchars($order['carrier_name'] ?? 'Not set') ?></p>
                            <p><strong>Method:</strong> <?= htmlspecialchars($order['shipping_method'] ?? 'Not set') ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-info">
                                    <?= ucfirst($order['shipment_status'] ?? 'pending') ?>
                                </span>
                            </p>
                            <?php if ($order['shipped_date']): ?>
                                <p><strong>Shipped:</strong> <?= date('M j, Y', strtotime($order['shipped_date'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items with Product Management -->
            <div class="card mb-4" id="order-items-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Order Items</h5>
                    <button type="button" class="btn btn-success btn-sm" onclick="showAddProductRow()">
                        <i class="fas fa-plus me-1"></i> Add Product
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="order-items-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th width="200">Quantity</th>
                                    <th width="120">Unit Price</th>
                                    <th width="120">Total</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="order-items-body">
                                <!-- Add Product Row (Hidden by default) -->
                                <tr id="add-product-row" style="display: none;" class="table-warning">
                                    <td>
                                        <select class="form-select form-select-sm" id="new-product-select">
                                            <option value="">Select a product...</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" id="new-product-quantity" 
                                               value="1" min="1" style="width: 80px;">
                                    </td>
                                    <td colspan="2">
                                        <span id="new-product-price" class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm" onclick="confirmAddProduct()">
                                            <i class="fas fa-check"></i> Add
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="hideAddProductRow()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                                
                                <?php if (!empty($order_items)): ?>
                                    <?php foreach ($order_items as $item): ?>
                                    <tr data-item-id="<?= $item['id'] ?>" data-product-id="<?= $item['product_id'] ?>">
                                        <td class="item-name"><?= htmlspecialchars($item['name_en'] ?? 'Unknown Product') ?></td>
                                        <td>
                                            <div class="input-group input-group-sm" style="width: 150px;">
                                                <button type="button" class="btn btn-outline-secondary" 
                                                        onclick="updateItemQuantity(<?= $item['id'] ?>, -1)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="form-control text-center item-quantity" 
                                                       value="<?= $item['quantity'] ?>" min="1"
                                                       onchange="setItemQuantity(<?= $item['id'] ?>, this.value)">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="updateItemQuantity(<?= $item['id'] ?>, 1)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="item-price">AED <?= number_format($item['price'], 2) ?></td>
                                        <td class="item-total">AED <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                    onclick="removeItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name_en'] ?? 'this product', ENT_QUOTES) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr id="no-items-row">
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No items in this order. Click "Add Product" to start.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td colspan="2"><strong id="order-subtotal">AED <?= number_format(array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $order_items)), 2) ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                    <td colspan="2"><strong id="order-shipping">AED <?= number_format($order['shipping_aed'] ?? 0, 2) ?></strong></td>
                                </tr>
                                <tr class="table-success">
                                    <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                    <td colspan="2"><strong id="order-grand-total" class="text-success fs-5">AED <?= number_format($order['total_amount'], 2) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Enhanced Editable Form -->
            <form method="POST">
                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                
                <!-- Customer Details Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Editable Customer Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_name" class="form-label">Customer Name</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                       value="<?= htmlspecialchars($customer['fullname'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="customer_email" class="form-label">Customer Email</label>
                                <input type="email" class="form-control" id="customer_email" name="customer_email" 
                                       value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="customer_phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="customer_phone" name="customer_phone" 
                                       value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="customer_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="customer_city" name="customer_city" 
                                       value="<?= htmlspecialchars($customer['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="customer_country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="customer_country" name="customer_country" 
                                       value="<?= htmlspecialchars($customer['country'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="customer_address" class="form-label">Complete Address</label>
                                <textarea class="form-control" id="customer_address" name="customer_address" rows="3"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Details Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Editable Order Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="shipping_aed" class="form-label">
                                    Shipping Cost (AED)
                                    <i class="fas fa-info-circle text-muted" title="You can manually edit or click Recalculate in the order items table"></i>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">AED</span>
                                    <input type="number" step="0.01" class="form-control" id="shipping_aed" name="shipping_aed" 
                                           value="<?= number_format($order['shipping_aed'] ?? 0, 2, '.', '') ?>"
                                           onchange="updateManualTotals()">
                                </div>
                                <small class="text-muted">Manual override available</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="total_amount" class="form-label">Grand Total (AED)</label>
                                <div class="input-group">
                                    <span class="input-group-text">AED</span>
                                    <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" 
                                           value="<?= number_format($order['total_amount'], 2, '.', '') ?>"
                                           readonly style="background-color: #e9ecef;">
                                </div>
                                <small class="text-muted">Auto-calculated</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method">
                                    <option value="cash_on_delivery" <?= ($order['payment_method'] ?? '') === 'cash_on_delivery' ? 'selected' : '' ?>>Cash on Delivery</option>
                                    <option value="ziina" <?= ($order['payment_method'] ?? '') === 'ziina' ? 'selected' : '' ?>>Ziina Payment</option>
                                    <option value="bank_transfer" <?= ($order['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                    <option value="credit_card" <?= ($order['payment_method'] ?? '') === 'credit_card' ? 'selected' : '' ?>>Credit Card</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="payment_status" class="form-label">Payment Status</label>
                                <select class="form-select" id="payment_status" name="payment_status">
                                    <option value="pending" <?= ($order['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid" <?= ($order['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="failed" <?= ($order['payment_status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discount Section -->
                <div class="card mb-4">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h5 class="mb-0"><i class="fas fa-tag me-2"></i>Discount & Coupon</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="coupon_code" class="form-label">Coupon Code</label>
                                <input type="text" class="form-control" id="coupon_code" name="coupon_code" 
                                       value="<?= htmlspecialchars($order['coupon_code'] ?? '') ?>"
                                       placeholder="e.g., SAVE10">
                                <small class="text-muted">Optional</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="discount_type" class="form-label">Discount Type</label>
                                <select class="form-select" id="discount_type" name="discount_type" onchange="calculateDiscount()">
                                    <option value="">No Discount</option>
                                    <option value="fixed" <?= ($order['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Amount (AED)</option>
                                    <option value="percent" <?= ($order['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="discount_value" class="form-label">Discount Value</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="discount_value" name="discount_value" 
                                           value="<?= number_format($order['discount_value'] ?? 0, 2, '.', '') ?>"
                                           placeholder="0.00"
                                           onchange="calculateDiscount()">
                                    <span class="input-group-text" id="discount-unit">
                                        <?= ($order['discount_type'] ?? '') === 'percent' ? '%' : 'AED' ?>
                                    </span>
                                </div>
                                <small class="text-muted">Enter amount or percentage</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="discount_amount" class="form-label">Discount Amount (AED)</label>
                                <div class="input-group">
                                    <span class="input-group-text">AED</span>
                                    <input type="number" step="0.01" class="form-control" id="discount_amount" name="discount_amount" 
                                           value="<?= number_format($order['discount_amount'] ?? 0, 2, '.', '') ?>"
                                           readonly style="background-color: #fff3cd;">
                                </div>
                                <small class="text-muted">Calculated automatically</small>
                            </div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>How it works:</strong> 
                            Fixed discount subtracts a fixed AED amount. 
                            Percentage discount calculates based on subtotal before shipping.
                            Discount is applied before calculating the grand total.
                        </div>
                    </div>
                </div>

                <!-- Shipment Details Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Shipment Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tracking_number" class="form-label">Tracking Number</label>
                                <input type="text" class="form-control" id="tracking_number" name="tracking_number" 
                                       value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>" 
                                       placeholder="Enter tracking number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="carrier_name" class="form-label">Carrier/Courier</label>
                                <input type="text" class="form-control" id="carrier_name" name="carrier_name" 
                                       value="<?= htmlspecialchars($order['carrier_name'] ?? '') ?>" 
                                       placeholder="e.g., DHL, FedEx, Aramex">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="shipping_method" class="form-label">Shipping Method</label>
                                <input type="text" class="form-control" id="shipping_method" name="shipping_method" 
                                       value="<?= htmlspecialchars($order['shipping_method'] ?? '') ?>" 
                                       placeholder="e.g., Express, Standard, Same Day">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="shipment_status" class="form-label">Shipment Status</label>
                                <select class="form-select" id="shipment_status" name="shipment_status">
                                    <option value="pending" <?= ($order['shipment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="processing" <?= ($order['shipment_status'] ?? '') === 'processing' ? 'selected' : '' ?>>Processing</option>
                                    <option value="shipped" <?= ($order['shipment_status'] ?? '') === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="delivered" <?= ($order['shipment_status'] ?? '') === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="shipped_date" class="form-label">Shipped Date</label>
                                <input type="date" class="form-control" id="shipped_date" name="shipped_date" 
                                       value="<?= $order['shipped_date'] ? date('Y-m-d', strtotime($order['shipped_date'])) : '' ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes & Remarks</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="note" class="form-label">Customer Note (visible on invoice)</label>
                                <textarea class="form-control" id="note" name="note" rows="4" 
                                          placeholder="Special instructions, delivery notes, etc."><?= htmlspecialchars($order['note'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="remarks" class="form-label">Internal Remarks (admin only)</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="4" 
                                          placeholder="Internal notes, payment details, etc."><?= htmlspecialchars($order['remarks'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Orders
                    </a>
                    <div>
                        <a href="generate_invoice.php?id=<?= $order_id ?>" target="_blank" class="btn btn-success me-2">
                            <i class="fas fa-eye me-1"></i> Preview Invoice
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-1"></i> Update All & Regenerate Invoice
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Toast Notification Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="notification-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-info-circle me-2" id="toast-icon"></i>
                <strong class="me-auto" id="toast-title">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="toast-message"></div>
        </div>
    </div>

    <script>
        const ORDER_ID = <?= $order_id ?>;
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
        let products = [];

        // Show notification toast
        function showNotification(message, type = 'success') {
            const toast = document.getElementById('notification-toast');
            const toastTitle = document.getElementById('toast-title');
            const toastMessage = document.getElementById('toast-message');
            const toastIcon = document.getElementById('toast-icon');
            const toastHeader = toast.querySelector('.toast-header');
            
            // Set colors based on type
            if (type === 'success') {
                toastHeader.className = 'toast-header bg-success text-white';
                toastIcon.className = 'fas fa-check-circle me-2';
                toastTitle.textContent = 'Success';
            } else if (type === 'error') {
                toastHeader.className = 'toast-header bg-danger text-white';
                toastIcon.className = 'fas fa-exclamation-circle me-2';
                toastTitle.textContent = 'Error';
            } else {
                toastHeader.className = 'toast-header bg-info text-white';
                toastIcon.className = 'fas fa-info-circle me-2';
                toastTitle.textContent = 'Info';
            }
            
            toastMessage.textContent = message;
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }

        // Show loading state
        function setLoading(isLoading) {
            const buttons = document.querySelectorAll('#order-items-table button');
            buttons.forEach(btn => {
                btn.disabled = isLoading;
                if (isLoading && btn.querySelector('i')) {
                    btn.querySelector('i').className = 'fas fa-spinner fa-spin';
                }
            });
        }

        // Fetch products for dropdown
        async function loadProducts() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_products');
                formData.append('csrf_token', CSRF_TOKEN);
                formData.append('order_id', ORDER_ID);
                
                const response = await fetch('ajax/manage_order_products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    products = data.products;
                    populateProductSelect();
                } else {
                    console.error('Failed to load products:', data.error);
                }
            } catch (error) {
                console.error('Error loading products:', error);
            }
        }

        // Populate product select dropdown
        function populateProductSelect() {
            const select = document.getElementById('new-product-select');
            select.innerHTML = '<option value="">Select a product...</option>';
            
            // Get existing product IDs to filter them out
            const existingProductIds = Array.from(document.querySelectorAll('#order-items-body tr[data-product-id]'))
                .map(row => parseInt(row.dataset.productId));
            
            products.forEach(product => {
                // Skip if product already in order
                if (existingProductIds.includes(product.id)) {
                    return;
                }
                
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = `${product.name_en} - AED ${parseFloat(product.price).toFixed(2)}`;
                option.dataset.price = product.price;
                option.dataset.stock = product.stock;
                select.appendChild(option);
            });
        }

        // Show add product row
        function showAddProductRow() {
            loadProducts();
            document.getElementById('add-product-row').style.display = '';
            document.getElementById('new-product-quantity').value = 1;
            document.getElementById('new-product-price').textContent = '-';
        }

        // Hide add product row
        function hideAddProductRow() {
            document.getElementById('add-product-row').style.display = 'none';
            document.getElementById('new-product-select').value = '';
        }

        // Update price display when product selected
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.getElementById('new-product-select');
            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const priceSpan = document.getElementById('new-product-price');
                
                if (selectedOption.dataset.price) {
                    const price = parseFloat(selectedOption.dataset.price);
                    const quantity = parseInt(document.getElementById('new-product-quantity').value) || 1;
                    priceSpan.textContent = `AED ${price.toFixed(2)} × ${quantity} = AED ${(price * quantity).toFixed(2)}`;
                } else {
                    priceSpan.textContent = '-';
                }
            });
            
            document.getElementById('new-product-quantity').addEventListener('input', function() {
                const select = document.getElementById('new-product-select');
                const selectedOption = select.options[select.selectedIndex];
                const priceSpan = document.getElementById('new-product-price');
                
                if (selectedOption.dataset.price) {
                    const price = parseFloat(selectedOption.dataset.price);
                    const quantity = parseInt(this.value) || 1;
                    priceSpan.textContent = `AED ${price.toFixed(2)} × ${quantity} = AED ${(price * quantity).toFixed(2)}`;
                }
            });
        });

        // Confirm add product
        async function confirmAddProduct() {
            const productId = document.getElementById('new-product-select').value;
            const quantity = parseInt(document.getElementById('new-product-quantity').value);
            
            if (!productId) {
                showNotification('Please select a product', 'error');
                return;
            }
            
            if (!quantity || quantity < 1) {
                showNotification('Please enter a valid quantity', 'error');
                return;
            }
            
            setLoading(true);
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_product');
                formData.append('order_id', ORDER_ID);
                formData.append('product_id', productId);
                formData.append('quantity', quantity);
                formData.append('csrf_token', CSRF_TOKEN);
                
                const response = await fetch('ajax/manage_order_products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    hideAddProductRow();
                    
                    // Remove "no items" row if exists
                    const noItemsRow = document.getElementById('no-items-row');
                    if (noItemsRow) {
                        noItemsRow.remove();
                    }
                    
                    // Add or update item row
                    addOrUpdateItemRow(data.item);
                    
                    // Update totals
                    updateTotalsDisplay(data.totals);
                } else {
                    showNotification(data.error || 'Failed to add product', 'error');
                }
            } catch (error) {
                console.error('Error adding product:', error);
                showNotification('An error occurred while adding the product', 'error');
            } finally {
                setLoading(false);
            }
        }

        // Add or update item row in table
        function addOrUpdateItemRow(item) {
            let row = document.querySelector(`tr[data-item-id="${item.id}"]`);
            
            if (row) {
                // Update existing row
                row.querySelector('.item-quantity').value = item.quantity;
                row.querySelector('.item-total').textContent = `AED ${(item.quantity * item.price).toFixed(2)}`;
                highlightRow(row);
            } else {
                // Create new row
                const tbody = document.getElementById('order-items-body');
                const newRow = document.createElement('tr');
                newRow.dataset.itemId = item.id;
                newRow.dataset.productId = item.product_id;
                
                newRow.innerHTML = `
                    <td class="item-name">${escapeHtml(item.name_en)}</td>
                    <td>
                        <div class="input-group input-group-sm" style="width: 150px;">
                            <button type="button" class="btn btn-outline-secondary" 
                                    onclick="updateItemQuantity(${item.id}, -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center item-quantity" 
                                   value="${item.quantity}" min="1"
                                   onchange="setItemQuantity(${item.id}, this.value)">
                            <button type="button" class="btn btn-outline-primary" 
                                    onclick="updateItemQuantity(${item.id}, 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </td>
                    <td class="item-price">AED ${parseFloat(item.price).toFixed(2)}</td>
                    <td class="item-total">AED ${(item.quantity * item.price).toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm" 
                                onclick="removeItem(${item.id}, '${escapeHtml(item.name_en)}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                
                tbody.insertBefore(newRow, document.getElementById('add-product-row').nextSibling);
                highlightRow(newRow);
            }
        }

        // Update item quantity (increment/decrement)
        async function updateItemQuantity(itemId, change) {
            const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
            const input = row.querySelector('.item-quantity');
            const currentQty = parseInt(input.value);
            const newQty = currentQty + change;
            
            if (newQty < 1) {
                return;
            }
            
            await setItemQuantity(itemId, newQty);
        }

        // Set item quantity directly
        async function setItemQuantity(itemId, quantity) {
            quantity = parseInt(quantity);
            
            if (!quantity || quantity < 1) {
                showNotification('Quantity must be at least 1', 'error');
                return;
            }
            
            setLoading(true);
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_quantity');
                formData.append('order_id', ORDER_ID);
                formData.append('item_id', itemId);
                formData.append('quantity', quantity);
                formData.append('csrf_token', CSRF_TOKEN);
                
                const response = await fetch('ajax/manage_order_products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update row
                    const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
                    row.querySelector('.item-quantity').value = data.item.quantity;
                    row.querySelector('.item-total').textContent = `AED ${(data.item.quantity * data.item.price).toFixed(2)}`;
                    highlightRow(row);
                    
                    // Update totals
                    updateTotalsDisplay(data.totals);
                    
                    showNotification('Quantity updated', 'success');
                } else {
                    showNotification(data.error || 'Failed to update quantity', 'error');
                }
            } catch (error) {
                console.error('Error updating quantity:', error);
                showNotification('An error occurred', 'error');
            } finally {
                setLoading(false);
            }
        }

        // Remove item from order
        async function removeItem(itemId, productName) {
            if (!confirm(`Are you sure you want to remove "${productName}" from this order?`)) {
                return;
            }
            
            setLoading(true);
            
            try {
                const formData = new FormData();
                formData.append('action', 'remove_product');
                formData.append('order_id', ORDER_ID);
                formData.append('item_id', itemId);
                formData.append('csrf_token', CSRF_TOKEN);
                
                const response = await fetch('ajax/manage_order_products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove row with animation
                    const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    
                    setTimeout(() => {
                        row.remove();
                        
                        // Check if table is empty
                        const remainingItems = document.querySelectorAll('#order-items-body tr[data-item-id]');
                        if (remainingItems.length === 0) {
                            const tbody = document.getElementById('order-items-body');
                            const noItemsRow = document.createElement('tr');
                            noItemsRow.id = 'no-items-row';
                            noItemsRow.innerHTML = `
                                <td colspan="5" class="text-center text-muted py-4">
                                    No items in this order. Click "Add Product" to start.
                                </td>
                            `;
                            tbody.insertBefore(noItemsRow, document.getElementById('add-product-row'));
                        }
                    }, 300);
                    
                    // Update totals
                    updateTotalsDisplay(data.totals);
                    
                    showNotification('Product removed successfully', 'success');
                } else {
                    showNotification(data.error || 'Failed to remove product', 'error');
                }
            } catch (error) {
                console.error('Error removing product:', error);
                showNotification('An error occurred', 'error');
            } finally {
                setLoading(false);
            }
        }

        // Update totals display
        function updateTotalsDisplay(totals) {
            document.getElementById('order-subtotal').textContent = `AED ${totals.subtotal.toFixed(2)}`;
            
            // Update shipping with recalculate button
            const shippingElement = document.getElementById('order-shipping');
            const buttonHtml = ' <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="recalculateShipping()" title="Recalculate based on weight and location"><i class="fas fa-sync-alt"></i> Recalculate</button>';
            shippingElement.innerHTML = `AED ${totals.shipping.toFixed(2)}${buttonHtml}`;
            
            // Get current discount
            const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
            
            // Update discount row if exists
            updateDiscountRow(discount);
            
            // Calculate grand total with discount
            const grandTotal = totals.subtotal + totals.shipping - discount;
            document.getElementById('order-grand-total').textContent = `AED ${grandTotal.toFixed(2)}`;
            
            // Update form fields
            document.getElementById('shipping_aed').value = totals.shipping.toFixed(2);
            document.getElementById('total_amount').value = grandTotal.toFixed(2);
            
            // Highlight updated values
            highlightElement(document.getElementById('order-subtotal'));
            highlightElement(shippingElement.parentElement);
            highlightElement(document.getElementById('order-grand-total').parentElement);
        }

        // Highlight element temporarily
        function highlightElement(element) {
            element.style.transition = 'background-color 0.5s';
            element.style.backgroundColor = '#fff3cd';
            
            setTimeout(() => {
                element.style.backgroundColor = '';
            }, 1000);
        }

        // Highlight row temporarily
        function highlightRow(row) {
            row.style.transition = 'background-color 0.5s';
            row.style.backgroundColor = '#d1e7dd';
            
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 1000);
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Calculate discount based on type and value
        function calculateDiscount() {
            const discountType = document.getElementById('discount_type').value;
            const discountValue = parseFloat(document.getElementById('discount_value').value) || 0;
            const discountUnit = document.getElementById('discount-unit');
            
            // Update unit display
            if (discountType === 'percent') {
                discountUnit.textContent = '%';
            } else if (discountType === 'fixed') {
                discountUnit.textContent = 'AED';
            } else {
                discountUnit.textContent = 'AED';
            }
            
            // Calculate discount amount
            let discountAmount = 0;
            
            if (discountType === 'fixed') {
                discountAmount = discountValue;
            } else if (discountType === 'percent') {
                // Get current subtotal
                const subtotalText = document.getElementById('order-subtotal').textContent;
                const subtotal = parseFloat(subtotalText.replace('AED ', '').replace(',', ''));
                discountAmount = (subtotal * discountValue) / 100;
            }
            
            // Update discount amount field
            document.getElementById('discount_amount').value = discountAmount.toFixed(2);
            
            // Recalculate grand total
            updateManualTotals();
        }

        // Recalculate shipping based on weight and customer location
        async function recalculateShipping() {
            setLoading(true);
            
            try {
                const formData = new FormData();
                formData.append('action', 'calculate_totals');
                formData.append('order_id', ORDER_ID);
                formData.append('csrf_token', CSRF_TOKEN);
                
                const response = await fetch('ajax/manage_order_products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update shipping field
                    document.getElementById('shipping_aed').value = data.totals.shipping.toFixed(2);
                    document.getElementById('order-shipping').innerHTML = `AED ${data.totals.shipping.toFixed(2)} <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="recalculateShipping()" title="Recalculate based on weight and location"><i class="fas fa-sync-alt"></i> Recalculate</button>`;
                    
                    // Recalculate total
                    updateManualTotals();
                    
                    showNotification(`Shipping recalculated: AED ${data.totals.shipping.toFixed(2)} (Weight: ${data.totals.weight}kg)`, 'success');
                } else {
                    showNotification(data.error || 'Failed to recalculate shipping', 'error');
                }
            } catch (error) {
                console.error('Error recalculating shipping:', error);
                showNotification('An error occurred', 'error');
            } finally {
                setLoading(false);
            }
        }

        // Update grand total when shipping or discount changes manually
        function updateManualTotals() {
            // Get values
            const subtotalText = document.getElementById('order-subtotal').textContent;
            const subtotal = parseFloat(subtotalText.replace('AED ', '').replace(',', ''));
            const shipping = parseFloat(document.getElementById('shipping_aed').value) || 0;
            const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
            
            // Calculate grand total
            const grandTotal = subtotal + shipping - discount;
            
            // Update displays
            document.getElementById('order-shipping').innerHTML = `AED ${shipping.toFixed(2)} <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="recalculateShipping()" title="Recalculate based on weight and location"><i class="fas fa-sync-alt"></i> Recalculate</button>`;
            
            // Update or create discount row
            updateDiscountRow(discount);
            
            document.getElementById('order-grand-total').textContent = `AED ${grandTotal.toFixed(2)}`;
            document.getElementById('total_amount').value = grandTotal.toFixed(2);
            
            // Highlight changes
            highlightElement(document.getElementById('order-shipping').parentElement);
            highlightElement(document.getElementById('order-grand-total').parentElement);
        }

        // Update discount row in totals table
        function updateDiscountRow(discountAmount) {
            const couponCode = document.getElementById('coupon_code').value;
            const tfoot = document.querySelector('#order-items-table tfoot');
            let discountRow = document.getElementById('discount-row');
            
            if (discountAmount > 0) {
                const discountText = couponCode ? ` (${couponCode})` : '';
                
                if (!discountRow) {
                    // Create discount row
                    discountRow = document.createElement('tr');
                    discountRow.id = 'discount-row';
                    discountRow.className = 'table-warning';
                    discountRow.innerHTML = `
                        <td colspan="3" class="text-end"><strong>Discount${discountText}:</strong></td>
                        <td colspan="2"><strong id="order-discount" class="text-danger">- AED ${discountAmount.toFixed(2)}</strong></td>
                    `;
                    // Insert before grand total row
                    const grandTotalRow = tfoot.querySelector('.table-success');
                    grandTotalRow.parentNode.insertBefore(discountRow, grandTotalRow);
                } else {
                    // Update existing row
                    discountRow.querySelector('td:first-child').innerHTML = `<strong>Discount${discountText}:</strong>`;
                    discountRow.querySelector('#order-discount').textContent = `- AED ${discountAmount.toFixed(2)}`;
                }
                
                highlightRow(discountRow);
            } else {
                // Remove discount row if discount is 0
                if (discountRow) {
                    discountRow.remove();
                }
            }
        }

        // Initialize discount calculation on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set up discount type change listener
            const discountType = document.getElementById('discount_type');
            if (discountType) {
                discountType.addEventListener('change', calculateDiscount);
            }
            
            // Set up discount value change listener
            const discountValue = document.getElementById('discount_value');
            if (discountValue) {
                discountValue.addEventListener('input', calculateDiscount);
            }
        });
    </script>
</body>
</html>
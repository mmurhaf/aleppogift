<?php
// File: admin/edit_invoice.php
// Invoice editor with design that mimics the actual PDF invoice

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();
$message = '';
$messageType = '';
$order = null;
$customer = null;
$order_items = [];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle add new item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $order_id = intval($_POST['order_id']);
    
    try {
        $product_name = trim($_POST['item_product_name'] ?? '');
        $quantity = intval($_POST['item_quantity'] ?? 1);
        $price = floatval($_POST['item_price'] ?? 0);
        $product_id = intval($_POST['item_product_id'] ?? 0);
        
        if (empty($product_name) || $quantity <= 0 || $price < 0) {
            throw new Exception("Please provide valid product name, quantity, and price");
        }
        
        // Insert new order item
        $db->query("INSERT INTO order_items 
            (order_id, product_id, product_name, quantity, price, total) 
            VALUES (?, ?, ?, ?, ?, ?)", [
            $order_id,
            $product_id > 0 ? $product_id : null,
            $product_name,
            $quantity,
            $price,
            $quantity * $price
        ]);
        
        $message = "Item added successfully!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Error adding item: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle delete item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $order_id = intval($_POST['order_id']);
    $item_id = intval($_POST['item_id']);
    
    try {
        $db->query("DELETE FROM order_items WHERE id = ? AND order_id = ?", [$item_id, $order_id]);
        $message = "Item deleted successfully!";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Error deleting item: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    $order_id = intval($_POST['order_id']);
    
    try {
        // Update order details
        $db->query("UPDATE orders SET 
            note = ?,
            remarks = ?,
            tracking_number = ?,
            carrier_name = ?,
            shipping_method = ?,
            shipment_status = ?,
            shipped_date = ?,
            shipping_aed = ?,
            total_amount = ?,
            payment_method = ?,
            payment_status = ?,
            discount_amount = ?,
            total_weight = ?
            WHERE id = ?", [
            $_POST['note'] ?? '',
            $_POST['remarks'] ?? '',
            $_POST['tracking_number'] ?? '',
            $_POST['carrier_name'] ?? '',
            $_POST['shipping_method'] ?? '',
            $_POST['shipment_status'] ?? 'pending',
            $_POST['shipped_date'] ?: null,
            floatval($_POST['shipping_aed'] ?? 0),
            floatval($_POST['total_amount'] ?? 0),
            $_POST['payment_method'] ?? '',
            $_POST['payment_status'] ?? 'pending',
            floatval($_POST['discount_amount'] ?? 0),
            floatval($_POST['total_weight'] ?? 0),
            $order_id
        ]);

        // Update customer details
        $customer_id = intval($_POST['customer_id']);
        if ($customer_id > 0) {
            $db->query("UPDATE customers SET 
                fullname = ?,
                email = ?,
                phone = ?,
                address = ?,
                city = ?,
                country = ?
                WHERE id = ?", [
                $_POST['customer_name'] ?? '',
                $_POST['customer_email'] ?? '',
                $_POST['customer_phone'] ?? '',
                $_POST['customer_address'] ?? '',
                $_POST['customer_city'] ?? '',
                $_POST['customer_country'] ?? '',
                $customer_id
            ]);
        }

        $message = "Invoice updated successfully!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Error updating invoice: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Load order data
if ($order_id > 0) {
    $order = $db->query("
        SELECT o.*, c.fullname AS customer_name, c.email AS customer_email,
               c.phone AS customer_phone, c.address AS customer_address,
               c.city AS customer_city, c.country AS customer_country
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
    ", [$order_id])->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $customer = $db->query("SELECT * FROM customers WHERE id = ?", [$order['customer_id']])->fetch(PDO::FETCH_ASSOC);
        $order_items = $db->query("
            SELECT oi.*, p.name_en, p.name_ar
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ", [$order_id])->fetchAll(PDO::FETCH_ASSOC);
    }
}

$invoice_number = 'INV-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
$invoice_date = $order ? date('F j, Y', strtotime($order['order_date'] ?? 'now')) : date('F j, Y');
$aed_to_usd = 0.27;
?>
<?php
$page_title = 'Edit Invoice';
require_once __DIR__ . '/includes/header.php';
?>
<style>
        :root {
            --primary-color: #e67b2e;
            --header-color: #2c3e50;
            --light-gray: #f5f5f5;
            --border-color: #dcdcdc;
        }
        
        .edit-invoice-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-editor {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, var(--primary-color), #ff9a56);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .invoice-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .invoice-header .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .invoice-body {
            padding: 40px;
        }
        
        .section-header {
            background: var(--header-color);
            color: white;
            padding: 12px 20px;
            margin: 30px -40px 20px -40px;
            font-size: 1.1rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header:first-child {
            margin-top: 0;
        }
        
        .invoice-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: var(--light-gray);
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            border-radius: 8px;
        }
        
        .info-box h3 {
            color: var(--header-color);
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px 12px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(230, 123, 46, 0.15);
        }
        
        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .items-table thead {
            background: var(--header-color);
            color: white;
        }
        
        .items-table th {
            padding: 15px 12px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .items-table td {
            padding: 15px 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .items-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .totals-table {
            width: 100%;
            max-width: 500px;
            margin-left: auto;
            margin-top: 30px;
        }
        
        .totals-table tr {
            border-bottom: 1px solid var(--border-color);
        }
        
        .totals-table td {
            padding: 12px 15px;
        }
        
        .totals-table .label {
            font-weight: 600;
            text-align: right;
            color: #555;
        }
        
        .totals-table .value {
            text-align: center;
            font-weight: 600;
            background: var(--light-gray);
            border-left: 3px solid var(--primary-color);
        }
        
        .totals-table .value input {
            font-weight: 600;
            text-align: center;
        }
        
        .grand-total-row {
            background: var(--primary-color);
            color: white;
        }
        
        .grand-total-row td {
            padding: 15px;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .grand-total-row .value {
            background: rgba(255, 255, 255, 0.2);
            border-left: 3px solid white;
        }
        
        .grand-total-row input {
            color: white;
            background: transparent;
            border-color: rgba(255,255,255,0.3);
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            padding: 30px 40px;
            background: var(--light-gray);
            margin: 40px -40px -40px -40px;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 50px;
            color: white;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-secondary {
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 50px;
            transition: all 0.3s;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .currency-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            background: #6c757d;
            color: white;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .action-buttons, .section-header {
                display: none;
            }
        }
    </style>
    <div class="edit-invoice-page">
    <div class="invoice-editor">
        <div class="invoice-header">
            <h1><i class="fas fa-file-invoice"></i> INVOICE</h1>
            <div class="subtitle">AleppoGift.com | Premium Syrian Products</div>
            <div style="margin-top: 15px; font-size: 0.95rem;">
                <strong>Date:</strong> <?= $invoice_date ?> &nbsp;|&nbsp;
                <strong>Invoice #:</strong> <?= $invoice_number ?> &nbsp;|&nbsp;
                <strong>Order ID:</strong> #<?= $order_id ?>
            </div>
        </div>

        <?php if ($message): ?>
            <div style="padding: 20px 40px 0 40px;">
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$order): ?>
            <div style="padding: 40px; text-align: center;">
                <h3>Order not found</h3>
                <p>Please select a valid order ID.</p>
                <a href="orders.php" class="btn btn-primary">Back to Orders</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                <input type="hidden" name="customer_id" value="<?= $order['customer_id'] ?>">
                
                <div class="invoice-body">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i> Company & Customer Information
                    </div>
                    
                    <div class="invoice-info-grid">
                        <div class="info-box">
                            <h3><i class="fas fa-building"></i> FROM:</h3>
                            <div style="color: #666; line-height: 1.8;">
                                <strong>Aleppo Gift Trading LLC</strong><br>
                                Dubai, United Arab Emirates<br>
                                Phone: +971 56 112 5320<br>
                                Email: info@aleppogift.com
                            </div>
                        </div>
                        
                        <div class="info-box">
                            <h3><i class="fas fa-user"></i> BILL TO:</h3>
                            <div class="mb-2">
                                <label class="form-label">Customer Name</label>
                                <input type="text" name="customer_name" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_name'] ?? '') ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Email</label>
                                <input type="email" name="customer_email" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_email'] ?? '') ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Phone</label>
                                <input type="text" name="customer_phone" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_phone'] ?? '') ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Address</label>
                                <input type="text" name="customer_address" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_address'] ?? '') ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">City</label>
                                    <input type="text" name="customer_city" class="form-control" 
                                           value="<?= htmlspecialchars($order['customer_city'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="customer_country" class="form-control" 
                                           value="<?= htmlspecialchars($order['customer_country'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-header">
                        <i class="fas fa-credit-card"></i> Payment & Shipping Information
                    </div>
                    
                    <div class="invoice-info-grid">
                        <div class="info-box">
                            <h3><i class="fas fa-money-bill-wave"></i> PAYMENT INFORMATION</h3>
                            <div class="mb-2">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="zina" <?= ($order['payment_method'] ?? '') === 'zina' ? 'selected' : '' ?>>Zina</option>
                                    <option value="stripe" <?= ($order['payment_method'] ?? '') === 'stripe' ? 'selected' : '' ?>>Stripe</option>
                                    <option value="cod" <?= ($order['payment_method'] ?? '') === 'cod' ? 'selected' : '' ?>>Cash on Delivery</option>
                                    <option value="bank_transfer" <?= ($order['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" class="form-select">
                                    <option value="pending" <?= ($order['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid" <?= ($order['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="failed" <?= ($order['payment_status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="info-box">
                            <h3><i class="fas fa-shipping-fast"></i> SHIPPING INFORMATION</h3>
                            <div class="mb-2">
                                <label class="form-label">Shipping Method</label>
                                <input type="text" name="shipping_method" class="form-control" 
                                       value="<?= htmlspecialchars($order['shipping_method'] ?? '') ?>"
                                       placeholder="e.g., Express, Standard">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Carrier Name</label>
                                <input type="text" name="carrier_name" class="form-control" 
                                       value="<?= htmlspecialchars($order['carrier_name'] ?? '') ?>"
                                       placeholder="e.g., DHL, FedEx, Aramex">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Tracking Number</label>
                                <input type="text" name="tracking_number" class="form-control" 
                                       value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Shipment Status</label>
                                    <select name="shipment_status" class="form-select">
                                        <option value="pending" <?= ($order['shipment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="processing" <?= ($order['shipment_status'] ?? '') === 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="shipped" <?= ($order['shipment_status'] ?? '') === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="delivered" <?= ($order['shipment_status'] ?? '') === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Shipped Date</label>
                                    <input type="date" name="shipped_date" class="form-control" 
                                           value="<?= !empty($order['shipped_date']) ? date('Y-m-d', strtotime($order['shipped_date'])) : '' ?>">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Total Weight (kg)</label>
                                <input type="number" step="0.01" name="total_weight" class="form-control" 
                                       value="<?= htmlspecialchars($order['total_weight'] ?? 0) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="section-header">
                        <i class="fas fa-box"></i> Order Items
                    </div>
                    
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="text-align: left;">PRODUCT DESCRIPTION</th>
                                <th style="width: 80px;">QTY</th>
                                <th style="width: 120px;">UNIT PRICE</th>
                                <th style="width: 120px;">TOTAL</th>
                                <th style="width: 80px;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            $item_number = 1;
                            foreach ($order_items as $item): 
                                $item_total = $item['price'] * $item['quantity'];
                                $subtotal += $item_total;
                                $product_name = $item['name_en'] ?: $item['name_ar'] ?: $item['product_name'] ?: 'Product';
                            ?>
                            <tr>
                                <td style="text-align: center;"><?= $item_number++ ?></td>
                                <td><?= htmlspecialchars($product_name) ?>
                                    <?php if (!empty($item['product_id'])): ?>
                                        <small style="color: #999;">(ID: <?= $item['product_id'] ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;"><?= $item['quantity'] ?></td>
                                <td style="text-align: center;">AED <?= number_format($item['price'], 2) ?></td>
                                <td style="text-align: center;">AED <?= number_format($item_total, 2) ?></td>
                                <td style="text-align: center;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this item?');">
                                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="delete_item" class="btn btn-sm btn-danger" style="padding: 4px 8px; font-size: 0.8rem;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Add New Item Form -->
                    <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px dashed var(--primary-color);">
                        <h4 style="color: var(--primary-color); margin-bottom: 15px;">
                            <i class="fas fa-plus-circle"></i> Add New Item
                        </h4>
                        <form method="POST" id="addItemForm">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <div class="row g-3">
                                <div class="col-md-1">
                                    <label class="form-label" style="font-size: 0.85rem;">Product ID</label>
                                    <input type="number" name="item_product_id" class="form-control form-control-sm" placeholder="Optional">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" style="font-size: 0.85rem;">Product Name <span style="color: red;">*</span></label>
                                    <input type="text" name="item_product_name" class="form-control form-control-sm" required placeholder="Enter product name">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" style="font-size: 0.85rem;">Quantity <span style="color: red;">*</span></label>
                                    <input type="number" name="item_quantity" class="form-control form-control-sm" value="1" min="1" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" style="font-size: 0.85rem;">Unit Price (AED) <span style="color: red;">*</span></label>
                                    <input type="number" name="item_price" class="form-control form-control-sm" step="0.01" min="0" required placeholder="0.00">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" style="font-size: 0.85rem;">&nbsp;</label>
                                    <button type="submit" name="add_item" class="btn btn-sm w-100" style="background: var(--primary-color); color: white; font-weight: bold;">
                                        <i class="fas fa-plus"></i> Add Item
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="section-header">
                        <i class="fas fa-calculator"></i> Order Summary & Totals
                    </div>
                    
                    <table class="totals-table">
                        <tr>
                            <td class="label">Subtotal:</td>
                            <td class="value">
                                AED <?= number_format($subtotal, 2) ?>
                                <span class="currency-badge">$<?= number_format($subtotal * $aed_to_usd, 2) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Shipping:</td>
                            <td class="value">
                                <input type="number" step="0.01" name="shipping_aed" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($order['shipping_aed'] ?? 0) ?>"
                                       onchange="calculateTotal()">
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Discount:</td>
                            <td class="value">
                                <input type="number" step="0.01" name="discount_amount" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($order['discount_amount'] ?? 0) ?>"
                                       onchange="calculateTotal()">
                            </td>
                        </tr>
                        <tr class="grand-total-row">
                            <td class="label">GRAND TOTAL:</td>
                            <td class="value">
                                <input type="number" step="0.01" name="total_amount" 
                                       id="total_amount"
                                       class="form-control" 
                                       value="<?= htmlspecialchars($order['total_amount'] ?? 0) ?>"
                                       readonly>
                            </td>
                        </tr>
                    </table>

                    <div class="section-header">
                        <i class="fas fa-sticky-note"></i> Notes & Remarks
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Note (Visible on Invoice)</label>
                            <textarea name="note" class="form-control" rows="4" 
                                      placeholder="Note visible to customer on invoice..."><?= htmlspecialchars($order['note'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Internal Remarks (Admin Only)</label>
                            <textarea name="remarks" class="form-control" rows="4" 
                                      placeholder="Internal notes, not visible to customer..."><?= htmlspecialchars($order['remarks'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="submit" name="save_invoice" class="btn btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="generate_invoice.php?id=<?= $order_id ?>" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-file-pdf"></i> View PDF
                    </a>
                    <a href="order_detail.php?id=<?= $order_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Order
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    </div>

    <script>
        function calculateTotal() {
            const subtotal = <?= $subtotal ?>;
            const shipping = parseFloat(document.querySelector('[name="shipping_aed"]').value) || 0;
            const discount = parseFloat(document.querySelector('[name="discount_amount"]').value) || 0;
            const total = subtotal + shipping - discount;
            document.getElementById('total_amount').value = total.toFixed(2);
        }

        // Auto-calculate on page load
        window.addEventListener('load', calculateTotal);
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

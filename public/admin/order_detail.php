<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new Database();

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($order_id <= 0) {
    die("Invalid order ID.");
}

// Fetch order info with error handling
try {
    $order = $db->query("SELECT * FROM orders WHERE id = :id", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        die("Order not found.");
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch order items with product & variation - add error handling
try {
    $items = $db->query("
        SELECT 
            oi.*, 
            p.name_en AS product_name, 
            p.id,
            pv.size, 
            pv.color,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_variations pv ON oi.variation_id = pv.id
        WHERE oi.order_id = :order_id
    ", ['order_id' => $order_id])->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database error fetching items: " . $e->getMessage());
}

// Fetch customer data with error handling
$customer = [];
if (!empty($order['customer_id'])) {
    try {
        $customer_data = $db->query("SELECT * FROM customers WHERE id = :id", ['id' => $order['customer_id']])->fetch(PDO::FETCH_ASSOC);
        if ($customer_data) {
            $customer = $customer_data;
        }
    } catch (Exception $e) {
        // Log error but don't die, just leave customer empty
        error_log("Error fetching customer: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($order['id']) ?> Details - AleppoGift</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --dark-color: #343a40;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .header h1 {
            color: var(--dark-color);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
        }
        
        .header h1 i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #6c757d, #495057);
            margin-right: 10px;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #28a745, #20c997);
            margin-right: 10px;
        }
        
        .btn-generate {
            background: linear-gradient(135deg, #17a2b8, #20c997);
            margin-right: 10px;
        }
        
        .header div {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header div {
                width: 100%;
                justify-content: flex-start;
                margin-top: 15px;
            }
            
            .btn {
                font-size: 0.8rem;
                padding: 8px 12px;
            }
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h2 {
            font-size: 1.3rem;
            color: var(--dark-color);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        
        .section-title {
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
            color: #333;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
        }
        
        .info-value {
            text-align: right;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }
        
        .status-paid {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-failed {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 500;
            color: var(--dark-color);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .summary-table {
            width: 100%;
            max-width: 400px;
            margin-left: auto;
        }
        
        .summary-table td {
            padding: 10px 15px;
        }
        
        .summary-table tr:last-child td {
            border-top: 2px solid #e0e0e0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .notes-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .notes-label {
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 5px;
            display: block;
        }
        
        .notes-content {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-line;
        }
        
        .json-container {
            background-color: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .json-key {
            color: #63b3ed;
            font-weight: bold;
        }
        
        .json-string {
            color: #68d391;
        }
        
        .json-boolean {
            color: #fbb6ce;
        }
        
        .json-url {
            color: #90cdf4;
            text-decoration: underline;
            cursor: pointer;
        }
        
        .json-toggle {
            background: #4a5568;
            border: none;
            color: #e2e8f0;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        .json-toggle:hover {
            background: #2d3748;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25em 0.5em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        
        .bg-success {
            background-color: #28a745 !important;
            color: white;
        }
        
        .bg-danger {
            background-color: #dc3545 !important;
            color: white;
        }
        
        .bg-info {
            background-color: #17a2b8 !important;
            color: white;
        }
        
        .empty-state {
            color: #6c757d;
            text-align: center;
            padding: 30px;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-table {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-invoice"></i> Order #<?= htmlspecialchars($order['id']) ?></h1>
            <div>
                <a href="regenerate_invoice.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-edit" title="Edit and regenerate invoice">
                    <i class="fas fa-edit"></i> Edit Invoice
                </a>
                <a href="generate_invoice.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-generate" target="_blank" title="View/Generate invoice">
                    <i class="fas fa-file-pdf"></i> View Invoice
                </a>
                <a href="#" class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Page</a>
                <a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Order Information</h2>
                <span class="status-badge status-<?= strtolower($order['payment_status']) ?>">
                    <?= ucfirst($order['payment_status']) ?>
                </span>
            </div>
            
            <div class="info-grid">
                <!-- Order Details Section -->
                <div class="info-section">
                    <h3 class="section-title">Order Details</h3>
                    
                    <div class="info-item">
                        <span class="info-label">Order Date</span>
                        <span class="info-value"><?= date('F j, Y \a\t g:i A', strtotime($order['order_date'])) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Payment Method</span>
                        <span class="info-value"><?= $order['payment_method'] ?: '—' ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Reference</span>
                        <span class="info-value"><?= $order['payment_reference'] ?: '—' ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Shipping Cost</span>
                        <span class="info-value">AED <?= number_format($order['shipping_aed'], 2) ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Total Weight</span>
                        <span class="info-value"><?= number_format($order['total_weight']) ?> g</span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Coupon Code</span>
                        <span class="info-value"><?= $order['coupon_code'] ?: '—' ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Discount</span>
                        <span class="info-value">
                            <?= $order['discount_amount'] ? 'AED '.number_format($order['discount_amount'], 2) : '—' ?>
                            <?= $order['discount_type'] && $order['discount_value'] ? 
                                '('.$order['discount_type'].' '.$order['discount_value'].($order['discount_type'] == 'percent' ? '%' : '').')' : '' ?>
                        </span>
                    </div>
                </div>
                
                <!-- Customer Details Section -->
                <div class="info-section">
                    <h3 class="section-title">Customer Details</h3>
                    
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?= htmlspecialchars($customer['fullname'] ?? '—') ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($customer['email'] ?? '—') ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($customer['phone'] ?? '—') ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($customer['address'] ?? '—') ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">City</span>
                        <span class="info-value"><?= htmlspecialchars($customer['city'] ?? '—') ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Country</span>
                        <span class="info-value"><?= htmlspecialchars($customer['country'] ?? '—') ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?= $customer['created_at'] ? date('F j, Y', strtotime($customer['created_at'])) : '—' ?></span>
                    </div>
                </div>
            </div>
            
            <?php if ($order['note'] || $order['remarks']): ?>
            <div class="notes-section">
                <?php if ($order['note']): ?>
                <div style="margin-bottom: 15px;">
                    <span class="notes-label">Customer Note</span>
                    <div class="notes-content"><?= nl2br(htmlspecialchars($order['note'])) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($order['remarks']): ?>
                <div>
                    <span class="notes-label">Admin Remarks</span>
                    <?php
                    $remarks = trim($order['remarks']);
                    $jsonData = json_decode($remarks, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)):
                        // It's valid JSON, display it formatted
                    ?>
                        <div class="notes-content">
                            <button class="json-toggle" onclick="toggleJsonView(this)">
                                <i class="fas fa-eye"></i> Toggle Raw/Formatted View
                            </button>
                            
                            <div class="json-formatted" style="display: block;">
                                <?php if (isset($jsonData['success'])): ?>
                                    <div style="margin-bottom: 15px;">
                                        <strong>Payment Status:</strong> 
                                        <span class="badge <?= $jsonData['success'] ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $jsonData['success'] ? 'Success' : 'Failed' ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($jsonData['payment_url'])): ?>
                                    <div style="margin-bottom: 10px;">
                                        <strong>Payment URL:</strong><br>
                                        <a href="<?= htmlspecialchars($jsonData['payment_url']) ?>" 
                                           target="_blank" class="json-url">
                                            <?= htmlspecialchars($jsonData['payment_url']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($jsonData['payment_id'])): ?>
                                    <div style="margin-bottom: 10px;">
                                        <strong>Payment ID:</strong><br>
                                        <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 0.9em; word-break: break-all;">
                                            <?= htmlspecialchars($jsonData['payment_id']) ?>
                                        </code>
                                        <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($jsonData['payment_id']) ?>')" 
                                                style="margin-left: 8px; background: none; border: 1px solid #6c757d; border-radius: 3px; padding: 2px 8px; font-size: 0.8em; cursor: pointer;" 
                                                title="Copy to clipboard">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($jsonData['status'])): ?>
                                    <div style="margin-bottom: 10px;">
                                        <strong>Status:</strong><br>
                                        <span class="badge bg-info">
                                            <?= ucwords(str_replace('_', ' ', htmlspecialchars($jsonData['status']))) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($jsonData['timestamp']) || isset($jsonData['created_at']) || isset($jsonData['updated_at'])): ?>
                                    <div style="margin-bottom: 10px;">
                                        <strong>Timestamp:</strong><br>
                                        <span style="color: #6c757d; font-size: 0.9em;">
                                            <?php
                                            $timestamp = $jsonData['timestamp'] ?? $jsonData['created_at'] ?? $jsonData['updated_at'];
                                            if (is_numeric($timestamp)) {
                                                echo date('M j, Y g:i A', $timestamp);
                                            } else {
                                                echo htmlspecialchars($timestamp);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php
                                // Display any other fields
                                $displayedKeys = ['success', 'payment_url', 'payment_id', 'status'];
                                foreach ($jsonData as $key => $value):
                                    if (!in_array($key, $displayedKeys)):
                                ?>
                                    <div style="margin-bottom: 10px;">
                                        <strong><?= ucwords(str_replace('_', ' ', htmlspecialchars($key))) ?>:</strong><br>
                                        <?php if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)): ?>
                                            <a href="<?= htmlspecialchars($value) ?>" target="_blank" class="json-url">
                                                <?= htmlspecialchars($value) ?>
                                            </a>
                                        <?php else: ?>
                                            <span><?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                            
                            <div class="json-raw" style="display: none;">
                                <div class="json-container">
                                    <?= htmlspecialchars(json_encode($jsonData, JSON_PRETTY_PRINT)) ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Not JSON, display as regular text -->
                        <div class="notes-content"><?= nl2br(htmlspecialchars($remarks)) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-box-open"></i> Order Items</h2>
                <span class="info-value"><?= count($items) ?> item<?= count($items) != 1 ? 's' : '' ?></span>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variation</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td style="min-width: 250px;">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <?php if (!empty($item['image'])): ?>
                                            <a href="../product.php?id=<?= htmlspecialchars($item['product_id'] ?? '') ?>" target="_blank">
                                            <img src="../<?= htmlspecialchars($item['image']) ?>" alt="" class="product-img">
                                            </a>
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                                <i class="fas fa-image" style="color: #6c757d;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 500;">
                                                <a href="../product.php?id=<?= htmlspecialchars($item['product_id'] ?? '') ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                                                <?= htmlspecialchars($item['product_name'] ?? 'Unknown Product') ?>
                                                </a>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #6c757d;">SKU: <?= htmlspecialchars($item['sku'] ?? '—') ?> / id: <?= htmlspecialchars($item['id'] ?? '—') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                        <?= htmlspecialchars($item['size'] ?? '') ?>
                                        <?= (!empty($item['size']) && !empty($item['color'])) ? ' / ' : '' ?>
                                        <?= htmlspecialchars($item['color'] ?? '') ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>AED <?= number_format($item['price'] ?? 0, 2) ?></td>
                                <td><?= intval($item['quantity'] ?? 0) ?></td>
                                <td>AED <?= number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 0), 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">No items found in this order</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <table class="summary-table">
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">AED <?= number_format(($order['total_amount'] ?? 0) - ($order['shipping_aed'] ?? 0) + ($order['discount_amount'] ?? 0), 2) ?></td>
                </tr>
                <?php if (!empty($order['discount_amount'])): ?>
                <tr>
                    <td>Discount:</td>
                    <td style="text-align: right;">-AED <?= number_format($order['discount_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Shipping:</td>
                    <td style="text-align: right;">AED <?= number_format($order['shipping_aed'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td>Total:</td>
                    <td style="text-align: right;">AED <?= number_format($order['total_amount'] ?? 0, 2) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <script>
        // Toggle JSON view function
        function toggleJsonView(button) {
            const container = button.parentElement;
            const formatted = container.querySelector('.json-formatted');
            const raw = container.querySelector('.json-raw');
            
            if (formatted.style.display === 'none') {
                formatted.style.display = 'block';
                raw.style.display = 'none';
                button.innerHTML = '<i class="fas fa-eye"></i> Toggle Raw/Formatted View';
            } else {
                formatted.style.display = 'none';
                raw.style.display = 'block';
                button.innerHTML = '<i class="fas fa-code"></i> Toggle Raw/Formatted View';
            }
        }
        
        // Copy to clipboard function
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    showToast('Copied to clipboard!');
                }).catch(function() {
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                fallbackCopyTextToClipboard(text);
            }
        }
        
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showToast('Copied to clipboard!');
            } catch (err) {
                showToast('Failed to copy');
            }
            
            document.body.removeChild(textArea);
        }
        
        function showToast(message) {
            const toast = document.createElement('div');
            toast.textContent = message;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                z-index: 10000;
                font-size: 14px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        // Add loading states for buttons
        document.addEventListener('DOMContentLoaded', function() {
            const editBtn = document.querySelector('.btn-edit');
            const generateBtn = document.querySelector('.btn-generate');
            
            if (editBtn) {
                editBtn.addEventListener('click', function(e) {
                    const confirmed = confirm('Are you sure you want to edit and regenerate the invoice for Order #<?= htmlspecialchars($order['id']) ?>? This will allow you to modify notes and remarks.');
                    if (confirmed) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                        return true;
                    } else {
                        e.preventDefault();
                        return false;
                    }
                });
            }
            
            if (generateBtn) {
                generateBtn.addEventListener('click', function() {
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
                    
                    // Reset button after a delay if still on page
                    setTimeout(() => {
                        this.innerHTML = originalContent;
                    }, 3000);
                });
            }
        });
    </script>
</body>
</html>

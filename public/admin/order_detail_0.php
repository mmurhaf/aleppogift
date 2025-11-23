<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($order_id <= 0) {
    die("Invalid order ID.");
}

// Fetch order info
$order = $db->query("SELECT * FROM orders WHERE id = :id", ['id' => $order_id])->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die("Order not found.");
}

// Fetch order items with product & variation
$items = $db->query("
    SELECT 
        oi.*, 
        p.name_en AS product_name, 
        pv.size, 
        pv.color,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_variations pv ON oi.variation_id = pv.id
    WHERE oi.order_id = :order_id
", ['order_id' => $order_id])->fetchAll(PDO::FETCH_ASSOC);

// Assuming $order contains your order data with a customer_id field
$customer = [];
if (!empty($order['customer_id'])) {
    $customer = $db->query("SELECT * FROM customers WHERE id = :id", ['id' => $order['customer_id']])->fetch(PDO::FETCH_ASSOC);
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
                <a href="#" class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</a>
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
                    <div class="notes-content"><?= nl2br(htmlspecialchars($order['remarks'])) ?></div>
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
                                        <?php if ($item['image']): ?>
                                            <img src="../<?= htmlspecialchars($item['image']) ?>" alt="" class="product-img">
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                                <i class="fas fa-image" style="color: #6c757d;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <div style="font-size: 0.8rem; color: #6c757d;">SKU: <?= $item['id'] ?: '—' ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($item['size'] || $item['color']): ?>
                                        <?= $item['size'] ?: '' ?>
                                        <?= ($item['size'] && $item['color']) ? ' / ' : '' ?>
                                        <?= $item['color'] ?: '' ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>AED <?= number_format($item['price'], 2) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>AED <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
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
                    <td style="text-align: right;">AED <?= number_format($order['total_amount'] - $order['shipping_aed'] + ($order['discount_amount'] ?? 0), 2) ?></td>
                </tr>
                <?php if ($order['discount_amount']): ?>
                <tr>
                    <td>Discount:</td>
                    <td style="text-align: right;">-AED <?= number_format($order['discount_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Shipping:</td>
                    <td style="text-align: right;">AED <?= number_format($order['shipping_aed'], 2) ?></td>
                </tr>
                <tr>
                    <td>Total:</td>
                    <td style="text-align: right;">AED <?= number_format($order['total_amount'], 2) ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>

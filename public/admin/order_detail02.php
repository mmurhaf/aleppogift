<?php
$root_dir = dirname(__DIR__); if (!file_exists($root_dir . '/config/config.php')) { $root_dir = dirname(__DIR__); if (!file_exists($root_dir . '/config/config.php')) { $root_dir = dirname(dirname(__DIR__)); } }
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

// Fetch order items with resilient fallbacks for mixed schemas/data
$items = [];
try {
    $items = $db->query("
        SELECT 
            oi.*, 
            COALESCE(NULLIF(p.name_en, ''), NULLIF(oi.product_name, ''), NULLIF(p.name_ar, ''), 'Unknown Product') AS product_name,
            p.id AS product_exists_id,
            pv.id AS variation_id_info,
            pv.size, 
            pv.color,
            pv.image_id,
            COALESCE(pi_variant.image_path, pi_main.image_path) AS image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_variations pv ON oi.variation_id = pv.id
        LEFT JOIN product_images pi_variant ON pv.image_id = pi_variant.id
        LEFT JOIN product_images pi_main ON p.id = pi_main.product_id AND pi_main.is_main = 1
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ", ['order_id' => $order_id])->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Order detail advanced item query failed for order #{$order_id}: " . $e->getMessage());
    try {
        $items = $db->query("
            SELECT 
                oi.*, 
                COALESCE(NULLIF(p.name_en, ''), NULLIF(oi.product_name, ''), NULLIF(p.name_ar, ''), 'Unknown Product') AS product_name,
                p.id AS product_exists_id,
                NULL AS variation_id_info,
                NULL AS size,
                NULL AS color,
                NULL AS image_id,
                pi_main.image_path AS image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_images pi_main ON p.id = pi_main.product_id AND pi_main.is_main = 1
            WHERE oi.order_id = :order_id
            ORDER BY oi.id ASC
        ", ['order_id' => $order_id])->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $fallbackException) {
        die("Database error fetching items: " . $fallbackException->getMessage());
    }
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
<?php
$page_title = 'Order #' . $order['id'];
require_once __DIR__ . '/includes/header.php';
?>
<style>
    /* Order Detail Page – scoped styles */

    .order-detail-page .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    .order-detail-page .info-section {
        background: #f9f9f9;
        padding: 16px;
        border-radius: 8px;
        border: 1px solid #eee;
    }
    .order-detail-page .section-title {
        margin: 0 0 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #ddd;
        color: #333;
        font-size: 1rem;
    }
    .order-detail-page .info-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 0.92rem;
    }
    .order-detail-page .info-label { font-weight: 600; color: #555; }
    .order-detail-page .info-value  { text-align: right; }
    .order-detail-page .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    .order-detail-page .status-pending  { background: rgba(255,193,7,.12); color: #b08000; }
    .order-detail-page .status-paid     { background: rgba(40,167,69,.12);  color: #196932; }
    .order-detail-page .status-failed   { background: rgba(220,53,69,.12);  color: #9a1c29; }
    .order-detail-page .product-img {
        width: 60px; height: 60px;
        object-fit: cover; border-radius: 4px;
        border: 1px solid #e0e0e0;
    }
    .order-detail-page .product-img[title*="Variant-specific"] {
        border: 2px solid #e67e22;
        box-shadow: 0 0 0 2px rgba(230,126,34,.1);
    }
    .order-detail-page .variant-name { font-weight: 500; color: #e67e22; font-size: .92rem; }
    .order-detail-page .variant-id   { font-size: .75rem; color: #6c757d; }
    .order-detail-page .variant-badge {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: .75rem; padding: 2px 8px;
        background: #d4edda; color: #155724;
        border-radius: 12px; font-weight: 500;
    }
    .order-detail-page .summary-table { width: 100%; max-width: 400px; margin-left: auto; }
    .order-detail-page .summary-table td { padding: 8px 12px; }
    .order-detail-page .summary-table tr:last-child td {
        border-top: 2px solid #e0e0e0; font-weight: 700; font-size: 1.05rem;
    }
    .order-detail-page .notes-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e0e0; }
    .order-detail-page .notes-label  { font-weight: 500; color: #6c757d; margin-bottom: 4px; display: block; }
    .order-detail-page .notes-content {
        background: #f8f9fa; padding: 12px; border-radius: 5px; white-space: pre-line;
    }
    .order-detail-page .json-container {
        background: #2d3748; color: #e2e8f0;
        padding: 14px; border-radius: 8px;
        font-family: 'Courier New', monospace; font-size: .9rem;
        overflow-x: auto; margin-top: 10px;
    }
    .order-detail-page .json-toggle {
        background: #4a5568; border: none; color: #e2e8f0;
        padding: 5px 10px; border-radius: 4px;
        cursor: pointer; font-size: .8rem; margin-bottom: 10px;
    }
    .order-detail-page .json-toggle:hover { background: #2d3748; }
    .order-detail-page .empty-state { color: #6c757d; text-align: center; padding: 28px; font-style: italic; }
    .order-detail-page .btn-print    { background: linear-gradient(135deg, #6c757d, #495057); }
    .order-detail-page .btn-edit     { background: linear-gradient(135deg, #28a745, #20c997); }
    .order-detail-page .btn-generate { background: linear-gradient(135deg, #17a2b8, #20c997); }
    @media (max-width: 768px) {
        .order-detail-page .info-grid { grid-template-columns: 1fr; }
        .order-detail-page .summary-table { max-width: 100%; }
    }
</style>
<div class="container-fluid py-4">
<div class="order-detail-page">

    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-file-invoice me-2"></i>Order #<?= htmlspecialchars($order['id']) ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                    <li class="breadcrumb-item active">Order #<?= htmlspecialchars($order['id']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="edit_invoice.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-success btn-sm btn-edit">
                <i class="fas fa-edit me-1"></i> Edit Invoice
            </a>
            <a href="generate_invoice.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-info btn-sm btn-generate" target="_blank">
                <i class="fas fa-file-pdf me-1"></i> View Invoice
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm btn-print" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print
            </button>
            <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Order Information</h5>
            <span class="status-badge status-<?= strtolower($order['payment_status']) ?>">
                    <?= ucfirst($order['payment_status']) ?>
                </span>
            </div>
            
            <div class="card-body">
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
                        <div class="notes-content" style="background: white; border: 1px solid #e0e0e0; padding: 20px;">
                            <button class="json-toggle" onclick="toggleJsonView(this)" style="float: right;">
                                <i class="fas fa-eye"></i> Toggle Raw/Formatted View
                            </button>
                            
                            <div class="json-formatted" style="display: block; clear: both; padding-top: 10px;">
                                <div class="payment-info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                                    <?php if (isset($jsonData['success'])): ?>
                                        <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid <?= $jsonData['success'] ? '#28a745' : '#dc3545' ?>;">
                                            <div style="color: #6c757d; font-size: 0.85em; font-weight: 500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Payment Status</div>
                                            <span class="badge <?= $jsonData['success'] ? 'bg-success' : 'bg-danger' ?>" style="font-size: 0.9em; padding: 6px 12px;">
                                                <?= $jsonData['success'] ? 'Success' : 'Failed' ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($jsonData['status'])): ?>
                                        <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #17a2b8;">
                                            <div style="color: #6c757d; font-size: 0.85em; font-weight: 500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Status</div>
                                            <span class="badge bg-info" style="font-size: 0.9em; padding: 6px 12px;">
                                                <?= ucwords(str_replace('_', ' ', htmlspecialchars($jsonData['status']))) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (isset($jsonData['payment_url'])): ?>
                                    <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px; border-left: 4px solid #e67e22;">
                                        <div style="color: #6c757d; font-size: 0.85em; font-weight: 500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Payment URL</div>
                                        <a href="<?= htmlspecialchars($jsonData['payment_url']) ?>" 
                                           target="_blank" 
                                           style="color: var(--primary-color); text-decoration: none; word-break: break-all; font-size: 0.9em;">
                                            <i class="fas fa-external-link-alt" style="margin-right: 6px;"></i><?= htmlspecialchars($jsonData['payment_url']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($jsonData['payment_id'])): ?>
                                    <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px; border-left: 4px solid #6c757d;">
                                        <div style="color: #6c757d; font-size: 0.85em; font-weight: 500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Payment ID</div>
                                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                            <code style="background: #e9ecef; padding: 8px 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 0.85em; word-break: break-all; flex: 1; min-width: 200px;">
                                                <?= htmlspecialchars($jsonData['payment_id']) ?>
                                            </code>
                                            <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($jsonData['payment_id']) ?>')" 
                                                    style="background: var(--primary-color); color: white; border: none; border-radius: 4px; padding: 8px 16px; font-size: 0.85em; cursor: pointer; transition: background 0.2s;" 
                                                    onmouseover="this.style.background='#d35400'" 
                                                    onmouseout="this.style.background=''"
                                                    title="Copy to clipboard">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
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
        </div><!-- /.card-body -->
    </div><!-- /.card order-information -->

    <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Order Items</h5>
                <span class="badge bg-secondary"><?= count($items) ?> item<?= count($items) != 1 ? 's' : '' ?></span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
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
                                        <?php $hasCatalogProduct = !empty($item['product_exists_id']) && !empty($item['product_id']) && intval($item['product_id']) > 0; ?>
                                        <?php if (!empty($item['image'])): ?>
                                            <?php if ($hasCatalogProduct): ?>
                                                <a href="../product.php?id=<?= htmlspecialchars($item['product_id']) ?>" target="_blank">
                                            <?php endif; ?>
                                            <img src="../<?= htmlspecialchars($item['image']) ?>" alt="" class="product-img" title="<?= !empty($item['variation_id']) ? 'Variant-specific image' : 'Main product image' ?>">
                                            <?php if ($hasCatalogProduct): ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                                <i class="fas fa-image" style="color: #6c757d;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 500;">
                                                <?php if ($hasCatalogProduct): ?>
                                                    <a href="../product.php?id=<?= htmlspecialchars($item['product_id']) ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                                                        <?= htmlspecialchars($item['product_name'] ?? 'Unknown Product') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($item['product_name'] ?? 'Unknown Product') ?>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #6c757d;">
                                                Product ID: <?= htmlspecialchars($item['product_id'] ?? '—') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($item['variation_id'])): ?>
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <?php if (!empty($item['color']) || !empty($item['size'])): ?>
                                                <div style="font-weight: 500; color: #E67B2E;">
                                                    <i class="fas fa-tag" style="font-size: 0.85rem;"></i>
                                                    <?php 
                                                    $variant_parts = [];
                                                    if (!empty($item['color'])) $variant_parts[] = htmlspecialchars($item['color']);
                                                    if (!empty($item['size'])) $variant_parts[] = 'Size: ' . htmlspecialchars($item['size']);
                                                    echo implode(' / ', $variant_parts);
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="font-size: 0.75rem; color: #6c757d;">
                                                Variant ID: <?= htmlspecialchars($item['variation_id']) ?>
                                            </div>
                                            <?php if (!empty($item['image_id'])): ?>
                                                <div style="font-size: 0.75rem; color: #28a745;">
                                                    <i class="fas fa-check-circle"></i> Specific image selected
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">No variant</span>
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
                <?php 
                $aed_to_usd = 0.27;
                $subtotal = ($order['total_amount'] ?? 0) - ($order['shipping_aed'] ?? 0) + ($order['discount_amount'] ?? 0);
                $subtotal_usd = $subtotal * $aed_to_usd;
                $shipping_usd = ($order['shipping_aed'] ?? 0) * $aed_to_usd;
                $total_usd = ($order['total_amount'] ?? 0) * $aed_to_usd;
                ?>
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">AED <?= number_format($subtotal, 2) ?> ($<?= number_format($subtotal_usd, 2) ?>)</td>
                </tr>
                <?php if (!empty($order['discount_amount'])): ?>
                <?php $discount_usd = $order['discount_amount'] * $aed_to_usd; ?>
                <tr>
                    <td>Discount:</td>
                    <td style="text-align: right;">-AED <?= number_format($order['discount_amount'], 2) ?> (-$<?= number_format($discount_usd, 2) ?>)</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Shipping:</td>
                    <td style="text-align: right;">AED <?= number_format($order['shipping_aed'] ?? 0, 2) ?> ($<?= number_format($shipping_usd, 2) ?>)</td>
                </tr>
                <tr>
                    <td>Total:</td>
                    <td style="text-align: right;">AED <?= number_format($order['total_amount'] ?? 0, 2) ?> ($<?= number_format($total_usd, 2) ?>)</td>
                </tr>
            </table>
        </div><!-- /.card-body -->
    </div><!-- /.card -->

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
</div><!-- /.order-detail-page -->
</div><!-- /.container-fluid -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

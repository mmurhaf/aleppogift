<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();

// Get total orders
$totalOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Get total products
$totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Get total customers
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();

/**
 * Get recent orders with customer information
 */
function getRecentOrders($limit = 20) {
    global $db;
    $stmt = $db->getPdo()->prepare("
        SELECT o.*, c.fullname, c.email 
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        ORDER BY o.order_date DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Count number of items in an order
 */
function countOrderItems($order_id) {
    global $db;
    $stmt = $db->getPdo()->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = :id");
    $stmt->execute(['id' => $order_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['count'] : 0;
}

?>
<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    /* Additional dashboard specific styles */
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .card-title {
        font-size: 0.9rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .user-info {
        display: flex;
        align-items: center;
    }
    
    .user-info img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
    }
    
    /* Recent orders table styles */
    .recent-orders-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .recent-orders-table th {
        background-color: var(--primary-color);
        color: white;
        padding: 12px 15px;
        text-align: left;
        font-size: 0.8rem;
        text-transform: uppercase;
        border-bottom: 2px solid #e0e0e0;
    }
    
    .recent-orders-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #e0e0e0;
        vertical-align: middle;
    }
    
    .recent-orders-table tr:last-child td {
        border-bottom: none;
    }
    
    .recent-orders-table tr:hover {
        background-color: rgba(255, 127, 0, 0.1);
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-pending {
        background-color: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }
    
    .status-paid {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    
    .status-failed {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .action-btn {
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .action-btn.view {
        background-color: rgba(255, 127, 0, 0.1);
        color: var(--primary-color);
    }
    
    .action-btn.view:hover {
        background-color: rgba(255, 127, 0, 0.2);
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
    
    .text-muted {
        color: #6c757d !important;
        font-size: 0.85em;
    }
    
    .customer-link {
        text-decoration: none;
        color: var(--primary-color);
        transition: color 0.3s;
    }
    
    .customer-link:hover {
        color: var(--secondary-color);
        text-decoration: underline;
    }
</style>

<div class="container-fluid py-4">
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Orders</div>
                        <div class="card-value"><?php echo $totalOrders; ?></div>
                    </div>
                    <div class="card-icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Products</div>
                        <div class="card-value"><?php echo $totalProducts; ?></div>
                    </div>
                    <div class="card-icon products">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Customers</div>
                        <div class="card-value"><?php echo $totalCustomers; ?></div>
                    </div>
                    <div class="card-icon customers">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            
            <div class="card" style="cursor: pointer; transition: transform 0.3s;" onclick="window.open('http://localhost:8501', '_blank')" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <div class="card-header">
                    <div>
                        <div class="card-title">Social Media Bot</div>
                        <div class="card-value" style="font-size: 1.2rem; color: #667eea;">Launch</div>
                    </div>
                    <div class="card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-robot"></i>
                    </div>
                </div>
            </div>
        </div>


        <!-- Additional content can be added here -->
        <div class="card">
            <h3>Recent Activity</h3>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                    <a href="orders.php" class="btn btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="recent-orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get recent orders (last 20)
                                $recentOrders = getRecentOrders(20);
                                
                                foreach ($recentOrders as $order): 
                                    $itemsCount = countOrderItems($order['id']);
                                    
                                    // Determine customer display name
                                    $customerDisplay = 'Guest';
                                    $customerEmail = '';
                                    
                                    if (!empty($order['fullname'])) {
                                        $customerDisplay = htmlspecialchars($order['fullname']);
                                        $customerEmail = htmlspecialchars($order['email'] ?? '');
                                    } elseif (!empty($order['email'])) {
                                        $customerDisplay = htmlspecialchars($order['email']);
                                        $customerEmail = '';
                                    }
                                ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td>
                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="customer-link">
                                            <?php echo $customerDisplay; ?>
                                            <?php if (!empty($customerEmail)): ?>
                                                <br><small class="text-muted"><?php echo $customerEmail; ?></small>
                                            <?php endif; ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo $itemsCount; ?> item<?php echo $itemsCount != 1 ? 's' : ''; ?></td>
                                    <td>AED <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($order['payment_status']); ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="action-btn view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_invoice.php?id=<?php echo $order['id']; ?>" class="action-btn" style="background: #e67b2e;" title="Edit Invoice">
                                            <i class="fas fa-file-invoice"></i> Edit Invoice
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Social Media Bot Instructions -->
        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 30px;">
            <div style="padding: 20px;">
                <h3 style="color: white; margin-bottom: 15px;">
                    <i class="fas fa-robot"></i> Social Media Bot - Quick Start
                </h3>
                <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <p style="margin-bottom: 10px; font-weight: 500;">
                        <i class="fas fa-info-circle"></i> To start the Social Media Automation System:
                    </p>
                    <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; margin-top: 10px;">
                        <div style="margin-bottom: 8px;">
                            <i class="fas fa-terminal" style="margin-right: 8px;"></i>
                            <code style="color: #ffd700;">cd C:\xampp\htdocs\aleppogift\social_bot_local</code>
                        </div>
                        <div>
                            <i class="fas fa-play-circle" style="margin-right: 8px;"></i>
                            <code style="color: #7fff00;">streamlit run app.py</code>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <span style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; font-size: 0.9rem;">
                        <i class="fas fa-video"></i> Auto-Generate Videos
                    </span>
                    <span style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; font-size: 0.9rem;">
                        <i class="fab fa-tiktok"></i> TikTok
                    </span>
                    <span style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; font-size: 0.9rem;">
                        <i class="fab fa-instagram"></i> Instagram
                    </span>
                    <span style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; font-size: 0.9rem;">
                        <i class="fab fa-facebook"></i> Facebook
                    </span>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AleppoGift</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/admin-theme.css">
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
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-gifts"></i> <span>AleppoGift</span></h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="products.php"><i class="fas fa-box-open"></i> <span>Products</span></a></li>
            <li><a href="categories.php"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
            <li><a href="brands.php"><i class="fas fa-copyright"></i> <span>Brands</span></a></li>
            <li><a href="coupons.php"><i class="fas fa-ticket-alt"></i> <span>Coupons</span></a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
            <li><a href="regenerate_invoice.php"><i class="fas fa-file-invoice-dollar"></i> <span>Regenerate Invoice</span></a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> <span>Customers</span></a></li>
            <li><a href="../testing/" target="_blank"><i class="fas fa-flask"></i> <span>Testing Dashboard</span></a></li>
        </ul>
        <button class="logout-btn" onclick="window.location.href='logout.php'">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </button>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Dashboard Overview</h1>
            <div class="user-info">
                <img src="https://via.placeholder.com/40" alt="Admin">
                <span>Admin</span>
            </div>
        </div>

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
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();
require_once('../../config/config.php');
require_once('../../includes/Database.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();

// Get total orders
$totalOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Get total products
$totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Get total customers
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();

/**
 * Get recent orders
 */
function getRecentOrders($limit = 5) {
    global $db;
    $stmt = $db->getPdo()->prepare("SELECT * FROM orders ORDER BY order_date DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Get customer name by ID
 */
function getCustomerById($customer_id) {
    global $db;
    $stmt = $db->getPdo()->prepare("SELECT fullname FROM customers WHERE id = :id");
    $stmt->execute(['id' => $customer_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
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
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #4fc3f7;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --sidebar-width: 250px;
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
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
        }
        
        .sidebar-header h2 i {
            margin-right: 10px;
            color: var(--accent-color);
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--accent-color);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .card-icon.orders {
            background-color: var(--primary-color);
        }
        
        .card-icon.products {
            background-color: var(--success-color);
        }
        
        .card-icon.customers {
            background-color: var(--warning-color);
        }
        
        .card-title {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 20px;
            width: calc(100% - 40px);
            margin-left: 20px;
        }
        
        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .logout-btn i {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-header h2 span, .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px 0;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
    <style>
    /* Add these styles to your existing CSS */
    .recent-orders-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .recent-orders-table th {
        background-color: #f8f9fa;
        padding: 12px 15px;
        text-align: left;
        font-size: 0.8rem;
        text-transform: uppercase;
        color: #6c757d;
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
        background-color: #f8f9fa;
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
        background-color: rgba(74, 111, 165, 0.1);
        color: var(--primary-color);
    }
    
    .action-btn.view:hover {
        background-color: rgba(74, 111, 165, 0.2);
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
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
                                // Get recent orders (last 5)
                                $recentOrders = getRecentOrders(5);
                                
                                foreach ($recentOrders as $order): 
                                    $customer = getCustomerById($order['customer_id']);
                                    $itemsCount = countOrderItems($order['id']);
                                ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($customer['name'] ?? 'Guest'); ?></td>
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
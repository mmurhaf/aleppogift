<?php
/**
 * Admin Reports & Analytics
 * Dubai Gift - Master Copy Brands E-commerce
 */

$pageTitle = "Reports & Analytics";
include 'header.php';

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
$reportType = $_GET['report_type'] ?? 'overview';

// Validate dates
if (strtotime($startDate) === false) $startDate = date('Y-m-01');
if (strtotime($endDate) === false) $endDate = date('Y-m-d');
if (strtotime($startDate) > strtotime($endDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// Get overview statistics
function getOverviewStats($db, $startDate, $endDate) {
    $stats = [];
    
    // Total Orders
    $stats['total_orders'] = $db->fetch("SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) BETWEEN ? AND ?", [$startDate, $endDate])['count'];
    
    // Total Revenue
    $stats['total_revenue'] = $db->fetch("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'paid' AND DATE(order_date) BETWEEN ? AND ?", [$startDate, $endDate])['total'];
    
    // Average Order Value
    $stats['avg_order_value'] = $stats['total_orders'] > 0 ? $stats['total_revenue'] / $stats['total_orders'] : 0;
    
    // New Customers
    $stats['new_customers'] = $db->fetch("SELECT COUNT(*) as count FROM customers WHERE DATE(created_at) BETWEEN ? AND ?", [$startDate, $endDate])['count'];
    
    // Products Sold
    $stats['products_sold'] = $db->fetch("SELECT COALESCE(SUM(oi.quantity), 0) as total FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE DATE(o.order_date) BETWEEN ? AND ?", [$startDate, $endDate])['total'];
    
    return $stats;
}

// Get daily sales data for chart
function getDailySales($db, $startDate, $endDate) {
    return $db->fetchAll("
        SELECT 
            DATE(order_date) as date,
            COUNT(*) as orders,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM orders 
        WHERE DATE(order_date) BETWEEN ? AND ?
        GROUP BY DATE(order_date)
        ORDER BY date ASC
    ", [$startDate, $endDate]);
}

// Get top products
function getTopProducts($db, $startDate, $endDate, $limit = 10) {
    return $db->fetchAll("
        SELECT 
            p.name_en,
            p.sku,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        INNER JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY oi.product_id
        ORDER BY total_quantity DESC
        LIMIT ?
    ", [$startDate, $endDate, $limit]);
}

// Get top customers
function getTopCustomers($db, $startDate, $endDate, $limit = 10) {
    return $db->fetchAll("
        SELECT 
            c.fullname,
            c.email,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM customers c
        INNER JOIN orders o ON c.id = o.customer_id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY total_spent DESC
        LIMIT ?
    ", [$startDate, $endDate, $limit]);
}

// Get category performance
function getCategoryPerformance($db, $startDate, $endDate) {
    return $db->fetchAll("
        SELECT 
            cat.name_en,
            COUNT(DISTINCT oi.order_id) as orders,
            SUM(oi.quantity) as products_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        INNER JOIN products p ON oi.product_id = p.id
        INNER JOIN categories cat ON p.category_id = cat.id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY cat.id
        ORDER BY revenue DESC
    ", [$startDate, $endDate]);
}

// Get brand performance
function getBrandPerformance($db, $startDate, $endDate) {
    return $db->fetchAll("
        SELECT 
            b.name_en,
            COUNT(DISTINCT oi.order_id) as orders,
            SUM(oi.quantity) as products_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        INNER JOIN products p ON oi.product_id = p.id
        INNER JOIN brands b ON p.brand_id = b.id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY b.id
        ORDER BY revenue DESC
    ", [$startDate, $endDate]);
}

// Get order status distribution
function getOrderStatusDistribution($db, $startDate, $endDate) {
    return $db->fetchAll("
        SELECT 
            order_status,
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM orders 
        WHERE DATE(order_date) BETWEEN ? AND ?
        GROUP BY order_status
        ORDER BY count DESC
    ", [$startDate, $endDate]);
}

// Generate report data based on type
switch ($reportType) {
    case 'overview':
        $stats = getOverviewStats($db, $startDate, $endDate);
        $dailySales = getDailySales($db, $startDate, $endDate);
        $topProducts = getTopProducts($db, $startDate, $endDate, 5);
        $topCustomers = getTopCustomers($db, $startDate, $endDate, 5);
        break;
        
    case 'products':
        $topProducts = getTopProducts($db, $startDate, $endDate, 20);
        $categoryPerformance = getCategoryPerformance($db, $startDate, $endDate);
        $brandPerformance = getBrandPerformance($db, $startDate, $endDate);
        break;
        
    case 'customers':
        $topCustomers = getTopCustomers($db, $startDate, $endDate, 20);
        $customerStats = [
            'new_customers' => $db->fetch("SELECT COUNT(*) as count FROM customers WHERE DATE(created_at) BETWEEN ? AND ?", [$startDate, $endDate])['count'],
            'returning_customers' => $db->fetch("SELECT COUNT(DISTINCT customer_id) as count FROM orders WHERE customer_id IN (SELECT customer_id FROM orders GROUP BY customer_id HAVING COUNT(*) > 1) AND DATE(order_date) BETWEEN ? AND ?", [$startDate, $endDate])['count']
        ];
        break;
        
    case 'orders':
        $orderStatusDistribution = getOrderStatusDistribution($db, $startDate, $endDate);
        $dailySales = getDailySales($db, $startDate, $endDate);
        break;
}
?>

<!-- Date Range and Report Type Selection -->
<div class="card mb-4">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0">Report Filters</h5>
            </div>
            <div class="col-auto">
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $startDate; ?>">
                    <span>to</span>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $endDate; ?>">
                    
                    <select name="report_type" class="form-select form-select-sm">
                        <option value="overview" <?php echo $reportType == 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="products" <?php echo $reportType == 'products' ? 'selected' : ''; ?>>Products</option>
                        <option value="customers" <?php echo $reportType == 'customers' ? 'selected' : ''; ?>>Customers</option>
                        <option value="orders" <?php echo $reportType == 'orders' ? 'selected' : ''; ?>>Orders</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary btn-sm">Generate</button>
                </form>
            </div>
        </div>
        <small class="text-muted">Report Period: <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></small>
    </div>
</div>

<?php if ($reportType == 'overview'): ?>
    <!-- Overview Report -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($stats['total_orders']); ?></h4>
                            <small>Total Orders</small>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>AED <?php echo number_format($stats['total_revenue'], 2); ?></h4>
                            <small>Total Revenue</small>
                        </div>
                        <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>AED <?php echo number_format($stats['avg_order_value'], 2); ?></h4>
                            <small>Avg Order Value</small>
                        </div>
                        <i class="fas fa-chart-line fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($stats['new_customers']); ?></h4>
                            <small>New Customers</small>
                        </div>
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Daily Sales Trend</h5>
        </div>
        <div class="card-body">
            <canvas id="salesChart" height="100"></canvas>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <!-- Top Products -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Products</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topProducts)): ?>
                        <p class="text-muted">No product sales in this period.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $product): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['name_en']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                            </td>
                                            <td><?php echo number_format($product['total_quantity']); ?></td>
                                            <td>AED <?php echo number_format($product['total_revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <!-- Top Customers -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Customers</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topCustomers)): ?>
                        <p class="text-muted">No customer orders in this period.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCustomers as $customer): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($customer['fullname']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                            </td>
                                            <td><?php echo number_format($customer['total_orders']); ?></td>
                                            <td>AED <?php echo number_format($customer['total_spent'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($reportType == 'products'): ?>
    <!-- Products Report -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Products by Sales</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topProducts)): ?>
                        <p class="text-muted">No product sales in this period.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" id="topProductsTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $product): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['name_en']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                            </td>
                                            <td><?php echo number_format($product['total_quantity']); ?></td>
                                            <td>AED <?php echo number_format($product['total_revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Category Performance</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($categoryPerformance)): ?>
                        <p class="text-muted">No category sales in this period.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Orders</th>
                                        <th>Products Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categoryPerformance as $category): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['name_en']); ?></td>
                                            <td><?php echo number_format($category['orders']); ?></td>
                                            <td><?php echo number_format($category['products_sold']); ?></td>
                                            <td>AED <?php echo number_format($category['revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($reportType == 'customers'): ?>
    <!-- Customers Report -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h4><?php echo number_format($customerStats['new_customers']); ?></h4>
                    <small>New Customers</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h4><?php echo number_format($customerStats['returning_customers']); ?></h4>
                    <small>Returning Customers</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Top Customers by Spending</h5>
        </div>
        <div class="card-body">
            <?php if (empty($topCustomers)): ?>
                <p class="text-muted">No customer orders in this period.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" id="topCustomersTable">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Total Orders</th>
                                <th>Total Spent</th>
                                <th>Avg Order Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topCustomers as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo number_format($customer['total_orders']); ?></td>
                                    <td>AED <?php echo number_format($customer['total_spent'], 2); ?></td>
                                    <td>AED <?php echo number_format($customer['total_spent'] / $customer['total_orders'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($reportType == 'orders'): ?>
    <!-- Orders Report -->
    <div class="row mb-4">
        <?php foreach ($orderStatusDistribution as $status): ?>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h4><?php echo number_format($status['count']); ?></h4>
                        <p class="mb-0"><?php echo ucfirst($status['order_status']); ?> Orders</p>
                        <small class="text-muted">AED <?php echo number_format($status['revenue'], 2); ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Daily Orders Chart -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daily Orders & Revenue</h5>
        </div>
        <div class="card-body">
            <canvas id="ordersChart" height="100"></canvas>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTables
    $('.table').DataTable({
        "pageLength": 25,
        "order": [[2, "desc"]]
    });
    
    <?php if ($reportType == 'overview' && !empty($dailySales)): ?>
    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($dailySales, 'date')); ?>,
            datasets: [{
                label: 'Revenue (AED)',
                data: <?php echo json_encode(array_column($dailySales, 'revenue')); ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'Orders',
                data: <?php echo json_encode(array_column($dailySales, 'orders')); ?>,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if ($reportType == 'orders' && !empty($dailySales)): ?>
    // Orders Chart
    const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    new Chart(ordersCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($dailySales, 'date')); ?>,
            datasets: [{
                label: 'Orders',
                data: <?php echo json_encode(array_column($dailySales, 'orders')); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>
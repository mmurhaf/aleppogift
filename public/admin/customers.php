<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();

$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$orderBy = 'c.id DESC';
if ($sort === 'oldest') {
    $orderBy = 'c.id ASC';
} elseif ($sort === 'name') {
    $orderBy = 'c.fullname ASC';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$params = [];
$where = '';
if ($search !== '') {
    $where = " WHERE c.fullname LIKE :q_name OR c.email LIKE :q_email OR c.phone LIKE :q_phone OR c.city LIKE :q_city OR c.country LIKE :q_country";
    $search_like = '%' . $search . '%';
    $params['q_name'] = $search_like;
    $params['q_email'] = $search_like;
    $params['q_phone'] = $search_like;
    $params['q_city'] = $search_like;
    $params['q_country'] = $search_like;
}

$totalCustomers = (int)$db->query("SELECT COUNT(*) FROM customers c" . $where, $params)->fetchColumn();
$totalPages = max(1, (int)ceil($totalCustomers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$customers = $db->query(
    "SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count
     FROM customers c" . $where . " ORDER BY $orderBy LIMIT $perPage OFFSET $offset",
    $params
)->fetchAll(PDO::FETCH_ASSOC);

$pagerQuery = http_build_query(array_filter([
    'q' => $search,
    'sort' => $sort !== 'newest' ? $sort : null,
]));
?>
<?php
$page_title = 'Customers';
require_once __DIR__ . '/includes/header.php';
?>
<style>
        
        body {
            background-color: var(--light-bg);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .dashboard-header {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.35rem;
        }
        .customer-table {
            background-color: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        .customer-table th {
            background-color: #f8f9fc;
            border-bottom-width: 1px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: #5a5c69;
        }
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-weight: bold;
        }
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 0.25rem;
        }
        .badge-active {
            background-color: #1cc88a;
            color: white;
        }
        .badge-inactive {
            background-color: #e74a3b;
            color: white;
        }
        .search-container {
            max-width: 300px;
        }
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
    </style>
    <div class="container-fluid py-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Customer Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Customers</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Customer Filters -->
        <form class="row mb-4 g-2" method="get" action="customers.php">
            <div class="col-md-6">
                <div class="input-group search-container">
                    <input type="text" name="q" class="form-control" placeholder="Search name, email, phone, or city" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <select name="sort" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest first</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest first</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A–Z</option>
                </select>
            </div>
        </form>

        <!-- Customers Table -->
        <div class="card shadow mb-4 customer-table">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="60"></th>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <div class="customer-avatar">
                                        <?php echo strtoupper(substr($customer['fullname'], 0, 1)); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($customer['fullname']); ?></strong>
                                    <div class="text-muted small">ID: <?php echo $customer['id']; ?></div>
                                </td>
                                <td>
                                    <div><a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>"><?php echo htmlspecialchars($customer['email']); ?></a></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($customer['phone']); ?></div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($customer['address']); ?>">
                                        <?php echo htmlspecialchars($customer['address']); ?>
                                    </div>
                                    <div class="small">
                                        <?php echo htmlspecialchars($customer['city']); ?>, <?php echo htmlspecialchars($customer['country']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php /* Order count would come from database */ ?>
                                        <?php echo isset($customer['order_count']) ? $customer['order_count'] : '0'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge badge-active">
                                        Active
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($customer['created_at'])): ?>
                                        <?php echo date('M j, Y', strtotime($customer['created_at'])); ?>
                                        <div class="text-muted small"><?php echo date('h:i A', strtotime($customer['created_at'])); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_customer.php?id=<?php echo $customer['id']; ?>" 
                                       class="btn btn-sm btn-primary action-btn" 
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_customer.php?id=<?php echo $customer['id']; ?>" 
                                       class="btn btn-sm btn-secondary action-btn" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalCustomers === 0): ?>
            <p class="text-muted">No customers match this search.</p>
        <?php elseif ($totalPages > 1): ?>
        <nav aria-label="Customer pages" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $pagerQuery !== '' ? '&' . $pagerQuery : ''; ?>">Previous</a>
                </li>
                <li class="page-item disabled"><span class="page-link"><?php echo $page; ?> / <?php echo $totalPages; ?></span></li>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $pagerQuery !== '' ? '&' . $pagerQuery : ''; ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>

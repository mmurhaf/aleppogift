<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

// التأكد من وجود معرّف الزبون
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid customer ID.");
}

$customer_id = (int) $_GET['id'];

$db = new Database();

// جلب بيانات الزبون
$sql = "SELECT * FROM customers WHERE id = :id";
$stmt = $db->query($sql, ['id' => $customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$customer) {
    die("Customer not found.");
}

// جلب الطلبات المرتبطة بالزبون
$sql_orders = "SELECT * FROM orders WHERE customer_id = :id ORDER BY order_date DESC";
$stmt_orders = $db->query($sql_orders, ['id' => $customer_id]);
$orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e6f0ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border: #dee2e6;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', Arial, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: #f5f7fb;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: white;
            color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #d1146a;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        
        .card-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .customer-profile {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 3rem;
            font-weight: 300;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 3px;
        }
        
        .info-value {
            font-weight: 500;
            color: var(--dark);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: rgba(67, 97, 238, 0.05);
        }
        
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-link:hover {
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border);
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .customer-profile {
                grid-template-columns: 1fr;
            }
            
            .avatar {
                margin: 0 auto;
            }
            
            th, td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Customer Details</h1>
            <div class="header-actions">
                <button class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="customer-profile">
                <div class="avatar">
                    <?= strtoupper(substr($customer['fullname'], 0, 1)) ?>
                </div>
                <div class="customer-info">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?= htmlspecialchars($customer['fullname']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($customer['email']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($customer['phone']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?= date('M j, Y', strtotime($customer['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="customer-info" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                <div class="info-item">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?= htmlspecialchars($customer['address']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">City</span>
                    <span class="info-value"><?= htmlspecialchars($customer['city']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Country</span>
                    <span class="info-value"><?= htmlspecialchars($customer['country']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-shopping-bag"></i> Order History</h2>
                <span class="info-value">Total Orders: <?= count($orders) ?></span>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Orders Found</h3>
                    <p>This customer hasn't placed any orders yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= $order['id'] ?></td>
                            <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                            <td>AED <?= number_format($order['total_amount'], 2) ?></td>
                            <td>
                                <span class="status status-<?= strtolower($order['payment_status']) ?>">
                                    <?= ucfirst($order['payment_status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($order['payment_method']) ?></td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <?php if (!empty($order['invoice_pdf'])): ?>
                                        <a href="/invoices/<?= htmlspecialchars($order['invoice_pdf']) ?>" 
                                           class="action-link" 
                                           target="_blank">
                                            <i class="fas fa-file-invoice"></i> Invoice
                                        </a>
                                    <?php endif; ?>
                                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="action-link">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

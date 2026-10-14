<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: customers.php');
    exit;
}

$customer_id = (int)$_GET['id'];
$db = new Database();
$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$customer = $db->query("SELECT * FROM customers WHERE id = :id", ['id' => $customer_id])->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    header('Location: customers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'The form expired. Save the customer again.';
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');

        if ($fullname === '' || $email === '') {
            $error = 'Name and email are required.';
        } else {
            $db->query(
                "UPDATE customers
                 SET fullname = :fullname, email = :email, phone = :phone,
                     address = :address, city = :city, country = :country
                 WHERE id = :id",
                [
                    'fullname' => $fullname,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'country' => $country,
                    'id' => $customer_id,
                ]
            );
            header('Location: view_customer.php?id=' . $customer_id);
            exit;
        }

        $customer['fullname'] = $fullname;
        $customer['email'] = $email;
        $customer['phone'] = $phone;
        $customer['address'] = $address;
        $customer['city'] = $city;
        $customer['country'] = $country;
    }
}

$page_title = 'Edit Customer';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Edit customer</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="customers.php">Customers</a></li>
                    <li class="breadcrumb-item"><a href="view_customer.php?id=<?php echo $customer_id; ?>">#<?php echo $customer_id; ?></a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="view_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary">Back</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="fullname">Full name</label>
                    <input class="form-control" id="fullname" name="fullname" required value="<?php echo htmlspecialchars((string)($customer['fullname'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" required value="<?php echo htmlspecialchars((string)($customer['email'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars((string)($customer['phone'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="city">City</label>
                    <input class="form-control" id="city" name="city" value="<?php echo htmlspecialchars((string)($customer['city'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="country">Country</label>
                    <input class="form-control" id="country" name="country" value="<?php echo htmlspecialchars((string)($customer['country'] ?? '')); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars((string)($customer['address'] ?? '')); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Save customer</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

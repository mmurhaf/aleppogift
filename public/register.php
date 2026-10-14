<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = new Database();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    $existing = $db->fetch(
        "SELECT id FROM customers WHERE email = ? AND password_hash IS NOT NULL AND password_hash != '' LIMIT 1",
        [$email]
    );
    if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('account.register_invalid');
    } elseif (strlen($password) < 8 || $password !== $confirm) {
        $error = t('account.password_rules');
    } elseif ($existing) {
        $error = t('account.email_exists');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $row = $db->fetch('SELECT id FROM customers WHERE email = ? ORDER BY id DESC LIMIT 1', [$email]);
        if ($row) {
            $db->query('UPDATE customers SET fullname = ?, phone = ?, password_hash = ?, is_active = 1 WHERE id = ?', [$name, $phone, $hash, $row['id']]);
            $id = (int)$row['id'];
        } else {
            $db->query('INSERT INTO customers (fullname, email, phone, password_hash, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())', [$name, $email, $phone, $hash]);
            $id = (int)$db->lastInsertId();
        }
        $customer = $db->fetch('SELECT * FROM customers WHERE id = ?', [$id]);
        customer_login_row($customer);
        adopt_guest_wishlist($db, $id);
        header('Location: ' . store_url('account.php'));
        exit;
    }
}
storefront_page_open(t('register'));
?>
<div class="row justify-content-center"><div class="col-md-6">
    <h1><?= htmlspecialchars(t('register')) ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <label class="form-label"><?= te('account.name') ?></label>
        <input class="form-control mb-3" name="name" required>
        <label class="form-label"><?= te('account.email') ?></label>
        <input class="form-control mb-3" type="email" name="email" required>
        <label class="form-label"><?= te('account.phone') ?></label>
        <input class="form-control mb-3" name="phone">
        <label class="form-label"><?= te('account.password') ?></label>
        <input class="form-control mb-3" type="password" name="password" minlength="8" required>
        <label class="form-label"><?= te('account.confirm_password') ?></label>
        <input class="form-control mb-3" type="password" name="confirm" minlength="8" required>
        <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('register')) ?></button>
    </form>
</div></div>
<?php storefront_page_close();
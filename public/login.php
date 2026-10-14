<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = new Database();
$error = '';
if (current_customer_id() > 0) {
    header('Location: ' . store_url('account.php'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $customer = $db->fetch(
        "SELECT * FROM customers WHERE email = ? AND password_hash IS NOT NULL AND password_hash != '' ORDER BY id DESC LIMIT 1",
        [$email]
    );
    if (!$customer || !password_verify($password, $customer['password_hash']) || (int)($customer['is_active'] ?? 1) !== 1) {
        $error = t('account.login_failed');
    } else {
        customer_login_row($customer);
        adopt_guest_wishlist($db, (int)$customer['id']);
        header('Location: ' . store_url('account.php'));
        exit;
    }
}
storefront_page_open(t('login'));
?>
<div class="row justify-content-center"><div class="col-md-6">
    <h1><?= htmlspecialchars(t('login')) ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <label class="form-label" for="email"><?= te('account.email') ?></label>
        <input class="form-control mb-3" type="email" name="email" id="email" required>
        <label class="form-label" for="password"><?= te('account.password') ?></label>
        <input class="form-control mb-3" type="password" name="password" id="password" required>
        <button class="btn btn-primary" type="submit"><?= htmlspecialchars(t('login')) ?></button>
        <p class="mt-3 mb-0"><a href="forgot_password.php"><?= te('account.forgot_password') ?></a> · <a href="register.php"><?= htmlspecialchars(t('register')) ?></a></p>
    </form>
</div></div>
<?php storefront_page_close();
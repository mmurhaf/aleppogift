<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/includes/Database.php';
require_admin_login();
$db = new Database();
if (isset($_GET['approve'])) {
    $db->query('UPDATE customer_photos SET is_approved = 1 WHERE id = ?', [(int)$_GET['approve']]);
}
if (isset($_GET['feature'])) {
    $db->query('UPDATE customer_photos SET is_featured = 1, is_approved = 1 WHERE id = ?', [(int)$_GET['feature']]);
}
if (isset($_GET['delete'])) {
    $photo = $db->fetch('SELECT * FROM customer_photos WHERE id = ?', [(int)$_GET['delete']]);
    if ($photo && !empty($photo['photo_path'])) {
        $path = $root_dir . '/public/' . ltrim($photo['photo_path'], '/');
        if (is_file($path)) {
            unlink($path);
        }
    }
    $db->query('DELETE FROM customer_photos WHERE id = ?', [(int)$_GET['delete']]);
    header('Location: gallery.php');
    exit;
}
$photos = $db->fetchAll('SELECT cp.*, p.name_en FROM customer_photos cp LEFT JOIN products p ON p.id = cp.product_id ORDER BY cp.id DESC');
$page_title = 'Gallery';
require __DIR__ . '/header.php';
?>
<div class="container-fluid">
    <div class="row g-3">
    <?php foreach ($photos as $photo): ?>
        <div class="col-md-3"><div class="card">
            <img src="../<?= htmlspecialchars($photo['photo_path']) ?>" class="card-img-top" style="<?= !empty($photo['privacy_blurred']) ? 'filter:blur(6px)' : '' ?>" alt="">
            <div class="card-body">
                <strong><?= htmlspecialchars((string)$photo['customer_name']) ?></strong>
                <div><?= htmlspecialchars((string)$photo['name_en']) ?> · <?= htmlspecialchars((string)$photo['photo_type']) ?></div>
                <div><?= (int)$photo['is_approved'] ? 'Approved' : 'Pending' ?><?= (int)$photo['is_featured'] ? ' · Featured' : '' ?></div>
                <a href="?approve=<?= (int)$photo['id'] ?>">Approve</a>
                <a href="?feature=<?= (int)$photo['id'] ?>">Feature</a>
                <a href="?delete=<?= (int)$photo['id'] ?>">Delete</a>
            </div>
        </div></div>
    <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
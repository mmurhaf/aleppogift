<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = new Database();
$ids = wishlist_ids($db);
$products = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $products = $db->fetchAll("SELECT p.*, (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) AS main_image FROM products p WHERE p.id IN ($placeholders)", $ids);
}
storefront_page_open(t('wishlist'));
?>
<h1><?= htmlspecialchars(t('wishlist')) ?></h1>
<?php if (!$products): ?><p><?= htmlspecialchars(t('empty')) ?></p><?php endif; ?>
<div class="row g-3">
<?php foreach ($products as $product): ?>
    <div class="col-md-3">
        <div class="card">
            <img src="<?= htmlspecialchars($product['main_image'] ?: 'uploads/default-product.jpg') ?>" class="card-img-top" alt="">
            <div class="card-body">
                <a href="product.php?id=<?= (int)$product['id'] ?>"><?= htmlspecialchars(loc_field($product, 'name')) ?></a>
                <form method="post" action="ajax/wishlist_toggle.php">
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <input type="hidden" name="redirect" value="wishlist.php">
                    <button class="btn btn-sm btn-outline-secondary mt-2" type="submit"><?= te('account.remove') ?></button>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php storefront_page_close();
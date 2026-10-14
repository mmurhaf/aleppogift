<?php
/**
 * shared_carts.php  –  Admin panel: Manage shared carts
 * Accessible at /admin/shared_carts.php
 */

$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db      = new Database();
$message = '';
$msg_type = 'success';

// ── POST Actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Expire / delete a shared cart
    if (isset($_POST['action']) && isset($_POST['token'])) {
        $token = trim($_POST['token']);
        if (strlen($token) === 64 && ctype_xdigit($token)) {
            if ($_POST['action'] === 'expire') {
                $db->query("UPDATE shared_carts SET status='expired' WHERE token=:t", ['t' => $token]);
                $message = 'Cart link has been expired.';
            } elseif ($_POST['action'] === 'delete') {
                $db->query("DELETE FROM shared_carts WHERE token=:t", ['t' => $token]);
                $message = 'Shared cart deleted.';
            } elseif ($_POST['action'] === 'reactivate') {
                $db->query(
                    "UPDATE shared_carts SET status='active', expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE token=:t",
                    ['t' => $token]
                );
                $message = 'Cart link reactivated for 7 more days.';
            }
        } else {
            $message  = 'Invalid token.';
            $msg_type = 'danger';
        }
    }
}

// ── Fetch all shared carts ────────────────────────────────────────────────────
$shared_carts = $db->query(
    "SELECT * FROM shared_carts ORDER BY created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$product_names = [];
$product_ids   = [];
foreach ($shared_carts as $sc) {
    foreach (json_decode($sc['cart_data'], true) ?: [] as $item) {
        $product_ids[(int)($item['product_id'] ?? 0)] = true;
    }
}
unset($product_ids[0]);
if ($product_ids) {
    $ids = implode(',', array_map('intval', array_keys($product_ids)));
    foreach ($db->query("SELECT id, name_en FROM products WHERE id IN ($ids)")->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $product_names[(int)$p['id']] = $p['name_en'];
    }
}

$base_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://aleppogift.com';
$page_title = 'Shared Carts';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    body { background-color: var(--light-color); font-family: 'Nunito', sans-serif; }
    .page-header {
        background: white;
        box-shadow: 0 0.15rem 1.75rem rgba(58,59,69,.15);
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        border-radius: .35rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: .75rem;
    }
    .sc-table { background: white; border-radius: .35rem; box-shadow: 0 0.15rem 1.75rem rgba(58,59,69,.1); overflow: hidden; }
    .sc-table th { background: #f8f9fc; border-bottom-width: 1px; font-weight: 700; text-transform: uppercase; font-size: .7rem; letter-spacing: .05em; color: #5a5c69; }
    .badge-active   { background: #1cc88a; color: #fff; }
    .badge-used     { background: #4e73df; color: #fff; }
    .badge-expired  { background: #e74a3b; color: #fff; }
    .token-short    { font-family: monospace; font-size: .8rem; color: #555; }
    .copy-btn       { cursor: pointer; border: none; background: transparent; color: #4e73df; }
    .copy-btn:hover { color: #2e59d9; }
    .action-btns    { display: flex; gap: .35rem; flex-wrap: wrap; }
    .cart-items-list { font-size: .8rem; color: #555; max-width: 200px; }
</style>

<div class="container-fluid py-4">

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h1 class="h4 mb-1 text-gray-800"><i class="fas fa-share-alt me-2"></i>Shared Carts</h1>
            <small class="text-muted">Carts you built for customers and shared via WhatsApp</small>
        </div>
        <a href="../cart.php" class="btn btn-primary btn-sm">
            <i class="fas fa-cart-plus me-1"></i> Go to Cart &rarr; Share
        </a>
    </div>

    <!-- How-to info box -->
    <div class="alert alert-info d-flex gap-2 align-items-start mb-4">
        <i class="fas fa-info-circle mt-1 flex-shrink-0"></i>
        <div>
            <strong>How it works:</strong>
            <ol class="mb-0 ps-3 mt-1">
                <li>Go to the <a href="../cart.php">cart page</a> and add the customer's items.</li>
                <li>Click <strong>"Save &amp; Share Cart"</strong> – fill in the customer name &amp; phone.</li>
                <li>Copy the generated link and send it to the customer on WhatsApp.</li>
                <li>The customer opens the link, reviews their cart, and places the order.</li>
                <li>Track the status of every shared cart in the table below.</li>
            </ol>
        </div>
    </div>

    <!-- Shared carts table -->
    <div class="sc-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($shared_carts)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No shared carts yet. Build one from the cart page!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shared_carts as $sc):
                        $share_url  = $base_url . '/shared-cart.php?token=' . $sc['token'];
                        $items      = json_decode($sc['cart_data'], true) ?: [];
                        $item_count = array_sum(array_column($items, 'quantity'));
                        $is_expired = ($sc['status'] === 'expired') ||
                                      ($sc['expires_at'] && strtotime($sc['expires_at']) < time());
                        $display_status = $is_expired ? 'expired' : $sc['status'];
                    ?>
                    <tr>
                        <td><small class="text-muted"><?= $sc['id'] ?></small></td>
                        <td>
                            <?php if ($sc['customer_name']): ?>
                                <strong><?= htmlspecialchars($sc['customer_name']) ?></strong><br>
                            <?php endif; ?>
                            <?php if ($sc['customer_phone']): ?>
                                <a href="https://wa.me/<?= preg_replace('/\D/','',$sc['customer_phone']) ?>" target="_blank" class="text-success small">
                                    <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($sc['customer_phone']) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($sc['note']): ?>
                                <br><small class="text-muted fst-italic"><?= htmlspecialchars($sc['note']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= $item_count ?> item<?= $item_count !== 1 ? 's' : '' ?></span>
                            <div class="cart-items-list mt-1">
                                <?php foreach (array_slice($items, 0, 3) as $item): ?>
                                    <div>· <?= htmlspecialchars($product_names[(int)$item['product_id']] ?? 'Product #' . (int)$item['product_id']) ?>
                                        <?= !empty($item['variation_id']) ? " (var #".(int)$item['variation_id'].")" : '' ?>
                                        &times;<?= (int)$item['quantity'] ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($items) > 3): ?>
                                    <div class="text-muted">…and <?= count($items) - 3 ?> more</div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= $display_status ?>">
                                <?= ucfirst($display_status) ?>
                            </span>
                        </td>
                        <td>
                            <?= $sc['expires_at']
                                ? '<small>' . htmlspecialchars(date('d M Y H:i', strtotime($sc['expires_at']))) . '</small>'
                                : '<small class="text-muted">Never</small>' ?>
                        </td>
                        <td><small><?= htmlspecialchars(date('d M Y H:i', strtotime($sc['created_at']))) ?></small></td>
                        <td><small><?= htmlspecialchars(date('d M Y H:i', strtotime($sc['updated_at']))) ?></small></td>
                        <td>
                            <div class="action-btns">
                                <!-- Copy link -->
                                <button type="button" class="btn btn-sm btn-outline-primary copy-btn"
                                        onclick="copyLink('<?= htmlspecialchars($share_url, ENT_QUOTES) ?>', this)"
                                        title="Copy share link">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <!-- Open in new tab -->
                                <a href="<?= htmlspecialchars($share_url) ?>" target="_blank"
                                   class="btn btn-sm btn-outline-secondary" title="Open &amp; edit this cart (replaces your current cart)">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <!-- WhatsApp -->
                                <?php if ($sc['customer_phone']): ?>
                                <a href="https://wa.me/<?= preg_replace('/\D/','',$sc['customer_phone']) ?>?text=<?= urlencode("Hi" . ($sc['customer_name'] ? " {$sc['customer_name']}" : "") . "! Here is your cart link:\n{$share_url}") ?>"
                                   target="_blank" class="btn btn-sm btn-success" title="Send via WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <?php endif; ?>
                                <!-- Reactivate -->
                                <?php if ($display_status === 'expired'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="reactivate">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($sc['token']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Reactivate (+7 days)">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <!-- Expire -->
                                <?php if ($display_status === 'active'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="expire">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($sc['token']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                            onclick="return confirm('Expire this cart link?')" title="Expire link">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <!-- Delete -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($sc['token']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Permanently delete this shared cart?')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /sc-table -->
</div><!-- /container -->

<script>
function copyLink(url, btn) {
    navigator.clipboard.writeText(url).then(function () {
        var icon = btn.querySelector('i');
        icon.className = 'fas fa-check text-success';
        setTimeout(function () { icon.className = 'fas fa-copy'; }, 2000);
    }).catch(function () {
        prompt('Copy this link:', url);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

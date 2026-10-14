<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db     = new Database();
$pdo    = $db->getPdo();

// Tables that store product_id as a foreign column
$child_tables = ['product_images', 'product_variations', 'order_items', 'product_reviews'];

$error   = '';
$success = '';
$preview = null;        // product row found for preview
$old_id  = 0;
$new_id  = 0;

// Suggested new ID = MAX(id) + 1
$suggested_new_id = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM products")->fetchColumn();

// ---------------------------------------------------------------
// Handle LOOKUP (preview before committing)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {

    $old_id = (int)($_POST['old_id'] ?? 0);
    $new_id = (int)($_POST['new_id'] ?? 0);

    if ($old_id <= 0 || $new_id <= 0) {
        $error = 'Both IDs must be positive integers.';
    } elseif ($old_id === $new_id) {
        $error = 'Old ID and New ID are identical — nothing to do.';
    } else {
        // Check old product exists
        $stmt = $pdo->prepare("SELECT id, name_en, name_ar, sku, status FROM products WHERE id = ?");
        $stmt->execute([$old_id]);
        $preview = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$preview) {
            $error = "No product found with ID <strong>{$old_id}</strong>.";
        } else {
            // Check new ID is not already taken
            $stmt2 = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmt2->execute([$new_id]);
            if ($stmt2->fetch()) {
                $error = "ID <strong>{$new_id}</strong> is already used by another product. Choose a different new ID.";
                $preview = null;
            }
        }
    }
}

// ---------------------------------------------------------------
// Handle COMMIT (actual update)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'commit') {

    $old_id = (int)($_POST['old_id'] ?? 0);
    $new_id = (int)($_POST['new_id'] ?? 0);

    if ($old_id <= 0 || $new_id <= 0 || $old_id === $new_id) {
        $error = 'Invalid IDs submitted.';
    } else {
        // Final safety checks
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$old_id]);
        if (!$stmt->fetch()) {
            $error = "Product ID {$old_id} no longer exists.";
        } else {
            $stmt2 = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmt2->execute([$new_id]);
            if ($stmt2->fetch()) {
                $error = "ID {$new_id} is already taken by another product.";
            } else {
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    $pdo->beginTransaction();

                    // 1. Update child tables
                    foreach ($child_tables as $tbl) {
                        $pdo->prepare("UPDATE `{$tbl}` SET product_id = ? WHERE product_id = ?")->execute([$new_id, $old_id]);
                    }

                    // 2. Update products.id
                    $pdo->prepare("UPDATE products SET id = ? WHERE id = ?")->execute([$new_id, $old_id]);

                    $pdo->commit();
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

                    $success = "Product ID successfully changed from <strong>{$old_id}</strong> to <strong>{$new_id}</strong>.";
                    $old_id = $new_id = 0; // reset form

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
}

// ---------------------------------------------------------------
// Page output
// ---------------------------------------------------------------
$page_title = 'Change Product ID';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4" style="max-width:760px">

    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center">
            <i class="fas fa-hashtag text-white"></i>
        </div>
        <div>
            <h4 class="mb-0">Change Product ID</h4>
            <p class="text-muted small mb-0">Remaps a product's ID and updates all related tables automatically.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="fas fa-circle-xmark"></i>
        <span><?php echo $error; ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="fas fa-circle-check"></i>
        <span><?php echo $success; ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Step 1: Look up form ── -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom fw-semibold py-3">
            <i class="fas fa-search me-2 text-primary"></i>Step 1 — Enter IDs
        </div>
        <div class="card-body p-4">
            <form method="POST" action="">
                <input type="hidden" name="action" value="lookup">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-medium">Current Product ID</label>
                        <input type="number" name="old_id" class="form-control form-control-lg"
                               placeholder="e.g. 9652" min="1"
                               value="<?php echo $old_id > 0 ? (int)$old_id : ''; ?>" required>
                    </div>
                    <div class="col-md-1 text-center pb-1">
                        <i class="fas fa-arrow-right text-muted" style="font-size:1.3rem"></i>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-medium">New Product ID</label>
                        <input type="number" name="new_id" class="form-control form-control-lg"
                               placeholder="e.g. <?php echo $suggested_new_id; ?>" min="1"
                               value="<?php echo $new_id > 0 ? (int)$new_id : $suggested_new_id; ?>" required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary btn-lg w-100" title="Look up">
                            <i class="fas fa-magnifying-glass"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($preview && !$error): ?>
    <!-- ── Step 2: Preview & confirm ── -->
    <div class="card shadow-sm border-0 border-start border-4 border-warning mb-4">
        <div class="card-header bg-white border-bottom fw-semibold py-3">
            <i class="fas fa-eye me-2 text-warning"></i>Step 2 — Review &amp; Confirm
        </div>
        <div class="card-body p-4">

            <table class="table table-bordered table-sm mb-4">
                <thead class="table-light">
                    <tr><th>Field</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <tr><td class="fw-medium">Current ID</td>
                        <td><span class="badge bg-danger fs-6"><?php echo (int)$preview['id']; ?></span></td></tr>
                    <tr><td class="fw-medium">New ID</td>
                        <td><span class="badge bg-success fs-6"><?php echo (int)$new_id; ?></span></td></tr>
                    <tr><td class="fw-medium">Name (EN)</td>
                        <td><?php echo htmlspecialchars($preview['name_en']); ?></td></tr>
                    <tr><td class="fw-medium">Name (AR)</td>
                        <td><?php echo htmlspecialchars($preview['name_ar']); ?></td></tr>
                    <tr><td class="fw-medium">SKU</td>
                        <td><?php echo htmlspecialchars($preview['sku'] ?? '—'); ?></td></tr>
                    <tr><td class="fw-medium">Status</td>
                        <td><?php echo $preview['status'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td></tr>
                </tbody>
            </table>

            <!-- Related row counts -->
            <h6 class="fw-semibold mb-2">Related rows that will be updated:</h6>
            <ul class="list-group list-group-flush mb-4">
                <?php foreach ($child_tables as $tbl):
                    $cnt = $pdo->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE product_id = ?");
                    $cnt->execute([$old_id]);
                    $n = $cnt->fetchColumn();
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <code><?php echo $tbl; ?></code>
                    <span class="badge bg-primary rounded-pill"><?php echo $n; ?> row(s)</span>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="fas fa-triangle-exclamation mt-1"></i>
                <div>This action is <strong>irreversible</strong>. All rows in the tables above will have their
                <code>product_id</code> updated, and <code>products.id</code> will be changed from
                <strong><?php echo (int)$old_id; ?></strong> to <strong><?php echo (int)$new_id; ?></strong>.</div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action"  value="commit">
                <input type="hidden" name="old_id"  value="<?php echo (int)$old_id; ?>">
                <input type="hidden" name="new_id"  value="<?php echo (int)$new_id; ?>">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-check me-2"></i>Confirm &amp; Apply Change
                    </button>
                    <a href="change_product_id.php" class="btn btn-outline-secondary">
                        <i class="fas fa-xmark me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Info box -->
    <div class="card border-0 bg-light">
        <div class="card-body p-3">
            <h6 class="fw-semibold mb-2"><i class="fas fa-circle-info me-2 text-info"></i>What gets updated</h6>
            <ul class="mb-0 small text-muted">
                <li><code>products</code> — <code>id</code> column</li>
                <?php foreach ($child_tables as $tbl): ?>
                <li><code><?php echo $tbl; ?></code> — <code>product_id</code> column</li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

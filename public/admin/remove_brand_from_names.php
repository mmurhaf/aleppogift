<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db  = new Database();
$pdo = $db->getPdo();

// ---------------------------------------------------------------
// Load all brands for the filter dropdown
// ---------------------------------------------------------------
$brands = $pdo->query("SELECT id, name_en, name_ar FROM brands WHERE status = 1 ORDER BY name_en")
              ->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

/**
 * Remove all occurrences of a brand name from a product name string.
 * Handles:
 *  - "BrandName text"   (at start)
 *  - "text BrandName"   (at end)
 *  - "text BrandName text" (middle)
 *  - "from BrandName" / "by BrandName" / "From BrandName"
 *  - "BrandName's"
 * Returns the cleaned string (extra spaces trimmed).
 */
function strip_brand(string $text, string $brand): string {
    if ($brand === '') return $text;

    $q = preg_quote($brand, '/');

    // "from/by BrandName"
    $text = preg_replace('/\b(?:from|by)\s+' . $q . '\b/iu', '', $text);
    // standalone brand word (possibly possessive)
    $text = preg_replace('/\b' . $q . '\'?s?\b/iu', '', $text);
    // collapse multiple spaces and trim stray punctuation
    $text = preg_replace('/[ \t]{2,}/', ' ', $text);
    $text = trim($text, " \t\n\r\0\x0B-,.");

    return $text;
}

// ---------------------------------------------------------------
// Determine selected brand filter
// ---------------------------------------------------------------
$filter_brand_id = isset($_POST['brand_id']) ? (int)$_POST['brand_id']
                 : (isset($_GET['brand_id'])  ? (int)$_GET['brand_id'] : 0);

$action = $_POST['action'] ?? '';

// ---------------------------------------------------------------
// Build brand lookup map
// ---------------------------------------------------------------
$brand_map = [];   // id => ['name_en'=>..., 'name_ar'=>...]
foreach ($brands as $b) {
    $brand_map[(int)$b['id']] = $b;
}

// ---------------------------------------------------------------
// COMMIT — apply the changes
// ---------------------------------------------------------------
$commit_results = [];
if ($action === 'commit' && $filter_brand_id > 0) {

    $brand_name = $brand_map[$filter_brand_id]['name_en'] ?? '';

    // Fetch affected products for this brand
    $stmt = $pdo->prepare(
        "SELECT id, name_en, name_ar, description_en FROM products WHERE brand_id = ? ORDER BY id"
    );
    $stmt->execute([$filter_brand_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $new_name_en = strip_brand((string)($row['name_en'] ?? ''), $brand_name);
        $new_desc_en = strip_brand((string)($row['description_en'] ?? ''), $brand_name);

        $name_changed = $new_name_en !== ($row['name_en'] ?? '');
        $desc_changed  = $new_desc_en !== ($row['description_en'] ?? '');

        if ($name_changed || $desc_changed) {
            try {
                $pdo->prepare(
                    "UPDATE products SET name_en = :n, description_en = :d WHERE id = :id"
                )->execute([
                    'n'  => $new_name_en,
                    'd'  => $new_desc_en,
                    'id' => $row['id'],
                ]);
                $commit_results[] = [
                    'id'           => $row['id'],
                    'old_name'     => $row['name_en'],
                    'new_name'     => $new_name_en,
                    'name_changed' => $name_changed,
                    'desc_changed' => $desc_changed,
                    'ok'           => true,
                ];
            } catch (Exception $e) {
                $commit_results[] = ['id' => $row['id'], 'ok' => false, 'err' => $e->getMessage()];
            }
        }
    }
}

// ---------------------------------------------------------------
// PREVIEW — calculate what would change (no writes)
// ---------------------------------------------------------------
$preview_rows = [];
if ($filter_brand_id > 0 && $action !== 'commit') {
    $brand_name = $brand_map[$filter_brand_id]['name_en'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT id, name_en, name_ar, description_en FROM products WHERE brand_id = ? ORDER BY id"
    );
    $stmt->execute([$filter_brand_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $new_name_en = strip_brand((string)($row['name_en'] ?? ''), $brand_name);
        $new_desc_en = strip_brand((string)($row['description_en'] ?? ''), $brand_name);

        $name_changed = $new_name_en !== ($row['name_en'] ?? '');
        $desc_changed  = $new_desc_en !== ($row['description_en'] ?? '');

        if ($name_changed || $desc_changed) {
            $preview_rows[] = [
                'id'           => $row['id'],
                'old_name'     => $row['name_en'],
                'new_name'     => $new_name_en,
                'old_desc'     => $row['description_en'],
                'new_desc'     => $new_desc_en,
                'name_changed' => $name_changed,
                'desc_changed' => $desc_changed,
            ];
        }
    }
}

$total_products  = $filter_brand_id > 0 ? count($rows ?? []) : 0;
$total_changes   = count($preview_rows);

// ---------------------------------------------------------------
// Page output
// ---------------------------------------------------------------
$page_title = 'Remove Brand from Product Names';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.diff-old { color:#c00; background:#fee; text-decoration:line-through; border-radius:3px; padding:0 3px; }
.diff-new { color:#0a0; background:#efe; border-radius:3px; padding:0 3px; }
.preview-table td { vertical-align:top; }
</style>

<div class="container-fluid px-4 py-4" style="max-width:1050px">

    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#ef4444);display:flex;align-items:center;justify-content:center">
            <i class="fas fa-eraser text-white"></i>
        </div>
        <div>
            <h4 class="mb-0">Remove Brand Name from Product Names</h4>
            <p class="text-muted small mb-0">Select a brand — preview which English names contain it, then apply the cleanup.</p>
        </div>
    </div>

    <!-- ── Brand selector ── -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom fw-semibold py-3">
            <i class="fas fa-copyright me-2 text-warning"></i>Select Brand
        </div>
        <div class="card-body p-4">
            <form method="POST" action="">
                <input type="hidden" name="action" value="preview">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Brand</label>
                        <select name="brand_id" class="form-select form-select-lg" required>
                            <option value="">— Choose a brand —</option>
                            <?php foreach ($brands as $b): ?>
                            <option value="<?php echo (int)$b['id']; ?>"
                                <?php echo $filter_brand_id === (int)$b['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['name_en']); ?>
                                (<?php echo htmlspecialchars($b['name_ar']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-magnifying-glass me-2"></i>Preview Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($action === 'commit' && $filter_brand_id > 0): ?>
    <!-- ── Commit results ── -->
    <div class="alert <?php echo empty($commit_results) ? 'alert-info' : 'alert-success'; ?> d-flex gap-2 align-items-start">
        <i class="fas <?php echo empty($commit_results) ? 'fa-circle-info' : 'fa-circle-check'; ?> mt-1"></i>
        <div>
            <?php if (empty($commit_results)): ?>
                No products needed updating — the brand name was not found in any English name or description.
            <?php else: ?>
                <strong><?php echo count($commit_results); ?> product(s) updated</strong> —
                "<?php echo htmlspecialchars($brand_map[$filter_brand_id]['name_en']); ?>"
                removed from English names and descriptions.
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($commit_results)): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom fw-semibold py-3">
            <i class="fas fa-list-check me-2 text-success"></i>Applied Changes
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0 preview-table">
                <thead class="table-light">
                    <tr><th style="width:60px">ID</th><th>Before</th><th>After</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($commit_results as $r): ?>
                    <?php if (!$r['ok']): ?>
                    <tr class="table-danger">
                        <td><?php echo (int)$r['id']; ?></td>
                        <td colspan="2" class="text-danger"><i class="fas fa-xmark me-1"></i><?php echo htmlspecialchars($r['err']); ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><a href="edit_product.php?id=<?php echo (int)$r['id']; ?>" target="_blank"><?php echo (int)$r['id']; ?></a></td>
                        <td><span class="diff-old"><?php echo htmlspecialchars($r['old_name']); ?></span></td>
                        <td><span class="diff-new"><?php echo htmlspecialchars($r['new_name']); ?></span></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($filter_brand_id > 0 && $action === 'preview'): ?>
    <!-- ── Preview ── -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <span class="badge bg-secondary me-1">Total products for brand: <?php echo $total_products; ?></span>
            <span class="badge <?php echo $total_changes > 0 ? 'bg-warning text-dark' : 'bg-success'; ?>">
                Products with brand name in text: <?php echo $total_changes; ?>
            </span>
        </div>
    </div>

    <?php if ($total_changes === 0): ?>
    <div class="alert alert-success d-flex gap-2">
        <i class="fas fa-circle-check mt-1"></i>
        <span>No changes needed — <strong><?php echo htmlspecialchars($brand_map[$filter_brand_id]['name_en']); ?></strong>
        does not appear in any English product name or description for this brand.</span>
    </div>

    <?php else: ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom fw-semibold py-3">
            <i class="fas fa-eye me-2 text-warning"></i>Preview — <?php echo $total_changes; ?> product(s) will be updated
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0 preview-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:55px">ID</th>
                        <th style="width:50px">Field</th>
                        <th>Before</th>
                        <th>After</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview_rows as $r): ?>
                        <?php if ($r['name_changed']): ?>
                        <tr>
                            <td rowspan="<?php echo ($r['name_changed'] && $r['desc_changed']) ? 2 : 1; ?>">
                                <a href="edit_product.php?id=<?php echo (int)$r['id']; ?>" target="_blank"
                                   class="text-decoration-none"><?php echo (int)$r['id']; ?></a>
                            </td>
                            <td><code class="small">name</code></td>
                            <td><span class="diff-old"><?php echo htmlspecialchars($r['old_name']); ?></span></td>
                            <td><span class="diff-new"><?php echo htmlspecialchars($r['new_name']); ?></span></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($r['desc_changed']): ?>
                        <tr>
                            <?php if (!$r['name_changed']): ?><td><?php echo (int)$r['id']; ?></td><?php endif; ?>
                            <td><code class="small">desc</code></td>
                            <td><span class="diff-old"><?php echo htmlspecialchars(mb_substr($r['old_desc'], 0, 100)) . (mb_strlen($r['old_desc']) > 100 ? '…' : ''); ?></span></td>
                            <td><span class="diff-new"><?php echo htmlspecialchars(mb_substr($r['new_desc'], 0, 100)) . (mb_strlen($r['new_desc']) > 100 ? '…' : ''); ?></span></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-warning d-flex gap-2 align-items-start mb-4">
        <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0"></i>
        <span>This will update <strong><?php echo $total_changes; ?></strong> product(s) — removing
        <strong>"<?php echo htmlspecialchars($brand_map[$filter_brand_id]['name_en']); ?>"</strong>
        from their English name and description. This cannot be undone automatically.</span>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="action"    value="commit">
        <input type="hidden" name="brand_id"  value="<?php echo (int)$filter_brand_id; ?>">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger px-4">
                <i class="fas fa-check me-2"></i>Apply to <?php echo $total_changes; ?> product(s)
            </button>
            <a href="remove_brand_from_names.php" class="btn btn-outline-secondary">
                <i class="fas fa-xmark me-2"></i>Cancel
            </a>
        </div>
    </form>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Info card -->
    <div class="card border-0 bg-light mt-4">
        <div class="card-body p-3">
            <h6 class="fw-semibold mb-2"><i class="fas fa-circle-info me-2 text-info"></i>What gets updated</h6>
            <ul class="mb-0 small text-muted">
                <li><code>products.name_en</code> — brand name word removed (case-insensitive)</li>
                <li><code>products.description_en</code> — brand name word removed</li>
                <li>Handles patterns: <em>"Hermes cup"</em>, <em>"cup from Hermes"</em>, <em>"Hermes's set"</em></li>
                <li>Arabic fields are <strong>not</strong> touched</li>
            </ul>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

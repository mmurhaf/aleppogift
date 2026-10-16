<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/includes/functions.php');
require_once($root_dir . '/includes/helpers/bundles.php');
require_admin_login();

$db = new Database();
ensureBundleSchema($db);
$message = '';
$error = '';

function bundle_items_from_post(): array {
    $raw = json_decode($_POST['items_json'] ?? '[]', true);
    if (!is_array($raw)) {
        return [];
    }
    $items = [];
    $seen = [];
    foreach ($raw as $row) {
        $productId = (int)($row['product_id'] ?? 0);
        $variationId = (int)($row['variation_id'] ?? 0);
        $quantity = (int)($row['quantity'] ?? 0);
        if ($productId < 1 || $quantity < 1) {
            continue;
        }
        $key = $productId . ':' . $variationId;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $items[] = [
            'product_id' => $productId,
            'variation_id' => $variationId > 0 ? $variationId : null,
            'quantity' => $quantity,
        ];
    }
    return $items;
}

function save_bundle_image(array $existing = null): ?string {
    if (!isset($_FILES['image']) || (int)$_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing['image_path'] ?? null;
    }
    if ((int)$_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Could not upload the bundle image.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $type = mime_content_type($_FILES['image']['tmp_name']) ?: '';
    if (!isset($allowed[$type])) {
        throw new RuntimeException('Bundle image must be JPG, PNG, WEBP, or GIF.');
    }
    $dir = __DIR__ . '/../uploads/bundles/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = 'bundle_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$type];
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $filename)) {
        throw new RuntimeException('Could not store the bundle image.');
    }
    return 'uploads/bundles/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (isset($_POST['delete_bundle'])) {
        $bundleId = (int)$_POST['delete_bundle'];
        $used = $db->query(
            "SELECT COUNT(*) AS total FROM order_bundles WHERE bundle_id = :id",
            ['id' => $bundleId]
        )->fetch(PDO::FETCH_ASSOC);
        if ((int)($used['total'] ?? 0) > 0) {
            $db->query(
                "UPDATE bundles SET status = 0, show_on_home = 0 WHERE id = :id",
                ['id' => $bundleId]
            );
            $message = 'This bundle is on past orders, so it was disabled instead of deleted.';
        } else {
            $db->query("DELETE FROM bundles WHERE id = :id", ['id' => $bundleId]);
            $message = 'Bundle deleted.';
        }
    } elseif (isset($_POST['save_bundle'])) {
        $bundleId = (int)($_POST['bundle_id'] ?? 0);
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameAr = trim($_POST['name_ar'] ?? '');
        $descriptionEn = trim($_POST['description_en'] ?? '');
        $descriptionAr = trim($_POST['description_ar'] ?? '');
        $discount = (float)($_POST['discount_percent'] ?? 0);
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        $showOnHome = isset($_POST['show_on_home']) ? 1 : 0;
        $items = bundle_items_from_post();

        if ($nameEn === '' || $nameAr === '') {
            $error = 'English and Arabic names are required.';
        } elseif ($discount < 0 || $discount > 100) {
            $error = 'Discount percent must be between 0 and 100.';
        } elseif (count($items) < 1) {
            $error = 'Add at least one product to the bundle.';
        } else {
            if (count($items) < 2) {
                $showOnHome = 0;
            }
            try {
                $existing = null;
                if ($bundleId > 0) {
                    $existing = $db->query("SELECT * FROM bundles WHERE id = :id", ['id' => $bundleId])->fetch(PDO::FETCH_ASSOC);
                }
                $imagePath = save_bundle_image($existing ?: null);
                $params = [
                    $nameEn,
                    $nameAr,
                    $descriptionEn,
                    $descriptionAr,
                    $discount,
                    $imagePath,
                    $status,
                    $showOnHome,
                    $displayOrder,
                ];
                if ($existing) {
                    $params[] = $bundleId;
                    $db->query(
                        "UPDATE bundles
                         SET name_en = ?, name_ar = ?, description_en = ?, description_ar = ?, discount_percent = ?,
                             image_path = ?, status = ?, show_on_home = ?, display_order = ?
                         WHERE id = ?",
                        $params
                    );
                } else {
                    $db->query(
                        "INSERT INTO bundles
                            (name_en, name_ar, description_en, description_ar, discount_percent, image_path, status, show_on_home, display_order)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        $params
                    );
                    $bundleId = (int)$db->lastInsertId();
                }

                $db->query("DELETE FROM bundle_items WHERE bundle_id = :id", ['id' => $bundleId]);
                foreach ($items as $item) {
                    $variationOk = true;
                    if ($item['variation_id']) {
                        $variation = $db->query(
                            "SELECT id FROM product_variations WHERE id = :id AND product_id = :product_id",
                            ['id' => $item['variation_id'], 'product_id' => $item['product_id']]
                        )->fetch(PDO::FETCH_ASSOC);
                        $variationOk = (bool)$variation;
                    }
                    $product = $db->query(
                        "SELECT id FROM products WHERE id = :id",
                        ['id' => $item['product_id']]
                    )->fetch(PDO::FETCH_ASSOC);
                    if (!$product || !$variationOk) {
                        continue;
                    }
                    $db->query(
                        "INSERT INTO bundle_items (bundle_id, product_id, variation_id, quantity) VALUES (?, ?, ?, ?)",
                        [$bundleId, $item['product_id'], $item['variation_id'], $item['quantity']]
                    );
                }
                $savedCount = (int)$db->query(
                    "SELECT COUNT(*) AS total FROM bundle_items WHERE bundle_id = :id",
                    ['id' => $bundleId]
                )->fetch(PDO::FETCH_ASSOC)['total'];
                if ($savedCount < 2 && $showOnHome) {
                    $db->query("UPDATE bundles SET show_on_home = 0 WHERE id = :id", ['id' => $bundleId]);
                }
                $message = $savedCount < 2
                    ? 'Bundle saved. It needs at least two products before it can appear on the home page.'
                    : 'Bundle saved.';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$editBundle = null;
$editItems = [];
if (isset($_GET['edit'])) {
    $editBundle = $db->query(
        "SELECT * FROM bundles WHERE id = :id",
        ['id' => (int)$_GET['edit']]
    )->fetch(PDO::FETCH_ASSOC);
    if ($editBundle) {
        $editItems = $db->query(
            "SELECT bi.product_id, bi.variation_id, bi.quantity, p.name_en, p.price, p.sale_price
             FROM bundle_items bi
             JOIN products p ON p.id = bi.product_id
             WHERE bi.bundle_id = :id
             ORDER BY bi.id ASC",
            ['id' => (int)$editBundle['id']]
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

$bundles = $db->query(
    "SELECT b.*,
            (SELECT COUNT(*) FROM bundle_items bi WHERE bi.bundle_id = b.id) AS item_count
     FROM bundles b
     ORDER BY b.display_order ASC, b.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Bundles';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    .bundle-card { background:#fff; border-radius:.35rem; box-shadow:0 .15rem 1.75rem rgba(58,59,69,.1); padding:1.25rem; margin-bottom:1.5rem; max-width:100%; }
    .bundle-card .row { --bs-gutter-x: 1rem; margin-left:0; margin-right:0; }
    .bundle-table th { font-size:.75rem; text-transform:uppercase; color:#5a5c69; }
    .bundle-table-wrap { width:100%; overflow-x:auto; }
    .bundle-item-row { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(0,.8fr) 90px auto; gap:.5rem; align-items:center; margin-bottom:.5rem; }
    .bundle-checks { display:flex; flex-wrap:wrap; gap:.75rem 1.5rem; align-items:center; }
    .bundle-empty { margin:0; color:#6c757d; }
    .search-results { border:1px solid #e3e6f0; border-radius:.35rem; max-height:220px; overflow:auto; background:#fff; }
    .search-results button { display:block; width:100%; text-align:left; background:#fff; border:0; border-bottom:1px solid #f1f1f1; padding:.55rem .75rem; }
    @media (max-width: 767.98px) {
        .bundle-card { padding:.9rem; }
        .bundle-item-row { grid-template-columns:minmax(0,1fr) 88px; }
        .bundle-item-name { grid-column:1 / -1; }
        .bundle-item-remove { grid-column:1 / -1; }
        .bundle-form-actions .btn { width:100%; }
        .bundle-table-wrap { overflow:visible; }
        .bundle-table thead { display:none; }
        .bundle-table,
        .bundle-table tbody,
        .bundle-table tr,
        .bundle-table td { display:block; width:100%; }
        .bundle-table tr {
            border:1px solid rgba(230,126,34,.16);
            border-radius:14px;
            background:#fffaf5;
            padding:.85rem .9rem;
            margin-bottom:.75rem;
        }
        .bundle-table td {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:.75rem;
            padding:.4rem 0 !important;
            border:0 !important;
            background:transparent !important;
            text-align:right;
        }
        .bundle-table td::before {
            content:attr(data-label);
            color:#7a4b16;
            font-size:.72rem;
            font-weight:800;
            letter-spacing:.06em;
            text-transform:uppercase;
            text-align:left;
        }
        .bundle-table td.bundle-name { display:block; text-align:left; padding-bottom:.35rem !important; overflow-wrap:anywhere; }
        .bundle-table td.bundle-name::before { display:none; }
        .bundle-table td.bundle-actions { display:flex; gap:.5rem; padding-top:.65rem !important; }
        .bundle-table td.bundle-actions::before { content:none; }
        .bundle-table td.bundle-actions .btn,
        .bundle-table td.bundle-actions form { flex:1 1 0; }
        .bundle-table td.bundle-actions form .btn { width:100%; }
    }
</style>
<div class="container-fluid py-3">
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="bundle-card">
        <h2 class="h5 mb-3"><?= $editBundle ? 'Edit bundle' : 'New bundle' ?></h2>
        <form method="post" enctype="multipart/form-data" id="bundle-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <input type="hidden" name="save_bundle" value="1">
            <input type="hidden" name="bundle_id" value="<?= (int)($editBundle['id'] ?? 0) ?>">
            <input type="hidden" name="items_json" id="items_json" value="<?= htmlspecialchars(json_encode($editItems), ENT_QUOTES, 'UTF-8') ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name (English)</label>
                    <input class="form-control" name="name_en" required value="<?= htmlspecialchars($editBundle['name_en'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Name (Arabic)</label>
                    <input class="form-control" name="name_ar" required dir="rtl" value="<?= htmlspecialchars($editBundle['name_ar'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description (English)</label>
                    <textarea class="form-control" name="description_en" rows="2"><?= htmlspecialchars($editBundle['description_en'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description (Arabic)</label>
                    <textarea class="form-control" name="description_ar" rows="2" dir="rtl"><?= htmlspecialchars($editBundle['description_ar'] ?? '') ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Discount %</label>
                    <input class="form-control" type="number" min="0" max="100" step="0.01" name="discount_percent" value="<?= htmlspecialchars((string)($editBundle['discount_percent'] ?? '0')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Display order</label>
                    <input class="form-control" type="number" name="display_order" value="<?= (int)($editBundle['display_order'] ?? 0) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Image</label>
                    <input class="form-control" type="file" name="image" accept="image/*">
                </div>
                <div class="col-md-6 bundle-checks">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="status" <?= !isset($editBundle) || !empty($editBundle['status']) ? 'checked' : '' ?>>
                        Active
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_on_home" <?= !isset($editBundle) || !empty($editBundle['show_on_home']) ? 'checked' : '' ?>>
                        Show on home page
                    </label>
                </div>
            </div>

            <hr>
            <label class="form-label">Add product</label>
            <input class="form-control mb-2" id="product-search" placeholder="Search by name, SKU, or ID" autocomplete="off">
            <div class="search-results d-none" id="search-results"></div>
            <div class="mt-3" id="bundle-items"></div>
            <p class="text-muted small">A bundle needs at least two products before it can be shown on the home page.</p>
            <div class="bundle-form-actions d-grid gap-2 d-md-block">
                <button class="btn btn-primary" type="submit">Save bundle</button>
                <?php if ($editBundle): ?><a class="btn btn-link" href="bundles.php">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bundle-card">
        <?php if (!$bundles): ?>
            <p class="bundle-empty">No bundles yet.</p>
        <?php else: ?>
        <div class="bundle-table-wrap">
        <table class="table bundle-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Bundle</th>
                    <th>Discount</th>
                    <th>Items</th>
                    <th>Home</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bundles as $bundle): ?>
                <tr>
                    <td class="bundle-name" data-label="Bundle">
                        <strong><?= htmlspecialchars($bundle['name_en']) ?></strong>
                        <div dir="rtl"><?= htmlspecialchars($bundle['name_ar']) ?></div>
                    </td>
                    <td data-label="Discount"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$bundle['discount_percent'], 2, '.', ''), '0'), '.')) ?>%</td>
                    <td data-label="Items"><?= (int)$bundle['item_count'] ?></td>
                    <td data-label="Home"><?= !empty($bundle['show_on_home']) ? 'Yes' : 'No' ?></td>
                    <td data-label="Status"><?= !empty($bundle['status']) ? 'Active' : 'Disabled' ?></td>
                    <td class="text-end bundle-actions" data-label="Actions">
                        <a class="btn btn-sm btn-outline-primary" href="bundles.php?edit=<?= (int)$bundle['id'] ?>">Edit</a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete or disable this bundle?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                            <button class="btn btn-sm btn-outline-danger" name="delete_bundle" value="<?= (int)$bundle['id'] ?>" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
const initialItems = <?= json_encode(array_map(function ($item) {
    return [
        'product_id' => (int)$item['product_id'],
        'variation_id' => (int)($item['variation_id'] ?? 0),
        'quantity' => (int)$item['quantity'],
        'name_en' => $item['name_en'],
        'price' => product_effective_price($item),
    ];
}, $editItems), JSON_UNESCAPED_UNICODE) ?>;
const items = initialItems.slice();
const list = document.getElementById('bundle-items');
const hidden = document.getElementById('items_json');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
}

function writeItems() {
    hidden.value = JSON.stringify(items.map(item => ({
        product_id: item.product_id,
        variation_id: item.variation_id || null,
        quantity: item.quantity
    })));
}
function syncItems() {
    writeItems();
    list.innerHTML = '';
    items.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'bundle-item-row';
        row.innerHTML =
            '<div class="bundle-item-name"><strong>' + escapeHtml(item.name_en) + '</strong><div class="text-muted small">#' + item.product_id + '</div></div>' +
            '<select class="form-select form-select-sm variation-select" data-index="' + index + '"></select>' +
            '<input class="form-control form-control-sm bundle-item-qty" type="number" min="1" value="' + item.quantity + '" data-qty="' + index + '">' +
            '<button type="button" class="btn btn-sm btn-outline-danger bundle-item-remove" data-remove="' + index + '">Remove</button>';
        list.appendChild(row);
        const select = row.querySelector('select');
        select.innerHTML = '<option value="0">No variation</option>';
        fetch('ajax/product_variations.php?product_id=' + item.product_id)
            .then(response => response.json())
            .then(data => {
                (data.variations || []).forEach(variation => {
                    const option = document.createElement('option');
                    option.value = variation.id;
                    const bits = [variation.color, variation.size].filter(Boolean).join(' / ');
                    option.textContent = (bits || 'Variation') + ' (+' + variation.additional_price + ')';
                    if (String(item.variation_id || 0) === String(variation.id)) option.selected = true;
                    select.appendChild(option);
                });
            });
    });
}

list.addEventListener('change', function (event) {
    const qty = event.target.getAttribute('data-qty');
    if (qty !== null) {
        items[qty].quantity = Math.max(1, parseInt(event.target.value, 10) || 1);
        writeItems();
    }
    if (event.target.classList.contains('variation-select')) {
        items[event.target.dataset.index].variation_id = parseInt(event.target.value, 10) || 0;
        writeItems();
    }
});
list.addEventListener('click', function (event) {
    const remove = event.target.getAttribute('data-remove');
    if (remove !== null) {
        items.splice(parseInt(remove, 10), 1);
        syncItems();
    }
});

let searchTimer = null;
document.getElementById('product-search').addEventListener('input', function () {
    const query = this.value.trim();
    const results = document.getElementById('search-results');
    clearTimeout(searchTimer);
    if (query.length < 2) {
        results.classList.add('d-none');
        return;
    }
    searchTimer = setTimeout(() => {
        fetch('ajax/search_products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query })
        })
        .then(response => response.json())
        .then(data => {
            results.innerHTML = '';
            (data.products || []).forEach(product => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = product.name_en + ' — ' + product.price + ' AED';
                button.addEventListener('click', () => {
                    if (!items.some(item => item.product_id === parseInt(product.id, 10) && !item.variation_id)) {
                        items.push({
                            product_id: parseInt(product.id, 10),
                            variation_id: 0,
                            quantity: 1,
                            name_en: product.name_en,
                            price: product.price
                        });
                        syncItems();
                    }
                    results.classList.add('d-none');
                    document.getElementById('product-search').value = '';
                });
                results.appendChild(button);
            });
            results.classList.toggle('d-none', !(data.products || []).length);
        });
    }, 250);
});

document.getElementById('bundle-form').addEventListener('submit', syncItems);
syncItems();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

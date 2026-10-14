<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/includes/Database.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/currencies.php';
require_once $root_dir . '/includes/store_features.php';
require_once $root_dir . '/includes/supplier_import.php';
require_admin_login();
$db = new Database();

if (isset($_GET['template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="aleppogift-supplier-import.csv"');
    echo "product_name,supplier_cost,currency,selling_price,category,brand,image_url,availability,stock,supplier,supplier_url,supplier_sku,description,name_ar\n";
    exit;
}

$results = [];
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {
    foreach ((array)$_POST['selected_ids'] as $id) {
        $id = (int)$id;
        if (($_POST['draft_action'] ?? '') === 'publish') {
            $db->query("UPDATE products SET listing_status = 'published', status = 1 WHERE id = ? AND listing_status = 'draft'", [$id]);
            $message = 'Selected drafts were published.';
        }
        if (($_POST['draft_action'] ?? '') === 'discard') {
            $row = $db->fetch("SELECT id FROM products WHERE id = ? AND listing_status = 'draft'", [$id]);
            if ($row) {
                $db->query('DELETE FROM product_images WHERE product_id = ?', [$id]);
                $db->query('DELETE FROM products WHERE id = ?', [$id]);
                $message = 'Selected drafts were discarded.';
            }
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    $file = $_FILES['csv'] ?? null;
    $markup = (float)($_POST['markup'] ?? 80);
    $supplier = trim((string)($_POST['supplier'] ?? ''));
    $supplierUrl = trim((string)($_POST['supplier_url'] ?? ''));
    if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 2 * 1024 * 1024 || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $message = 'Upload a CSV file of 2 MB or smaller.';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn($col) => strtolower(trim((string)$col)), $header);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false && count($rows) < 500) {
            $row = array_combine($header, array_pad($data, count($header), ''));
            if (!$row || trim((string)($row['product_name'] ?? '')) === '' || trim((string)($row['supplier_cost'] ?? '')) === '') {
                $rows[] = ['product_name' => $row['product_name'] ?? '', 'supplier_cost' => ''];
                continue;
            }
            $row['supplier'] = $row['supplier'] ?: $supplier;
            $row['supplier_url'] = $row['supplier_url'] ?: $supplierUrl;
            $rows[] = $row;
        }
        fclose($handle);
        $results = supplier_import_rows($db, $rows, $file['name'], $supplier, $markup);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_link'])) {
    $url = trim((string)($_POST['catalog_url'] ?? ''));
    $max = min(100, max(1, (int)($_POST['max_products'] ?? 100)));
    $markup = (float)($_POST['markup'] ?? 80);
    $role = supplier_price_role($url, (string)($_POST['price_role'] ?? 'auto'));
    $pages = 0;
    $parsed = [];
    $next = $url;
    while ($next && $pages < 8 && count($parsed) < $max) {
        $html = supplier_http_get($next);
        if ($html === '') {
            break;
        }
        foreach (supplier_parse_products($html, $next) as $item) {
            if (count($parsed) >= $max) {
                break;
            }
            $item['price_role'] = $role;
            $item['product_name'] = $item['name'];
            $item['supplier_url'] = $item['url'];
            $item['supplier_sku'] = $item['sku'];
            $item['image_url'] = $item['image'];
            $parsed[] = $item;
        }
        $pages++;
        $next = supplier_next_page($html, $next);
    }
    $results = supplier_import_rows($db, $parsed, $url, parse_url($url, PHP_URL_HOST) ?: '', $markup);
}
$drafts = $db->fetchAll("SELECT id, name_en, price, supplier_name, supplier_sku FROM products WHERE listing_status = 'draft' ORDER BY id DESC LIMIT 100");
$page_title = 'Supplier import';
require __DIR__ . '/header.php';
?>
<div class="container-fluid">
    <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <div class="card card-body mb-3">
        <h2>CSV import</h2>
        <p><a href="?template=1">Download the CSV template</a>. Required columns are product_name and supplier_cost. Category and brand must already exist. The same supplier and supplier SKU is skipped. Nothing is published until you publish it below.</p>
        <form method="post" enctype="multipart/form-data" class="row g-2">
            <input type="hidden" name="import_csv" value="1">
            <div class="col-md-4"><input class="form-control" type="file" name="csv" accept=".csv" required></div>
            <div class="col-md-2"><input class="form-control" name="markup" value="80" placeholder="Markup %"></div>
            <div class="col-md-3"><input class="form-control" name="supplier" placeholder="Default supplier"></div>
            <div class="col-md-3"><input class="form-control" name="supplier_url" placeholder="Default supplier URL"></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Import CSV</button></div>
        </form>
    </div>
    <div class="card card-body mb-3">
        <h2>Import from a link</h2>
        <p>Auto treats DubGift, Krmeen, and Saleto prices as the selling price. Every other site is supplier cost, and the markup sets the selling price. Up to 100 products and 8 pages.</p>
        <form method="post" class="row g-2">
            <input type="hidden" name="import_link" value="1">
            <div class="col-md-5"><input class="form-control" name="catalog_url" placeholder="https://" required></div>
            <div class="col-md-2"><input class="form-control" name="max_products" value="100"></div>
            <div class="col-md-2"><input class="form-control" name="markup" value="80"></div>
            <div class="col-md-2">
                <select class="form-select" name="price_role">
                    <option value="auto">Auto</option>
                    <option value="selling">Selling price</option>
                    <option value="cost">Supplier cost</option>
                </select>
            </div>
            <div class="col-md-1"><button class="btn btn-primary" type="submit">Import</button></div>
        </form>
    </div>
    <?php if ($results): ?>
    <div class="card card-body mb-3"><h2>This run</h2><ul><?php foreach ($results as $result): ?><li><?= htmlspecialchars(($result['status'] ?? '') . ' ' . ($result['name'] ?? '') . ' ' . ($result['reason'] ?? '')) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="card card-body">
        <h2>Drafts</h2>
        <table class="table"><thead><tr><th></th><th>Name</th><th>Price</th><th>Supplier</th></tr></thead><tbody>
        <?php foreach ($drafts as $draft): ?>
            <tr><td><input type="checkbox" name="selected_ids[]" value="<?= (int)$draft['id'] ?>"></td><td><?= htmlspecialchars($draft['name_en']) ?></td><td><?= number_format((float)$draft['price'], 2) ?></td><td><?= htmlspecialchars((string)$draft['supplier_name']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <button class="btn btn-success" name="draft_action" value="publish" type="submit">Publish selected</button>
        <button class="btn btn-outline-danger" name="draft_action" value="discard" type="submit">Discard selected</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php';
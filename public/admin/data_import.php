<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/includes/Database.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/store_features.php';
require_admin_login();
$db = new Database();
if (!admin_is_super($db)) {
    http_response_code(403);
    echo 'Only a super admin can import catalog data.';
    exit;
}
$entities = [
    'categories' => 'categories',
    'brands' => 'brands',
    'products' => 'products',
    'product_images' => 'product_images',
    'variations' => 'product_variations',
];
$report = [];
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $source = new PDO(
            'mysql:host=' . $_POST['host'] . ';port=' . (int)($_POST['port'] ?: 3306) . ';dbname=' . $_POST['database'] . ';charset=utf8mb4',
            $_POST['user'] ?? '',
            $_POST['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $chosen = array_intersect(array_keys($entities), (array)($_POST['entities'] ?? []));
        foreach ($chosen as $key) {
            $table = $entities[$key];
            $sourceCols = $source->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
            $targetCols = $db->getPdo()->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
            $shared = array_values(array_intersect($sourceCols, $targetCols));
            $report[$table] = [
                'shared' => $shared,
                'source_only' => array_values(array_diff($sourceCols, $targetCols)),
                'target_only' => array_values(array_diff($targetCols, $sourceCols)),
                'source_rows' => (int)$source->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn(),
                'target_rows' => (int)$db->fetch('SELECT COUNT(*) AS c FROM `' . $table . '`')['c'],
            ];
            if (isset($_POST['run']) && $shared) {
                $list = '`' . implode('`,`', $shared) . '`';
                $rows = $source->query('SELECT ' . $list . ' FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
                $copied = 0;
                $stmt = $db->getPdo()->prepare('INSERT IGNORE INTO `' . $table . '` (' . $list . ') VALUES (' . implode(',', array_fill(0, count($shared), '?')) . ')');
                foreach ($rows as $row) {
                    $stmt->execute(array_values($row));
                    $copied += $stmt->rowCount();
                }
                $report[$table]['copied'] = $copied;
            }
        }
        $message = isset($_POST['run']) ? 'Import finished. Existing drafts were not updated.' : 'Analysis finished. Credentials were not stored.';
    } catch (Throwable $e) {
        $message = 'Could not connect: ' . $e->getMessage();
    }
}
$page_title = 'Catalog data import';
require __DIR__ . '/header.php';
?>
<div class="container-fluid">
    <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <div class="row g-2">
            <div class="col-md-3"><input class="form-control" name="host" placeholder="Host" required value="<?= htmlspecialchars($_POST['host'] ?? '') ?>"></div>
            <div class="col-md-1"><input class="form-control" name="port" placeholder="3306" value="<?= htmlspecialchars($_POST['port'] ?? '3306') ?>"></div>
            <div class="col-md-2"><input class="form-control" name="database" placeholder="Database" required></div>
            <div class="col-md-2"><input class="form-control" name="user" placeholder="User" required></div>
            <div class="col-md-2"><input class="form-control" type="password" name="password" placeholder="Password"></div>
        </div>
        <div class="my-3">
            <?php foreach ($entities as $key => $table): ?>
                <label class="me-3"><input type="checkbox" name="entities[]" value="<?= $key ?>"> <?= $table ?></label>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-outline-primary" name="analyze" value="1" type="submit">Analyze</button>
        <button class="btn btn-primary" name="run" value="1" type="submit">Run import</button>
    </form>
    <?php foreach ($report as $table => $info): ?>
        <h2 class="mt-4"><?= htmlspecialchars($table) ?></h2>
        <p>Source rows <?= (int)$info['source_rows'] ?> · target rows <?= (int)$info['target_rows'] ?><?php if (isset($info['copied'])): ?> · copied <?= (int)$info['copied'] ?><?php endif; ?></p>
        <p>Shared: <?= htmlspecialchars(implode(', ', $info['shared'])) ?></p>
        <p>Source only: <?= htmlspecialchars(implode(', ', $info['source_only'])) ?></p>
        <p>Target only: <?= htmlspecialchars(implode(', ', $info['target_only'])) ?></p>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
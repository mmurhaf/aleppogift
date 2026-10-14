<?php
/**
 * One-shot production migration runner, uploaded by scripts/deploy_recent.py --migrate
 * under a random file name with a random token, called once, then deleted.
 * Steps: schema migration, RU/SP names (after table backups), translations sync.
 */

$expectedToken = '__AG_MIGRATE_TOKEN__';
if ($expectedToken === '__AG_MIGRATE_' . 'TOKEN__' || !hash_equals($expectedToken, (string)($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit;
}

@set_time_limit(600);
ignore_user_abort(true);
header('Content-Type: text/plain; charset=utf-8');
while (ob_get_level()) {
    ob_end_clean();
}

function out(string $line): void
{
    echo $line, "\n";
    flush();
}

$steps = array_filter(explode(',', (string)($_GET['steps'] ?? 'features,names,translations')));
$failed = false;

try {
    define('AG_MIGRATION_WEB', true);
    require_once __DIR__ . '/includes/bootstrap.php';
    require_once __DIR__ . '/includes/i18n.php';
    $pdo = (new Database())->getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('SET NAMES utf8mb4');

    if (in_array('features', $steps, true)) {
        out('== features: database/apply_additional_features.php');
        ob_start();
        require_once __DIR__ . '/database/apply_additional_features.php';
        $log = apply_additional_features();
        $echoed = trim((string)ob_get_clean());
        if ($echoed !== '') {
            out($echoed);
        }
        foreach ($log as $line) {
            out($line);
        }
    }

    if (in_array('names', $steps, true)) {
        out('== names: database/update_names_ru_sp.sql');
        $suffix = date('Ymd_His');
        foreach (['products', 'categories', 'brands'] as $table) {
            $backup = "bak_{$table}_{$suffix}";
            $pdo->exec("CREATE TABLE `$backup` AS SELECT * FROM `$table`");
            out("backup: $table -> $backup (" . (int)$pdo->query("SELECT COUNT(*) FROM `$backup`")->fetchColumn() . ' rows)');
        }

        $sql = file_get_contents(__DIR__ . '/database/update_names_ru_sp.sql');
        if ($sql === false) {
            throw new RuntimeException('database/update_names_ru_sp.sql not found on server');
        }
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
        $lines = array_filter(preg_split('/\R/', $sql), function ($line) {
            return strpos(ltrim($line), '--') !== 0;
        });
        $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', implode("\n", $lines))));
        $count = 0;
        foreach ($statements as $statement) {
            $pdo->exec($statement);
            $count++;
        }
        out("executed $count statements");
    }

    if (in_array('translations', $steps, true)) {
        out('== translations: includes/lang -> translations table');
        $pdo->exec(file_get_contents(__DIR__ . '/database/create_translations_table.sql'));
        $upsert = $pdo->prepare(
            "INSERT INTO translations (lang_code, tkey, tvalue, source) VALUES (?, ?, ?, 'file')
             ON DUPLICATE KEY UPDATE tvalue = IF(source = 'custom', tvalue, VALUES(tvalue))"
        );
        $english = translation_file_strings('en');
        foreach (array_keys(store_languages()) as $lang) {
            $strings = translation_file_strings($lang);
            $pdo->beginTransaction();
            foreach ($strings as $key => $value) {
                $upsert->execute([$lang, (string)$key, (string)$value]);
            }
            $pdo->commit();
            out(sprintf('%s: %d strings synced, %d missing vs English', $lang, count($strings), count(array_diff_key($english, $strings))));
        }
    }
} catch (Throwable $e) {
    $failed = true;
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    out('ERROR: ' . $e->getMessage());
}

out($failed ? 'RESULT: FAILED' : 'RESULT: OK');
@unlink(__FILE__);

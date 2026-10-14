<?php
/**
 * Creates the translations table and mirrors includes/lang/{lang}/*.php into it.
 * Rows marked source = 'custom' are left untouched.
 *
 * Usage: php database/sync_translations.php [--report]
 *   --report  also list keys that exist in English but are missing in each language
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/i18n.php';

$db = new Database();
$pdo = $db->getPdo();
$pdo->exec("SET NAMES utf8mb4");
$pdo->exec(file_get_contents(__DIR__ . '/create_translations_table.sql'));

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
    $missing = array_diff_key($english, $strings);
    echo sprintf("%s: %d strings synced, %d missing vs English\n", $lang, count($strings), count($missing));
    if (in_array('--report', $argv, true) && $lang !== 'en') {
        foreach (array_keys($missing) as $key) {
            echo "    - $key\n";
        }
    }
}

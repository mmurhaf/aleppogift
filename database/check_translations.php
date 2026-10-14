<?php
/**
 * Compares includes/lang/{ar,ru}/*.php against English: missing/extra keys and placeholder mismatches.
 * Usage: php database/check_translations.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = __DIR__ . '/../includes/lang';
$load = function (string $lang) use ($base): array {
    $out = [];
    foreach (glob("$base/$lang/*.php") ?: [] as $file) {
        $chunk = include $file;
        foreach ((array)$chunk as $k => $v) {
            if (isset($out[$k])) {
                echo "DUPLICATE $lang: $k (" . basename($file) . ")\n";
            }
            $out[$k] = $v;
        }
    }
    return $out;
};
$placeholders = function (string $s): array {
    preg_match_all('/\{\w+\}/', $s, $m);
    sort($m[0]);
    return $m[0];
};

$en = $load('en');
$problems = 0;
foreach (['ar', 'ru'] as $lang) {
    $other = $load($lang);
    foreach (array_diff_key($en, $other) as $k => $_) {
        echo "MISSING $lang: $k\n";
        $problems++;
    }
    foreach (array_diff_key($other, $en) as $k => $_) {
        echo "EXTRA $lang: $k\n";
        $problems++;
    }
    foreach (array_intersect_key($other, $en) as $k => $v) {
        if ($placeholders((string)$v) !== $placeholders((string)$en[$k])) {
            echo "PLACEHOLDER $lang: $k\n";
            $problems++;
        }
    }
}
echo count($en) . " English keys, $problems problems\n";
exit($problems ? 1 : 0);

<?php
// OPcache reset - delete this file after use
$file = realpath(__DIR__ . '/admin/merge-products.php');
$resetAll = opcache_reset();
$invalidate = $file ? opcache_invalidate($file, true) : false;
echo json_encode([
    'opcache_reset' => $resetAll,
    'file_invalidated' => $invalidate,
    'file_path' => $file,
    'opcache_enabled' => function_exists('opcache_get_status') ? (opcache_get_status(false)['opcache_enabled'] ?? false) : 'function_missing',
]);

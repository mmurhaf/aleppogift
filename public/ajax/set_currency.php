<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$code = strtoupper(trim((string)($_GET['code'] ?? $_POST['code'] ?? '')));
$return = (string)($_GET['return'] ?? $_POST['return'] ?? '');

if (!remember_store_currency($code)) {
    $code = 'AED';
    remember_store_currency($code);
}

header('Location: ' . currency_safe_return($return));
exit;

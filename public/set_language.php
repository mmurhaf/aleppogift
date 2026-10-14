<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$languages = function_exists('store_languages') ? store_languages() : ['en' => [], 'ar' => []];
$requested = (string)($_GET['lang'] ?? 'en');
$_SESSION['store_lang'] = isset($languages[$requested]) ? $requested : 'en';
$back = $_SERVER['HTTP_REFERER'] ?? store_url();
$host = $_SERVER['HTTP_HOST'] ?? '';
$refHost = parse_url($back, PHP_URL_HOST);
if ($refHost && $host && strcasecmp($refHost, $host) !== 0) {
    $back = store_url();
}
header('Location: ' . $back);
exit;
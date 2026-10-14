<?php
http_response_code(403);
header('Content-Type: text/html; charset=UTF-8');

$originalUri = $_SERVER['REDIRECT_URL'] ?? ($_SERVER['REQUEST_URI'] ?? 'unknown');
$queryString = $_SERVER['REDIRECT_QUERY_STRING'] ?? ($_SERVER['QUERY_STRING'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

$reason = 'unknown_rule';
if (preg_match('#^/(logs|config|\.git)(/|$)#i', $originalUri)) {
    $reason = 'protected_directory';
} elseif (preg_match('/\.(log|sql|env|ini|bak)$/i', $originalUri)) {
    $reason = 'protected_file_extension';
} elseif (strpos($originalUri, '/public/ajax/') === 0 && strtoupper($method) === 'OPTIONS') {
    $reason = 'cors_preflight_rule';
}

$logEntry = [
    'timestamp' => date('c'),
    'event' => 'http_403_forbidden',
    'reason' => $reason,
    'request' => [
        'host' => $host,
        'method' => $method,
        'uri' => $originalUri,
        'query' => $queryString,
        'status' => $_SERVER['REDIRECT_STATUS'] ?? '403'
    ],
    'client' => [
        'ip' => $ip,
        'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        'user_agent' => $userAgent,
        'referrer' => $referrer
    ]
];

$logFile = __DIR__ . '/../logs/forbidden_403.log';
@error_log(json_encode($logEntry, JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $logFile);
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = t('pages.403.meta_title');
$pageKicker = '403';
$pageHeading = t('pages.403.heading');
$pageLead = t('pages.403.lead');
require __DIR__ . '/../includes/visitor_open.php';
?>
<article class="prose-panel" style="text-align:center;">
    <p><?= te('pages.403.body') ?></p>
    <a class="btn btn-primary" href="<?= htmlspecialchars(store_url()) ?>"><?= te('pages.404.back_shop') ?></a>
</article>
<?php require __DIR__ . '/../includes/visitor_close.php'; ?>

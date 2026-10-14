<?php
http_response_code(404);
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = t('pages.404.meta_title');
$pageKicker = '404';
$pageHeading = t('pages.404.heading');
$pageLead = t('pages.404.lead');
require __DIR__ . '/../includes/visitor_open.php';
?>
<article class="prose-panel" style="text-align:center;">
    <p class="page-kicker">404</p>
    <p><?= te('pages.404.body') ?></p>
    <a class="btn btn-primary" href="<?= htmlspecialchars(store_url()) ?>"><?= te('pages.404.back_shop') ?></a>
</article>
<?php require __DIR__ . '/../includes/visitor_close.php'; ?>

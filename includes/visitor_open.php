<?php
$pageTitle = $pageTitle ?? 'AleppoGift';
$pageKicker = $pageKicker ?? 'AleppoGift';
$pageHeading = $pageHeading ?? $pageTitle;
$pageLead = $pageLead ?? '';
$pageDescription = $pageDescription ?? $pageLead;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('store_lang_html') ? store_lang_html() : 'en') ?>" dir="<?= function_exists('store_lang_dir') ? store_lang_dir() : 'ltr' ?>">
<head>
    <?php
    $googleTag = __DIR__ . '/google_tag.php';
    if (is_file($googleTag)) {
        require_once $googleTag;
    }
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php if ($pageDescription !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php endif; ?>
    <?php require __DIR__ . '/storefront_assets.php'; ?>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>
<main class="container store-main my-4">
    <header class="page-hero">
        <p class="page-kicker"><?= htmlspecialchars($pageKicker) ?></p>
        <h1><?= htmlspecialchars($pageHeading) ?></h1>
        <?php if ($pageLead !== ''): ?>
        <p class="page-lead"><?= htmlspecialchars($pageLead) ?></p>
        <?php endif; ?>
    </header>

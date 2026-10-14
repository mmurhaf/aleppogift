<?php
if (!function_exists('store_url')) {
    require_once __DIR__ . '/functions.php';
}
$storefrontAssetVersion = '20261014a';
?>
<link rel="icon" href="<?= htmlspecialchars(store_url('assets/images/favicon.ico')) ?>" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/main.css')) ?>?v=<?= $storefrontAssetVersion ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/header-fixes.css')) ?>?v=<?= $storefrontAssetVersion ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/footer.css')) ?>?v=<?= $storefrontAssetVersion ?>">

<?php
// Set proper headers and output buffering
header('Content-Type: text/html; charset=UTF-8');
ob_start();

// Use the secure bootstrap instead of direct config loading
require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('pages.about.meta_title') ?></title>
    <meta name="description" content="<?= te('pages.about.meta_description') ?>">
    <meta name="keywords" content="AleppoGift about, tableware UAE, plates and glasses Dubai, coffee cups UAE, trays and vases Dubai, blankets and pillows UAE">
    <?= seo_lang_links('https://aleppogift.com/about.php') ?>
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>

</head>
<body>

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <main class="container store-main my-4">
        <header class="page-hero">
            <p class="page-kicker"><?= te('pages.about.kicker') ?></p>
            <h1><?= te('pages.about.heading') ?></h1>
            <p class="page-lead"><?= te('pages.about.lead') ?></p>
        </header>

        <div class="about-section">
            <h2 class="mb-4"><?= te('pages.about.about_heading') ?></h2>
            <p><?= te('pages.about.intro_p1') ?></p>

            <p><?= te('pages.about.intro_p2') ?></p>
        </div>

        <div class="alert alert-warning" role="alert">
            <strong><?= te('pages.about.important_label') ?></strong> <?= te('pages.about.important_text') ?>
        </div>
        
        <div class="about-section">
            <h2 class="mb-4"><?= te('pages.about.mission_heading') ?></h2>
            
            <div class="mission-values">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <h3><?= te('pages.about.value1_title') ?></h3>
                    <p><?= te('pages.about.value1_text') ?></p>
                </div>
                
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3><?= te('pages.about.value2_title') ?></h3>
                    <p><?= te('pages.about.value2_text') ?></p>
                </div>
                
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3><?= te('pages.about.value3_title') ?></h3>
                    <p><?= te('pages.about.value3_text') ?></p>
                </div>
            </div>
        </div>
        
        <div class="about-section">
            <h2 class="mb-4"><?= te('pages.about.products_heading') ?></h2>
            <p><?= te('pages.about.products_intro') ?></p>
            <ul>
                <li><?= te('pages.about.products_li1') ?></li>
                <li><?= te('pages.about.products_li2') ?></li>
                <li><?= te('pages.about.products_li3') ?></li>
                <li><?= te('pages.about.products_li4') ?></li>
                <li><?= te('pages.about.products_li5') ?></li>
            </ul>
            
            <p><?= te('pages.about.products_help') ?></p>
        </div>
        
        <div class="about-section team-section">
            <h2 class="mb-4"><?= te('pages.about.team_heading') ?></h2>
            
            <div class="row">
                <div class="col-md-4 team-member">
                    <div class="team-avatar" aria-hidden="true">MM</div>
                    <h3>Mohammed Murhaf</h3>
                    <p><?= te('pages.about.role_ceo') ?></p>
                </div>
                
                <div class="col-md-4 team-member">
                    <div class="team-avatar" aria-hidden="true">MY</div>
                    <h3>Mohammed Yassen</h3>
                    <p><?= te('pages.about.role_product') ?></p>
                </div>
                
                <div class="col-md-4 team-member">
                    <div class="team-avatar" aria-hidden="true">AA</div>
                    <h3>Abdulkarim Abdulkarim</h3>
                    <p><?= te('pages.about.role_relations') ?></p>
                </div>
            </div>
        </div>
    </main>

    <?php require_once(__DIR__ . '/../includes/footer.php'); ?> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/main.js?v=20261016qty"></script>
	<script src="assets/js/enhanced-main.js?v=20261012i18n"></script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
<?php
require_once 'Database.php';

$db = new Database();
$pdo = $db->getPdo();

try {
    $stmt = $pdo->prepare("SELECT id, name_en, name_ar, name_ru, name_sp, logo AS picture FROM brands WHERE logo IS NOT NULL AND logo <> '' ORDER BY RAND()");
    $stmt->execute();
    $showcaseBrands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("brand display error: " . $e->getMessage());
    $showcaseBrands = [];
}
?>

<div class="brand-showcase">
    <header class="tg-section-head is-row">
        <h2 class="section-heading"><?= te('theme.top_brands') ?></h2>
        <a class="tg-link" href="<?= htmlspecialchars(store_url('products.php')) ?>"><?= te('theme.view_all') ?></a>
    </header>

    <?php if (!empty($showcaseBrands)): ?>
        <div class="tg-carousel" data-tg-carousel>
            <button type="button" class="tg-carousel-btn is-prev" aria-label="<?= te('catalog.previous') ?>" data-tg-prev><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <div class="tg-carousel-track brand-grid" data-tg-track>
                <?php foreach ($showcaseBrands as $brand): ?>
                    <?php
                        $logoPath = store_url(brand_logo_web_path($brand['picture'] ?? ''));
                        $fallbackLogo = htmlspecialchars(store_url('assets/images/no-image.png'), ENT_QUOTES, 'UTF-8');
                    ?>
                    <a class="brand-card brand-link" href="<?= htmlspecialchars(store_url('products.php?brand=' . (int)$brand['id'])) ?>">
                        <span class="brand-image-container">
                            <img src="<?= htmlspecialchars($logoPath) ?>"
                                 alt="<?= htmlspecialchars(loc_field($brand, 'name')) ?>"
                                 class="brand-image"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='<?= $fallbackLogo ?>'">
                        </span>
                        <span class="brand-name"><?= htmlspecialchars(loc_field($brand, 'name')) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="tg-carousel-btn is-next" aria-label="<?= te('catalog.next') ?>" data-tg-next><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
    <?php else: ?>
        <p class="no-brands"><?= te('catalog.brands_soon') ?></p>
    <?php endif; ?>
</div>

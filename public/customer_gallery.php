<?php
/**
 * Customer Photos Gallery - Success Stories
 * Showcase customer photos, delivery confirmations, and product usage
 */

require_once(__DIR__ . '/../includes/bootstrap.php');

$db = null;
$conn = null;
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Throwable $e) {
    $conn = null;
}

$allowedFilters = ['all', 'product', 'delivery', 'unboxing', 'inuse'];
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$photos = [];
$counts = ['all' => 0, 'product' => 0, 'delivery' => 0, 'unboxing' => 0, 'inuse' => 0];

try {
    if (!$conn) {
        throw new RuntimeException('Gallery is unavailable.');
    }
    $sql = "SELECT cp.*, p.name_en as product_name
            FROM customer_photos cp
            LEFT JOIN products p ON cp.product_id = p.id
            WHERE cp.is_approved = 1";
    $params = [];
    if ($filter !== 'all') {
        $sql .= " AND cp.photo_type = ?";
        $params[] = $filter;
    }
    $sql .= " ORDER BY cp.is_featured DESC, cp.created_at DESC LIMIT 50";
    $photoStmt = $conn->prepare($sql);
    $photoStmt->execute($params);
    $photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

    $counts['all'] = (int)$conn->query("SELECT COUNT(*) FROM customer_photos WHERE is_approved = 1")->fetchColumn();
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM customer_photos WHERE is_approved = 1 AND photo_type = ?");
    foreach (['product', 'delivery', 'unboxing', 'inuse'] as $photoType) {
        $countStmt->execute([$photoType]);
        $counts[$photoType] = (int)$countStmt->fetchColumn();
    }
} catch (Throwable $e) {
    $photos = [];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('pages.gallery.meta_title') ?></title>
    <meta name="description" content="<?= te('pages.gallery.meta_description') ?>">
    
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    
    <style>
        .hero-gallery {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 3rem;
        }
        
        .filter-tabs {
            background: white;
            border-radius: 50px;
            padding: 0.5rem;
            display: inline-flex;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
        }
        
        .filter-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .filter-btn:hover {
            background: #f8f9fa;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .filter-btn .badge {
            margin-left: 0.5rem;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            background: white;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        
        .gallery-item.featured {
            grid-column: span 2;
            grid-row: span 2;
        }
        
        .gallery-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            cursor: pointer;
        }
        
        .gallery-item.featured .gallery-image {
            aspect-ratio: 16/9;
        }
        
        .gallery-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 1.5rem;
            color: white;
            transform: translateY(100%);
            transition: transform 0.3s;
        }
        
        .gallery-item:hover .gallery-overlay {
            transform: translateY(0);
        }
        
        .featured-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #ffc107;
            color: #000;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            z-index: 1;
        }
        
        .likes-count {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(255,255,255,0.9);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        
        .lightbox.active {
            display: flex;
        }
        
        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .lightbox-close {
            position: absolute;
            top: 2rem;
            right: 2rem;
            font-size: 2rem;
            color: white;
            cursor: pointer;
            background: rgba(255,255,255,0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <?php require_once(__DIR__ . '/../includes/header.php'); ?>

    <main class="container store-main my-4">
        <header class="page-hero">
            <p class="page-kicker"><?= te('pages.gallery.kicker') ?></p>
            <h1><?= te('pages.gallery.heading') ?></h1>
            <p class="page-lead"><?= te('pages.gallery.lead') ?></p>
        </header>

    <div class="text-center">
        <div class="filter-tabs">
            <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" onclick="window.location.href='?filter=all'">
                <?= te('pages.gallery.filter_all') ?> <span class="badge bg-secondary"><?= $counts['all'] ?></span>
            </button>
            <button class="filter-btn <?= $filter === 'product' ? 'active' : '' ?>" onclick="window.location.href='?filter=product'">
                <?= te('pages.gallery.filter_product') ?> <span class="badge bg-secondary"><?= $counts['product'] ?></span>
            </button>
            <button class="filter-btn <?= $filter === 'delivery' ? 'active' : '' ?>" onclick="window.location.href='?filter=delivery'">
                <?= te('pages.gallery.filter_delivery') ?> <span class="badge bg-secondary"><?= $counts['delivery'] ?></span>
            </button>
            <button class="filter-btn <?= $filter === 'unboxing' ? 'active' : '' ?>" onclick="window.location.href='?filter=unboxing'">
                <?= te('pages.gallery.filter_unboxing') ?> <span class="badge bg-secondary"><?= $counts['unboxing'] ?></span>
            </button>
            <button class="filter-btn <?= $filter === 'inuse' ? 'active' : '' ?>" onclick="window.location.href='?filter=inuse'">
                <?= te('pages.gallery.filter_inuse') ?> <span class="badge bg-secondary"><?= $counts['inuse'] ?></span>
            </button>
        </div>
    </div>
    
    <!-- Gallery Grid -->
    <div class="container mb-5">
        <?php if (empty($photos)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> <?= te('pages.gallery.empty') ?>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($photos as $photo): ?>
                    <div class="gallery-item <?= $photo['is_featured'] ? 'featured' : '' ?>">
                        <?php if ($photo['is_featured']): ?>
                            <span class="featured-badge">
                                <i class="fas fa-star"></i> <?= te('pages.gallery.featured') ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($photo['likes_count'] > 0): ?>
                            <span class="likes-count">
                                <i class="fas fa-heart text-danger"></i> <?= $photo['likes_count'] ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php
                        $galleryPath = ltrim(str_replace('\\', '/', (string)($photo['photo_path'] ?? '')), '/');
                        $galleryPath = preg_replace('#^(?:\.\./)+#', '', $galleryPath);
                        $galleryPath = preg_replace('#^public/#', '', $galleryPath);
                        ?>
                        <img src="<?= htmlspecialchars(store_url($galleryPath)) ?>" 
                             alt="<?= htmlspecialchars($photo['caption'] ?? t('pages.gallery.photo_alt')) ?>" 
                             class="gallery-image"
                             onclick="openLightbox(this.src)">
                        
                        <div class="gallery-overlay">
                            <?php if ($photo['customer_name']): ?>
                                <p class="mb-1"><strong><?= htmlspecialchars($photo['customer_name']) ?></strong></p>
                            <?php endif; ?>
                            
                            <?php if ($photo['caption']): ?>
                                <p class="mb-2"><?= htmlspecialchars($photo['caption']) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($photo['product_name']): ?>
                                <p class="mb-0">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($photo['product_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Call to Action -->
    <div class="container text-center mb-5">
        <div class="card bg-light border-0 p-5">
            <h3 class="mb-3"><?= te('pages.gallery.share_heading') ?></h3>
            <p class="lead mb-4"><?= te('pages.gallery.share_text') ?></p>
            <div class="d-flex justify-content-center gap-3">
                <a href="https://wa.me/971561125320?text=<?= htmlspecialchars(rawurlencode(t('pages.gallery.whatsapp_message'))) ?>" class="btn btn-success btn-lg" target="_blank">
                    <i class="fab fa-whatsapp"></i> <?= te('pages.gallery.share_whatsapp') ?>
                </a>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> <?= te('pages.gallery.start_shopping') ?>
                </a>
            </div>
        </div>
    </div>
    </main>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <img id="lightboxImage" src="" alt="<?= te('pages.gallery.lightbox_alt') ?>">
    </div>
    
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openLightbox(src) {
            document.getElementById('lightboxImage').src = src;
            document.getElementById('lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    </script>
</body>
</html>

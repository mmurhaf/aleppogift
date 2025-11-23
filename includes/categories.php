<?php
require_once 'Database.php';

$db = new Database();
$pdo = $db->getPdo();

try {
    $stmt = $pdo->prepare("SELECT id, name_en, picture FROM categories WHERE picture IS NOT NULL ORDER BY name_en ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Category display error: " . $e->getMessage());
    $categories = [];
}
?>

<!-- Category Showcase Section -->
<section class="category-showcase py-5">
    <div class="container">
        <h2 class="section-heading text-center mb-5">Shop by Category</h2>
        
        <?php if (!empty($categories)): ?>
            <div class="category-grid">
                <?php foreach ($categories as $cat): ?>
                    <?php
                        $params = ['category' => (int)$cat['id']];
                        $query = http_build_query($params);
                    ?>
                    <div class="category-card">
                        <a href="?<?= htmlspecialchars($query); ?>" class="category-link" aria-label="<?= htmlspecialchars($cat['name_en']); ?>">
                            <div class="category-image-container">
                                <img src="<?= htmlspecialchars($cat['picture']); ?>" 
                                    alt="<?= htmlspecialchars($cat['name_en']); ?>" 
                                    class="category-image"
                                    loading="lazy">
                                <div class="category-overlay"></div>
                                <div class="category-name"><?= htmlspecialchars($cat['name_en']); ?></div>
                                
                            </div>
                            <?= htmlspecialchars($cat['name_en']); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-categories text-center py-4">
                <i class="fas fa-tags fa-3x mb-3 text-muted"></i>
                <p class="text-muted">Categories coming soon</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.category-showcase {
    background-color: #ffffff;
    position: relative;
    overflow: hidden;
}

.section-heading {
    font-weight: 700;
    color: #2c3e50;
    position: relative;
    padding-bottom: 15px;
    font-size: 2rem;
    margin-bottom: 2rem;
}

.section-heading:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: linear-gradient(90deg, #3498db, #9b59b6);
    border-radius: 2px;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 30px;
    padding: 20px;
}

.category-card {
    transition: all 0.3s ease;
    text-align: center;
}

.category-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.category-image-container {
    position: relative;
    width: 180px;
    height: 180px;
    margin: 0 auto;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.category-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.category-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to top, rgba(0,0,0,0.7), rgba(0,0,0,0.1));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.category-name {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 15px;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    text-align: center;
    transform: translateY(20px);
    opacity: 0;
    transition: all 0.3s ease;
}

.category-card:hover {
    transform: translateY(-10px);
}

.category-card:hover .category-image-container {
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
}

.category-card:hover .category-image {
    transform: scale(1.05);
}

.category-card:hover .category-overlay,
.category-card:hover .category-name {
    opacity: 1;
    transform: translateY(0);
}

.no-categories {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    max-width: 600px;
    margin: 0 auto;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .category-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    }
}

@media (max-width: 992px) {
    .category-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 25px;
    }
    
    .category-image-container {
        width: 160px;
        height: 160px;
    }
}

@media (max-width: 768px) {
    .category-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 20px;
    }
    
    .category-image-container {
        width: 140px;
        height: 140px;
    }
    
    .section-heading {
        font-size: 1.7rem;
    }
}

@media (max-width: 576px) {
    .category-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .category-image-container {
        width: 100%;
        max-width: 160px;
        height: 140px;
    }
    
    .category-name {
        font-size: 1rem;
        padding: 10px;
    }
}
</style>
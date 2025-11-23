<?php
require_once 'Database.php';

$db = new Database();
$pdo = $db->getPdo();

try {
$stmt = $pdo->prepare("SELECT id, name_en, logo AS picture FROM brands WHERE logo IS NOT NULL ORDER BY RAND()");
$stmt->execute();
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("brand display error: " . $e->getMessage());
    $brands = [];
}
?>

<!-- brand Showcase Section -->
<section class="brand-showcase py-5">
    <div class="container">
        <h2 class="section-heading text-center mb-5">Shop by brand</h2>
        
        <?php if (!empty($brands)): ?>
            <div class="brand-grid">
                <?php foreach ($brands as $brand): ?>
                    <?php
                        // Prepare query string safely
                        $params = ['brand' => (int)$brand['id']];
                        $query = http_build_query($params);
                    ?>
                    <div class="brand-card">
                        <a href="?<?= htmlspecialchars($query); ?>" class="brand-link" aria-label="<?= htmlspecialchars($brand['name_en']); ?>">
                            <div class="brand-image-container">
                                <?php
                                    // Handle brand logo path - database stores only filename after fix_brand_paths.php
                                    $logoPath = 'public/uploads/brands/' . htmlspecialchars($brand['picture']);
                                ?>
                                <img src="<?= $logoPath; ?>" 
                                    alt="<?= htmlspecialchars($brand['name_en']); ?>" 
                                    class="brand-image"
                                    loading="lazy">
                                    onerror="this.src='assets/images/no-image.png'">
                                <div class="brand-overlay"></div>
                            </div>
                            <!-- Removed the h3.brand-title element here -->
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-brands text-center py-4">
                <i class="fas fa-tags fa-3x mb-3 text-muted"></i>
                <p class="text-muted">brands coming soon</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<style>
.brand-showcase {
    background-color: #f8f9fa;
    position: relative;
    overflow: hidden;
}

.section-heading {
    font-weight: 700;
    color: #2c3e50;
    position: relative;
    padding-bottom: 15px;
    font-size: 2rem;
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

.brand-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 25px;
    padding: 10px;
}

.brand-card {
    transition: transform 0.3s ease;
}

.brand-link {
    text-decoration: none;
    color: inherit;
}

.brand-image-container {
    position: relative;
    width: 80px; /* Reduced from 160px to 80px */
    height: 80px; /* Reduced from 160px to 80px */
    margin: 0 auto;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.brand-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.brand-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(52, 152, 219, 0.8), rgba(155, 89, 182, 0.8));
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 50%;
}

.brand-title {
    text-align: center;
    margin-top: 15px;
    font-weight: 600;
    color: #2c3e50;
    transition: color 0.3s ease;
}

.brand-card:hover {
    transform: translateY(-5px);
}

.brand-card:hover .brand-image {
    transform: scale(1.1);
}

.brand-card:hover .brand-overlay {
    opacity: 1;
}

.brand-card:hover .brand-title {
    color: #3498db;
}

.no-brands {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .brand-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 20px;
    }
    
    .brand-image-container {
        width: 70px; /* Reduced from 140px to 70px */
        height: 70px; /* Reduced from 140px to 70px */
    }
}

@media (max-width: 768px) {
    .brand-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 15px;
    }
    
    .brand-image-container {
        width: 60px; /* Reduced from 120px to 60px */
        height: 60px; /* Reduced from 120px to 60px */
    }
    
    .section-heading {
        font-size: 1.7rem;
    }
}

@media (max-width: 576px) {
    .brand-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .brand-image-container {
        width: 50px; /* Reduced from 100px to 50px */
        height: 50px; /* Reduced from 100px to 50px */
    }
}
</style>
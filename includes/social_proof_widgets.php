<?php
/**
 * Social Proof Widgets Component
 * Displays real-time statistics and social proof elements
 * 
 * Expected context variables:
 * - $db (Database instance) - REQUIRED. Reuses parent page connection
 */

// Ensure database connection exists - create only if not already available
if (!isset($db) || !($db instanceof Database)) {
    $db = new Database();
}

// Resolve the correct order-status column across schema versions.
$order_status_column = 'shipment_status';
if ($db->fetch("SHOW COLUMNS FROM orders LIKE 'shipment_status'")) {
    $order_status_column = 'shipment_status';
} elseif ($db->fetch("SHOW COLUMNS FROM orders LIKE 'order_status'")) {
    $order_status_column = 'order_status';
}

$order_date_column = 'order_date';
if ($db->fetch("SHOW COLUMNS FROM orders LIKE 'created_at'")) {
    $order_date_column = 'created_at';
} elseif ($db->fetch("SHOW COLUMNS FROM orders LIKE 'order_date'")) {
    $order_date_column = 'order_date';
}

$social_proof_cache = dirname(__DIR__) . '/cache/social_proof.json';
$social_proof_ttl = 300;
$live_stats = null;

if (is_file($social_proof_cache) && (time() - filemtime($social_proof_cache)) < $social_proof_ttl) {
    $cached_stats = json_decode((string)file_get_contents($social_proof_cache), true);
    if (is_array($cached_stats) && isset($cached_stats['total_orders'], $cached_stats['happy_customers'], $cached_stats['products_sold'], $cached_stats['countries'])) {
        $live_stats = $cached_stats;
    }
}

if ($live_stats === null) {
    $live_stats = [
        'total_orders' => (int)($db->fetch("SELECT COUNT(*) as count FROM orders WHERE {$order_status_column} IN ('completed', 'delivered')")['count'] ?? 0),
        'happy_customers' => (int)($db->fetch("SELECT COUNT(DISTINCT customer_id) as count FROM orders WHERE {$order_status_column} IN ('completed', 'delivered') AND customer_id IS NOT NULL AND customer_id > 0")['count'] ?? 0),
        'products_sold' => (int)($db->fetch("SELECT COALESCE(SUM(quantity), 0) as total FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE o.{$order_status_column} IN ('completed', 'delivered')")['total'] ?? 0),
        'countries' => (int)($db->fetch("SELECT COUNT(DISTINCT TRIM(country)) as count FROM customers WHERE country IS NOT NULL AND TRIM(country) != ''")['count'] ?? 0),
    ];
    if (!is_dir(dirname($social_proof_cache))) {
        @mkdir(dirname($social_proof_cache), 0755, true);
    }
    @file_put_contents($social_proof_cache, json_encode($live_stats));
}

$stats = $db->fetchAll(
    "SELECT * FROM social_proof_stats
    WHERE is_active = 1
    ORDER BY display_order ASC"
);

if (!$stats) {
    $stats = [
        ['stat_type' => 'total_orders', 'stat_label' => 'Orders Delivered', 'stat_icon' => 'fas fa-box', 'stat_value' => $live_stats['total_orders']],
        ['stat_type' => 'happy_customers', 'stat_label' => 'Happy Customers', 'stat_icon' => 'fas fa-smile', 'stat_value' => $live_stats['happy_customers']],
        ['stat_type' => 'countries', 'stat_label' => 'Countries Served', 'stat_icon' => 'fas fa-globe', 'stat_value' => $live_stats['countries']],
        ['stat_type' => 'products_sold', 'stat_label' => 'Products Sold', 'stat_icon' => 'fas fa-gift', 'stat_value' => $live_stats['products_sold']],
    ];
}


// Get recent orders for live activity (anonymized)
$recent_orders = $db->fetchAll(" 
    SELECT 
        CONCAT(LEFT(COALESCE(c.fullname, 'Customer'), 1), '***') as customer,
        COALESCE(c.city, 'UAE') as city,
        TIMESTAMPDIFF(MINUTE, o.{$order_date_column}, NOW()) as minutes_ago
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.{$order_status_column} IN ('completed', 'delivered', 'processing')
    AND o.{$order_date_column} >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY o.{$order_date_column} DESC 
    LIMIT 5
 ");
?>

<!-- Social Proof Stats Counter -->
<div class="social-proof-stats">
    <div class="container">
        <div class="social-proof-shell">
            <div class="social-proof-heading text-center">
                <p class="social-proof-kicker mb-2"><?= te('product.social_kicker') ?></p>
                <h2 class="social-proof-title mb-3"><?= te('product.social_title') ?></h2>
                <p class="social-proof-subtitle mb-0"><?= te('product.social_subtitle') ?></p>
            </div>

            <div class="row g-3 g-lg-4 justify-content-center">
                <?php foreach ($stats as $stat):
                    $statType = $stat['stat_type'] ?? '';
                    $statValue = array_key_exists($statType, $live_stats)
                        ? (int)$live_stats[$statType]
                        : (int)($stat['stat_value'] ?? 0);
                ?>
                    <div class="col-6 col-md-3">
                        <div class="stat-counter">
                            <div class="stat-icon" aria-hidden="true">
                                <i class="<?= htmlspecialchars($stat['stat_icon']) ?>"></i>
                            </div>
                            <div class="stat-number" data-target="<?= $statValue ?>"><?= number_format($statValue) ?></div>
                            <div class="stat-label"><?= htmlspecialchars(t_or('product.stat.' . $stat['stat_label'], (string)$stat['stat_label'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Live Activity Feed (optional - can be placed in sidebar) -->
<?php if (!empty($recent_orders) && isset($show_live_activity) && $show_live_activity): ?>
<div class="live-activity-widget">
    <div class="live-activity-header">
        <span class="live-indicator"></span>
        <strong><?= te('product.recent_orders') ?></strong>
    </div>
    <div class="live-activity-list">
        <?php foreach ($recent_orders as $order): ?>
            <div class="live-activity-item">
                <i class="fas fa-shopping-bag"></i>
                <div>
                    <?= strtr(te('product.customer_from'), [
                        '{customer}' => '<strong>' . htmlspecialchars($order['customer']) . '</strong>',
                        '{city}' => htmlspecialchars($order['city'] === 'UAE' ? t('product.uae') : $order['city']),
                    ]) ?>
                    <br><small class="text-muted"><?= $order['minutes_ago'] < 60 ? te('product.minutes_ago', ['n' => $order['minutes_ago']]) : te('product.hours_ago', ['n' => floor($order['minutes_ago']/60)]) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
/* ============================================================
   Social Proof Stats — Warm Luxury Gold/Amber Palette
   ============================================================ */
.social-proof-stats {
    /* ---------- design tokens ---------- */
    --sp-gold:          #f5c842;
    --sp-gold-dim:      rgba(245, 200, 66, 0.55);
    --sp-gold-faint:    rgba(245, 200, 66, 0.12);
    --sp-ink:           #fff8ee;
    --sp-muted:         rgba(255, 228, 175, 0.72);
    --sp-card-bg:       rgba(255, 210, 100, 0.07);
    --sp-card-border:   rgba(245, 200, 66, 0.30);
    --sp-shadow-deep:   rgba(12, 5, 0, 0.55);
    --sp-shadow-card:   rgba(20, 8, 0, 0.45);

    position: relative;
    overflow: hidden;
    padding: clamp(2.5rem, 5vw, 4.5rem) 0;
    margin: clamp(2rem, 4vw, 3rem) 0;
    color: var(--sp-ink);

    /* warm mahogany-to-deep-amber base */
    background:
        radial-gradient(ellipse 130% 90% at  8%  5%, rgba(255, 175, 50, 0.22) 0%, transparent 52%),
        radial-gradient(ellipse  90% 80% at 92% 95%, rgba(180,  72,  8, 0.26) 0%, transparent 52%),
        radial-gradient(ellipse  70% 60% at 50% 50%, rgba(120,  40,  0, 0.18) 0%, transparent 65%),
        linear-gradient(148deg,
            #100700  0%,
            #1f0e04 22%,
            #2e1608 46%,
            #3a1c0a 62%,
            #231008 80%,
            #0e0602 100%);
}

/* Shimmer sweep */
.social-proof-stats::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(
        118deg,
        rgba(255, 220, 100, 0.10)  0%,
        transparent                38%,
        transparent                62%,
        rgba(255, 200,  80, 0.08) 100%
    );
    mix-blend-mode: screen;
}

/* Gold dot grid */
.social-proof-stats::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image: radial-gradient(rgba(245, 200, 66, 0.18) 0.9px, transparent 0.9px);
    background-size: 22px 22px;
    opacity: 0.55;
}

.social-proof-shell {
    position: relative;
    z-index: 1;
}

/* ---------- heading ---------- */
.social-proof-heading {
    margin-bottom: clamp(1.25rem, 3vw, 2.5rem);
}

.social-proof-kicker {
    display: inline-block;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--sp-gold);
    padding: 0.2rem 0.9rem;
    border: 1px solid rgba(245, 200, 66, 0.38);
    border-radius: 100px;
    background: rgba(245, 200, 66, 0.08);
    margin-bottom: 0.75rem;
}

.social-proof-title {
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(1.6rem, 2.8vw, 2.25rem);
    font-weight: 700;
    color: var(--sp-ink);
    text-shadow: 0 2px 18px rgba(0, 0, 0, 0.45);
}

.social-proof-subtitle {
    color: var(--sp-muted);
    font-size: clamp(0.88rem, 1.4vw, 0.98rem);
    max-width: 680px;
    margin-inline: auto;
    line-height: 1.65;
}

/* ---------- stat cards ---------- */
.stat-counter {
    position: relative;
    height: 100%;
    padding: clamp(1.1rem, 2vw, 1.6rem) clamp(0.8rem, 1.5vw, 1.2rem);
    border-radius: 20px;
    border: 1px solid var(--sp-card-border);
    background: var(--sp-card-bg);
    box-shadow:
        0 2px  0  rgba(245, 200, 66, 0.22),   /* top gold rim */
        0 18px 36px var(--sp-shadow-card),
        inset 0 1px 0 rgba(255, 230, 140, 0.15);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    text-align: center;
    transition: transform 260ms ease, box-shadow 260ms ease, border-color 260ms ease, background 260ms ease;
    overflow: hidden;
}

/* Subtle inner glow on top edge */
.stat-counter::before {
    content: "";
    position: absolute;
    top: 0; left: 10%; right: 10%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255, 220, 100, 0.55), transparent);
    border-radius: 50%;
}

.stat-counter:hover {
    transform: translateY(-5px);
    background: rgba(255, 210, 100, 0.11);
    border-color: rgba(245, 200, 66, 0.55);
    box-shadow:
        0 3px  0  rgba(245, 200, 66, 0.35),
        0 24px 44px var(--sp-shadow-deep),
        inset 0 1px 0 rgba(255, 230, 140, 0.22);
}

/* ---------- icon circle ---------- */
.stat-icon {
    width: 62px;
    height: 62px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 1.5rem;
    color: #fff8e0;
    border: 1px solid rgba(245, 200, 66, 0.40);
    background: linear-gradient(145deg,
        rgba(245, 195, 60, 0.42),
        rgba(200, 100, 20, 0.50));
    box-shadow:
        inset 0 1px 0 rgba(255, 240, 160, 0.45),
        0 8px 20px rgba(15, 6, 0, 0.40),
        0 0 0 6px rgba(245, 200, 66, 0.07);
}

/* ---------- number ---------- */
.stat-number {
    font-size: clamp(1.85rem, 3.5vw, 2.4rem);
    font-weight: 700;
    line-height: 1.05;
    margin-bottom: 0.5rem;
    font-family: "Playfair Display", Georgia, serif;
    color: #ffffff;
    text-shadow:
        0 0 22px rgba(245, 195, 60, 0.40),
        0 3px 14px rgba(0, 0, 0, 0.30);
    animation: spCountUp 0.65s cubic-bezier(.22,.68,0,1.2) both;
}

/* ---------- label ---------- */
.stat-label {
    font-size: 0.74rem;
    color: rgba(255, 228, 170, 0.88);
    text-transform: uppercase;
    letter-spacing: 0.10em;
    font-weight: 600;
    line-height: 1.4;
}

/* ---------- live activity widget ---------- */
.live-activity-widget {
    background: #fff8ee;
    border-radius: 14px;
    border: 1px solid rgba(200, 140, 40, 0.22);
    box-shadow: 0 4px 16px rgba(12, 5, 0, 0.12);
    overflow: hidden;
    margin: 2rem 0;
}

.live-activity-header {
    background: linear-gradient(90deg, #b8620a, #d4840e);
    color: #fff8ee;
    padding: 0.9rem 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 0.04em;
}

.live-indicator {
    width: 8px;
    height: 8px;
    background: #f5c842;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(245, 200, 66, 0.6);
    animation: spPulse 1.8s ease-in-out infinite;
}

.live-activity-list {
    max-height: 300px;
    overflow-y: auto;
}

.live-activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid rgba(200, 140, 40, 0.12);
    transition: background 0.2s;
}

.live-activity-item:hover  { background: rgba(245, 200, 66, 0.06); }
.live-activity-item i      { font-size: 1.4rem; color: #c47a18; }
.live-activity-item strong { color: #2c1505; }
.live-activity-item small  { color: #7a5030; }

/* ---------- animations ---------- */
@keyframes spCountUp {
    from { opacity: 0; transform: translateY(14px) scale(0.92); }
    to   { opacity: 1; transform: translateY(0)    scale(1);    }
}

@keyframes spPulse {
    0%   { box-shadow: 0 0 0 0   rgba(245, 200, 66, 0.70); }
    60%  { box-shadow: 0 0 0 7px rgba(245, 200, 66, 0);    }
    100% { box-shadow: 0 0 0 0   rgba(245, 200, 66, 0);    }
}

/* ---------- responsive ---------- */
@media (max-width: 767.98px) {
    .social-proof-stats    { padding: 2.2rem 0; }
    .social-proof-heading  { margin-bottom: 1.2rem; }

    .stat-counter {
        padding: 1rem 0.75rem;
        border-radius: 16px;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        margin-bottom: 0.75rem;
        font-size: 1.2rem;
    }

    .stat-label { font-size: 0.67rem; }
}
</style>

<script>
// Animated counter for statistics
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stat-number');
    
    const animateCounter = (counter) => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000; // 2 seconds
        const step = target / (duration / 16); // 60fps
        let current = 0;
        
        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.textContent = Math.floor(current).toLocaleString();
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
            }
        };
        
        updateCounter();
    };
    
    // Intersection Observer for triggering animation when visible
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    counters.forEach(counter => observer.observe(counter));
});
</script>

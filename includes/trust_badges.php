<?php
/**
 * Trust Badges Display Component
 * Shows security and trust badges based on location
 * Usage: include with $location parameter ('checkout', 'product', 'footer', 'all')
 * 
 * Expected context variables:
 * - $db (Database instance) - REQUIRED. Reuses parent page connection
 * - $location (string) - Optional. Defaults to 'all'
 */

if (!isset($location)) {
    $location = 'all';
}

// Ensure database connection exists - create only if not already available
if (!isset($db) || !($db instanceof Database)) {
    $db = new Database();
}

$badges = $db->fetchAll(
        "SELECT * FROM trust_badges
        WHERE is_active = 1
            AND (display_location = :location OR display_location = 'all')
        ORDER BY display_order ASC",
        ['location' => $location]
);

if (empty($badges)) {
    return;
}
?>

<div class="trust-badges">
    <?php foreach ($badges as $badge): ?>
        <?php
            $badgeName = preg_replace('/\bziina\b/i', 'Card', (string)$badge['badge_name']);
            $badgeDescription = preg_replace('/\s+via\s+ziina\b/i', '', (string)$badge['badge_description']);
            $badgeDescription = preg_replace('/\bziina\b/i', 'card', $badgeDescription);
            $badgeName = t_or('product.badge.' . $badgeName, $badgeName);
            $badgeDescription = t_or('product.badge_desc.' . $badgeDescription, $badgeDescription);
        ?>
        <div class="trust-badge">
            <div class="trust-badge-icon <?= $badge['badge_type'] ?>">
                <i class="<?= htmlspecialchars($badge['badge_icon']) ?>"></i>
            </div>
            <div class="trust-badge-content">
                <h6><?= htmlspecialchars($badgeName) ?></h6>
                <p><?= htmlspecialchars($badgeDescription) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
    .trust-badges {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin: 2rem 0;
    }
    
    .trust-badge {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: white;
        padding: 1rem;
        border-radius: 10px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .trust-badge:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        transform: translateY(-2px);
    }
    
    .trust-badge-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .trust-badge-icon.security {
        background: #d4edda;
        color: #28a745;
    }
    
    .trust-badge-icon.payment {
        background: #cfe2ff;
        color: #0d6efd;
    }
    
    .trust-badge-icon.shipping {
        background: #fff3cd;
        color: #fd7e14;
    }
    
    .trust-badge-icon.guarantee {
        background: #f8d7da;
        color: #dc3545;
    }
    
    .trust-badge-icon.certification {
        background: #e7e7ff;
        color: #667eea;
    }
    
    .trust-badge-content h6 {
        margin: 0 0 0.25rem 0;
        font-weight: 600;
        font-size: 0.9rem;
        color: #212529;
    }
    
    .trust-badge-content p {
        margin: 0;
        font-size: 0.75rem;
        color: #6c757d;
        line-height: 1.3;
    }
    
    @media (max-width: 768px) {
        .trust-badges {
            grid-template-columns: 1fr;
        }
    }
</style>

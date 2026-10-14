<?php
session_start();
require_once(__DIR__ . '/../includes/shipping.php');
require_once(__DIR__ . '/../includes/currencies.php');
require_once(__DIR__ . '/../includes/i18n.php');
$shippingCost = null;
$error = null;

$usdRate = currency_rate('USD') ?: 0.272294;

$lang = store_lang();
$rateLabel = currency_rates_label($lang === 'ar' ? 'ar' : 'en');
$usdNoteRate = number_format($usdRate, 4);

$selectedCountry = $_POST['country'] ?? '';
$city = $_POST['city'] ?? '';
$weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$selectedCountry || $weight <= 0) {
        $error = t('shippingpage.error_invalid');
    } else {
        $shippingCost = calculateShippingCost($selectedCountry, $city, $weight);
    }
}

// Load countries from the same source as checkout page
require_once(__DIR__ . '/ajax/countries.php');

$countryOptions = '';
foreach ($countries as $name => $flag) {
    $selected = $selectedCountry === $name ? 'selected' : '';
    $countryOptions .= "<option value=\"" . htmlspecialchars($name) . "\" $selected>$flag " . htmlspecialchars($name) . "</option>\n";
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <title><?= te('shippingpage.title') ?></title>
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>
    <noscript>
        <style>
            /* Fallback styles if Bootstrap fails to load */
            .container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
            .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }
            .col-lg-6 { flex: 0 0 50%; max-width: 50%; padding: 0 15px; }
            .card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1rem; }
            .card-header { padding: 0.75rem 1.25rem; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
            .card-body { padding: 1.25rem; }
            .form-select, .form-control { width: 100%; padding: 0.375rem 0.75rem; border: 1px solid #ced4da; border-radius: 0.375rem; }
            .btn { padding: 0.375rem 0.75rem; border: 1px solid transparent; border-radius: 0.375rem; text-decoration: none; display: inline-block; }
            .btn-primary { background-color: #8B0000; border-color: #8B0000; color: white; }
            .table { width: 100%; border-collapse: collapse; }
            .table th, .table td { padding: 0.75rem; border-top: 1px solid #dee2e6; }
        </style>
    </noscript>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= te('shippingpage.meta_description') ?>">
    <meta name="keywords" content="<?= te('shippingpage.meta_keywords') ?>">
    <meta name="author" content="Aleppo Gift">
    <meta name="robots" content="index, follow">
    <?= seo_lang_links('https://aleppogift.com/shipping.php') ?>
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= te('shippingpage.title') ?>">
    <meta property="og:description" content="<?= te('shippingpage.og_description') ?>">
    <meta property="og:image" content="https://aleppogift.com/assets/images/og-image.jpg">
    <meta property="og:url" content="<?= htmlspecialchars(seo_canonical_url('https://aleppogift.com/shipping.php')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aleppo Gift">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= te('shippingpage.title') ?>">
    <meta name="twitter:description" content="<?= te('shippingpage.twitter_description') ?>">
    <meta name="twitter:image" content="https://aleppogift.com/assets/images/og-image.jpg">
    
    <!-- Structured Data for Service -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "name": "Shipping Calculator",
      "description": "Calculate shipping costs for international delivery",
      "provider": {
        "@type": "Organization",
        "name": "Aleppo Gift",
        "url": "https://aleppogift.com"
      },
      "areaServed": [
        {
          "@type": "Country",
          "name": "United Arab Emirates"
        },
        {
          "@type": "Place",
          "name": "GCC Countries"
        },
        {
          "@type": "Place",
          "name": "Worldwide"
        }
      ],
      "serviceType": "International Shipping"
    }
    </script>
    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(212, 132, 14, 0.08);
        }
        .alert-success {
            border-left: 4px solid #d4840e;
        }
        
        /* RTL Support */
        html[dir="rtl"] {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        html[dir="rtl"] .alert-success {
            border-left: none;
            border-right: 4px solid #d4840e;
        }
        html[dir="rtl"] .me-2 {
            margin-left: 0.5rem !important;
            margin-right: 0 !important;
        }
        html[dir="rtl"] .me-1 {
            margin-left: 0.25rem !important;
            margin-right: 0 !important;
        }
        html[dir="rtl"] .ps-4 {
            padding-right: 1.5rem !important;
            padding-left: 0 !important;
        }
        
        /* Currency Table Responsive Improvements */
        @media (max-width: 767.98px) {
            .table th, .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.875rem;
            }
            .table th.ps-4, .table td.ps-4 {
                padding-left: 0.5rem !important;
            }
            .card-header h3 {
                font-size: 1rem;
            }
            .card-header small {
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 575.98px) {
            .table th, .table td {
                padding: 0.4rem 0.2rem;
                font-size: 0.8rem;
            }
            .font-monospace {
                font-size: 0.75rem !important;
            }
        }
        
        /* Ensure table doesn't overflow */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .bg-info2 {
    background-color: #d4840e;
}
        
        /* Prevent header flickering/blinking */
        .modern-header, 
        .navbar {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
            animation: none !important;
            transition: none !important;
        }
        
        header {
            opacity: 1 !important;
            visibility: visible !important;
        }
    </style>
</head>
<body class="shipping-page">

<?php require_once(__DIR__ . '/../includes/header.php'); ?>

<main class="container store-main my-4">
    <header class="page-hero">
        <p class="page-kicker"><?= te('shippingpage.kicker') ?></p>
        <h1><?= te('shippingpage.page_title') ?></h1>
        <p class="page-lead"><?= te('shippingpage.page_subtitle') ?></p>
        <div class="page-hero-tools">
            <div class="btn-group" role="group" aria-label="<?= te('shippingpage.language_switcher') ?>">
                <?php foreach (store_languages() as $langCode => $langMeta): ?>
                <a href="<?= htmlspecialchars(lang_switch_url($langCode)) ?>" hreflang="<?= htmlspecialchars($langMeta['html']) ?>" lang="<?= htmlspecialchars($langMeta['html']) ?>"
                   class="btn <?= $lang === $langCode ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm"><?= htmlspecialchars($langMeta['label']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </header>

    <div class="row g-4">
        <!-- Calculator Form Column -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-primary">
                <div class="card-header text-prime py-3">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-calculator me-2"></i> <?= te('shippingpage.estimate_shipping') ?>
                    </h3>
                </div>
                <div class="card-body p-4">
                    <form method="post" id="shippingForm">
                        <div class="mb-4">
                            <label for="country" class="form-label fw-bold"><?= te('shippingpage.country_label') ?></label>
                            <select id="country" name="country" class="form-select form-select-lg" required>
                                <option value=""><?= te('shippingpage.select_country') ?></option>
                                <?= $countryOptions ?>
                            </select>
                            <?php if (empty($countryOptions)): ?>
                            <div class="alert alert-warning mt-2">
                                <small><i class="fas fa-exclamation-triangle me-1"></i> 
                                <?= te('shippingpage.countries_not_loaded') ?></small>
                            </div>
                            <?php else: ?>
                            <small class="text-success">
                                <i class="fas fa-check-circle me-1"></i> 
                                <?= te('shippingpage.countries_loaded', ['count' => substr_count($countryOptions, '<option')]) ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <label for="city" class="form-label fw-bold"><?= te('shippingpage.city_label') ?></label>
                            <input type="text" id="city" name="city" class="form-control form-control-lg" 
                                   placeholder="<?= te('shippingpage.city_placeholder') ?>" value="<?= htmlspecialchars($city) ?>">
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i> <?= te('shippingpage.city_info') ?>
                            </small>
                        </div>

                        <div class="mb-4">
                            <label for="weight" class="form-label fw-bold"><?= te('shippingpage.weight_label') ?></label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0.1" id="weight" name="weight" 
                                       class="form-control form-control-lg" required 
                                       placeholder="<?= te('shippingpage.weight_placeholder') ?>"
                                       value="<?= htmlspecialchars($weight) ?>">
                                <span class="input-group-text"><?= te('shippingpage.kg') ?></span>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i> <?= te('shippingpage.weight_info') ?>
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3">
                            <i class="fas fa-calculator me-2"></i> <?= te('shippingpage.calculate_btn') ?>
                        </button>
                    </form>

                    <?php if ($error): ?>
                        <div class="alert alert-danger mt-4 p-3 rounded">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php elseif ($shippingCost !== null): ?>
                        <div class="alert alert-success mt-4 p-3 rounded shadow-sm">
                            <div class="row">
                                <div class="col-md-2 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-check-circle fa-3x" style="color: var(--Aleppo-secondary);"></i>
                                </div>
                                <div class="col-md-10">
                                    <h4 class="mb-2"><?= te('shippingpage.shipping_result') ?></h4>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <p class="mb-1 fs-4 fw-bold text-primary">AED <?= number_format($shippingCost, 2) ?></p>
                                        </div>
                                        <div class="col-sm-6">
                                            <?php
                                            $quoteCurrency = selected_store_currency();
                                            if ($quoteCurrency['code'] === 'AED') {
                                                $quoteCurrency = currency_by_code('USD');
                                            }
                                            $quoteAmount = $shippingCost * (float)$quoteCurrency['rate'];
                                            $quoteLabel = t('shippingpage.quote_equivalent', ['code' => $quoteCurrency['code']]);
                                            ?>
                                            <p class="mb-1 fs-5 fw-semibold text-secondary">
                                                <?= htmlspecialchars($quoteCurrency['prefix']) ?><?= number_format($quoteAmount, (int)$quoteCurrency['decimals']) ?>
                                            </p>
                                            <small class="text-muted"><?= htmlspecialchars($quoteLabel) ?></small>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= te('shippingpage.cost_breakdown') ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="checkout.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> <?= te('shippingpage.back_to_shop') ?>
                </a>
            </div>
        </div>
        
        <!-- Rates Table Column -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-primary h-100">
                <div class="card-header text-prime py-3">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-clipboard-list me-2"></i> <?= te('shippingpage.shipping_rates') ?>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4"><?= te('shippingpage.region_country') ?></th>
                                    <th><?= te('shippingpage.base_rate') ?></th>
                                    <th><?= te('shippingpage.additional_rate') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4"><strong><?= te('shippingpage.uae') ?></strong></td>
                                    <td><?= te('shippingpage.uae_base') ?></td>
                                    <td><?= te('shippingpage.uae_additional') ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong><?= te('shippingpage.oman') ?></strong></td>
                                    <td><?= te('shippingpage.oman_base') ?></td>
                                    <td><?= te('shippingpage.oman_additional') ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong><?= te('shippingpage.gcc_countries') ?></strong></td>
                                    <td><?= te('shippingpage.gcc_base') ?></td>
                                    <td><?= te('shippingpage.gcc_additional') ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="ps-4 text-muted">
                                        <small><i class="fas fa-info-circle me-1"></i> <?= te('shippingpage.gcc_note') ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong><?= te('shippingpage.other_countries') ?></strong></td>
                                    <td><?= te('shippingpage.other_base') ?></td>
                                    <td><?= te('shippingpage.other_additional') ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong><?= te('shippingpage.usa_others') ?></strong></td>
                                    <td><?= te('shippingpage.usa_base') ?></td>
                                    <td><?= te('shippingpage.usa_additional') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="mb-3">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <small class="text-muted"><?= te('shippingpage.footer_note') ?></small>
                    </div>
                    <div class="mb-3">
                        <i class="fas fa-dollar-sign text-success me-2"></i>
                        <small class="text-muted"><?= te('shippingpage.usd_note', ['rate' => $usdNoteRate]) ?></small>
                    </div>
                    
                    <!-- Important Notice -->
                    <div class="alert alert-warning alert-sm p-2 mb-3" style="font-size: 0.85rem;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong><?= te('shippingpage.important') ?></strong> <?= te('shippingpage.important_text') ?>
                    </div>
                    
                    <div class="text-muted">
                        <strong><?= te('shippingpage.shipping_examples') ?>:</strong><br>
                        <div class="mt-2" style="font-size: 0.9rem;">
                            <div class="mb-2">
                                <strong>• <?= te('shippingpage.example_qatar') ?></strong> <?= te('shippingpage.example_qatar_parcels') ?><br>
                                <span class="text-primary ms-3"><?= te('shippingpage.parcel_line', ['n' => 1, 'calc' => '120 + (7×30) = 330']) ?></span><br>
                                <span class="text-primary ms-3"><?= te('shippingpage.parcel_line', ['n' => 2, 'calc' => '120 + (7×30) = 330']) ?></span><br>
                                <span class="text-primary ms-3"><?= te('shippingpage.parcel_line', ['n' => 3, 'calc' => '120 + (5×30) = 270']) ?></span><br>
                                <span class="fw-bold text-success ms-3"><?= te('shippingpage.example_total', ['aed' => 930, 'usd' => number_format(930 * $usdRate, 0)]) ?></span>
                            </div>
                            <div class="mb-2">
                                <strong>• <?= te('shippingpage.example_germany') ?></strong> <?= te('shippingpage.single_parcel') ?><br>
                                <span class="text-primary ms-3"><?= te('shippingpage.amount_approx', ['calc' => '220 + (14×70) = 1,200', 'usd' => number_format(1200 * $usdRate, 0)]) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Currency Reference Table -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-sm border-info">
                <div class="card-header bg-info2 text-white py-3">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-globe me-2"></i> <?= te('shippingpage.currency_table_title') ?>
                    </h3>
                    <small class="opacity-75"><?= te('shippingpage.currency_table_subtitle', ['date' => $rateLabel]) ?></small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="min-width: 120px;"><?= te('shippingpage.currency_name') ?></th>
                                    <th class="d-none d-md-table-cell" style="min-width: 100px;"><?= te('shippingpage.country_region') ?></th>
                                    <th class="text-center" style="min-width: 80px;">
                                        <small><?= te('shippingpage.exchange_rate_aed') ?></small>
                                    </th>
                                    <th class="text-end d-none d-lg-table-cell" style="min-width: 70px;">
                                        <small><?= te('shippingpage.sample_cost_30') ?></small>
                                    </th>
                                    <th class="text-end" style="min-width: 70px;">
                                        <small><?= te('shippingpage.sample_cost_120') ?></small>
                                    </th>
                                    <th class="text-end d-none d-sm-table-cell" style="min-width: 70px;">
                                        <small><?= te('shippingpage.sample_cost_300') ?></small>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach (currency_catalog() as $currency):
                                    $cost30 = 30 * $currency['rate'];
                                    $cost120 = 120 * $currency['rate'];
                                    $cost300 = 300 * $currency['rate'];
                                    $decimals = (int)$currency['decimals'];
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <strong><?= htmlspecialchars(t_or('currency.' . $currency['code'], $currency['name'])) ?> (<?= htmlspecialchars($currency['code']) ?>)</strong>
                                        <div class="d-md-none text-muted small mt-1"><?= htmlspecialchars($currency['country']) ?></div>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= htmlspecialchars($currency['country']) ?></td>
                                    <td class="text-center font-monospace small"><?= format_currency_rate((float)$currency['rate']) ?></td>
                                    <td class="text-end font-monospace small d-none d-lg-table-cell"><?= number_format($cost30, $decimals) ?></td>
                                    <td class="text-end font-monospace small"><?= number_format($cost120, $decimals) ?></td>
                                    <td class="text-end font-monospace small d-none d-sm-table-cell"><?= number_format($cost300, $decimals) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= te('shippingpage.currency_disclaimer') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once(__DIR__ . '/../includes/footer.php'); ?>

<!-- Bootstrap JS with multiple fallbacks -->
<script>
// Try to load Bootstrap JS with fallbacks
function loadBootstrapJS() {
    const scripts = [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
        'https://stackpath.bootstrapcdn.com/bootstrap/5.3.2/js/bootstrap.bundle.min.js',
        'https://unpkg.com/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'
    ];
    
    let currentScript = 0;
    
    function tryLoadScript() {
        if (currentScript >= scripts.length) {
            console.warn('All Bootstrap JS sources failed to load');
            return;
        }
        
        const script = document.createElement('script');
        script.src = scripts[currentScript];
        script.onload = function() {
            console.log('Bootstrap JS loaded from:', scripts[currentScript]);
        };
        script.onerror = function() {
            currentScript++;
            tryLoadScript();
        };
        document.head.appendChild(script);
    }
    
    tryLoadScript();
}

// Load Bootstrap JS immediately
loadBootstrapJS();
</script>
<script>
// Bootstrap fallback loader
function loadBootstrapFallback() {
    if (typeof bootstrap === 'undefined') {
        console.log('Loading Bootstrap JS fallback...');
        const fallbackScript = document.createElement('script');
        fallbackScript.src = 'https://stackpath.bootstrapcdn.com/bootstrap/5.3.2/js/bootstrap.bundle.min.js';
        fallbackScript.onload = function() {
            console.log('Bootstrap JS fallback loaded successfully');
        };
        fallbackScript.onerror = function() {
            console.warn('Bootstrap JS fallback also failed to load');
            // Try a third fallback
            const thirdFallback = document.createElement('script');
            thirdFallback.src = 'https://maxcdn.bootstrapcdn.com/bootstrap/5.3.2/js/bootstrap.bundle.min.js';
            document.head.appendChild(thirdFallback);
        };
        document.head.appendChild(fallbackScript);
    }
}

// Check Bootstrap loading after a brief delay
setTimeout(function() {
    if (typeof bootstrap === 'undefined') {
        loadBootstrapFallback();
    }
}, 1000);

// Enhanced country dropdown handler with error checking
function initializeCountryDropdown() {
    const countrySelect = document.getElementById('country');
    if (!countrySelect) {
        console.error('Country dropdown not found');
        return;
    }

    // Check if countries are loaded
    const optionCount = countrySelect.options.length;
    if (optionCount <= 1) {
        console.warn('Countries not loaded properly, showing warning');
        const warningDiv = document.createElement('div');
        warningDiv.className = 'alert alert-warning mt-2';
        warningDiv.innerHTML = '<small><i class="fas fa-exclamation-triangle"></i> ' + <?= json_encode(te('shippingpage.countries_not_loaded'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?> + '</small>';
        countrySelect.parentNode.appendChild(warningDiv);
    }

    countrySelect.addEventListener('change', function () {
        const country = this.value;
        const cityInput = document.getElementById('city');
        
        const placeholders = {
            default: <?= json_encode(t('shippingpage.city_placeholder'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
            withCountry: <?= json_encode(t('shippingpage.city_placeholder_in'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
        };

        // Check if the city input element exists before accessing its properties
        if (!cityInput) {
            console.error('City input element not found');
            return;
        }

        // Clear the city input when country changes
        cityInput.value = '';
        
        // Update placeholder based on country and language
        if (country) {
            cityInput.placeholder = placeholders.withCountry.replace('{country}', country);
        } else {
            cityInput.placeholder = placeholders.default;
        }

        console.log('Country changed to:', country);
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeCountryDropdown();
    
    // Additional diagnostic info for debugging
    const countrySelect = document.getElementById('country');
    if (countrySelect) {
        console.log('Countries dropdown initialized with', countrySelect.options.length, 'options');
    }
});
</script>
</body>
</html>

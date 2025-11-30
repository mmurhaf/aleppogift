<?php
session_start();
require_once(__DIR__ . '/../includes/shipping.php');
$shippingCost = null;
$error = null;

// USD conversion rate (1 AED = 0.27 USD approximately)
$usdRate = 0.27;

// Language support
$lang = $_GET['lang'] ?? $_POST['lang'] ?? 'en';
$lang = in_array($lang, ['en', 'ar']) ? $lang : 'en';

// Language strings
$translations = [
    'en' => [
        'title' => 'Shipping Calculator - AleppoGift',
        'page_title' => 'Shipping Rates Calculator',
        'page_subtitle' => 'Get instant shipping estimates for your order',
        'estimate_shipping' => 'Estimate Your Shipping',
        'shipping_rates' => 'Our Shipping Rates',
        'country_label' => 'Destination Country *',
        'select_country' => '-- Select Country --',
        'city_label' => 'City (Optional)',
        'city_placeholder' => 'Enter your city',
        'city_info' => 'Some cities may have special rates (e.g., Al Gharbia)',
        'weight_info' => 'For GCC countries, every 8kg creates a separate parcel',
        'weight_label' => 'Package Weight (kg) *',
        'weight_placeholder' => 'Enter weight',
        'calculate_btn' => 'Calculate Shipping',
        'region_country' => 'Region / Country',
        'base_rate' => 'Base Rate',
        'additional_rate' => 'Additional Rate',
        'uae' => 'UAE',
        'uae_base' => 'AED 30',
        'uae_additional' => 'Flat rate (AED 60 for Al Gharbia)',
        'oman' => 'Oman (All Cities)',
        'oman_base' => 'AED 70 for 5kg',
        'oman_additional' => 'AED 10/kg above 5kg',
        'gcc_countries' => 'GCC Countries (Qatar, Kuwait, Saudi Arabia, Bahrain)',
        'gcc_base' => 'AED 120 per parcel (8kg max)',
        'gcc_additional' => 'AED 30/kg above 1kg per parcel',
        'gcc_note' => 'Each 8kg creates a new parcel',
        'other_countries' => 'Europe',
        'other_base' => 'AED 220 for 1kg',
        'other_additional' => 'AED 70/kg above 1kg',
        'usa_others' => 'USA & Others',
        'usa_base' => 'AED 300 for 1kg',
        'usa_additional' => 'AED 80/kg above 1kg',
        'shipping_examples' => 'Examples',
        'example_22kg_qatar' => 'Qatar (22kg): 930 AED (~251 USD)',
        'example_germany' => 'Germany (15kg): 1200 AED (~324 USD) • USA (10kg): 1020 AED (~275 USD)',
        'footer_note' => 'Final shipping cost calculated during checkout based on your selections and total weight.',
        'usd_note' => 'USD rates shown for reference only (1 AED ≈ 0.27 USD). Actual charges in AED.',
        'shipping_result' => 'Estimated Shipping Cost',
        'cost_breakdown' => 'Cost Breakdown',
        'usd_equivalent' => 'USD Equivalent',
        'currency_table_title' => 'International Currency Reference',
        'currency_table_subtitle' => 'Sample shipping costs in major currencies (as of October 21, 2025)',
        'currency_disclaimer' => 'Please note that exchange rates fluctuate daily; these values are for illustrative purposes only.',
        'currency_name' => 'Currency',
        'country_region' => 'Country/Region',
        'exchange_rate_aed' => 'Rate (1 AED =)',
        'sample_cost_30' => 'UAE Regular (30 AED)',
        'sample_cost_120' => 'GCC Base (120 AED)',
        'sample_cost_300' => 'International (300 AED)',
        'back_to_shop' => 'Back to Shop',
        'lang_english' => 'English',
        'lang_arabic' => 'العربية'
    ],
    'ar' => [
        'title' => 'حاسبة الشحن - هدية حلب',
        'page_title' => 'حاسبة أسعار الشحن',
        'page_subtitle' => 'احصل على تقديرات فورية لتكلفة الشحن لطلبك',
        'estimate_shipping' => 'احسب تكلفة الشحن',
        'shipping_rates' => 'أسعار الشحن لدينا',
        'country_label' => 'البلد المقصود *',
        'select_country' => '-- اختر البلد --',
        'city_label' => 'المدينة (اختياري)',
        'city_placeholder' => 'أدخل مدينتك',
        'city_info' => 'بعض المدن قد تحتوي على أسعار خاصة (مثل الغربية)',
        'weight_info' => 'لدول مجلس التعاون الخليجي، كل 8 كيلو ينشئ طرداً منفصلاً',
        'weight_label' => 'وزن الطرد (كيلو) *',
        'weight_placeholder' => 'أدخل الوزن',
        'calculate_btn' => 'احسب تكلفة الشحن',
        'region_country' => 'المنطقة / البلد',
        'base_rate' => 'السعر الأساسي',
        'additional_rate' => 'السعر الإضافي',
        'uae' => 'الإمارات العربية المتحدة',
        'uae_base' => '30 درهم',
        'uae_additional' => 'سعر ثابت (60 درهم للغربية)',
        'oman' => 'عُمان (جميع المدن)',
        'oman_base' => '70 درهم لـ 5 كيلو',
        'oman_additional' => '10 درهم/كيلو فوق 5 كيلو',
        'gcc_countries' => 'دول مجلس التعاون الخليجي (قطر، الكويت، السعودية، البحرين)',
        'gcc_base' => '120 درهم لكل طرد (8 كيلو حد أقصى)',
        'gcc_additional' => '30 درهم/كيلو فوق 1 كيلو لكل طرد',
        'gcc_note' => 'كل 8 كيلو ينشئ طرداً جديداً',
        'other_countries' => 'أوروبا',
        'other_base' => '220 درهم لـ 1 كيلو',
        'other_additional' => '70 درهم/كيلو فوق 1 كيلو',
        'usa_others' => 'أمريكا وبلدان أخرى',
        'usa_base' => '300 درهم لـ 1 كيلو',
        'usa_additional' => '80 درهم/كيلو فوق 1 كيلو',
        'shipping_examples' => 'أمثلة',
        'example_22kg_qatar' => 'قطر (22 كيلو): 930 درهم (~251 دولار أمريكي)',
        'example_detailed_qatar' => 'الطرد الأول: 120 + (7×30) = 330 درهم | الطرد الثاني: 120 + (7×30) = 330 درهم | الطرد الثالث: 120 + (5×30) = 270 درهم',
        'footer_note' => 'يتم حساب تكلفة الشحن النهائية أثناء الدفع بناءً على اختياراتك والوزن الإجمالي.',
        'usd_note' => 'أسعار الدولار الأمريكي للمرجع فقط (1 درهم ≈ 0.27 دولار). الرسوم الفعلية بالدرهم الإماراتي.',
        'shipping_result' => 'تقدير الشحن',
        'cost_breakdown' => 'تفصيل التكلفة',
        'usd_equivalent' => 'المعادل بالدولار الأمريكي',
        'currency_table_title' => 'مرجع العملات الدولية',
        'currency_table_subtitle' => 'عينات من تكاليف الشحن بالعملات الرئيسية (اعتباراً من 21 أكتوبر 2025)',
        'currency_disclaimer' => 'يرجى ملاحظة أن أسعار الصرف تتقلب يومياً؛ هذه القيم للأغراض التوضيحية فقط.',
        'currency_name' => 'العملة',
        'country_region' => 'البلد/المنطقة',
        'exchange_rate_aed' => 'السعر (1 درهم =)',
        'sample_cost_30' => 'الإمارات عادي (30 درهم)',
        'sample_cost_120' => 'دول الخليج أساسي (120 درهم)',
        'sample_cost_300' => 'دولي (300 درهم)',
        'back_to_shop' => 'العودة للمتجر',
        'lang_english' => 'English',
        'lang_arabic' => 'العربية'
    ]
];

$t = $translations[$lang];

$selectedCountry = $_POST['country'] ?? '';
$city = $_POST['city'] ?? '';
$weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$selectedCountry || $weight <= 0) {
        $error = "Please select a country and enter a valid weight.";
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
<html lang="<?= $lang ?>" <?= $lang === 'ar' ? 'dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <title><?= $t['title'] ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" onerror="this.onerror=null;this.href='https://stackpath.bootstrapcdn.com/bootstrap/5.3.2/css/bootstrap.min.css';">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" onerror="this.onerror=null;this.href='https://maxcdn.bootstrapcdn.com/font-awesome/6.4.0/css/all.min.css';">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/header-fixes.css">
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
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Calculate your shipping costs with our easy-to-use shipping estimator. Get accurate rates based on your country, city, and package weight.">
    <meta name="keywords" content="shipping, calculator, estimator, rates, AleppoGift">
    <meta name="author" content="AleppoGift Team">
    <style>
        :root {
            --Aleppo-primary: #8B0000;  /* Deep red/maroon */
            --Aleppo-secondary: #D4AF37;  /* Gold */
            --Aleppo-light: #F8F1E5;  /* Cream/off-white */
        }
        .bg-primary {
            background-color: var(--Aleppo-primary) !important;
        }
        .btn-primary {
            background-color: var(--Aleppo-primary);
            border-color: var(--Aleppo-primary);
        }
        .btn-primary:hover {
            background-color: #6d0000;
            border-color: #6d0000;
        }
        .btn-outline-primary {
            color: var(--Aleppo-primary);
            border-color: var(--Aleppo-primary);
        }
        .btn-outline-primary:hover {
            background-color: var(--Aleppo-primary);
            border-color: var(--Aleppo-primary);
        }
        .text-primary {
            color: var(--Aleppo-primary) !important;
        }
        .border-primary {
            border-color: var(--Aleppo-primary) !important;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(139, 0, 0, 0.05);
        }
        .card-header {
            border-bottom: 2px solid var(--Aleppo-secondary);
            
        }
        .alert-success {
            border-left: 4px solid var(--Aleppo-secondary);
        }
        
        /* RTL Support */
        html[dir="rtl"] {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        html[dir="rtl"] .alert-success {
            border-left: none;
            border-right: 4px solid var(--Aleppo-secondary);
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
        
        .lang-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        html[dir="rtl"] .lang-switcher {
            right: auto;
            left: 20px;
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
    background-color: var(--Aleppo-primary);
}
    </style>
</head>
<body style="background-color: var(--Aleppo-light);">

<?php require_once(__DIR__ . '/../includes/header.php'); ?>

<!-- Language Switcher -->
<div class="lang-switcher">
    <div class="btn-group" role="group">
        <a href="?lang=en<?= isset($_POST['country']) ? '&country=' . urlencode($_POST['country']) . '&city=' . urlencode($_POST['city']) . '&weight=' . urlencode($_POST['weight']) : '' ?>" 
           class="btn <?= $lang === 'en' ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
            <?= $t['lang_english'] ?>
        </a>
        <a href="?lang=ar<?= isset($_POST['country']) ? '&country=' . urlencode($_POST['country']) . '&city=' . urlencode($_POST['city']) . '&weight=' . urlencode($_POST['weight']) : '' ?>" 
           class="btn <?= $lang === 'ar' ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
            <?= $t['lang_arabic'] ?>
        </a>
    </div>
</div>

<div class="container py-4">

    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-primary">
            <i class="fas fa-shipping-fast me-2"></i> <?= $t['page_title'] ?>
        </h1>
        <p class="lead" style="color: var(--Aleppo-secondary);"><?= $t['page_subtitle'] ?></p>
    </div>

    <div class="row g-4">
        <!-- Calculator Form Column -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-primary">
                <div class="card-header text-prime py-3">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-calculator me-2"></i> <?= $t['estimate_shipping'] ?>
                    </h3>
                </div>
                <div class="card-body p-4">
                    <form method="post" id="shippingForm">
                        <input type="hidden" name="lang" value="<?= $lang ?>">
                        <div class="mb-4">
                            <label for="country" class="form-label fw-bold"><?= $t['country_label'] ?></label>
                            <select id="country" name="country" class="form-select form-select-lg" required>
                                <option value=""><?= $t['select_country'] ?></option>
                                <?= $countryOptions ?>
                            </select>
                            <?php if (empty($countryOptions)): ?>
                            <div class="alert alert-warning mt-2">
                                <small><i class="fas fa-exclamation-triangle me-1"></i> 
                                Countries not loaded. Please refresh the page.</small>
                            </div>
                            <?php else: ?>
                            <small class="text-success">
                                <i class="fas fa-check-circle me-1"></i> 
                                <?= substr_count($countryOptions, '<option') ?> countries loaded
                            </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <label for="city" class="form-label fw-bold"><?= $t['city_label'] ?></label>
                            <input type="text" id="city" name="city" class="form-control form-control-lg" 
                                   placeholder="<?= $t['city_placeholder'] ?>" value="<?= htmlspecialchars($city) ?>">
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i> <?= $t['city_info'] ?>
                            </small>
                        </div>

                        <div class="mb-4">
                            <label for="weight" class="form-label fw-bold"><?= $t['weight_label'] ?></label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0.1" id="weight" name="weight" 
                                       class="form-control form-control-lg" required 
                                       placeholder="<?= $t['weight_placeholder'] ?>"
                                       value="<?= htmlspecialchars($weight) ?>">
                                <span class="input-group-text">kg</span>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i> <?= $t['weight_info'] ?>
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3">
                            <i class="fas fa-calculator me-2"></i> <?= $t['calculate_btn'] ?>
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
                                    <h4 class="mb-2"><?= $t['shipping_result'] ?></h4>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <p class="mb-1 fs-4 fw-bold text-primary">AED <?= number_format($shippingCost, 2) ?></p>
                                        </div>
                                        <div class="col-sm-6">
                                            <p class="mb-1 fs-5 fw-semibold text-secondary">
                                                USD <?= number_format($shippingCost * $usdRate, 2) ?>
                                            </p>
                                            <small class="text-muted"><?= $t['usd_equivalent'] ?></small>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= $t['cost_breakdown'] ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="checkout.php?lang=<?= $lang ?>" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> <?= $t['back_to_shop'] ?>
                </a>
            </div>
        </div>
        
        <!-- Rates Table Column -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-primary h-100">
                <div class="card-header text-prime py-3">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-clipboard-list me-2"></i> <?= $t['shipping_rates'] ?>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4"><?= $t['region_country'] ?></th>
                                    <th><?= $t['base_rate'] ?></th>
                                    <th><?= $t['additional_rate'] ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4"><strong><?= $t['uae'] ?></strong></td>
                                    <td><?= $t['uae_base'] ?></td>
                                    <td><?= $t['uae_additional'] ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong><?= $t['oman'] ?></strong></td>
                                    <td><?= $t['oman_base'] ?></td>
                                    <td><?= $t['oman_additional'] ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong><?= $t['gcc_countries'] ?></strong></td>
                                    <td><?= $t['gcc_base'] ?></td>
                                    <td><?= $t['gcc_additional'] ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="ps-4 text-muted">
                                        <small><i class="fas fa-info-circle me-1"></i> <?= $t['gcc_note'] ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong><?= $t['other_countries'] ?></strong></td>
                                    <td><?= $t['other_base'] ?></td>
                                    <td><?= $t['other_additional'] ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4"><strong><?= $t['usa_others'] ?></strong></td>
                                    <td><?= $t['usa_base'] ?></td>
                                    <td><?= $t['usa_additional'] ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="mb-3">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <small class="text-muted"><?= $t['footer_note'] ?></small>
                    </div>
                    <div class="mb-3">
                        <i class="fas fa-dollar-sign text-success me-2"></i>
                        <small class="text-muted"><?= $t['usd_note'] ?></small>
                    </div>
                    
                    <!-- Important Notice -->
                    <div class="alert alert-warning alert-sm p-2 mb-3" style="font-size: 0.85rem;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Shipping costs are calculated based on destination and package dimensions. 
                        The rates shown provide approximate estimates since item boxes vary in shapes and dimensions. 
                        For GCC countries, parcels are limited to 8kg each - larger orders are split into multiple parcels.
                    </div>
                    
                    <div class="text-muted">
                        <strong><?= $t['shipping_examples'] ?>:</strong><br>
                        <div class="mt-2" style="font-size: 0.9rem;">
                            <div class="mb-2">
                                <strong>• Qatar (22kg):</strong> 3 parcels → (8kg + 8kg + 6kg)<br>
                                <span class="text-primary ms-3">Parcel 1: 120 + (7×30) = 330 AED</span><br>
                                <span class="text-primary ms-3">Parcel 2: 120 + (7×30) = 330 AED</span><br>
                                <span class="text-primary ms-3">Parcel 3: 120 + (5×30) = 270 AED</span><br>
                                <span class="fw-bold text-success ms-3">Total: 930 AED (~252 USD)</span>
                            </div>
                            <div class="mb-2">
                                <strong>• Germany (15kg):</strong> Single parcel<br>
                                <span class="text-primary ms-3">220 + (14×70) = 1,200 AED (~325 USD)</span>
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
                        <i class="fas fa-globe me-2"></i> <?= $t['currency_table_title'] ?>
                    </h3>
                    <small class="opacity-75"><?= $t['currency_table_subtitle'] ?></small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="min-width: 120px;"><?= $t['currency_name'] ?></th>
                                    <th class="d-none d-md-table-cell" style="min-width: 100px;"><?= $t['country_region'] ?></th>
                                    <th class="text-center" style="min-width: 80px;">
                                        <small><?= $t['exchange_rate_aed'] ?></small>
                                    </th>
                                    <th class="text-end d-none d-lg-table-cell" style="min-width: 70px;">
                                        <small><?= $t['sample_cost_30'] ?></small>
                                    </th>
                                    <th class="text-end" style="min-width: 70px;">
                                        <small><?= $t['sample_cost_120'] ?></small>
                                    </th>
                                    <th class="text-end d-none d-sm-table-cell" style="min-width: 70px;">
                                        <small><?= $t['sample_cost_300'] ?></small>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $currencies = [
                                    ['name' => 'US Dollar (USD)', 'country' => 'United States', 'rate' => 0.272],
                                    ['name' => 'Euro (EUR)', 'country' => 'Eurozone', 'rate' => 0.252],
                                    ['name' => 'British Pound (GBP)', 'country' => 'United Kingdom', 'rate' => 0.215],
                                    ['name' => 'Japanese Yen (JPY)', 'country' => 'Japan', 'rate' => 40.85],
                                    ['name' => 'Canadian Dollar (CAD)', 'country' => 'Canada', 'rate' => 0.375],
                                    ['name' => 'Australian Dollar (AUD)', 'country' => 'Australia', 'rate' => 0.410],
                                    ['name' => 'Swiss Franc (CHF)', 'country' => 'Switzerland', 'rate' => 0.242],
                                    ['name' => 'Chinese Yuan (CNY)', 'country' => 'China', 'rate' => 1.96],
                                    ['name' => 'Indian Rupee (INR)', 'country' => 'India', 'rate' => 22.80],
                                    ['name' => 'Saudi Riyal (SAR)', 'country' => 'Saudi Arabia', 'rate' => 1.02],
                                    ['name' => 'Qatari Riyal (QAR)', 'country' => 'Qatar', 'rate' => 0.99],
                                    ['name' => 'Kuwaiti Dinar (KWD)', 'country' => 'Kuwait', 'rate' => 0.083],
                                    ['name' => 'Omani Rial (OMR)', 'country' => 'Oman', 'rate' => 0.105],
                                    ['name' => 'Bahraini Dinar (BHD)', 'country' => 'Bahrain', 'rate' => 0.103],
                                    ['name' => 'South Korean Won (KRW)', 'country' => 'South Korea', 'rate' => 365],
                                    ['name' => 'Singapore Dollar (SGD)', 'country' => 'Singapore', 'rate' => 0.365],
                                    ['name' => 'New Zealand Dollar (NZD)', 'country' => 'New Zealand', 'rate' => 0.445],
                                    ['name' => 'South African Rand (ZAR)', 'country' => 'South Africa', 'rate' => 5.05],
                                    ['name' => 'Brazilian Real (BRL)', 'country' => 'Brazil', 'rate' => 1.48],
                                    ['name' => 'Mexican Peso (MXN)', 'country' => 'Mexico', 'rate' => 4.75]
                                ];
                                
                                foreach ($currencies as $currency):
                                    $cost30 = 30 * $currency['rate'];
                                    $cost120 = 120 * $currency['rate'];
                                    $cost300 = 300 * $currency['rate'];
                                    $decimals = ($currency['rate'] >= 1) ? 0 : 2;
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <strong><?= $currency['name'] ?></strong>
                                        <div class="d-md-none text-muted small mt-1"><?= $currency['country'] ?></div>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= $currency['country'] ?></td>
                                    <td class="text-center font-monospace small"><?= number_format($currency['rate'], ($currency['rate'] >= 1) ? 2 : 3) ?></td>
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
                        <?= $t['currency_disclaimer'] ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

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
        warningDiv.innerHTML = '<small><i class="fas fa-exclamation-triangle"></i> Countries not loaded. Please refresh the page.</small>';
        countrySelect.parentNode.appendChild(warningDiv);
    }

    countrySelect.addEventListener('change', function () {
        const country = this.value;
        const cityInput = document.getElementById('city');
        
        // Language-specific placeholders
        const placeholders = {
            'en': {
                default: '<?= $t['city_placeholder'] ?>',
                withCountry: 'Enter your city in '
            },
            'ar': {
                default: '<?= $t['city_placeholder'] ?>',
                withCountry: 'أدخل مدينتك في '
            }
        };
        
        const currentLang = '<?= $lang ?>';

        // Check if the city input element exists before accessing its properties
        if (!cityInput) {
            console.error('City input element not found');
            return;
        }

        // Clear the city input when country changes
        cityInput.value = '';
        
        // Update placeholder based on country and language
        if (country) {
            cityInput.placeholder = placeholders[currentLang].withCountry + country;
        } else {
            cityInput.placeholder = placeholders[currentLang].default;
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

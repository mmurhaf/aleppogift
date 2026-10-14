<?php
if (!function_exists('store_url')) {
    require_once __DIR__ . '/functions.php';
}
// Calculate cart count properly
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

$nav_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$nav_script = strtolower(basename($nav_path));
$nav_is_home = $nav_script === '' || $nav_script === 'index.php' || substr($nav_path, -1) === '/';
$nav_is_shipping = $nav_script === 'shipping.php';
$nav_is_about = $nav_script === 'about.php';
$nav_is_contact = $nav_script === 'contact.php';
$nav_active_category = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 0;

$nav_categories = [];
try {
    if (class_exists('Database')) {
        $navDb = (isset($db) && $db instanceof Database) ? $db : new Database();
        $nav_rows = $navDb->query("SELECT id, name_en, name_ar, name_ru, name_sp FROM categories WHERE status = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($nav_rows as $nav_row) {
            if (function_exists('is_placeholder_name') && is_placeholder_name($nav_row['name_en'] ?? '')) {
                continue;
            }
            $nav_categories[] = $nav_row;
        }
    }
} catch (Throwable $e) {
    $nav_categories = [];
}
$nav_inline_limit = 5;
$nav_languages = function_exists('store_languages') ? store_languages() : [
    'en' => ['label' => 'English', 'short' => 'EN', 'html' => 'en', 'dir' => 'ltr'],
    'ar' => ['label' => 'العربية', 'short' => 'ع', 'html' => 'ar', 'dir' => 'rtl'],
];
$nav_lang = function_exists('store_lang') ? store_lang() : 'en';
$nav_lang_meta = $nav_languages[$nav_lang] ?? $nav_languages['en'];
$nav_logged_in = function_exists('current_customer_id') && current_customer_id() > 0;
$nav_account_url = store_url($nav_logged_in ? 'account.php' : 'login.php');
$nav_account_label = $nav_logged_in ? t('account') : t('login');
$nav_category_name = function ($row) {
    return function_exists('loc_field') ? loc_field($row, 'name') : $row['name_en'];
};
$nav_has_currency = function_exists('currency_menu_groups') && function_exists('selected_store_currency');
$activeCurrency = $nav_has_currency ? selected_store_currency() : null;
?>
<link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/storefront.css')) ?>?v=20261008a">
<link rel="stylesheet" href="<?= htmlspecialchars(store_url('assets/css/tanagra-theme.css')) ?>?v=20261014d">
<script>window.AG_LANG=<?= json_encode($nav_lang) ?>;window.AG_I18N=<?= json_encode(function_exists('translation_js_strings') ? translation_js_strings() : new stdClass(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;window.agT=function(k,p){var s=(window.AG_I18N&&window.AG_I18N[k])||k;if(p){for(var n in p){s=s.split('{'+n+'}').join(p[n]);}}return s;};</script>
<script>document.documentElement.lang=<?= json_encode($nav_lang_meta['html']) ?>;document.documentElement.dir=<?= json_encode($nav_lang_meta['dir']) ?>;</script>

    <div class="tg-usp-bar" role="note">
        <ul class="tg-usp-list">
            <li><?= te('catalog.promise_cod') ?></li>
            <li><?= te('catalog.promise_delivery') ?></li>
            <li><?= te('catalog.promise_confirm') ?></li>
        </ul>
    </div>

    <header class="tg-header" id="tg-header" role="banner">
        <div class="tg-header-main">
            <div class="tg-header-start">
                <button class="tg-icon-btn tg-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#tgMenu" aria-controls="tgMenu" aria-label="<?= te('header.toggle_nav') ?>">
                    <span class="tg-burger" aria-hidden="true"></span>
                </button>
                <div class="dropdown lang-switcher tg-desktop-only">
                    <button class="tg-text-btn" type="button" id="languageSwitcher" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= htmlspecialchars(t('language') . ', ' . $nav_lang_meta['label']) ?>">
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($nav_lang_meta['label']) ?></span>
                    </button>
                    <ul class="dropdown-menu tg-dropdown lang-menu" aria-labelledby="languageSwitcher">
                        <?php foreach ($nav_languages as $langCode => $langMeta): ?>
                        <li>
                            <a class="dropdown-item<?= $langCode === $nav_lang ? ' active' : '' ?>"
                               href="<?= htmlspecialchars(function_exists('lang_switch_url') ? lang_switch_url($langCode) : store_url('set_language.php?lang=' . $langCode)) ?>"
                               hreflang="<?= htmlspecialchars($langMeta['html']) ?>"
                               lang="<?= htmlspecialchars($langMeta['html']) ?>"><?= htmlspecialchars($langMeta['label']) ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if ($nav_has_currency): ?>
                <div class="dropdown currency-switcher tg-desktop-only">
                    <button class="tg-text-btn" type="button" id="currencySwitcher" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= te('header.currency_selected', ['name' => t_or('currency.' . $activeCurrency['code'], $activeCurrency['name'])]) ?>">
                        <span class="fi fi-<?= htmlspecialchars($activeCurrency['flag']) ?>" aria-hidden="true"></span>
                        <span><?= htmlspecialchars($activeCurrency['code']) ?></span>
                    </button>
                    <ul class="dropdown-menu tg-dropdown currency-menu" aria-labelledby="currencySwitcher">
                        <?php $currencyGroupIndex = 0; foreach (currency_menu_groups() as $currencyGroupLabel => $currencyGroupItems): ?>
                            <?php if ($currencyGroupIndex > 0): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                            <li><h6 class="dropdown-header"><?= htmlspecialchars(t_or('currency.group.' . $currencyGroupLabel, $currencyGroupLabel)) ?></h6></li>
                            <?php foreach ($currencyGroupItems as $currencyOption): ?>
                            <li>
                                <a class="dropdown-item<?= $currencyOption['code'] === $activeCurrency['code'] ? ' active' : '' ?>"
                                   href="<?= htmlspecialchars(currency_switch_url($currencyOption['code'])) ?>">
                                    <span class="currency-option-name"><span class="fi fi-<?= htmlspecialchars($currencyOption['flag']) ?>" aria-hidden="true"></span><?= htmlspecialchars(t_or('currency.' . $currencyOption['code'], $currencyOption['name'])) ?></span>
                                    <span class="currency-code"><?= htmlspecialchars($currencyOption['symbol']) ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        <?php $currencyGroupIndex++; endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <button class="tg-icon-btn tg-mobile-only" type="button" id="search-toggle-mobile" aria-label="<?= te('header.open_search') ?>">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                </button>
            </div>

            <a class="tg-logo" href="<?= htmlspecialchars(store_url()) ?>" aria-label="<?= te('header.home_aria') ?>">
                <img src="<?= htmlspecialchars(store_url('uploads/logo.png')) ?>" alt="<?= te('header.logo_alt') ?>" width="186" height="72">
            </a>

            <div class="tg-header-end header-actions">
                <button class="tg-icon-btn tg-desktop-only" type="button" id="search-toggle" aria-label="<?= te('header.open_search') ?>">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                </button>
                <a class="tg-icon-btn tg-desktop-only" href="<?= htmlspecialchars($nav_account_url) ?>" aria-label="<?= htmlspecialchars($nav_account_label) ?>" title="<?= htmlspecialchars($nav_account_label) ?>"><i class="far fa-user" aria-hidden="true"></i></a>
                <a class="tg-icon-btn tg-desktop-only" href="<?= htmlspecialchars(store_url('wishlist.php')) ?>" aria-label="<?= te('wishlist') ?>" title="<?= te('wishlist') ?>"><i class="far fa-heart" aria-hidden="true"></i></a>
                <button class="tg-icon-btn tg-cart-btn tg-desktop-only"
                        type="button"
                        id="cart-toggle-desktop"
                        aria-label="<?= te('header.open_cart_count', ['count' => (int)$cart_count]) ?>"
                        title="<?= te('header.open_cart') ?>">
                    <i class="fas fa-bag-shopping" aria-hidden="true"></i>
                    <span class="cart-count-badge<?= (int)$cart_count > 0 ? '' : ' is-zero' ?>"
                          id="cart-count"
                          aria-label="<?= te('header.items_in_cart', ['count' => (int)$cart_count]) ?>"><?php echo (int)$cart_count; ?></span>
                </button>
                <button class="tg-icon-btn tg-cart-btn tg-mobile-only"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#cartOffcanvas"
                        aria-controls="cartOffcanvas"
                        aria-label="<?= te('header.open_cart_count', ['count' => (int)$cart_count]) ?>"
                        title="<?= te('header.open_cart') ?>">
                    <i class="fas fa-bag-shopping" aria-hidden="true"></i>
                    <span class="cart-count-badge<?= (int)$cart_count > 0 ? '' : ' is-zero' ?>"
                          id="cart-count-mobile"
                          aria-label="<?= te('header.items_in_cart', ['count' => (int)$cart_count]) ?>"><?php echo (int)$cart_count; ?></span>
                </button>
            </div>
        </div>

        <nav class="tg-nav" aria-label="<?= te('header.main_nav') ?>">
            <ul class="tg-nav-list">
                <li class="dropdown tg-nav-item">
                    <a class="tg-nav-link dropdown-toggle<?= $nav_is_home && !$nav_active_category ? ' is-active' : '' ?>" href="#" id="shopMenu" role="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"><?= htmlspecialchars(t('shop')) ?></a>
                    <div class="dropdown-menu tg-mega" aria-labelledby="shopMenu">
                        <div class="tg-mega-inner">
                            <div class="tg-mega-col">
                                <p class="tg-mega-title"><?= te('theme.our_categories') ?></p>
                                <ul class="tg-mega-links">
                                    <li><a href="<?= htmlspecialchars(store_url('products.php')) ?>"><?= htmlspecialchars(t('all_products')) ?></a></li>
                                    <li><a href="<?= htmlspecialchars(store_url('products.php?sort=newest')) ?>"><?= te('theme.new_arrivals') ?></a></li>
                                    <?php foreach ($nav_categories as $nav_category): ?>
                                    <li><a href="<?= htmlspecialchars(store_url('products.php?category=' . (int)$nav_category['id'])) ?>"><?= htmlspecialchars($nav_category_name($nav_category)) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a class="tg-mega-feature" href="<?= htmlspecialchars(store_url('products.php?sort=newest')) ?>">
                                <img src="<?= htmlspecialchars(store_url('assets/images/hero-bg.jpg')) ?>" alt="" loading="lazy">
                                <span><?= te('theme.new_arrivals') ?></span>
                            </a>
                        </div>
                    </div>
                </li>
                <?php foreach (array_slice($nav_categories, 0, $nav_inline_limit) as $nav_category): ?>
                <li class="tg-nav-item tg-nav-cat">
                    <a class="tg-nav-link<?= $nav_active_category === (int)$nav_category['id'] ? ' is-active' : '' ?>" href="<?= htmlspecialchars(store_url('products.php?category=' . (int)$nav_category['id'])) ?>"><?= htmlspecialchars($nav_category_name($nav_category)) ?></a>
                </li>
                <?php endforeach; ?>
                <li class="tg-nav-item">
                    <a class="tg-nav-link<?= $nav_is_shipping ? ' is-active' : '' ?>" href="<?= htmlspecialchars(store_url('shipping.php')) ?>"<?= $nav_is_shipping ? ' aria-current="page"' : '' ?>><?= htmlspecialchars(t('shipping')) ?></a>
                </li>
                <li class="tg-nav-item">
                    <a class="tg-nav-link<?= $nav_is_about ? ' is-active' : '' ?>" href="<?= htmlspecialchars(store_url('about.php')) ?>"<?= $nav_is_about ? ' aria-current="page"' : '' ?>><?= htmlspecialchars(t('about')) ?></a>
                </li>
                <li class="tg-nav-item">
                    <a class="tg-nav-link<?= $nav_is_contact ? ' is-active' : '' ?>" href="<?= htmlspecialchars(store_url('contact.php')) ?>"<?= $nav_is_contact ? ' aria-current="page"' : '' ?>><?= htmlspecialchars(t('contact')) ?></a>
                </li>
            </ul>
        </nav>

        <div id="search-bar" class="tg-search d-none">
            <form class="tg-search-form" action="<?= htmlspecialchars(store_url('products.php')) ?>" method="get" role="search">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search"
                       name="search"
                       placeholder="<?= te('header.search_placeholder') ?>"
                       value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                       aria-label="<?= te('header.search_products') ?>"
                       autocomplete="off">
                <button class="tg-btn" type="submit"><?= te('search') ?></button>
                <button type="button" class="tg-icon-btn" id="search-close" aria-label="<?= te('header.close_search') ?>">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </header>

    <div class="offcanvas offcanvas-start tg-drawer" tabindex="-1" id="tgMenu" aria-labelledby="tgMenuLabel">
        <div class="offcanvas-header">
            <p class="tg-drawer-title" id="tgMenuLabel"><?= te('theme.menu') ?></p>
            <button type="button" class="tg-icon-btn" data-bs-dismiss="offcanvas" aria-label="<?= te('close') ?>"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="offcanvas-body">
            <nav aria-label="<?= te('header.main_nav') ?>">
                <ul class="tg-drawer-links is-primary">
                    <li><a href="<?= htmlspecialchars(store_url('products.php')) ?>"><?= htmlspecialchars(t('all_products')) ?></a></li>
                    <li><a href="<?= htmlspecialchars(store_url('products.php?sort=newest')) ?>"><?= te('theme.new_arrivals') ?></a></li>
                    <?php foreach ($nav_categories as $nav_category): ?>
                    <li><a href="<?= htmlspecialchars(store_url('products.php?category=' . (int)$nav_category['id'])) ?>"><?= htmlspecialchars($nav_category_name($nav_category)) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <ul class="tg-drawer-links">
                    <li><a href="<?= htmlspecialchars(store_url('shipping.php')) ?>"><?= htmlspecialchars(t('shipping')) ?></a></li>
                    <li><a href="<?= htmlspecialchars(store_url('about.php')) ?>"><?= htmlspecialchars(t('about')) ?></a></li>
                    <li><a href="<?= htmlspecialchars(store_url('contact.php')) ?>"><?= htmlspecialchars(t('contact')) ?></a></li>
                    <li><a href="<?= htmlspecialchars(store_url('track_order.php')) ?>"><?= te('track') ?></a></li>
                </ul>
                <ul class="tg-drawer-links is-icons">
                    <li><a href="<?= htmlspecialchars($nav_account_url) ?>"><i class="far fa-user" aria-hidden="true"></i><?= htmlspecialchars($nav_account_label) ?></a></li>
                    <li><a href="<?= htmlspecialchars(store_url('wishlist.php')) ?>"><i class="far fa-heart" aria-hidden="true"></i><?= te('wishlist') ?></a></li>
                </ul>
            </nav>
            <div class="tg-drawer-prefs">
                <p class="tg-drawer-label"><?= htmlspecialchars(t('language')) ?></p>
                <div class="tg-drawer-langs">
                    <?php foreach ($nav_languages as $langCode => $langMeta): ?>
                    <a class="<?= $langCode === $nav_lang ? 'is-active' : '' ?>"
                       href="<?= htmlspecialchars(function_exists('lang_switch_url') ? lang_switch_url($langCode) : store_url('set_language.php?lang=' . $langCode)) ?>"
                       hreflang="<?= htmlspecialchars($langMeta['html']) ?>"
                       lang="<?= htmlspecialchars($langMeta['html']) ?>"><?= htmlspecialchars($langMeta['label']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php if ($nav_has_currency): ?>
                <details class="tg-drawer-currency">
                    <summary>
                        <span class="fi fi-<?= htmlspecialchars($activeCurrency['flag']) ?>" aria-hidden="true"></span>
                        <?= htmlspecialchars(t_or('currency.' . $activeCurrency['code'], $activeCurrency['name'])) ?> (<?= htmlspecialchars($activeCurrency['code']) ?>)
                    </summary>
                    <ul>
                        <?php foreach (currency_menu_groups() as $currencyGroupItems): ?>
                            <?php foreach ($currencyGroupItems as $currencyOption): ?>
                            <li>
                                <a class="<?= $currencyOption['code'] === $activeCurrency['code'] ? 'is-active' : '' ?>" href="<?= htmlspecialchars(currency_switch_url($currencyOption['code'])) ?>">
                                    <span class="fi fi-<?= htmlspecialchars($currencyOption['flag']) ?>" aria-hidden="true"></span>
                                    <?= htmlspecialchars(t_or('currency.' . $currencyOption['code'], $currencyOption['name'])) ?>
                                    <span class="currency-code"><?= htmlspecialchars($currencyOption['symbol']) ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="cartPreview"
         class="card position-fixed cart-preview-panel"
         role="dialog"
         aria-label="<?= te('header.cart_preview') ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><?= te('header.your_cart') ?></h6>
            <button type="button" class="cart-preview-close" onclick="hideCartPreview()" aria-label="<?= te('header.close_cart_preview') ?>">×</button>
        </div>
        <div class="card-body cart-preview-body">
            <div id="cart-items-preview">
                <div class="cart-preview-empty">
                    <i class="fas fa-bag-shopping" aria-hidden="true"></i>
                    <p><?= te('header.cart_empty') ?></p>
                    <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(store_url('products.php')) ?>"><?= te('header.browse_products') ?></a>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end tg-drawer" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
        <div class="offcanvas-header">
            <p class="tg-drawer-title" id="cartOffcanvasLabel"><?= te('header.your_cart') ?></p>
            <button type="button" class="tg-icon-btn" data-bs-dismiss="offcanvas" aria-label="<?= te('close') ?>"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="offcanvas-body p-0">
            <div id="cart-items-offcanvas">
                <div class="cart-preview-empty">
                    <p><?= te('header.cart_empty') ?></p>
                </div>
            </div>
        </div>
    </div>
<script>
window.AED_TO_USD_RATE = <?= json_encode(function_exists('getAedToUsdRate') ? getAedToUsdRate() : 0.272294) ?>;
window.AG_CURRENCY = <?= json_encode(function_exists('selected_store_currency') ? [
    'code' => selected_store_currency()['code'],
    'rate' => (float)selected_store_currency()['rate'],
    'decimals' => (int)selected_store_currency()['decimals'],
    'prefix' => selected_store_currency()['prefix'],
] : ['code' => 'AED', 'rate' => 1, 'decimals' => 2, 'prefix' => 'AED '], JSON_UNESCAPED_UNICODE) ?>;
window.AG_AED_SYMBOL = <?= json_encode(function_exists('getUAESymbolSVG') ? getUAESymbolSVG('uae-symbol') : 'AED ') ?>;
window.formatStoreMoney = function (amount, withReference) {
    var aed = Number(amount) || 0;
    var currency = window.AG_CURRENCY || { code: 'AED', rate: 1, decimals: 2, prefix: 'AED ' };
    var converted = aed * Number(currency.rate || 1);
    var text = currency.prefix + converted.toFixed(currency.decimals);
    if (!withReference) return text;
    if (currency.code === 'AED') {
        return 'AED ' + aed.toFixed(2) + ' (USD ' + (aed * (window.AED_TO_USD_RATE || 0.272294)).toFixed(2) + ')';
    }
    return text + ' (AED ' + aed.toFixed(2) + ')';
};
</script>

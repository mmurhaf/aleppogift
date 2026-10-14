<?php
/**
 * Storefront currencies. Amounts in the catalog are units per 1 AED.
 * Gulf currencies come first, then USD, then EUR, then the rest.
 */

if (basename($_SERVER['PHP_SELF'] ?? '') === 'currencies.php' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    die('Direct access not permitted');
}

function currency_catalog_defaults(): array {
    $rows = [
        ['code' => 'AED', 'name' => 'UAE Dirham', 'country' => 'United Arab Emirates', 'flag' => 'ae', 'symbol' => 'د.إ', 'prefix' => 'AED ', 'rate' => 1, 'decimals' => 2, 'group' => 1, 'sort' => 1],
        ['code' => 'SAR', 'name' => 'Saudi Riyal', 'country' => 'Saudi Arabia', 'flag' => 'sa', 'symbol' => 'ر.س', 'prefix' => 'SAR ', 'rate' => 1.021103, 'decimals' => 2, 'group' => 1, 'sort' => 2],
        ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'country' => 'Kuwait', 'flag' => 'kw', 'symbol' => 'د.ك', 'prefix' => 'KWD ', 'rate' => 0.084188, 'decimals' => 3, 'group' => 1, 'sort' => 3],
        ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'country' => 'Bahrain', 'flag' => 'bh', 'symbol' => 'د.ب', 'prefix' => 'BHD ', 'rate' => 0.102383, 'decimals' => 3, 'group' => 1, 'sort' => 4],
        ['code' => 'QAR', 'name' => 'Qatari Riyal', 'country' => 'Qatar', 'flag' => 'qa', 'symbol' => 'ر.ق', 'prefix' => 'QAR ', 'rate' => 0.99115, 'decimals' => 2, 'group' => 1, 'sort' => 5],
        ['code' => 'OMR', 'name' => 'Omani Rial', 'country' => 'Oman', 'flag' => 'om', 'symbol' => 'ر.ع', 'prefix' => 'OMR ', 'rate' => 0.104696, 'decimals' => 3, 'group' => 1, 'sort' => 6],
        ['code' => 'USD', 'name' => 'US Dollar', 'country' => 'United States', 'flag' => 'us', 'symbol' => '$', 'prefix' => '$', 'rate' => 0.272294, 'decimals' => 2, 'group' => 2, 'sort' => 1],
        ['code' => 'EUR', 'name' => 'Euro', 'country' => 'Eurozone', 'flag' => 'eu', 'symbol' => '€', 'prefix' => '€', 'rate' => 0.242022, 'decimals' => 2, 'group' => 3, 'sort' => 1],
        ['code' => 'AUD', 'name' => 'Australian Dollar', 'country' => 'Australia', 'flag' => 'au', 'symbol' => 'A$', 'prefix' => 'A$', 'rate' => 0.391905, 'decimals' => 2, 'group' => 4, 'sort' => 1],
        ['code' => 'BRL', 'name' => 'Brazilian Real', 'country' => 'Brazil', 'flag' => 'br', 'symbol' => 'R$', 'prefix' => 'R$', 'rate' => 1.422065, 'decimals' => 2, 'group' => 4, 'sort' => 2],
        ['code' => 'GBP', 'name' => 'British Pound', 'country' => 'United Kingdom', 'flag' => 'gb', 'symbol' => '£', 'prefix' => '£', 'rate' => 0.205771, 'decimals' => 2, 'group' => 4, 'sort' => 3],
        ['code' => 'CAD', 'name' => 'Canadian Dollar', 'country' => 'Canada', 'flag' => 'ca', 'symbol' => 'C$', 'prefix' => 'CA$', 'rate' => 0.387948, 'decimals' => 2, 'group' => 4, 'sort' => 4],
        ['code' => 'CNY', 'name' => 'Chinese Yuan', 'country' => 'China', 'flag' => 'cn', 'symbol' => '¥', 'prefix' => 'CN¥', 'rate' => 1.827192, 'decimals' => 2, 'group' => 4, 'sort' => 5],
        ['code' => 'INR', 'name' => 'Indian Rupee', 'country' => 'India', 'flag' => 'in', 'symbol' => '₹', 'prefix' => '₹', 'rate' => 26.258574, 'decimals' => 2, 'group' => 4, 'sort' => 6],
        ['code' => 'JPY', 'name' => 'Japanese Yen', 'country' => 'Japan', 'flag' => 'jp', 'symbol' => '¥', 'prefix' => '¥', 'rate' => 42.94287, 'decimals' => 0, 'group' => 4, 'sort' => 7],
        ['code' => 'MXN', 'name' => 'Mexican Peso', 'country' => 'Mexico', 'flag' => 'mx', 'symbol' => 'MX$', 'prefix' => 'MX$', 'rate' => 4.950897, 'decimals' => 2, 'group' => 4, 'sort' => 8],
        ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'country' => 'New Zealand', 'flag' => 'nz', 'symbol' => 'NZ$', 'prefix' => 'NZ$', 'rate' => 0.48504, 'decimals' => 2, 'group' => 4, 'sort' => 9],
        ['code' => 'SGD', 'name' => 'Singapore Dollar', 'country' => 'Singapore', 'flag' => 'sg', 'symbol' => 'S$', 'prefix' => 'S$', 'rate' => 0.348426, 'decimals' => 2, 'group' => 4, 'sort' => 10],
        ['code' => 'ZAR', 'name' => 'South African Rand', 'country' => 'South Africa', 'flag' => 'za', 'symbol' => 'R', 'prefix' => 'R', 'rate' => 4.534072, 'decimals' => 2, 'group' => 4, 'sort' => 11],
        ['code' => 'KRW', 'name' => 'South Korean Won', 'country' => 'South Korea', 'flag' => 'kr', 'symbol' => '₩', 'prefix' => '₩', 'rate' => 366.190548, 'decimals' => 0, 'group' => 4, 'sort' => 12],
        ['code' => 'CHF', 'name' => 'Swiss Franc', 'country' => 'Switzerland', 'flag' => 'ch', 'symbol' => 'Fr', 'prefix' => 'CHF ', 'rate' => 0.225544, 'decimals' => 2, 'group' => 4, 'sort' => 13],
    ];

    $catalog = [];
    foreach ($rows as $row) {
        $catalog[$row['code']] = $row;
    }
    return $catalog;
}

function currency_db() {
    if (isset($GLOBALS['db']) && is_object($GLOBALS['db']) && method_exists($GLOBALS['db'], 'fetch')) {
        return $GLOBALS['db'];
    }

    static $db = null;
    static $failed = false;
    if ($failed) {
        return null;
    }
    if ($db) {
        return $db;
    }
    if (!class_exists('Database')) {
        if (!defined('DB_HOST')) {
            $failed = true;
            return null;
        }
        require_once __DIR__ . '/Database.php';
    }

    try {
        $db = new Database();
        return $db;
    } catch (Throwable $e) {
        $failed = true;
        return null;
    }
}

function currency_saved_rates(bool $refresh = false): array {
    static $loaded = false;
    static $rates = [];
    if ($refresh) {
        $loaded = false;
        $rates = [];
    }
    if ($loaded) {
        return $rates;
    }
    $loaded = true;

    try {
        $db = currency_db();
        if (!$db) {
            return $rates;
        }
        if (function_exists('get_site_setting')) {
            $raw = get_site_setting($db, 'currency_rates', '');
        } else {
            $row = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = ?", ['currency_rates']);
            $raw = is_array($row) ? ($row['setting_value'] ?? '') : '';
        }
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $rates = $decoded;
        }
    } catch (Throwable $e) {
        $rates = [];
    }

    return $rates;
}

function currency_rates_updated_on(bool $refresh = false): string {
    static $date = null;
    if ($refresh) {
        $date = null;
    }
    if ($date !== null) {
        return $date;
    }
    $date = '2026-10-05';
    try {
        $db = currency_db();
        if (!$db) {
            return $date;
        }
        if (function_exists('get_site_setting')) {
            $saved = get_site_setting($db, 'currency_rates_updated_at', '');
        } else {
            $row = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = ?", ['currency_rates_updated_at']);
            $saved = is_array($row) ? ($row['setting_value'] ?? '') : '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$saved)) {
            $date = $saved;
        }
    } catch (Throwable $e) {
        // Keep the catalog date.
    }
    return $date;
}

function currency_rates_label(string $lang = 'en'): string {
    $timestamp = strtotime(currency_rates_updated_on() . ' 12:00:00');
    if (!$timestamp) {
        return '5 October 2026';
    }
    if (function_exists('store_date')) {
        return store_date($timestamp);
    }
    if ($lang === 'ar') {
        $months = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $month = $months[(int)date('n', $timestamp)] ?? '';
        return date('j', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp);
    }
    return date('j F Y', $timestamp);
}

function currency_catalog(bool $refresh = false): array {
    static $catalog = null;
    if ($refresh) {
        $catalog = null;
        currency_saved_rates(true);
        currency_rates_updated_on(true);
    }
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = currency_catalog_defaults();
    $saved = currency_saved_rates();
    foreach ($catalog as $code => $row) {
        if ($code === 'AED') {
            $catalog[$code]['rate'] = 1.0;
            continue;
        }
        if (isset($saved[$code]) && is_numeric($saved[$code]) && (float)$saved[$code] > 0) {
            $catalog[$code]['rate'] = (float)$saved[$code];
        }
    }

    uasort($catalog, function ($a, $b) {
        if ($a['group'] !== $b['group']) {
            return $a['group'] <=> $b['group'];
        }
        if ($a['sort'] !== $b['sort']) {
            return $a['sort'] <=> $b['sort'];
        }
        return strcmp($a['name'], $b['name']);
    });

    return $catalog;
}

function currency_by_code(string $code): ?array {
    $code = strtoupper(trim($code));
    $catalog = currency_catalog();
    return $catalog[$code] ?? null;
}

function currency_rate(string $code): float {
    $currency = currency_by_code($code);
    return $currency ? (float)$currency['rate'] : 0.0;
}

function selected_currency_code(): string {
    $code = '';
    if (!empty($_COOKIE['ag_currency'])) {
        $code = strtoupper((string)$_COOKIE['ag_currency']);
    } elseif (!empty($_SESSION['ag_currency'])) {
        $code = strtoupper((string)$_SESSION['ag_currency']);
    }
    return currency_by_code($code) ? $code : 'AED';
}

function selected_store_currency(): array {
    return currency_by_code(selected_currency_code()) ?? currency_by_code('AED');
}

function convert_from_aed($amountAed, ?string $code = null): float {
    $currency = $code ? currency_by_code($code) : selected_store_currency();
    if (!$currency) {
        return (float)$amountAed;
    }
    return (float)$amountAed * (float)$currency['rate'];
}

function format_currency_rate(float $rate): string {
    if ($rate >= 10) {
        return number_format($rate, 2, '.', '');
    }
    if ($rate >= 0.1) {
        return number_format($rate, 4, '.', '');
    }
    return number_format($rate, 6, '.', '');
}

function format_store_money($amountAed, bool $withReference = false): string {
    $amountAed = (float)$amountAed;
    $currency = selected_store_currency();
    $converted = $amountAed * (float)$currency['rate'];
    $text = $currency['prefix'] . number_format($converted, (int)$currency['decimals']);
    if (!$withReference) {
        return $text;
    }
    if ($currency['code'] === 'AED') {
        $usd = $amountAed * currency_rate('USD');
        return 'AED ' . number_format($amountAed, 2) . ' (USD ' . number_format($usd, 2) . ')';
    }
    return $text . ' (AED ' . number_format($amountAed, 2) . ')';
}

function format_store_price_pair($amountAed, string $mainClass = 'price-current', ?int $places = null): string {
    $amountAed = (float)$amountAed;
    $currency = selected_store_currency();
    if ($places === null) {
        $places = $currency['code'] === 'AED' ? 0 : (int)$currency['decimals'];
    }
    $places = max(0, min(3, $places));
    $converted = number_format($amountAed * (float)$currency['rate'], $places);
    $currencyLabel = '<span class="price-currency">' . htmlspecialchars(trim((string)$currency['prefix']), ENT_QUOTES, 'UTF-8') . '</span>';
    if ($currency['code'] === 'AED' && function_exists('getUAESymbolSVG')) {
        $main = getUAESymbolSVG('uae-symbol') . $converted;
        $alt = '$' . number_format($amountAed * currency_rate('USD'), 2);
    } elseif ($currency['code'] === 'AED') {
        $main = $currencyLabel . $converted;
        $alt = '$' . number_format($amountAed * currency_rate('USD'), 2);
    } else {
        $main = $currencyLabel . $converted;
        $alt = 'AED ' . number_format($amountAed, 0);
    }

    return '<span class="' . htmlspecialchars($mainClass, ENT_QUOTES, 'UTF-8') . '" data-price-aed="'
        . htmlspecialchars((string)$amountAed, ENT_QUOTES, 'UTF-8') . '">' . $main . '</span>'
        . '<span class="price-usd">' . $alt . '</span>';
}

function currency_switch_url(string $code): string {
    $return = $_SERVER['REQUEST_URI'] ?? '/';
    $path = function_exists('store_url') ? store_url('ajax/set_currency.php') : 'ajax/set_currency.php';
    return $path . '?code=' . rawurlencode(strtoupper($code)) . '&return=' . rawurlencode($return);
}

function currency_safe_return(string $return): string {
    $fallback = function_exists('store_url') ? store_url() : '/';
    $return = trim($return);
    if ($return === '' || strpbrk($return, "\r\n") !== false) {
        return $fallback;
    }

    if (preg_match('#^https?://#i', $return)) {
        $parts = parse_url($return);
        $host = $parts['host'] ?? '';
        $current = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || strcasecmp($host, $current) !== 0) {
            return $fallback;
        }
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return $path . $query;
    }

    if ($return[0] !== '/' || str_starts_with($return, '//')) {
        return $fallback;
    }
    return $return;
}

function remember_store_currency(string $code): bool {
    $currency = currency_by_code($code);
    if (!$currency) {
        return false;
    }
    $code = $currency['code'];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['ag_currency'] = $code;
    }
    setcookie('ag_currency', $code, [
        'expires' => time() + 365 * 24 * 60 * 60,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['ag_currency'] = $code;
    return true;
}

function save_currency_rates($db, array $postedRates, ?string $updatedOn = null): bool {
    if (!function_exists('set_site_setting')) {
        return false;
    }
    $defaults = currency_catalog_defaults();
    $clean = [];
    foreach ($defaults as $code => $row) {
        if ($code === 'AED') {
            continue;
        }
        if (!isset($postedRates[$code]) || !is_numeric($postedRates[$code])) {
            return false;
        }
        $rate = (float)$postedRates[$code];
        if ($rate <= 0 || $rate > 10000000) {
            return false;
        }
        $clean[$code] = $rate;
    }

    $saved = set_site_setting($db, 'currency_rates', json_encode($clean));
    $date = $updatedOn ?: date('Y-m-d');
    $saved = set_site_setting($db, 'currency_rates_updated_at', $date) && $saved;
    currency_catalog(true);
    return $saved;
}

function currency_menu_groups(): array {
    $groups = [
        'Gulf countries' => [],
        'US dollar' => [],
        'Euro' => [],
        'Other currencies' => [],
    ];
    foreach (currency_catalog() as $currency) {
        if ((int)$currency['group'] === 1) {
            $groups['Gulf countries'][] = $currency;
        } elseif ($currency['code'] === 'USD') {
            $groups['US dollar'][] = $currency;
        } elseif ($currency['code'] === 'EUR') {
            $groups['Euro'][] = $currency;
        } else {
            $groups['Other currencies'][] = $currency;
        }
    }
    return $groups;
}

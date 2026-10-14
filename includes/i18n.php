<?php
/**
 * Storefront translations.
 *
 * Defaults live in includes/lang/{lang}/*.php (each file returns [key => text]).
 * The `translations` table mirrors them (source = 'file', via database/sync_translations.php);
 * rows with source = 'custom' override the files, so copy can be edited without a deploy.
 * Missing keys fall back to English.
 */

if (!function_exists('store_languages')) {

function store_languages(): array
{
    return [
        'en' => ['label' => 'English', 'short' => 'EN', 'html' => 'en', 'dir' => 'ltr'],
        'ar' => ['label' => 'العربية', 'short' => 'ع', 'html' => 'ar', 'dir' => 'rtl'],
        'ru' => ['label' => 'Русский', 'short' => 'RU', 'html' => 'ru', 'dir' => 'ltr'],
        'sp' => ['label' => 'Español', 'short' => 'ES', 'html' => 'es', 'dir' => 'ltr'],
    ];
}

/** Resolve a language key ('sp') or its HTML/URL code ('es') to a store language key. */
function store_lang_from_code($code): ?string
{
    $code = strtolower(trim((string)$code));
    foreach (store_languages() as $key => $meta) {
        if ($code === $key || $code === $meta['html']) {
            return $key;
        }
    }
    return null;
}

/** A ?lang= URL parameter wins over the session so every language has its own crawlable URL. */
function store_lang(): string
{
    if (isset($_GET['lang']) && is_string($_GET['lang'])) {
        $requested = store_lang_from_code($_GET['lang']);
        if ($requested !== null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['store_lang'] = $requested;
            }
            return $requested;
        }
    }
    $lang = $_SESSION['store_lang'] ?? 'en';
    return isset(store_languages()[$lang]) ? $lang : 'en';
}

/** Value used in the ?lang= URL parameter ('es' for Spanish). */
function store_lang_param(string $lang): string
{
    return store_languages()[$lang]['html'] ?? $lang;
}

/** $url with its lang parameter set for $lang. English URLs carry no parameter unless $explicit. */
function lang_url(string $url, string $lang, bool $explicit = false): string
{
    $fragment = '';
    $hashPos = strpos($url, '#');
    if ($hashPos !== false) {
        $fragment = substr($url, $hashPos);
        $url = substr($url, 0, $hashPos);
    }
    $parts = explode('?', $url, 2);
    $query = [];
    if (isset($parts[1])) {
        parse_str($parts[1], $query);
    }
    unset($query['lang']);
    if ($lang !== 'en' || $explicit) {
        $query['lang'] = store_lang_param($lang);
    }
    $qs = http_build_query($query);
    return $parts[0] . ($qs !== '' ? '?' . $qs : '') . $fragment;
}

/** Language switcher target: the current page in $lang (explicit so switching back to English sticks). */
function lang_switch_url(string $lang): string
{
    return lang_url($_SERVER['REQUEST_URI'] ?? '/', $lang, true);
}

/** Canonical URL of the current language version of a page, given its English canonical URL. */
function seo_canonical_url(string $canonical): string
{
    return lang_url($canonical, store_lang());
}

/** <link rel="canonical"> plus hreflang alternates for every store language. */
function seo_lang_links(string $canonical): string
{
    $out = '<link rel="canonical" href="' . htmlspecialchars(seo_canonical_url($canonical), ENT_QUOTES, 'UTF-8') . '">' . "\n";
    foreach (store_languages() as $code => $meta) {
        $out .= '    <link rel="alternate" hreflang="' . htmlspecialchars($meta['html'], ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars(lang_url($canonical, $code), ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    $out .= '    <link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    return $out;
}

function store_lang_dir(): string
{
    return store_languages()[store_lang()]['dir'] ?? 'ltr';
}

function store_lang_html(): string
{
    return store_languages()[store_lang()]['html'] ?? 'en';
}

function translation_file_strings(string $lang): array
{
    static $cache = [];
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $strings = [];
    $dir = __DIR__ . '/lang/' . basename($lang);
    if (is_dir($dir)) {
        $files = glob($dir . '/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            $chunk = include $file;
            if (is_array($chunk)) {
                $strings = array_merge($strings, $chunk);
            }
        }
    }
    return $cache[$lang] = $strings;
}

function translation_db_strings(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }
    $rows = [];
    if (!class_exists('Database') || !defined('DB_HOST')) {
        return $rows;
    }
    try {
        $db = (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof Database) ? $GLOBALS['db'] : new Database();
        $stmt = $db->getPdo()->query("SELECT lang_code, tkey, tvalue FROM translations WHERE source = 'custom'");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['tvalue'] !== null && $row['tvalue'] !== '') {
                $rows[$row['lang_code']][$row['tkey']] = $row['tvalue'];
            }
        }
    } catch (Throwable $e) {
        $rows = [];
    }
    return $rows;
}

function translation_strings(string $lang): array
{
    static $merged = [];
    if (!isset($merged[$lang])) {
        $db = translation_db_strings();
        $merged[$lang] = array_merge(translation_file_strings($lang), $db[$lang] ?? []);
    }
    return $merged[$lang];
}

/**
 * Translate a key for the active language. Placeholders use {name} syntax:
 * t('cart.items_count', ['count' => 3]).
 */
function t(string $key, array $params = []): string
{
    $lang = store_lang();
    $text = translation_strings($lang)[$key] ?? null;
    if ($text === null && $lang !== 'en') {
        $text = translation_strings('en')[$key] ?? null;
    }
    $text = $text ?? $key;
    if ($params) {
        $replace = [];
        foreach ($params as $name => $value) {
            $replace['{' . $name . '}'] = (string)$value;
        }
        $text = strtr($text, $replace);
    }
    return $text;
}

/** Translate a data-driven key (status codes, currency names...) or return $fallback when no string exists. */
function t_or(string $key, string $fallback, array $params = []): string
{
    if (!isset(translation_strings(store_lang())[$key]) && !isset(translation_strings('en')[$key])) {
        return $fallback;
    }
    return t($key, $params);
}

/** Localized date: $style 'long' (October 12, 2026) or 'short' (Oct 12, 2026). Accepts a timestamp or date string. */
function store_date($value, string $style = 'long'): string
{
    $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
    if (!$ts) {
        return '';
    }
    $prefix = $style === 'short' ? 'date.month_short_' : 'date.month_';
    return t('date.format_' . ($style === 'short' ? 'short' : 'long'), [
        'day' => date('j', $ts),
        'month' => t($prefix . date('n', $ts)),
        'year' => date('Y', $ts),
    ]);
}

/** HTML-escaped translation for direct output in templates. */
function te(string $key, array $params = []): string
{
    return htmlspecialchars(t($key, $params), ENT_QUOTES, 'UTF-8');
}

/** Strings whose keys start with "js." are exposed to the browser as window.AG_I18N. */
function translation_js_strings(): array
{
    $out = [];
    $keys = array_keys(translation_strings('en') + translation_strings(store_lang()));
    foreach ($keys as $key) {
        if (strpos($key, 'js.') === 0) {
            $out[substr($key, 3)] = t($key);
        }
    }
    return $out;
}

}

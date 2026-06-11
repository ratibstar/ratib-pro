<?php
declare(strict_types=1);

if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', dirname(__DIR__, 1));
}

define('RATEB_VIEWS_PATH', RATEB_ROOT . DIRECTORY_SEPARATOR . 'views');
define('RATEB_STORAGE_PATH', RATEB_ROOT . '/storage');

define('RATEB_APP_NAME', 'RATEB');
define('RATEB_APP_VERSION', '1.0.0');

if (defined('RATEB_CP_ENTRY') && defined('RATEB_CP_APP_URL')) {
    define('RATEB_CP_MODE', true);
    define('RATEB_BASE_URL', (string) RATEB_CP_APP_URL);
} else {
    define('RATEB_CP_MODE', false);
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(str_replace('/public/index.php', '', $scriptName), '/');
    define('RATEB_BASE_URL', $basePath !== '' ? $basePath : '/rateb-erp/public');
}

define('RATEB_DEFAULT_LOCALE', 'ar');
define('RATEB_SUPPORTED_LOCALES', ['en', 'ar']);

if (!function_exists('rateb_asset')) {
    function rateb_asset(string $path): string
    {
        if (defined('RATEB_CP_MODE') && RATEB_CP_MODE && defined('RATEB_CP_ASSETS_URL')) {
            return rtrim((string) RATEB_CP_ASSETS_URL, '/') . '/' . ltrim($path, '/');
        }
        return RATEB_BASE_URL . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('rateb_url')) {
    function rateb_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        if (defined('RATEB_CP_MODE') && RATEB_CP_MODE) {
            $base = rtrim((string) RATEB_BASE_URL, '&');
            if (strpos($base, '?') === false) {
                $base .= '?control=1';
            }
            return $base . '&route=' . rawurlencode($path !== '' ? $path : 'admin');
        }
        return RATEB_BASE_URL . '/' . $path;
    }
}

if (!function_exists('rateb_locale')) {
    function rateb_locale(): string
    {
        $locale = $_SESSION['rateb_locale'] ?? RATEB_DEFAULT_LOCALE;
        return in_array($locale, RATEB_SUPPORTED_LOCALES, true) ? $locale : RATEB_DEFAULT_LOCALE;
    }
}

if (!function_exists('rateb_is_rtl')) {
    function rateb_is_rtl(): bool
    {
        return rateb_locale() === 'ar';
    }
}

if (!function_exists('rateb_label')) {
    function rateb_label(string $labelOrKey): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', trim($labelOrKey)));
        $translated = __($key);
        return $translated !== $key ? $translated : $labelOrKey;
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        static $cache = [];
        $locale = rateb_locale();
        if (!isset($cache[$locale])) {
            $file = RATEB_ROOT . '/config/lang/' . $locale . '.php';
            $cache[$locale] = is_file($file) ? require $file : [];
        }
        $text = $cache[$locale][$key] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        return $text;
    }
}

<?php
declare(strict_types=1);

if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', dirname(__DIR__, 1));
}

define('RATEB_VIEWS_PATH', RATEB_ROOT . DIRECTORY_SEPARATOR . 'views');
define('RATEB_STORAGE_PATH', RATEB_ROOT . '/storage');

define('RATEB_APP_NAME', 'RATEB');
define('RATEB_APP_VERSION', '1.0.0');

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = rtrim(str_replace('/public/index.php', '', $scriptName), '/');
define('RATEB_BASE_URL', $basePath !== '' ? $basePath : '/rateb-erp/public');

define('RATEB_DEFAULT_LOCALE', 'en');
define('RATEB_SUPPORTED_LOCALES', ['en', 'ar']);

if (!function_exists('rateb_asset')) {
    function rateb_asset(string $path): string
    {
        return RATEB_BASE_URL . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('rateb_url')) {
    function rateb_url(string $path = ''): string
    {
        return RATEB_BASE_URL . '/' . ltrim($path, '/');
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

if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        static $strings = null;
        if ($strings === null) {
            $locale = rateb_locale();
            $file = RATEB_ROOT . '/config/lang/' . $locale . '.php';
            $strings = is_file($file) ? require $file : [];
        }
        $text = $strings[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        return $text;
    }
}

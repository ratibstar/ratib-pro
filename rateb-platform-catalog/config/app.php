<?php

declare(strict_types=1);

if (!defined('RATEB_CATALOG_ROOT')) {
    define('RATEB_CATALOG_ROOT', dirname(__DIR__));
}

define('RATEB_PLATFORM_CATALOG_VERSION', '1.3.1');
define('RATEB_CATALOG_VIEWS_PATH', RATEB_CATALOG_ROOT . '/views');
define('RATEB_PLATFORM_CATALOG_APP_NAME', 'RATEB Platform Catalog');

if (!function_exists('rateb_platform_catalog_public_prefix')) {
    function rateb_platform_catalog_public_prefix(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('#(/rateb-platform-catalog/public)/index\.php$#', $script, $matches)) {
            return $matches[1];
        }

        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if (str_ends_with($dir, '/rateb-platform-catalog/public')) {
            return $dir;
        }

        return '';
    }
}

if (!defined('RATEB_PLATFORM_CATALOG_BASE_URL')) {
    define('RATEB_PLATFORM_CATALOG_BASE_URL', rateb_platform_catalog_public_prefix());
}

if (!function_exists('catalog__')) {
    function catalog__(string $key, ?string $locale = null): string
    {
        static $cache = [];
        $locale = $locale ?? (defined('RATEB_PLATFORM_CATALOG_DEFAULT_LOCALE')
            ? (string) RATEB_PLATFORM_CATALOG_DEFAULT_LOCALE
            : 'ar');
        $fallback = defined('RATEB_PLATFORM_CATALOG_FALLBACK_LOCALE')
            ? (string) RATEB_PLATFORM_CATALOG_FALLBACK_LOCALE
            : 'en';

        if (!isset($cache[$locale])) {
            $file = RATEB_CATALOG_ROOT . '/config/lang/' . $locale . '.php';
            $cache[$locale] = is_file($file) ? require $file : [];
        }

        if (isset($cache[$locale][$key])) {
            return (string) $cache[$locale][$key];
        }

        if ($locale !== $fallback) {
            return catalog__($key, $fallback);
        }

        return $key;
    }
}

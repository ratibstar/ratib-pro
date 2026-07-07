<?php

declare(strict_types=1);

if (!defined('RATEB_CATALOG_ROOT')) {
    define('RATEB_CATALOG_ROOT', dirname(__DIR__));
}

define('RATEB_PLATFORM_CATALOG_VERSION', '1.3.1');
define('RATEB_CATALOG_VIEWS_PATH', RATEB_CATALOG_ROOT . '/views');
define('RATEB_PLATFORM_CATALOG_APP_NAME', 'RATEB Platform Catalog');
define('RATEB_PLATFORM_CATALOG_BASE_URL', '');

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

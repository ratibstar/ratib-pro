<?php
declare(strict_types=1);

if (!defined('RATEB_ROOT')) {
    $root = realpath(dirname(__DIR__));
    define('RATEB_ROOT', str_replace('\\', '/', $root !== false ? $root : dirname(__DIR__)));
}

define('RATEB_VIEWS_PATH', RATEB_ROOT . DIRECTORY_SEPARATOR . 'views');
define('RATEB_STORAGE_PATH', RATEB_ROOT . '/storage');

define('RATEB_APP_NAME', 'RTAB');
define('RATEB_APP_VERSION', '1.0.0');
define('RATEB_ASSET_BUILD', '20260611-rtab15');

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

if (!function_exists('rateb_site_origin')) {
    function rateb_site_origin(): string
    {
        if (defined('SITE_URL') && (string) SITE_URL !== '') {
            return rtrim((string) SITE_URL, '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'out.ratib.sa';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('rateb_public_url')) {
    /** Direct ERP URL — works without Control Panel login. */
    function rateb_public_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $base = rateb_site_origin() . '/rateb-erp/public';
        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('rateb_asset')) {
    function rateb_asset(string $path): string
    {
        $path = ltrim($path, '/');
        $ver = defined('RATEB_ASSET_BUILD') ? (string) RATEB_ASSET_BUILD : '1';
        $suffix = '?v=' . rawurlencode($ver);
        return rateb_public_url('assets/' . $path) . $suffix;
    }
}

if (!function_exists('rateb_url')) {
    /** Always use standalone public URLs (no CP session required). */
    function rateb_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return rateb_public_url($path !== '' ? $path : 'admin');
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

if (!function_exists('rateb_can')) {
    function rateb_can(string $slug): bool
    {
        if (!empty($_SESSION['rateb_is_super_admin'])) {
            return true;
        }
        $userId = (int) ($_SESSION['rateb_user_id'] ?? 0);
        if ($userId <= 0 || $slug === '') {
            return $slug === '';
        }
        static $cache = [];
        if (!isset($cache[$userId])) {
            $cache[$userId] = (new \Rateb\App\Services\AuthorizationService())->userPermissionSlugs($userId);
        }
        return in_array($slug, $cache[$userId], true);
    }
}

if (!function_exists('rateb_permission_label')) {
    function rateb_permission_label(array $row): string
    {
        if (rateb_locale() === 'ar' && !empty($row['name_ar'])) {
            return (string) $row['name_ar'];
        }
        return (string) ($row['name'] ?? '');
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

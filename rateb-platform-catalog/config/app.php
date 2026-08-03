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

if (!function_exists('catalog_admin_host_allowed')) {
    /** Catalog admin UI — rateb.sa platform host only (never agency ERP hosts). */
    function catalog_admin_host_allowed(): bool
    {
        if (PHP_SAPI === 'cli' && trim((string) ($_SERVER['HTTP_HOST'] ?? '')) === '') {
            return true;
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $envRoot = dirname(RATEB_CATALOG_ROOT) . '/config/env';
        $agencyLookup = $envRoot . '/agency_lookup.php';
        if (is_file($agencyLookup)) {
            require_once $agencyLookup;
        }

        $normalized = function_exists('rateb_normalize_http_host')
            ? rateb_normalize_http_host($host)
            : strtolower(trim(explode(':', $host)[0]));

        if ($normalized === '') {
            return false;
        }

        if (str_ends_with($normalized, '.rateb.sa') && !in_array($normalized, ['rateb.sa', 'www.rateb.sa'], true)) {
            return false;
        }

        if (function_exists('rateb_lookup_agency_erp_by_host') && rateb_lookup_agency_erp_by_host($normalized) !== null) {
            return false;
        }

        $resolver = $envRoot . '/erp_agency_resolver.php';
        if (is_file($resolver)) {
            require_once $resolver;
        }

        return function_exists('rateb_erp_is_main_platform_host')
            && rateb_erp_is_main_platform_host($normalized);
    }
}

if (!function_exists('catalog_admin_dashboard_url')) {
    function catalog_admin_dashboard_url(): string
    {
        $base = defined('RATEB_PLATFORM_CATALOG_BASE_URL') && (string) RATEB_PLATFORM_CATALOG_BASE_URL !== ''
            ? rtrim((string) RATEB_PLATFORM_CATALOG_BASE_URL, '/')
            : '/rateb-platform-catalog/public';
        if (str_ends_with($base, '/public')) {
            return $base . '/admin';
        }

        return $base . '/admin';
    }
}

if (!function_exists('catalog_current_request_url')) {
    function catalog_current_request_url(): string
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        $scheme = $secure ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/rateb-platform-catalog/admin');

        return $scheme . '://' . $host . $uri;
    }
}

if (!function_exists('catalog_safe_return_url')) {
    function catalog_safe_return_url(string $return): string
    {
        $return = trim($return);
        if ($return === '' || str_contains($return, '..')) {
            return '';
        }

        if (preg_match('#^https?://#i', $return)) {
            $host = strtolower((string) parse_url($return, PHP_URL_HOST));
            if (!in_array($host, ['rateb.sa', 'www.rateb.sa', 'localhost', '127.0.0.1'], true)) {
                return '';
            }
            if (!str_contains($return, '/rateb-platform-catalog/')) {
                return '';
            }

            return $return;
        }

        if (!str_starts_with($return, '/rateb-platform-catalog/')) {
            return '';
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        $scheme = $secure ? 'https' : 'http';

        return $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . $return;
    }
}

if (!function_exists('catalog_admin_erp_sso_url')) {
    function catalog_admin_erp_sso_url(?string $return = null): string
    {
        $return = $return ?? catalog_current_request_url();

        return '/rateb-erp/public/platform-catalog/sso?return=' . rawurlencode($return);
    }
}

if (!function_exists('catalog_maybe_redirect_erp_sso')) {
    /** Redirect unauthenticated catalog admin to ERP SSO handoff (same origin). */
    function catalog_maybe_redirect_erp_sso(): void
    {
        if (PHP_SAPI === 'cli' || !catalog_admin_host_allowed()) {
            return;
        }

        if (isset($_SESSION['platform_user_id']) && (int) $_SESSION['platform_user_id'] > 0) {
            return;
        }

        header('Location: ' . catalog_admin_erp_sso_url(), true, 302);
        exit;
    }
}

if (!function_exists('catalog_admin_erp_login_url')) {
    function catalog_admin_erp_login_url(): string
    {
        return catalog_admin_erp_sso_url();
    }
}

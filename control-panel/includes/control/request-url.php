<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/request-url.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/request-url.php`.
 */
/**
 * Scheme/host detection for URLs and cookies when behind reverse proxy (cPanel, Cloudflare).
 */
if (!function_exists('control_request_is_https')) {
    function control_request_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        $xf = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($xf === 'https') {
            return true;
        }
        $xff = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
        if ($xff === 'on' || $xff === '1') {
            return true;
        }
        return false;
    }
}

if (!function_exists('control_request_origin_base')) {
    /**
     * Absolute origin including path prefix before /pages (e.g. https://host/control-panel).
     */
    function control_request_origin_base(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = rtrim(preg_replace('#/pages/[^?]*.*$#', '', $path), '/');
        $scheme = control_request_is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return rtrim($scheme . '://' . $host . $basePath, '/');
    }
}

if (!function_exists('control_control_api_base_url')) {
    function control_control_api_base_url(): string
    {
        return control_request_origin_base() . '/api/control';
    }
}

if (!function_exists('control_ratib_pro_public_base_url')) {
    /**
     * URL origin for shared Ratib Pro static files (css/, js/) when the control panel lives under a subpath
     * (e.g. /control-panel). Those assets are served from the parent app root, not under /control-panel/.
     */
    function control_ratib_pro_public_base_url(): string
    {
        if (!function_exists('control_request_origin_base')) {
            return '';
        }
        $origin = rtrim(control_request_origin_base(), '/');
        $base = '';
        if (function_exists('getBaseUrl')) {
            # e.g. /control-panel
            $base = rtrim((string) getBaseUrl(), '/');
        }
        if ($base !== '' && $base !== '/' && strlen($origin) >= strlen($base) && substr($origin, -strlen($base)) === $base) {
            $stripped = substr($origin, 0, -strlen($base));
            return $stripped !== '' ? rtrim($stripped, '/') : $origin;
        }
        if (defined('RATIB_PRO_URL') && RATIB_PRO_URL !== '') {
            return rtrim((string) RATIB_PRO_URL, '/');
        }
        if (defined('SITE_URL') && SITE_URL !== '') {
            return rtrim((string) SITE_URL, '/');
        }
        return $origin;
    }
}

if (!function_exists('control_ratib_pro_asset_url')) {
    /**
     * Absolute URL to a file under the Ratib Pro project root (e.g. css/hr.js) with cache-bust query.
     * Resolves filesystem path from this file: includes/control → repo root is three levels up.
     */
    function control_ratib_pro_asset_url(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $pub = control_ratib_pro_public_base_url();
        $repoRoot = dirname(__DIR__, 3);
        $fs = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $v = is_file($fs) ? filemtime($fs) : time();
        return $pub . '/' . $relativePath . '?v=' . $v;
    }
}

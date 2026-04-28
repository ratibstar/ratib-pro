<?php
/**
 * EN: Handles core framework/runtime behavior in `core/bootstrap.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/bootstrap.php`.
 */
/**
 * Multi-Tenant Bootstrap
 *
 * Load order: config/env -> TenantResolver (if enabled) -> Database, Auth
 * Include from config.php or index.php
 */
if (defined('CORE_BOOTSTRAP_LOADED')) {
    return;
}

// PHP 7 compatibility polyfills used across control-plane code.
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle !== '' && strpos((string) $haystack, (string) $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

$multiTenantEnabled = getenv('MULTI_TENANT_SUBDOMAIN_ENABLED') === '1' || getenv('MULTI_TENANT_SUBDOMAIN_ENABLED') === 'true';
if (defined('MULTI_TENANT_SUBDOMAIN_ENABLED')) {
    $multiTenantEnabled = MULTI_TENANT_SUBDOMAIN_ENABLED;
}
if ($multiTenantEnabled) {
    require_once __DIR__ . '/TenantResolver.php';
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BaseModel.php';

define('CORE_BOOTSTRAP_LOADED', true);

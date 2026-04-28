<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/bootstrap_multi_tenant.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/bootstrap_multi_tenant.php`.
 */
/**
 * Multi-Tenant Bootstrap - Include this in config.php when ready
 *
 * Add AFTER database connection is established:
 *
 *   if (file_exists(__DIR__ . '/bootstrap_multi_tenant.php')) {
 *       require_once __DIR__ . '/bootstrap_multi_tenant.php';
 *   }
 *
 * Set MULTI_TENANT_ENABLED in your config or env to activate.
 */
if (defined('MULTI_TENANT_BOOTSTRAP_LOADED')) {
    return;
}
define('MULTI_TENANT_BOOTSTRAP_LOADED', true);

if (!defined('MULTI_TENANT_ENABLED')) {
    define('MULTI_TENANT_ENABLED', false);
}

if (MULTI_TENANT_ENABLED && isset($GLOBALS['conn']) && $GLOBALS['conn'] !== null) {
    try {
        require_once __DIR__ . '/TenantLoader.php';
        require_once __DIR__ . '/helpers/CountryFilter.php';
        TenantLoader::init();
        if (defined('COUNTRY_ID')) {
            TenantLoader::validateSession();
        }
    } catch (Throwable $e) {
        error_log('Multi-tenant bootstrap failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!defined('COUNTRY_ID')) {
            define('COUNTRY_ID', 0);
            define('COUNTRY_CODE', '');
            define('COUNTRY_NAME', '');
        }
    }
}

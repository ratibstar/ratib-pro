<?php
/**
 * admin.rateb.sa — bind Pro + ERP databases from control_agencies (agency #34 / تجربة 2).
 */
declare(strict_types=1);

// Demo-host Module Add-on UI preview only. This file is loaded solely for admin.rateb.sa.
// Does not enable commerce on rateb.sa. Do not process purchases from this preview.
putenv('RATEB_ENV=staging');
$_ENV['RATEB_ENV'] = 'staging';
putenv('RATIB_MODULE_ADDON_PREVIEW=1');
$_ENV['RATIB_MODULE_ADDON_PREVIEW'] = '1';
putenv('MODULE_ADDON_COMMERCE_ENABLED=1');
$_ENV['MODULE_ADDON_COMMERCE_ENABLED'] = '1';

if (defined('DB_NAME')) {
    return;
}

$agencyHost = 'admin.rateb.sa';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'agency_resolver.php';
if (!resolve_agency_by_host($agencyHost)) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'erp_agency_resolver.php';
    resolve_agency_erp_by_host($agencyHost);
}

/**
 * Pin ERP DB for this subdomain when control_agencies lookup is unavailable on this vhost.
 * Prevents silent fallback to platform DB (admin_rateb-erp) where old supplier data remains.
 */
if (!defined('RATEB_ERP_DB_NAME') || (string) RATEB_ERP_DB_NAME === '') {
    $directadminDb = __DIR__ . DIRECTORY_SEPARATOR . 'directadmin_db.php';
    if (is_file($directadminDb)) {
        require_once $directadminDb;
    }
    if (!defined('RATEB_ERP_DB_NAME')) {
        define('RATEB_ERP_DB_NAME', 'admin_admin-rateb');
    }
    if (!defined('RATEB_ERP_DB_HOST')) {
        $h = getenv('DB_HOST');
        define('RATEB_ERP_DB_HOST', ($h !== false && $h !== '') ? (string) $h : 'localhost');
    }
    if (!defined('RATEB_ERP_DB_USER')) {
        $u = getenv('DB_USER');
        define('RATEB_ERP_DB_USER', ($u !== false && $u !== '') ? (string) $u : (function_exists('rateb_default_mysql_user') ? rateb_default_mysql_user() : 'admin_rateb'));
    }
    if (!defined('RATEB_ERP_DB_PASS')) {
        $p = getenv('DB_PASS');
        define('RATEB_ERP_DB_PASS', $p !== false ? (string) $p : '');
    }
    if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
        define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
    }
    if (!defined('RATEB_ERP_AGENCY_RESOLVED')) {
        define('RATEB_ERP_AGENCY_RESOLVED', true);
    }
    if (!defined('RATEB_ERP_AGENCY_ID')) {
        define('RATEB_ERP_AGENCY_ID', 34);
    }
    if (!defined('SITE_URL')) {
        define('SITE_URL', 'https://' . $agencyHost);
    }
}

// Always pin agency id for admin.rateb.sa even when DB binding was resolved earlier.
if (!defined('RATEB_ERP_AGENCY_ID')) {
    define('RATEB_ERP_AGENCY_ID', 34);
}
if (!defined('RATEB_ERP_AGENCY_RESOLVED')) {
    define('RATEB_ERP_AGENCY_RESOLVED', true);
}

<?php
/**
 * alarfaj.rateb.sa — dedicated Al Arfaj ERP host (admin_rateb_erp_alarfaj).
 * Pins the tenant DB so this alias cannot fall through to platform / تجربة 2.
 */
declare(strict_types=1);

if (defined('DB_NAME')) {
    return;
}

$agencyHost = 'alarfaj.rateb.sa';

require_once __DIR__ . '/directadmin_db.php';

$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

define('DB_HOST', ($dbHost !== false && $dbHost !== '') ? (string) $dbHost : 'localhost');
define('DB_PORT', ($dbPort !== false && $dbPort !== '') ? (int) $dbPort : 3306);
define('DB_USER', ($dbUser !== false && $dbUser !== '') ? (string) $dbUser : rateb_default_mysql_user());
define('DB_PASS', ($dbPass !== false && $dbPass !== '') ? (string) $dbPass : '');
define('DB_NAME', ($dbName !== false && $dbName !== '') ? (string) $dbName : rateb_main_pro_database());
define('CONTROL_PANEL_DB_NAME', getenv('CONTROL_PANEL_DB_NAME') ?: rateb_control_panel_database());

require_once __DIR__ . DIRECTORY_SEPARATOR . 'agency_resolver.php';
if (!resolve_agency_by_host($agencyHost)) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'erp_agency_resolver.php';
    resolve_agency_erp_by_host($agencyHost);
}

if (!defined('RATEB_ERP_DB_NAME') || (string) RATEB_ERP_DB_NAME === '') {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'agency_lookup.php';
    $row = function_exists('rateb_lookup_agency_erp_by_host') ? rateb_lookup_agency_erp_by_host($agencyHost) : null;
    if (is_array($row) && function_exists('rateb_apply_agency_erp_constants')) {
        rateb_apply_agency_erp_constants($row);
    } else {
        if (!defined('RATEB_ERP_DB_NAME')) {
            define('RATEB_ERP_DB_NAME', 'admin_rateb_erp_alarfaj');
        }
        if (!defined('RATEB_ERP_DB_HOST')) {
            define('RATEB_ERP_DB_HOST', (string) DB_HOST);
        }
        if (!defined('RATEB_ERP_DB_USER')) {
            define('RATEB_ERP_DB_USER', (string) DB_USER);
        }
        if (!defined('RATEB_ERP_DB_PASS')) {
            define('RATEB_ERP_DB_PASS', (string) DB_PASS);
        }
        if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
            define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
        }
        if (!defined('RATEB_ERP_AGENCY_RESOLVED')) {
            define('RATEB_ERP_AGENCY_RESOLVED', true);
        }
    }
}

if (!defined('RATEB_ERP_DB_NAME') || (string) RATEB_ERP_DB_NAME === '') {
    define('RATEB_ERP_DB_NAME', 'admin_rateb_erp_alarfaj');
}
if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
    define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
}
if (!defined('RATEB_ERP_AGENCY_RESOLVED')) {
    define('RATEB_ERP_AGENCY_RESOLVED', true);
}

define('SITE_URL', 'https://alarfaj.rateb.sa');
define('SINGLE_URL_MODE', true);
define('APP_NAME', 'RATEB');
define('APP_VERSION', '1.0.1');
define('BASE_URL', '');
define('NO_BANGLA', true);
define('OBSERVABILITY_DASHBOARD_ENABLED', true);
define('ADMIN_CONTROL_CENTER_ENABLED', true);

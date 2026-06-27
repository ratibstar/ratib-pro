<?php
/**
 * Staging host profile — dev.rateb.sa
 * ERP database: admin_rateb_dev ONLY (never production admin_rateb-erp).
 */
if (defined('DB_NAME')) {
    return;
}
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

define('RATEB_ERP_DB_NAME', 'admin_rateb_dev');

define('SITE_URL', 'https://dev.rateb.sa');
define('SINGLE_URL_MODE', true);
define('APP_NAME', 'RATEB');
define('APP_VERSION', '1.0.1');
define('BASE_URL', '');
define('NO_BANGLA', true);
define('OBSERVABILITY_DASHBOARD_ENABLED', true);
define('ADMIN_CONTROL_CENTER_ENABLED', true);

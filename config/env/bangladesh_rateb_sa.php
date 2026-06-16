<?php
/**
 * Bangladesh — separate data for bangladesh.rateb.sa
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
define('DB_USER', ($dbUser !== false && $dbUser !== '') ? (string) $dbUser : ratib_default_mysql_user());
define('DB_PASS', ($dbPass !== false && $dbPass !== '') ? (string) $dbPass : '');
define('DB_NAME', ($dbName !== false && $dbName !== '') ? (string) $dbName : (ratib_db_prefix() . '_bangladesh'));
define('CONTROL_PANEL_DB_NAME', getenv('CONTROL_PANEL_DB_NAME') ?: ratib_control_panel_database());

define('SITE_URL', 'https://bangladesh.rateb.sa');
define('APP_NAME', 'RATEB');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '');
define('NO_BANGLA', true);

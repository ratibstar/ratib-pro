<?php
/**
 * Control Panel DB credentials for agency/ERP host lookup (no session).
 */
declare(strict_types=1);

if (defined('CONTROL_DB_HOST')) {
    return;
}

$directadminDb = __DIR__ . DIRECTORY_SEPARATOR . 'directadmin_db.php';
if (is_file($directadminDb)) {
    require_once $directadminDb;
}

$host = getenv('CONTROL_DB_HOST') ?: getenv('DB_HOST');
$port = getenv('CONTROL_DB_PORT') ?: getenv('DB_PORT');
$user = getenv('CONTROL_DB_USER') ?: getenv('DB_USER');
$pass = getenv('CONTROL_DB_PASS');
if ($pass === false) {
    $pass = getenv('DB_PASS');
}
$name = getenv('CONTROL_PANEL_DB_NAME') ?: getenv('CONTROL_DB_NAME');

define('CONTROL_DB_HOST', ($host !== false && $host !== '') ? (string) $host : 'localhost');
define('CONTROL_DB_PORT', ($port !== false && $port !== '') ? (int) $port : 3306);
define('CONTROL_DB_USER', ($user !== false && $user !== '') ? (string) $user : (function_exists('rateb_default_mysql_user') ? rateb_default_mysql_user() : 'root'));
define('CONTROL_DB_PASS', $pass !== false ? (string) $pass : '');
define(
    'CONTROL_DB_NAME',
    ($name !== false && $name !== '')
        ? (string) $name
        : (function_exists('rateb_control_panel_database') ? rateb_control_panel_database() : 'admin_control_panel_db')
);

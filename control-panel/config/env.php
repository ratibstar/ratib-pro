<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/config/env.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/config/env.php`.
 */
/**
 * Control Panel - Standalone Environment
 * Use this when running the control panel separately from Ratib Pro.
 * On server: ensure DB_USER has access to CONTROL_PANEL_DB_NAME in cPanel → MySQL® Databases.
 */
if (defined('ENV_LOADED')) {
    return;
}
$e = function($k, $d) { $v = getenv($k); return ($v !== false && $v !== '') ? $v : $d; };
define('DB_HOST', $e('CONTROL_DB_HOST', 'localhost'));
define('DB_PORT', (int)$e('CONTROL_DB_PORT', '3306'));
define('DB_USER', $e('CONTROL_DB_USER', 'outratib_out'));
define('DB_PASS', $e('CONTROL_DB_PASS', '9s%BpMr1]dfb'));
define('CONTROL_PANEL_DB_NAME', $e('CONTROL_PANEL_DB_NAME', 'outratib_control_panel_db'));
define('DB_NAME', $e('CONTROL_DB_NAME', CONTROL_PANEL_DB_NAME));
/** Ratib Pro / N-Genius orders DB (usually outratib_out). Used to fill registration list from ngenius_reg_orders. */
define('RATIB_PRO_DB_NAME', $e('RATIB_PRO_DB_NAME', 'outratib_out'));
define('SITE_URL', $e('CONTROL_SITE_URL', 'https://out.ratib.sa'));
define('RATIB_PRO_URL', $e('RATIB_PRO_URL', SITE_URL));
// Designed app: pages/designed-launcher.php works without /Designed/ rewrites; override with full URL if needed.
define('DESIGNED_APP_URL', rtrim($e('DESIGNED_APP_URL', rtrim(RATIB_PRO_URL, '/') . '/pages/designed-launcher.php'), '/'));
define('APP_NAME', 'Ratib Control Panel');
define('APP_VERSION', '1.0.0');
// When running inside ratibprogram/control-panel/ subfolder, set base path so URLs work
$baseUrl = $e('CONTROL_BASE_URL', '');
if ($baseUrl === '' && isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/control-panel/') !== false) {
    $baseUrl = '/control-panel';
}
define('BASE_URL', $baseUrl);
define('IS_CONTROL_PANEL', true);
define('ENV_LOADED', true);

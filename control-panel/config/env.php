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
// Aliases used by newer services/scripts.
define('DB_DATABASE', $e('DB_DATABASE', DB_NAME));
define('DB_USERNAME', $e('DB_USERNAME', DB_USER));
define('DB_PASSWORD', $e('DB_PASSWORD', DB_PASS));
// Security/readiness keys (allow env override; keep safe defaults for setup checks).
define('EXTERNAL_API_TOKEN', $e('EXTERNAL_API_TOKEN', ''));
define('WEBHOOK_SIGNING_SECRET', $e('WEBHOOK_SIGNING_SECRET', ''));
define('SEC_RATE_LIMIT_IP_MAX', (int) $e('SEC_RATE_LIMIT_IP_MAX', '120'));
define('REQUEST_SIGNING_SECRET', $e('REQUEST_SIGNING_SECRET', ''));
/** Ratib Pro / N-Genius orders DB (usually outratib_out). Used to fill registration list from ngenius_reg_orders. */
define('RATIB_PRO_DB_NAME', $e('RATIB_PRO_DB_NAME', 'outratib_out'));
/** RATEB ERP — isolated database (all rateb_* tables). Grant DB_USER access in cPanel → MySQL®. */
define('RATEB_ERP_DB_NAME', $e('RATEB_ERP_DB_NAME', 'outratib_rateb-erp'));
/** Optional: dedicated MySQL user for ERP only (leave empty to use CONTROL_DB_USER / outratib_out). */
define('RATEB_ERP_DB_USER', $e('RATEB_ERP_DB_USER', ''));
define('RATEB_ERP_DB_PASS', $e('RATEB_ERP_DB_PASS', ''));
/** Deploy/automation token for rateb-erp/public/run-migrations.php (defaults to CPANEL API token env). */
define('RATEB_ERP_MIGRATE_TOKEN', $e('RATEB_ERP_MIGRATE_TOKEN', $e('CPANEL_API_TOKEN', '')));
define('SITE_URL', $e('CONTROL_SITE_URL', 'https://rateb.sa'));
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

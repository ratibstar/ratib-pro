<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env/out_ratib_sa.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env/out_ratib_sa.php`.
 */
/**
 * Main out.ratib.sa — no Bangla, English only.
 * Uses outratib_out DB for Ratib Pro. Control Panel uses control_panel_db (separate).
 */
if (defined('DB_NAME')) {
    return;
}
// Prefer project-root .env (merged in load.php via ratib_env_load_bridge_dotenv) so credentials are not only hardcoded.
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');
define('DB_HOST', ($dbHost !== false && $dbHost !== '') ? (string) $dbHost : 'localhost');
define('DB_PORT', ($dbPort !== false && $dbPort !== '') ? (int) $dbPort : 3306);
define('DB_USER', ($dbUser !== false && $dbUser !== '') ? (string) $dbUser : 'outratib_out');
define('DB_PASS', ($dbPass !== false && $dbPass !== '') ? (string) $dbPass : '9s%BpMr1]dfb');
define('DB_NAME', ($dbName !== false && $dbName !== '') ? (string) $dbName : 'outratib_out');
define('CONTROL_PANEL_DB_NAME', getenv('CONTROL_PANEL_DB_NAME') ?: 'outratib_control_panel_db');

define('SITE_URL', 'https://out.ratib.sa');
define('SINGLE_URL_MODE', true);  // All countries use same URL; DB switches by country selection
define('APP_NAME', 'Ratib Program');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '');
define('NO_BANGLA', true);
// Internal observability dashboard gate for this host.
define('OBSERVABILITY_DASHBOARD_ENABLED', true);
define('ADMIN_CONTROL_CENTER_ENABLED', true);

/*
 * Optional: if public_html/uploads is not writable by PHP, partner agency file uploads use
 * ../ratib_uploads automatically, or set an absolute path:
 *   define('RATIB_UPLOADS_BASE', '/home/outratib/ratib_uploads');
 */

/*
 * N-Genius Saudi (KSA) LIVE configuration.
 * Live host: https://api-gateway.ksa.ngenius-payments.com
 * Live realm: networkinternational
 * Credentials: config/ngenius.secrets.php (recommended).
 */
define('NGENIUS_IDENTITY_BASE', 'https://api-gateway.ksa.ngenius-payments.com');
define('NGENIUS_ORDER_BASE', 'https://api-gateway.ksa.ngenius-payments.com');
define('NGENIUS_API_BASE', 'https://api-gateway.ksa.ngenius-payments.com');
define('NGENIUS_TOKEN_URL', 'https://api-gateway.ksa.ngenius-payments.com/identity/auth/access-token');
define('NGENIUS_REALM', 'networkinternational');
/* Optional overrides (otherwise defaults from config/env.php): USD list prices → SAR at checkout.
define('NGENIUS_CHECKOUT_CURRENCY', 'SAR');
define('NGENIUS_USD_TO_SAR', '3.75');
*/

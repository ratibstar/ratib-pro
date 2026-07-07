<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env/rateb_sa.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env/rateb_sa.php`.
 */
/**
 * Main rateb.sa — no Bangla, English only.
 * DirectAdmin DBs: admin_control_panel_db (master CP), admin_rateb (Pro/orders), admin_* countries, admin_rateb-erp (ERP).
 */
if (defined('DB_NAME')) {
    return;
}
require_once __DIR__ . '/directadmin_db.php';
// Prefer project-root .env (merged in load.php via rateb_env_load_bridge_dotenv) so credentials are not only hardcoded.
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
if (!defined('RATEB_ERP_DB_NAME')) {
    $_erpDb = getenv('RATEB_ERP_DB_NAME');
    define('RATEB_ERP_DB_NAME', ($_erpDb !== false && $_erpDb !== '') ? (string) $_erpDb : rateb_erp_database_name());
}

define('SITE_URL', 'https://rateb.sa');
define('SINGLE_URL_MODE', true);  // All countries use same URL; DB switches by country selection
define('APP_NAME', 'RATEB');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '');
define('NO_BANGLA', true);

/*
 * Mobile portal JWT/QR signing — REQUIRED on production (api/mobile/).
 * Set in project-root .env: MOBILE_AUTH_SECRET=<long-random-string>
 * Never commit the secret. Without it, mobile login returns 503 config_error on rateb.sa.
 */
if (!defined('MOBILE_AUTH_SECRET')) {
    $_mobSecret = getenv('MOBILE_AUTH_SECRET');
    if ($_mobSecret !== false && trim((string) $_mobSecret) !== '') {
        define('MOBILE_AUTH_SECRET', (string) $_mobSecret);
    }
}

// Internal observability dashboard gate for this host.
define('OBSERVABILITY_DASHBOARD_ENABLED', true);
define('ADMIN_CONTROL_CENTER_ENABLED', true);

// ERP login bypass (off — normal login required). Set true only for temporary open access.
define('RATEB_ERP_LOGIN_BYPASS', false);

/*
 * Homepage CMS (rateb_site_content): default to DB-only resolution on this host so stale disk/json
 * snapshots cannot hide fresh saves. Override in .env: RATEB_SITE_CONTENT_PUBLIC_SOURCE=auto
 * RATEB_CMS_HOME_PUBLIC_SOURCE is also defined because some PHP-FPM pools strip putenv(); homepage reads the constant.
 */
if (!defined('RATEB_CMS_HOME_PUBLIC_SOURCE')) {
    $_ratebPubSrc = getenv('RATEB_SITE_CONTENT_PUBLIC_SOURCE');
    if ($_ratebPubSrc === false || trim((string) $_ratebPubSrc) === '') {
        define('RATEB_CMS_HOME_PUBLIC_SOURCE', 'db_only');
        putenv('RATEB_SITE_CONTENT_PUBLIC_SOURCE=db_only');
        $_ENV['RATEB_SITE_CONTENT_PUBLIC_SOURCE'] = 'db_only';
        $_SERVER['RATEB_SITE_CONTENT_PUBLIC_SOURCE'] = 'db_only';
    } else {
        define('RATEB_CMS_HOME_PUBLIC_SOURCE', trim((string) $_ratebPubSrc));
    }
}

/*
 * Optional: if public_html/uploads is not writable by PHP, partner agency file uploads use
 * ../rateb_uploads automatically, or set an absolute path:
 *   define('RATEB_UPLOADS_BASE', '/home/admin/rateb_uploads');
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


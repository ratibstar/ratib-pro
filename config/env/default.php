<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env/default.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env/default.php`.
 */
/**
 * Default fallback — used when no host-specific env file exists.
 * Same as Bangladesh. Add config/env/{host}.php for each new link (e.g. saudi_rateb_sa.php).
 */
if (defined('DB_NAME')) {
    return;
}
require_once __DIR__ . '/directadmin_db.php';
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', rateb_default_mysql_user());
define('DB_PASS', '');
define('DB_NAME', rateb_db_prefix() . '_bangladesh');
define('CONTROL_PANEL_DB_NAME', getenv('CONTROL_PANEL_DB_NAME') ?: rateb_control_panel_database());

define('SITE_URL', 'https://bangladesh.rateb.sa');
define('APP_NAME', 'RATEB');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '');
define('NO_BANGLA', true);

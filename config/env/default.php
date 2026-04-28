<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env/default.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env/default.php`.
 */
/**
 * Default fallback — used when no host-specific env file exists.
 * Same as Bangladesh. Add config/env/{host}.php for each new link (e.g. saudi_out_ratib_sa.php).
 */
if (defined('DB_NAME')) {
    return;
}
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'outratib_out');
define('DB_PASS', '9s%BpMr1]dfb');
define('DB_NAME', 'outratib_out');
define('CONTROL_PANEL_DB_NAME', getenv('CONTROL_PANEL_DB_NAME') ?: 'outratib_control_panel_db');

define('SITE_URL', 'https://bangladesh.out.ratib.sa');
define('APP_NAME', 'Ratib Program');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '');
define('NO_BANGLA', true);

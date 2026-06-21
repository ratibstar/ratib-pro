<?php
declare(strict_types=1);

/**
 * Load project .env + RCC DB credentials for standalone API/CLI (no Control Panel session).
 */
if (defined('RCC_ENV_LOADED')) {
    return;
}
define('RCC_ENV_LOADED', true);

$projectRoot = dirname(__DIR__, 2);

$compat = $projectRoot . '/includes/rateb-php74-compat.php';
if (is_file($compat)) {
    require_once $compat;
}

$dotenvBridge = $projectRoot . '/config/env/dotenv_bridge.php';
if (is_file($dotenvBridge)) {
    require_once $dotenvBridge;
    if (function_exists('rateb_bootstrap_project_dotenv')) {
        rateb_bootstrap_project_dotenv($projectRoot);
    }
}

$directadminDb = $projectRoot . '/config/env/directadmin_db.php';
if (is_file($directadminDb)) {
    require_once $directadminDb;
}

$envVal = static function (string $key, $default = '') {
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    if (defined($key)) {
        return constant($key);
    }
    return $default;
};

if (!defined('RATIB_CC_DB_NAME')) {
    $defaultDb = function_exists('rateb_contact_center_database_name')
        ? rateb_contact_center_database_name()
        : 'admin_call-center';
    define('RATIB_CC_DB_NAME', (string) $envVal('RATIB_CC_DB_NAME', $defaultDb));
}
if (!defined('RATIB_CC_DB_USER')) {
    $defaultUser = function_exists('rateb_contact_center_db_user')
        ? rateb_contact_center_db_user()
        : RATIB_CC_DB_NAME;
    define('RATIB_CC_DB_USER', (string) $envVal('RATIB_CC_DB_USER', $defaultUser));
}
if (!defined('RATIB_CC_DB_PASS')) {
    $pass = $envVal('RATIB_CC_DB_PASS', '');
    if ($pass === '' || $pass === null) {
        $pass = $envVal('RATEB_CC_DB_PASS', $envVal('DB_PASS', ''));
    }
    define('RATIB_CC_DB_PASS', (string) $pass);
}
if (!defined('RATIB_CC_DB_HOST')) {
    define('RATIB_CC_DB_HOST', (string) $envVal('RATIB_CC_DB_HOST', $envVal('DB_HOST', '127.0.0.1')));
}
if (!defined('RATIB_CC_DB_PORT')) {
    define('RATIB_CC_DB_PORT', (int) $envVal('RATIB_CC_DB_PORT', $envVal('DB_PORT', 3306)));
}

foreach ([
    'RATIB_CC_DB_NAME' => RATIB_CC_DB_NAME,
    'RATIB_CC_DB_USER' => RATIB_CC_DB_USER,
    'RATIB_CC_DB_PASS' => RATIB_CC_DB_PASS,
    'RATIB_CC_DB_HOST' => RATIB_CC_DB_HOST,
    'RATIB_CC_DB_PORT' => (string) RATIB_CC_DB_PORT,
] as $key => $value) {
    if (getenv($key) === false) {
        putenv($key . '=' . $value);
    }
}

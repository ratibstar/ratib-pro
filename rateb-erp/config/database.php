<?php
declare(strict_types=1);

$parentRoot = dirname(RATEB_ROOT, 1);
$parentEnv = $parentRoot . '/config/env/load.php';
if (is_file($parentEnv)) {
    require_once $parentEnv;
}

// Reuse main RATIB .env on out.ratib.sa (same DB as control panel)
$envFile = $parentRoot . '/.env';
if (is_file($envFile) && !getenv('DB_HOST')) {
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . trim($val, " \t\"'"));
        }
    }
}

// When bootstrapped from Control Panel, DB_* constants are already defined in control-panel/config/env.php.
if (!defined('RATEB_DB_HOST')) {
    if (defined('DB_HOST')) {
        define('RATEB_DB_HOST', (string) DB_HOST);
        define('RATEB_DB_PORT', defined('DB_PORT') ? (int) DB_PORT : 3306);
        define('RATEB_DB_NAME', defined('DB_NAME') ? (string) DB_NAME : (defined('CONTROL_PANEL_DB_NAME') ? (string) CONTROL_PANEL_DB_NAME : 'outratib_control_panel_db'));
        define('RATEB_DB_USER', defined('DB_USER') ? (string) DB_USER : 'root');
        define('RATEB_DB_PASS', defined('DB_PASS') ? (string) DB_PASS : '');
    } else {
        $dbHost = getenv('CONTROL_DB_HOST') ?: getenv('DB_HOST');
        $dbPort = getenv('CONTROL_DB_PORT') ?: getenv('DB_PORT');
        $dbName = getenv('CONTROL_PANEL_DB_NAME') ?: getenv('CONTROL_DB_NAME') ?: getenv('DB_NAME');
        $dbUser = getenv('CONTROL_DB_USER') ?: getenv('DB_USER');
        $dbPass = getenv('CONTROL_DB_PASS');
        if ($dbPass === false) {
            $dbPass = getenv('DB_PASS');
        }
        define('RATEB_DB_HOST', $dbHost !== false && $dbHost !== '' ? (string) $dbHost : '127.0.0.1');
        define('RATEB_DB_PORT', (int) ($dbPort !== false && $dbPort !== '' ? $dbPort : 3306));
        define('RATEB_DB_NAME', $dbName !== false && $dbName !== '' ? (string) $dbName : 'outratib_control_panel_db');
        define('RATEB_DB_USER', $dbUser !== false && $dbUser !== '' ? (string) $dbUser : 'root');
        define('RATEB_DB_PASS', $dbPass !== false ? (string) $dbPass : '');
    }
}

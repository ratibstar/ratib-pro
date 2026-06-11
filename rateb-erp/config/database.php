<?php
declare(strict_types=1);

if (!function_exists('rateb_erp_database_name')) {
    function rateb_erp_database_name(): string
    {
        if (defined('RATEB_ERP_DB_NAME') && (string) RATEB_ERP_DB_NAME !== '') {
            return (string) RATEB_ERP_DB_NAME;
        }
        $fromEnv = getenv('RATEB_ERP_DB_NAME');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        $legacy = getenv('RATEB_DB_NAME');
        if ($legacy !== false && $legacy !== '') {
            return (string) $legacy;
        }
        return 'outratib_rateb-erp';
    }
}

$parentRoot = dirname(RATEB_ROOT, 1);
$parentEnv = $parentRoot . '/config/env/load.php';
if (is_file($parentEnv)) {
    require_once $parentEnv;
}

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

// RATEB ERP uses its own database (outratib_rateb-erp) — NOT the control panel DB.
if (!defined('RATEB_DB_HOST')) {
    if (defined('DB_HOST')) {
        define('RATEB_DB_HOST', (string) DB_HOST);
        define('RATEB_DB_PORT', defined('DB_PORT') ? (int) DB_PORT : 3306);
        define('RATEB_DB_USER', defined('DB_USER') ? (string) DB_USER : 'root');
        define('RATEB_DB_PASS', defined('DB_PASS') ? (string) DB_PASS : '');
        define('RATEB_DB_NAME', rateb_erp_database_name());
    } else {
        $dbHost = getenv('CONTROL_DB_HOST') ?: getenv('DB_HOST');
        $dbPort = getenv('CONTROL_DB_PORT') ?: getenv('DB_PORT');
        $dbUser = getenv('CONTROL_DB_USER') ?: getenv('DB_USER');
        $dbPass = getenv('CONTROL_DB_PASS');
        if ($dbPass === false) {
            $dbPass = getenv('DB_PASS');
        }
        define('RATEB_DB_HOST', $dbHost !== false && $dbHost !== '' ? (string) $dbHost : '127.0.0.1');
        define('RATEB_DB_PORT', (int) ($dbPort !== false && $dbPort !== '' ? $dbPort : 3306));
        define('RATEB_DB_NAME', rateb_erp_database_name());
        define('RATEB_DB_USER', $dbUser !== false && $dbUser !== '' ? (string) $dbUser : 'root');
        define('RATEB_DB_PASS', $dbPass !== false ? (string) $dbPass : '');
    }
}

if (!defined('RATEB_ERP_DB_NAME')) {
    define('RATEB_ERP_DB_NAME', RATEB_DB_NAME);
}

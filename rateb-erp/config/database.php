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

if (!defined('RATEB_DB_HOST')) {
    define('RATEB_DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}
if (!defined('RATEB_DB_PORT')) {
    define('RATEB_DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
}
if (!defined('RATEB_DB_NAME')) {
    define('RATEB_DB_NAME', getenv('DB_NAME') ?: 'outratib_out');
}
if (!defined('RATEB_DB_USER')) {
    define('RATEB_DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('RATEB_DB_PASS')) {
    define('RATEB_DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');
}

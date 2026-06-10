<?php
declare(strict_types=1);

$parentEnv = dirname(RATEB_ROOT, 1) . '/config/env/load.php';
if (is_file($parentEnv)) {
    require_once $parentEnv;
}

define('RATEB_DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('RATEB_DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('RATEB_DB_NAME', getenv('DB_NAME') ?: 'rateb_erp');
define('RATEB_DB_USER', getenv('DB_USER') ?: 'root');
define('RATEB_DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');

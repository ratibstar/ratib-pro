<?php

declare(strict_types=1);

$parentEnv = dirname(__DIR__, 2) . '/config/env/load.php';
if (is_file($parentEnv)) {
    require_once $parentEnv;
}

if (!defined('RATEB_PLATFORM_CATALOG_DB_HOST')) {
    define('RATEB_PLATFORM_CATALOG_DB_HOST', getenv('RATEB_PLATFORM_CATALOG_DB_HOST') ?: '127.0.0.1');
}
if (!defined('RATEB_PLATFORM_CATALOG_DB_READ_HOST')) {
    define('RATEB_PLATFORM_CATALOG_DB_READ_HOST', getenv('RATEB_PLATFORM_CATALOG_DB_READ_HOST') ?: RATEB_PLATFORM_CATALOG_DB_HOST);
}
if (!defined('RATEB_PLATFORM_CATALOG_DB_PORT')) {
    define('RATEB_PLATFORM_CATALOG_DB_PORT', (int) (getenv('RATEB_PLATFORM_CATALOG_DB_PORT') ?: 3306));
}
if (!defined('RATEB_PLATFORM_CATALOG_DB_USER')) {
    define('RATEB_PLATFORM_CATALOG_DB_USER', getenv('RATEB_PLATFORM_CATALOG_DB_USER') ?: 'root');
}
if (!defined('RATEB_PLATFORM_CATALOG_DB_PASS')) {
    define('RATEB_PLATFORM_CATALOG_DB_PASS', getenv('RATEB_PLATFORM_CATALOG_DB_PASS') ?: '');
}
if (!defined('RATEB_PLATFORM_CATALOG_DB_NAME')) {
    define('RATEB_PLATFORM_CATALOG_DB_NAME', getenv('RATEB_PLATFORM_CATALOG_DB_NAME') ?: 'admin_rateb_platform_catalog');
}

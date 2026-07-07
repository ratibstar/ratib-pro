<?php

declare(strict_types=1);

if (!defined('RATEB_PLATFORM_CATALOG_STORAGE_PATH')) {
    define(
        'RATEB_PLATFORM_CATALOG_STORAGE_PATH',
        getenv('RATEB_PLATFORM_CATALOG_STORAGE_PATH') ?: (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/storage' : dirname(__DIR__) . '/storage')
    );
}

if (!defined('RATEB_PLATFORM_CATALOG_CDN_BASE')) {
    define('RATEB_PLATFORM_CATALOG_CDN_BASE', getenv('RATEB_PLATFORM_CATALOG_CDN_BASE') ?: '');
}

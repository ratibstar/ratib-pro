<?php

declare(strict_types=1);

/**
 * Phase 2.8 production release metadata — Release 2.8.1 (database search default).
 * Architecture baseline remains v1.3.1 (RATEB_PLATFORM_CATALOG_VERSION in app.php).
 */
if (!defined('RATEB_PLATFORM_CATALOG_RELEASE')) {
    define('RATEB_PLATFORM_CATALOG_RELEASE', '2.8.1');
}

if (!defined('RATEB_PLATFORM_CATALOG_PHASE')) {
    define('RATEB_PLATFORM_CATALOG_PHASE', '2.8');
}

if (!defined('RATEB_PLATFORM_CATALOG_ARCHITECTURE_VERSION')) {
    define(
        'RATEB_PLATFORM_CATALOG_ARCHITECTURE_VERSION',
        defined('RATEB_PLATFORM_CATALOG_VERSION') ? (string) RATEB_PLATFORM_CATALOG_VERSION : '1.3.1'
    );
}

if (!defined('RATEB_PLATFORM_CATALOG_BUILD_TIMESTAMP')) {
    define('RATEB_PLATFORM_CATALOG_BUILD_TIMESTAMP', '2026-07-08T06:15:00+03:00');
}

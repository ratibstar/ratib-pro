<?php

declare(strict_types=1);

/**
 * Deploy/automation — run RATEB Platform Catalog migrations (no control-panel login required).
 * Auth: X-Rateb-Migrate-Token must match CPANEL_API_TOKEN / RATEB_ERP_MIGRATE_TOKEN / storage token file.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/platform-catalog-bridge.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!control_platform_catalog_verify_migrate_token()) {
    http_response_code(403);
    exit("Forbidden\n");
}

if (!control_platform_catalog_is_installed()) {
    http_response_code(503);
    exit("ERROR: rateb-platform-catalog folder not found\n");
}

try {
    $log = control_platform_catalog_run_migrations();
    foreach ($log as $line) {
        echo $line, "\n";
    }
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ', $e->getMessage(), "\n";
}

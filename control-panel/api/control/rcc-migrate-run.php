<?php
/**
 * Deploy/automation — run RATIB Contact Center migrations (no control-panel login required).
 * Auth: X-Rateb-Migrate-Token must match CPANEL_API_TOKEN / RATEB_ERP_MIGRATE_TOKEN / storage token file.
 */
declare(strict_types=1);

if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/contact-center-bridge.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!control_contact_center_verify_migrate_token()) {
    http_response_code(403);
    exit("Forbidden\n");
}

if (!control_contact_center_is_installed()) {
    http_response_code(503);
    exit("ERROR: ratib-contact-center folder not found\n");
}

try {
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $log = control_contact_center_run_migrations();
    foreach ($log as $line) {
        echo $line, "\n";
    }
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ', $e->getMessage(), "\n";
}

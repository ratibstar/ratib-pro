<?php
/**
 * Deploy/automation — run RATEB ERP migrations (no control-panel login required).
 * Auth: X-Rateb-Migrate-Token must match CPANEL_API_TOKEN / RATEB_ERP_MIGRATE_TOKEN / storage token file.
 */
declare(strict_types=1);

if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/rateb-erp-bridge.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
if ($provided === '') {
    http_response_code(403);
    exit("Forbidden\n");
}

$expected = '';
if (defined('RATEB_ERP_MIGRATE_TOKEN') && (string) RATEB_ERP_MIGRATE_TOKEN !== '') {
    $expected = (string) RATEB_ERP_MIGRATE_TOKEN;
}
if ($expected === '') {
    $fromEnv = getenv('CPANEL_API_TOKEN');
    if ($fromEnv !== false && $fromEnv !== '') {
        $expected = (string) $fromEnv;
    }
}
$erpRoot = control_rateb_erp_root_path();
$tokenFile = $erpRoot . '/storage/.deploy-migrate-token';
if ($expected === '' && is_file($tokenFile)) {
    $expected = trim((string) file_get_contents($tokenFile));
}

if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit("Forbidden\n");
}

if (!control_rateb_erp_is_installed()) {
    http_response_code(503);
    exit("ERROR: rateb-erp folder not found\n");
}

try {
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $log = control_rateb_erp_run_migrations();
    foreach ($log as $line) {
        echo $line, "\n";
    }
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ', $e->getMessage(), "\n";
}

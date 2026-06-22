<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/control-panel/includes/config.php';
require_once dirname(__DIR__, 2) . '/control-panel/includes/control/contact-center-bridge.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!control_contact_center_verify_migrate_token()) {
    http_response_code(403);
    exit("Forbidden\n");
}

if (!control_contact_center_is_installed()) {
    http_response_code(503);
    exit("ERROR: ratib-contact-center not found\n");
}

try {
    if (function_exists('set_time_limit')) {
        @set_time_limit(60);
    }
    control_contact_center_apply_db_env();
    $before = control_contact_center_realtime_hub_status();
    echo 'before: running=' . ($before['running'] ? 'yes' : 'no') . ' port=' . $before['port'] . "\n";

    $result = control_contact_center_start_realtime_hub();
    echo 'start: ' . ($result['message'] ?? '') . "\n";
    if (!empty($result['error'])) {
        echo 'detail: ' . $result['error'] . "\n";
    }

    $after = control_contact_center_realtime_hub_status();
    echo 'after: running=' . ($after['running'] ? 'yes' : 'no') . ' pid=' . ($after['pid'] ?? 'none') . "\n";
    echo 'ws_url: ' . $after['ws_url'] . "\n";

    if ($after['running']) {
        echo "OK\n";
        exit(0);
    }

    http_response_code(503);
    echo "WARN: hub not listening on 127.0.0.1:" . $after['port'] . " — add cPanel cron (see REALTIME-HUB-RUN.txt)\n";
    exit(1);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}

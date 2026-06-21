<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/../../control-panel/includes/config.php';
require_once dirname(__DIR__) . '/../../control-panel/includes/control/contact-center-bridge.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
if ($provided === '') {
    http_response_code(403);
    exit("Forbidden\n");
}

$expected = getenv('CPANEL_API_TOKEN') ?: '';
if ($expected === '' || !hash_equals((string) $expected, $provided)) {
    $tokenFile = dirname(__DIR__, 2) . '/storage/deploy-migrate-token';
    if (is_file($tokenFile)) {
        $expected = trim((string) file_get_contents($tokenFile));
    }
}
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit("Forbidden\n");
}

control_contact_center_apply_db_env();
$result = control_contact_center_start_realtime_hub();
$status = control_contact_center_realtime_hub_status();
echo 'message: ' . ($result['message'] ?? '') . "\n";
echo 'running: ' . ($status['running'] ? 'yes' : 'no') . "\n";
echo 'ws_url: ' . $status['ws_url'] . "\n";
echo ($status['running'] ? "OK\n" : "WARN\n");

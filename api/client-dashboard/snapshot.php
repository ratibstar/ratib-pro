<?php
declare(strict_types=1);

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo '{"ok":false,"message":"method_not_allowed"}';
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/bootstrap.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Data/FallbackPayloads.php';
require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Orchestration/SnapshotOrchestrator.php';

ratib_client_dashboard_api_require_access();

$conn = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;

try {
    $payload = Ratib_ClientDashboard_SnapshotOrchestrator::build($conn);
} catch (Throwable $e) {
    require_once dirname(__DIR__, 2) . '/modules/client-dashboard/Data/SnapshotBuilder.php';
    $payload = Ratib_ClientDashboard_SnapshotBuilder::build($conn);
    $payload['observability'] = [
        'degraded_flags' => ['orchestrator_exception'],
        'adapter_events_tail' => [],
        'recent_actions_tail' => [],
    ];
    $payload['orchestrator_error'] = 'degraded_legacy_snapshot';
}

if (function_exists('apiUrl')) {
    $payload['links'] = [
        'infra_dashboard' => apiUrl('infrastructure-marketplace/dashboard.php'),
        'orders' => apiUrl('client-dashboard/orders.php'),
        'activities' => apiUrl('client-dashboard/activities.php'),
        'notifications' => apiUrl('client-dashboard/notifications.php'),
    ];
}

$encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    $payload = Ratib_ClientDashboard_FallbackPayloads::homeSnapshotEnvelope();
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
}
if (!is_string($encoded)) {
    $encoded = '{"ok":false,"message":"snapshot_encoding_failed"}';
}
echo $encoded;
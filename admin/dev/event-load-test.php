<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/dev/event-load-test.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/dev/event-load-test.php`.
 */
declare(strict_types=1);

/**
 * Generate synthetic events for stream/timeline/load testing.
 * HTTP: ?count=2000&spike=1&tenants=80&sim_workers=16&confirm=1
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/ControlCenterAccess.php';
require_once __DIR__ . '/../core/EventBus.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ADMIN_CONTROL_MODE')) {
    define('ADMIN_CONTROL_MODE', true);
}

header('Content-Type: application/json; charset=UTF-8');

if (!ControlCenterAccess::canAccessControlCenter()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'data' => [], 'meta' => ['request_id' => getRequestId(), 'event_count' => 0, 'error' => 'Forbidden']]);
    exit;
}

$count = max(1, min(100000, (int) ($_GET['count'] ?? 2000)));
$spike = ((string) ($_GET['spike'] ?? '')) === '1';
$tenantPool = max(1, min(5000, (int) ($_GET['tenants'] ?? 50)));
$simWorkers = max(1, min(128, (int) ($_GET['sim_workers'] ?? 8)));
$spikeEvery = max(2, min(500, (int) ($_GET['spike_every'] ?? 20)));
$confirm = ((string) ($_GET['confirm'] ?? '')) === '1';

if (!$confirm) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'meta' => [
            'request_id' => getRequestId(),
            'event_count' => 0,
            'hint' => 'Add confirm=1&count=2000&spike=1&tenants=80&sim_workers=16',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$emitted = 0;
$errors = 0;

try {
    $pdo = getControlDB();
    $baseTypes = ['CONTROL_LOG', 'QUERY_GATEWAY_POLICY', 'ADMIN_AUDIT', 'REQUEST_START'];
    $workerRequestIds = [];
    for ($w = 0; $w < $simWorkers; $w++) {
        $workerRequestIds[$w] = bin2hex(random_bytes(16));
    }
    for ($i = 0; $i < $count; $i++) {
        $worker = $i % $simWorkers;
        if ($i % 200 === 0) {
            $workerRequestIds[$worker] = bin2hex(random_bytes(16));
        }
        $rid = $workerRequestIds[$worker];
        $tenant = random_int(1, $tenantPool);
        $type = $baseTypes[$i % count($baseTypes)];
        $level = 'info';
        if ($spike && $i % $spikeEvery === 0) {
            $type = 'QUERY_EXECUTION_FAILED';
            $level = 'error';
        }
        try {
            emitEvent($type, $level, 'Load test event ' . $i, [
                'source' => 'event_load_test',
                'request_id' => $rid,
                'tenant_id' => $tenant,
                'endpoint' => '/admin/dev/event-load-test.php',
                'query' => 'SELECT * FROM tenants WHERE id = ' . (string) $tenant,
                'mode' => 'SAFE',
                'duration_ms' => random_int(1, 120),
                'priority' => $level === 'error' ? 'high' : 'low',
            ], $pdo);
            $emitted++;
        } catch (Throwable $e) {
            $errors++;
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => [['emitted' => $emitted, 'errors' => $errors]],
        'meta' => ['request_id' => getRequestId(), 'event_count' => 0, 'error' => $e->getMessage()],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [[
        'emitted' => $emitted,
        'errors' => $errors,
        'requested' => $count,
        'spike' => $spike,
        'tenant_pool' => $tenantPool,
        'simulated_workers' => $simWorkers,
        'spike_every' => $spikeEvery,
    ]],
    'meta' => ['request_id' => getRequestId(), 'event_count' => 1],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

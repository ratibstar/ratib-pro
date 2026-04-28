<?php
declare(strict_types=1);

define('TENANT_REQUIRED', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/ensure-worker-tracking-schema.php';
require_once __DIR__ . '/../../admin/core/EventBus.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

function tracking_latest_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        tracking_latest_json(['success' => false, 'message' => 'GET required'], 405);
    }
    $tenantId = TenantExecutionContext::getTenantId();
    if ($tenantId === null || $tenantId <= 0) {
        tracking_latest_json(['success' => false, 'message' => 'Tenant context missing'], 403);
    }
    $tenantId = (int) $tenantId;

    $controlPdo = getControlDB();
    ratibEnsureWorkerTrackingSchema($controlPdo);

    $workerId = isset($_GET['worker_id']) ? (int) $_GET['worker_id'] : 0;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
    $limit = max(1, min(500, $limit));

    if ($workerId > 0) {
        $st = $controlPdo->prepare(
            "SELECT s.worker_id, s.tenant_id, s.last_seen, s.status AS session_status,
                    s.last_lat AS lat, s.last_lng AS lng, s.last_speed AS speed, s.last_battery AS battery, s.last_source AS source
             FROM worker_tracking_sessions s
             WHERE s.tenant_id = ? AND s.worker_id = ?
             LIMIT 1"
        );
        $st->execute([$tenantId, $workerId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        tracking_latest_json(['success' => true, 'data' => $row ? [$row] : []]);
    }

    $st = $controlPdo->prepare(
        "SELECT s.worker_id, s.tenant_id, s.last_seen, s.status AS session_status,
                s.last_lat AS lat, s.last_lng AS lng, s.last_speed AS speed, s.last_battery AS battery, s.last_source AS source
         FROM worker_tracking_sessions s
         WHERE s.tenant_id = ?
         ORDER BY s.last_seen DESC
         LIMIT {$limit}"
    );
    $st->execute([$tenantId]);
    tracking_latest_json(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    tracking_latest_json(['success' => false, 'message' => $e->getMessage()], 500);
}

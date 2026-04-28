<?php
declare(strict_types=1);

define('TENANT_REQUIRED', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/ensure-worker-tracking-schema.php';
require_once __DIR__ . '/../../admin/core/EventBus.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

function history_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        history_json(['success' => false, 'message' => 'GET required'], 405);
    }

    $tenantId = TenantExecutionContext::getTenantId();
    if ($tenantId === null || $tenantId <= 0) {
        history_json(['success' => false, 'message' => 'Tenant context missing'], 403);
    }
    $tenantId = (int) $tenantId;

    $workerId = isset($_GET['worker_id']) ? (int) $_GET['worker_id'] : 0;
    if ($workerId <= 0) {
        history_json(['success' => false, 'message' => 'worker_id required'], 422);
    }

    $fromRaw = trim((string) ($_GET['from'] ?? ''));
    $toRaw = trim((string) ($_GET['to'] ?? ''));
    $fromTs = $fromRaw !== '' ? strtotime($fromRaw) : strtotime('-6 hours');
    $toTs = $toRaw !== '' ? strtotime($toRaw) : time();
    if ($fromTs === false || $toTs === false || $fromTs > $toTs) {
        history_json(['success' => false, 'message' => 'Invalid date range'], 422);
    }
    $from = date('Y-m-d H:i:s', $fromTs);
    $to = date('Y-m-d H:i:s', $toTs);

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 2000;
    $limit = max(10, min(10000, $limit));

    $controlPdo = getControlDB();
    ratibEnsureWorkerTrackingSchema($controlPdo);

    $sql = "SELECT id, worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at
            FROM worker_locations
            WHERE tenant_id = :tenant_id
              AND worker_id = :worker_id
              AND recorded_at BETWEEN :from_ts AND :to_ts
            ORDER BY recorded_at ASC, id ASC
            LIMIT {$limit}";
    $st = $controlPdo->prepare($sql);
    $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $st->bindValue(':worker_id', $workerId, PDO::PARAM_INT);
    $st->bindValue(':from_ts', $from);
    $st->bindValue(':to_ts', $to);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    history_json([
        'success' => true,
        'data' => [
            'worker_id' => $workerId,
            'tenant_id' => $tenantId,
            'from' => $from,
            'to' => $to,
            'count' => count($rows),
            'path' => $rows,
        ],
    ]);
} catch (Throwable $e) {
    history_json(['success' => false, 'message' => $e->getMessage()], 500);
}

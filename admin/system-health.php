<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/system-health.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/system-health.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/core/ControlCenterAccess.php';
require_once __DIR__ . '/core/EventBus.php';
require_once __DIR__ . '/core/EventMetricsAggregator.php';
require_once __DIR__ . '/core/EventQueue.php';

if (!ControlCenterAccess::canAccessControlCenter()) {
    eventApiResponse(false, [], ['request_id' => getRequestId(), 'event_count' => 0, 'error' => 'Forbidden'], 403);
}

try {
    $pdo = getControlDB();
    $critical = $pdo->query(
        "SELECT id, event_type, level, tenant_id, request_id, message, created_at
         FROM system_events
         WHERE level = 'critical'
         ORDER BY id DESC
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: null;

    $errorRateStmt = $pdo->query(
        "SELECT
            SUM(CASE WHEN level IN ('error','critical') THEN 1 ELSE 0 END) AS err_count,
            COUNT(*) AS total_count
         FROM system_events
         WHERE created_at > (NOW() - INTERVAL 5 MINUTE)"
    );
    $errorRateRow = $errorRateStmt ? ($errorRateStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
    $errCount = (int) ($errorRateRow['err_count'] ?? 0);
    $totalCount = (int) ($errorRateRow['total_count'] ?? 0);
    $errorRate = $totalCount > 0 ? round(($errCount / $totalCount) * 100, 2) : 0.0;

    $activeTenants = 0;
    $chk = $pdo->prepare('SHOW TABLES LIKE :t');
    $chk->execute([':t' => 'tenants']);
    if ((bool) $chk->fetchColumn()) {
        $st = $pdo->query("SELECT COUNT(*) AS c FROM tenants WHERE status = 'active'");
        $activeTenants = (int) (($st ? ($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) : 0));
    }

    $queryRateStmt = $pdo->query(
        "SELECT COUNT(*) AS c
         FROM system_events
         WHERE event_type = 'QUERY_EXECUTED'
           AND created_at > (NOW() - INTERVAL 1 MINUTE)"
    );
    $queryRate = (int) (($queryRateStmt ? ($queryRateStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) : 0));

    $liveMetrics = EventMetricsAggregator::snapshot($pdo);

    $queueDepth = 0;
    try {
        $queueDepth = EventQueue::queueSize($pdo);
    } catch (Throwable $e) {
        $queueDepth = 0;
    }

    eventApiResponse(true, [[
        'health' => [
            'last_critical_event' => $critical,
            'error_rate_percent_5m' => $errorRate,
            'active_tenants' => $activeTenants,
            'query_rate_per_minute' => $queryRate,
            'event_queue_depth' => $queueDepth,
            'live_metrics' => $liveMetrics,
        ],
    ]], [
        'request_id' => getRequestId(),
        'event_count' => 1,
    ]);
} catch (Throwable $e) {
    emitEvent('SYSTEM_HEALTH_ERROR', 'error', 'Failed to load system health', [
        'source' => 'system_health',
        'error' => $e->getMessage(),
    ]);
    eventApiResponse(false, [], [
        'request_id' => getRequestId(),
        'event_count' => 0,
        'error' => 'System health unavailable',
    ], 500);
}

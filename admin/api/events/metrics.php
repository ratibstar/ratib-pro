<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/api/events/metrics.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/api/events/metrics.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ADMIN_CONTROL_MODE')) {
    define('ADMIN_CONTROL_MODE', true);
}
require_once __DIR__ . '/../../core/ControlCenterAccess.php';
require_once __DIR__ . '/../../core/EventBus.php';
require_once __DIR__ . '/../../core/EventRepository.php';
require_once __DIR__ . '/../../core/EventMetricsAggregator.php';

if (!ControlCenterAccess::canAccessControlCenter()) {
    eventApiResponse(false, [], ['request_id' => getRequestId(), 'event_count' => 0, 'error' => 'Forbidden'], 403);
}

$tenantId = (int) ($_GET['tenant_id'] ?? 0);
$role = ControlCenterAccess::role();
if ($role !== ControlCenterAccess::SUPER_ADMIN && $tenantId <= 0) {
    eventApiResponse(false, [], ['request_id' => getRequestId(), 'event_count' => 0, 'error' => 'tenant_id required'], 400);
}

try {
    $pdo = getControlDB();
    $live = EventMetricsAggregator::snapshot($pdo);
    $where = '';
    if ($tenantId > 0) {
        $where = ' WHERE tenant_id = :tid ';
    }

    $q1 = "SELECT event_type, COUNT(*) AS c
           FROM system_events
           {$where}
           GROUP BY event_type
           ORDER BY c DESC
           LIMIT 10";
    $stmt = $pdo->prepare($q1);
    if ($tenantId > 0) {
        $stmt->bindValue(':tid', $tenantId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $top = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $metrics = [
        'events_per_minute' => (int) ($live['events_per_minute'] ?? 0),
        'error_rate' => (float) ($live['error_rate_percent'] ?? 0),
        'top_event_types' => $top,
        'query_throughput' => (int) ($live['query_throughput_minute'] ?? 0),
        'active_tenants' => (int) ($live['active_tenants_estimate'] ?? 0),
    ];
    eventApiResponse(true, [$metrics], [
        'request_id' => getRequestId(),
        'event_count' => 1,
    ]);
} catch (Throwable $e) {
    eventApiResponse(false, [], [
        'request_id' => getRequestId(),
        'event_count' => 0,
        'error' => 'Metrics failed',
    ], 500);
}


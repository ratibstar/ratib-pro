<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/api/events/export.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/api/events/export.php`.
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
require_once __DIR__ . '/../../core/EventExporter.php';

if (!ControlCenterAccess::canAccessControlCenter()) {
    eventApiResponse(false, [], ['request_id' => getRequestId(), 'event_count' => 0, 'error' => 'Forbidden'], 403);
}

$role = ControlCenterAccess::role();
$format = strtolower(trim((string) ($_GET['format'] ?? 'json')));
$tenantId = (int) ($_GET['tenant_id'] ?? 0);
$limit = max(1, min(1000, (int) ($_GET['limit'] ?? 200)));

if ($role !== ControlCenterAccess::SUPER_ADMIN && $tenantId <= 0) {
    eventApiResponse(false, [], ['request_id' => getRequestId(), 'event_count' => 0, 'error' => 'tenant_id required'], 400);
}

$filters = [];
if ($tenantId > 0) {
    $filters['tenant_id'] = $tenantId;
}
if (!empty($_GET['from'])) {
    $filters['from'] = (string) $_GET['from'];
}
if (!empty($_GET['to'])) {
    $filters['to'] = (string) $_GET['to'];
}
if (!empty($_GET['event_type'])) {
    $filters['event_type'] = (string) $_GET['event_type'];
}
if (!empty($_GET['level'])) {
    $filters['level'] = (string) $_GET['level'];
}

try {
    $events = EventRepository::latest($filters, $limit);
    if ($format === 'otlp') {
        eventApiResponse(true, [EventExporter::asOtlpLike($events)], [
            'request_id' => getRequestId(),
            'event_count' => count($events),
            'format' => 'otlp-like',
        ]);
    }
    eventApiResponse(true, EventExporter::asJsonStream($events), [
        'request_id' => getRequestId(),
        'event_count' => count($events),
        'format' => 'json',
    ]);
} catch (Throwable $e) {
    eventApiResponse(false, [], [
        'request_id' => getRequestId(),
        'event_count' => 0,
        'error' => 'Export failed',
    ], 500);
}


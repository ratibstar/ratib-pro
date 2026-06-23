<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bootstrap.php';
require_once dirname(__DIR__) . '/control-panel-session.php';

use Ratib\ContactCenter\App\Application\Services\ReportService;
use Ratib\ContactCenter\App\Core\Security\ApiAuthMiddleware;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;

header('Content-Type: application/json; charset=utf-8');

try {
    (new ApiAuthMiddleware())->authenticate(['rcc.reports.view']);
    TenantContext::set(AuthContext::tenantId());

    $tenantId = AuthContext::tenantId();
    $type = (string) ($_GET['type'] ?? 'agents');
    $from = (string) ($_GET['from'] ?? gmdate('Y-m-d', strtotime('-7 days')));
    $to = (string) ($_GET['to'] ?? gmdate('Y-m-d 23:59:59'));
    $export = (string) ($_GET['export'] ?? '') === 'csv';

    $service = new ReportService();
    $rows = match ($type) {
        'queues' => $service->queuePerformance($tenantId, $from, $to),
        'sla' => $service->slaReport($tenantId, $from, $to),
        'calls' => $service->callReport($tenantId, $from, $to),
        'conversations' => $service->conversationReport($tenantId, $from, $to),
        'ai' => $service->aiReport($tenantId, $from, $to),
        default => $service->agentPerformance($tenantId, $from, $to),
    };

    if ($export) {
        AuthContext::requirePermission('rcc.reports.export');
        $filename = 'rcc-' . $type . '-' . gmdate('YmdHis') . '.csv';
        $path = $service->exportCsv($filename, $rows);
        $base = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api/v1/reports.php')), '/');
        $downloadUrl = $base . '/report-download.php?download=' . rawurlencode(basename($path));
        echo json_encode([
            'ok' => true,
            'export' => basename($path),
            'download_url' => $downloadUrl,
            'rows' => count($rows),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true, 'type' => $type, 'rows' => $rows, 'count' => count($rows)], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

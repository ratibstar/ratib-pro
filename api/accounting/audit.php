<?php
declare(strict_types=1);

require_once __DIR__ . '/control/bootstrap.php';

use App\Accounting\Admin\Services\AccountingControlService;

accounting_control_require_auth('accounting.audit');

$svc = new AccountingControlService();
$filters = accounting_control_filters();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $data = $svc->listAuditLogs($filters);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="accounting-audit-logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['event_uuid', 'action', 'system', 'status', 'created_at']);
    foreach ($data['rows'] as $row) {
        fputcsv($out, [$row['event_uuid'], $row['action'], $row['system'], $row['status'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

$packs = $svc->listEvidencePacks($filters);
accounting_control_json([
    'ok' => true,
    'data' => [
        'logs' => $svc->listAuditLogs($filters),
        'evidence_packs' => $packs,
    ],
]);

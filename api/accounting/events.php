<?php
declare(strict_types=1);

require_once __DIR__ . '/control/bootstrap.php';

use App\Accounting\Admin\Services\AccountingControlService;

accounting_control_require_auth('accounting.events');

$svc = new AccountingControlService();
$filters = accounting_control_filters();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $data = $svc->listEvents($filters);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="accounting-events.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['event_uuid', 'source_system', 'event_type', 'status', 'company_id', 'branch_id', 'created_at']);
    foreach ($data['rows'] as $row) {
        fputcsv($out, [
            $row['event_uuid'],
            $row['source_system'],
            $row['event_type'],
            $row['status'],
            $row['company_id'],
            $row['branch_id'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

accounting_control_json(['ok' => true, 'data' => $svc->listEvents($filters)]);

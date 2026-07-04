<?php
declare(strict_types=1);

require_once __DIR__ . '/control/bootstrap.php';

use App\Accounting\Admin\Services\AccountingControlService;

accounting_control_require_auth('accounting.reconciliation');

$svc = new AccountingControlService();
$filters = accounting_control_filters();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST') {
    $action = (string) ($filters['action'] ?? 'reconcile');
    if ($action === 'execute') {
        if (empty($filters['confirm'])) {
            accounting_control_json(['ok' => false, 'message' => 'Confirmation required'], 400);
        }
        $proposal = is_array($filters['proposal'] ?? null) ? $filters['proposal'] : [];
        $options = [
            'dry_run' => !empty($filters['dry_run']),
            'approved' => !empty($filters['approved']),
        ];
        accounting_control_json(['ok' => true, 'data' => $svc->executeCorrection($proposal, $options)]);
    }
    accounting_control_json(['ok' => true, 'data' => $svc->reconcile($filters)]);
}

accounting_control_json(['ok' => true, 'data' => $svc->listReconciliationReports($filters)]);

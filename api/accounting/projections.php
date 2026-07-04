<?php
declare(strict_types=1);

require_once __DIR__ . '/control/bootstrap.php';

use App\Accounting\Admin\Services\AccountingControlService;

accounting_control_require_auth('accounting.projections');

$svc = new AccountingControlService();
$filters = accounting_control_filters();
$type = (string) ($filters['type'] ?? 'trial_balance');

$tableMap = [
    'trial_balance' => 'accounting_trial_balance_snapshots',
    'balance_sheet' => 'accounting_balance_sheet_snapshots',
    'profit_loss' => 'accounting_profit_loss_snapshots',
    'cashflow' => 'accounting_cashflow_snapshots',
];

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($filters['action'] ?? '');
    if ($action === 'rebuild') {
        if (empty($filters['confirm'])) {
            accounting_control_json(['ok' => false, 'message' => 'Confirmation required'], 400);
        }
        accounting_control_json(['ok' => true, 'data' => $svc->rebuildSnapshots($filters)]);
    }
}

$table = $tableMap[$type] ?? $tableMap['trial_balance'];
accounting_control_json(['ok' => true, 'data' => $svc->listProjections($table, $filters)]);

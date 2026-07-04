<?php
declare(strict_types=1);

require_once __DIR__ . '/control/bootstrap.php';

use App\Accounting\Admin\Services\AccountingControlService;

accounting_control_require_auth('accounting.consolidation');

$svc = new AccountingControlService();
$filters = accounting_control_filters();
$type = (string) ($filters['type'] ?? 'trial_balance');

$tableMap = [
    'trial_balance' => 'accounting_consolidated_trial_balance',
    'balance_sheet' => 'accounting_consolidated_balance_sheet',
    'profit_loss' => 'accounting_consolidated_profit_loss',
];

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (empty($filters['confirm'])) {
        accounting_control_json(['ok' => false, 'message' => 'Confirmation required'], 400);
    }
    accounting_control_json(['ok' => true, 'data' => $svc->runConsolidation($filters)]);
}

$table = $tableMap[$type] ?? $tableMap['trial_balance'];
accounting_control_json(['ok' => true, 'data' => $svc->listConsolidated($table, $filters)]);

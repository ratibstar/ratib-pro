<?php
declare(strict_types=1);

require_once __DIR__ . '/control/bootstrap.php';

use App\Accounting\Admin\Services\AccountingControlService;

accounting_control_require_auth('accounting.replay');

$svc = new AccountingControlService();
$filters = accounting_control_filters();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $dryRun = !empty($filters['dry_run']);
    accounting_control_json(['ok' => true, 'data' => $svc->replay($filters, $dryRun)]);
}

$dryRun = !empty($filters['dry_run']);
if ($dryRun) {
    accounting_control_json(['ok' => true, 'data' => $svc->replay($filters, true)]);
}

$confirm = !empty($filters['confirm']);
if (!$confirm) {
    accounting_control_json(['ok' => false, 'message' => 'Confirmation required. Set confirm=1'], 400);
}

accounting_control_json(['ok' => true, 'data' => $svc->replay($filters, false)]);

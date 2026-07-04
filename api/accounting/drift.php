<?php
declare(strict_types=1);

require_once __DIR__ . '/control/bootstrap.php';

use App\Accounting\Admin\Services\AccountingControlService;

accounting_control_require_auth('accounting.drift');

$svc = new AccountingControlService();
$filters = accounting_control_filters();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    accounting_control_json(['ok' => true, 'data' => $svc->detectDrift($filters)]);
}

accounting_control_json([
    'ok' => true,
    'data' => [
        'reports' => $svc->listDriftReports($filters),
        'live' => !empty($filters['run']) ? $svc->detectDrift($filters) : null,
    ],
]);

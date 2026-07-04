<?php
declare(strict_types=1);

require_once __DIR__ . '/control/bootstrap.php';

use App\Accounting\Admin\Services\AccountingControlService;

accounting_control_require_auth('accounting.integrity');

$svc = new AccountingControlService();
$filters = accounting_control_filters();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($filters['certify'])) {
    accounting_control_json(['ok' => true, 'data' => $svc->integrityOverview($filters)]);
}

accounting_control_json([
    'ok' => true,
    'data' => [
        'overview' => $svc->integrityOverview($filters),
        'evidence_packs' => $svc->listEvidencePacks($filters),
    ],
]);

<?php
declare(strict_types=1);

/**
 * Phase 5 CLI — Financial Integrity Layer
 *
 * Usage:
 *   php scripts/accounting-phase5-cli.php reconcile --company=12 --from=2026-01-01 --to=2026-01-31
 *   php scripts/accounting-phase5-cli.php goldenLedger --company=12 --from=2026-01-01 --to=2026-01-31
 *   php scripts/accounting-phase5-cli.php conflicts --company=12 --from=2026-01-01 --to=2026-01-31
 *   php scripts/accounting-phase5-cli.php executeCorrection --company=12 --key=recon-abc [--dry-run=1] [--approved=1]
 *   php scripts/accounting-phase5-cli.php certify --company=12 --from=2026-01-01 --to=2026-01-31
 *   php scripts/accounting-phase5-cli.php lockCheck --company=12 --date=2026-01-15
 */

$root = dirname(__DIR__);
require_once $root . '/config/env/load.php';
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register($root . '/app');

use App\Accounting\Drift\AccountingDriftDetector;
use App\Accounting\Integrity\AccountingAuditCertificationEngine;
use App\Accounting\Integrity\AccountingCorrectionExecutor;
use App\Accounting\Integrity\AccountingGoldenLedgerResolver;
use App\Accounting\Integrity\AccountingLedgerLockManager;
use App\Accounting\Integrity\AccountingReconciliationEngine;
use App\Accounting\Integrity\IntegrityRepository;

$args = $argv ?? [];
$command = $args[1] ?? 'help';

$params = [];
foreach (array_slice($args, 2) as $arg) {
    if (preg_match('/^--(\w+)=(.+)$/', $arg, $m)) {
        $params[$m[1]] = $m[2];
    }
}

$companyId = (int) ($params['company'] ?? 0);
$from = (string) ($params['from'] ?? date('Y-m-01'));
$to = (string) ($params['to'] ?? date('Y-m-t'));
$branchId = isset($params['branch']) ? (int) $params['branch'] : null;
$context = [
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'period_from' => $from,
    'period_to' => $to,
];

switch ($command) {
    case 'reconcile':
        $drift = (new AccountingDriftDetector())->detectDrift($context);
        $report = (new AccountingReconciliationEngine())->reconcileFromDrift($drift, $context);
        echo json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit($report->hasUnresolvedDrift() ? 2 : 0);

    case 'goldenLedger':
        $view = (new AccountingGoldenLedgerResolver())->resolve($context);
        echo json_encode($view->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);

    case 'conflicts':
        $conflicts = (new AccountingGoldenLedgerResolver())->detectConflicts($context);
        echo json_encode($conflicts->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit($conflicts->hasConflicts() ? 2 : 0);

    case 'executeCorrection':
        $key = (string) ($params['key'] ?? '');
        $dryRun = !isset($params['dry-run']) || $params['dry-run'] !== '0';
        $approved = isset($params['approved']) && $params['approved'] === '1';
        $repo = new IntegrityRepository();
        $logs = $repo->fetchCorrectionLog($companyId);
        $proposal = null;
        foreach ($logs as $log) {
            if (($log['idempotency_key'] ?? '') === $key) {
                $proposal = $log['payload'];
                break;
            }
        }
        if ($proposal === null && $key !== '') {
            $proposal = [
                'idempotency_key' => $key,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'period_from' => $from,
                'period_to' => $to,
                'type' => 'adjustment',
                'lines' => [],
            ];
        }
        if ($proposal === null) {
            echo json_encode(['ok' => false, 'message' => 'No proposal found'], JSON_PRETTY_PRINT) . "\n";
            exit(1);
        }
        $result = (new AccountingCorrectionExecutor())->execute($proposal, [
            'dry_run' => $dryRun,
            'approved' => $approved,
        ]);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit($result['ok'] ? 0 : 1);

    case 'certify':
        $drift = (new AccountingDriftDetector())->detectDrift($context);
        $pack = (new AccountingAuditCertificationEngine())->certify($drift, $context);
        echo json_encode($pack->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);

    case 'lockCheck':
        $date = (string) ($params['date'] ?? date('Y-m-d'));
        $verdict = (new AccountingLedgerLockManager())->assertMutable($companyId, $date, $branchId, 'create');
        echo json_encode($verdict->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit($verdict->isBlocked() ? 2 : 0);

    default:
        echo "Commands: reconcile | goldenLedger | conflicts | executeCorrection | certify | lockCheck\n";
        echo "Options: --company=ID --from=YYYY-MM-DD --to=YYYY-MM-DD [--branch=ID]\n";
        exit(1);
}

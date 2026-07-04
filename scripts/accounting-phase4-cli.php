<?php
declare(strict_types=1);

/**
 * Phase 4 CLI — Enterprise Financial Projection & Consolidation
 *
 * Usage:
 *   php scripts/accounting-phase4-cli.php rebuild --company=12 --from=2026-01-01 --to=2026-01-31
 *   php scripts/accounting-phase4-cli.php closePeriod --company=12 --from=2026-01-01 --to=2026-01-31
 *   php scripts/accounting-phase4-cli.php runConsolidation --company=12 --from=2026-01-01 --to=2026-01-31
 *   php scripts/accounting-phase4-cli.php detectDrift --company=12 --from=2026-01-01 --to=2026-01-31
 */

$root = dirname(__DIR__);
require_once $root . '/config/env/load.php';
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register($root . '/app');

use App\Accounting\Closing\AccountingPeriodCloser;
use App\Accounting\Consolidation\AccountingConsolidationEngine;
use App\Accounting\Drift\AccountingDriftDetector;
use App\Accounting\Projections\AccountingSnapshotRebuilder;

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

switch ($command) {
    case 'rebuild':
        $result = (new AccountingSnapshotRebuilder())->rebuild($companyId, $from, $to);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit($result['ok'] ? 0 : 1);

    case 'closePeriod':
        $result = (new AccountingPeriodCloser())->closePeriod($companyId, $from, $to);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit($result['ok'] ? 0 : 1);

    case 'runConsolidation':
        $result = (new AccountingConsolidationEngine())->runConsolidation([
            'company_id' => $companyId,
            'period_start' => $from,
            'period_end' => $to,
        ]);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);

    case 'detectDrift':
        $report = (new AccountingDriftDetector())->detectDrift([
            'company_id' => $companyId,
            'period_start' => $from,
            'period_end' => $to,
        ]);
        echo json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit($report->hasDrift() ? 2 : 0);

    default:
        echo "Commands: rebuild | closePeriod | runConsolidation | detectDrift\n";
        echo "Options: --company=ID --from=YYYY-MM-DD --to=YYYY-MM-DD\n";
        exit(1);
}

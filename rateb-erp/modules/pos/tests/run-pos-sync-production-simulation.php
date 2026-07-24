<?php

declare(strict_types=1);

/**
 * Phase 15.2 production simulation runner.
 *
 * Usage:
 *   POS_V2_INTEGRATION_SEED=1 php modules/pos/tests/run-pos-sync-production-simulation.php
 */

require_once __DIR__ . '/PosSyncProductionSimulationTest.php';

$runner = new PosSyncProductionSimulationTest();
$out = $runner->run();
$results = $out['results'];
$perf = $out['perf'];
$summary = $out['summary'];

$failed = 0;
$passList = [];
$failList = [];

echo str_repeat('=', 64) . PHP_EOL;
echo 'PHASE 15.2 POS PRODUCTION SIMULATION' . PHP_EOL;
echo str_repeat('=', 64) . PHP_EOL;

foreach ($results as $row) {
    $mark = $row['passed'] ? 'PASS' : 'FAIL';
    $line = $mark . ' [' . $row['id'] . '] ' . $row['name'] . ' — ' . $row['detail'];
    echo $line . PHP_EOL;
    if ($row['passed']) {
        $passList[] = $row['id'] . ': ' . $row['name'];
    } else {
        $failList[] = $row['id'] . ': ' . $row['name'] . ' (' . $row['detail'] . ')';
        $failed++;
    }
}

echo PHP_EOL . '--- Performance ---' . PHP_EOL;
foreach ($perf as $k => $v) {
    echo $k . ': ' . $v . PHP_EOL;
}

echo PHP_EOL . str_repeat('=', 64) . PHP_EOL;
echo 'A. Simulation coverage: ' . ($summary['scenarios_passed'] ?? 0) . '/' . ($summary['scenarios_total'] ?? 11) . PHP_EOL;
echo 'B. PASS (' . count($passList) . ')' . PHP_EOL;
foreach ($passList as $p) {
    echo '   - ' . $p . PHP_EOL;
}
echo 'C. FAIL (' . count($failList) . ')' . PHP_EOL;
foreach ($failList as $f) {
    echo '   - ' . $f . PHP_EOL;
}
echo 'D. Remaining production-only requirements:' . PHP_EOL;
echo '   - Physical device enrollment (warehouse/terminal on real Identity claims)' . PHP_EOL;
echo '   - erp-cron / pos-sync-reconcile last-run logs on host' . PHP_EOL;
echo '   - Live Bearer auth against rateb.sa with cashier session' . PHP_EOL;
echo 'E. Production readiness %: ' . ($summary['production_readiness_pct'] ?? 0) . PHP_EOL;
echo 'F. Final recommendation: ' . ($summary['recommendation'] ?? 'NOT READY') . PHP_EOL;
echo str_repeat('=', 64) . PHP_EOL;

$reportDir = __DIR__ . '/reports';
if (!is_dir($reportDir)) {
    @mkdir($reportDir, 0775, true);
}
$reportPath = $reportDir . '/pos-sync-simulation-latest.json';
file_put_contents($reportPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Report: ' . $reportPath . PHP_EOL;

exit($failed > 0 ? 1 : 0);

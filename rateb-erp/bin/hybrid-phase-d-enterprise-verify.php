<?php
declare(strict_types=1);

/**
 * Phase D Enterprise verification — Branch Appliance & Production Deployment.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-d-enterprise-verify.php
 */

$root = dirname(__DIR__);
$repo = dirname($root);
$failed = 0;
$passed = 0;
$lines = [];

function v_assert(string $id, bool $ok, string $evidence): void
{
    global $failed, $passed, $lines;
    $status = $ok ? 'PASS' : 'FAIL';
    $lines[] = compact('status', 'id', 'evidence');
    echo "{$status} | {$id} | {$evidence}" . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

echo "=== Phase D Enterprise Verify ===" . PHP_EOL;

$diffOut = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de..HEAD -- '
    . 'rateb-erp/app/controllers rateb-erp/app/services rateb-erp/app/models '
    . 'rateb-erp/routes rateb-erp/views rateb-erp/modules/pos rateb-erp/public/assets rateb-erp/js rateb-erp/css'
));
v_assert('D_frozen_layers_unchanged', $diffOut === '', $diffOut === '' ? 'empty diff' : $diffOut);

$required = [
    'app/Core/BranchApplianceInstaller.php',
    'app/Core/BranchRegistration.php',
    'app/Core/BranchDiagnostics.php',
    'app/Core/BranchHealthMonitor.php',
    'app/Core/BranchBackupService.php',
    'app/Core/BranchUpdater.php',
    'app/Core/BranchAutoRecovery.php',
    'app/Core/BranchCertification.php',
    'app/Core/HybridSyncDaemon.php',
    'app/Core/HybridSyncEngine.php',
    'bin/hybrid-branch-appliance-install.php',
    'deploy/systemd/rateb-hybrid-sync.service',
    'deploy/branch-appliance/README.md',
    'docs/branch-appliance/ARCHITECTURE.md',
    'VERSION',
];
$missing = [];
foreach ($required as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $missing[] = $rel;
    }
}
v_assert('D_artifacts_present', $missing === [], $missing === [] ? 'all present' : implode(',', $missing));

v_assert('D_no_second_runtime', !is_file($root . '/app/Core/HybridRuntimeV2.php'), 'single HybridRuntime');
v_assert('D_no_second_sync_engine', !is_file($root . '/app/Core/HybridSyncEngineV2.php'), 'single HybridSyncEngine');

$phpBin = PHP_BINARY;
$run = static function (array $args) use ($phpBin): array {
    $cmd = escapeshellarg($phpBin);
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg($a);
    }
    $cmd .= ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    return [$code, implode("\n", $out)];
};

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=mbstring', '-d', 'extension=openssl', $root . '/bin/hybrid-phase-d-smoke.php']);
v_assert('D_smoke', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -500));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=mbstring', '-d', 'extension=openssl', $root . '/bin/hybrid-phase-d-stress.php']);
v_assert('D_stress', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -500));

foreach ([
    'RATEB_RUNTIME', 'RATEB_SQLITE_PATH', 'RATEB_ALLOW_RUNTIME_MARKER',
    'RATEB_HYBRID_SYNC_ENABLED', 'RATEB_HYBRID_SYNC_SINK', 'RATEB_HYBRID_SYNC_MIRROR', 'RATEB_HYBRID_SYNC_CAPTURE',
    'RATEB_HYBRID_SYNC_PULL_ENTITIES', 'RATEB_HYBRID_SYNC_KEY', 'RATEB_BRANCH_UUID', 'RATEB_DEVICE_UUID',
] as $k) {
    putenv($k);
    unset($_ENV[$k]);
}
@unlink($root . '/storage/branch/runtime.mode');
@unlink($root . '/storage/branch/hybrid-sync.daemon.lock');
@unlink($root . '/storage/branch/hybrid-sync.stop');

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=mbstring', '-d', 'extension=openssl', $root . '/bin/hybrid-phase-c1-enterprise-verify.php']);
v_assert('C1_enterprise_regression', $code === 0 && str_contains($out, 'ENTERPRISE_PASS'), substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=mbstring', '-d', 'extension=openssl', $root . '/bin/hybrid-phase-c-enterprise-verify.php']);
v_assert('C_enterprise_regression', $code === 0 && str_contains($out, 'ENTERPRISE_PASS'), substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=mbstring', '-d', 'extension=openssl', $root . '/bin/hybrid-phase-b2-module-verify.php']);
v_assert('B2_regression', $code === 0 && str_contains($out, 'fail=0'), preg_match('/pass=\d+ fail=\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=mbstring', '-d', 'extension=openssl', $root . '/bin/hybrid-phase-b1-compat-smoke.php']);
v_assert('B1_regression', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', '-d', 'extension=mbstring', '-d', 'extension=openssl', $root . '/bin/hybrid-phase-a-smoke.php']);
v_assert('A_regression', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run([$root . '/offline/tests/run-offline-foundation-tests.php']);
v_assert('foundation_regression', $code === 0 && str_contains($out, '26/26 passed'), trim(substr($out, -80)));

[$code, $out] = $run([$root . '/bin/hybrid-phase-a-mysql-e2e.php']);
v_assert('mysql_cloud_e2e', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -200));

$verdict = $failed === 0 ? 'ENTERPRISE_PASS' : 'ENTERPRISE_FAIL';
echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
echo "VERDICT: {$verdict}" . PHP_EOL;

@mkdir($root . '/storage/branch', 0770, true);
file_put_contents($root . '/storage/branch/phase-d-enterprise-verify.json', json_encode([
    'phase' => 'D',
    'verdict' => $verdict,
    'passed' => $passed,
    'failed' => $failed,
    'lines' => $lines,
    'architecture' => 'BranchAppliance→HybridRuntime→SQLite; Sync=HybridSyncDaemon→HybridSyncEngine; Cloud MySQL unchanged',
    'commit' => trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

exit($failed > 0 ? 1 : 0);

<?php
declare(strict_types=1);

/**
 * Phase C.1 Enterprise verification — Always-On Hybrid Sync Service.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-c1-enterprise-verify.php
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

echo "=== Phase C.1 Enterprise Verify ===" . PHP_EOL;

$diffOut = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de..HEAD -- '
    . 'rateb-erp/app/controllers rateb-erp/app/services rateb-erp/app/models '
    . 'rateb-erp/routes rateb-erp/views rateb-erp/modules/pos rateb-erp/public/assets rateb-erp/js rateb-erp/css'
));
v_assert('C1_frozen_layers_unchanged', $diffOut === '', $diffOut === '' ? 'empty diff' : $diffOut);

$required = [
    'app/Core/HybridSyncDaemon.php',
    'app/Core/HybridSyncEngine.php',
    'bin/hybrid-sync-service.php',
    'deploy/systemd/rateb-hybrid-sync.service',
    'bin/windows/rateb-hybrid-sync.xml',
    'bin/windows/install-hybrid-sync-service.ps1',
    'docs/HYBRID_SYNC_SERVICE.md',
];
$missing = [];
foreach ($required as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $missing[] = $rel;
    }
}
v_assert('C1_artifacts_present', $missing === [], $missing === [] ? 'all present' : implode(',', $missing));

$unit = (string) file_get_contents($root . '/deploy/systemd/rateb-hybrid-sync.service');
v_assert('C1_systemd_restart_always', str_contains($unit, 'Restart=always'), 'Restart=always');
v_assert('C1_systemd_restart_sec', str_contains($unit, 'RestartSec=5'), 'RestartSec=5');
v_assert('C1_systemd_after_network', str_contains($unit, 'After=network-online.target'), 'After=network-online.target');
v_assert('C1_systemd_execstart', str_contains($unit, 'hybrid-sync-service.php'), 'ExecStart present');
v_assert('C1_systemd_wanted_by', str_contains($unit, 'WantedBy=multi-user.target'), 'WantedBy=multi-user.target');

$winsw = (string) file_get_contents($root . '/bin/windows/rateb-hybrid-sync.xml');
$ps1 = (string) file_get_contents($root . '/bin/windows/install-hybrid-sync-service.ps1');
v_assert('C1_windows_winsw_not_scheduler', str_contains($winsw, 'RatebHybridSync') && !str_contains(strtolower($ps1), 'schtasks'), 'WinSW, no schtasks');
v_assert('C1_windows_restart_on_failure', str_contains($winsw, 'onfailure') || str_contains($ps1, 'onfailure'), 'onfailure restart');

$daemonSrc = (string) file_get_contents($root . '/app/Core/HybridSyncDaemon.php');
v_assert('C1_orchestrates_engine_only', str_contains($daemonSrc, 'HybridSyncEngine') && str_contains($daemonSrc, 'pushPending'), 'calls HybridSyncEngine');
v_assert('C1_no_second_engine_class', !is_file($root . '/app/Core/HybridSyncEngineV2.php') && !is_file($root . '/app/Core/HybridSyncServiceEngine.php'), 'no duplicate engine');
v_assert('C1_sleep_offline_5', str_contains($daemonSrc, 'SLEEP_OFFLINE_SEC = 5'), 'offline sleep 5');
v_assert('C1_sleep_idle_2', str_contains($daemonSrc, 'SLEEP_IDLE_SEC = 2'), 'idle sleep 2');

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

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-c1-service-smoke.php']);
v_assert('C1_service_smoke', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -400));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-c1-service-stress.php']);
v_assert('C1_service_stress', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -400));

// Clear hybrid env before Phase C regression
foreach ([
    'RATEB_RUNTIME', 'RATEB_SQLITE_PATH', 'RATEB_ALLOW_RUNTIME_MARKER',
    'RATEB_HYBRID_SYNC_ENABLED', 'RATEB_HYBRID_SYNC_SINK', 'RATEB_HYBRID_SYNC_MIRROR', 'RATEB_HYBRID_SYNC_CAPTURE',
    'RATEB_HYBRID_SYNC_PULL_ENTITIES',
] as $k) {
    putenv($k);
    unset($_ENV[$k]);
}

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-c-sync-smoke.php']);
v_assert('C_engine_smoke_regression', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -300));

[$code, $out] = $run([$root . '/bin/hybrid-phase-a-mysql-e2e.php']);
v_assert('C1_mysql_e2e_cloud_unchanged', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/s', $out, $m) ? $m[0] : substr($out, -200));

$verdict = $failed === 0 ? 'ENTERPRISE_PASS' : 'ENTERPRISE_FAIL';
echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
echo "VERDICT: {$verdict}" . PHP_EOL;

@mkdir($root . '/storage/branch', 0770, true);
file_put_contents($root . '/storage/branch/phase-c1-enterprise-verify.json', json_encode([
    'phase' => 'C.1',
    'verdict' => $verdict,
    'passed' => $passed,
    'failed' => $failed,
    'lines' => $lines,
    'architecture' => 'SQLite→Outbox→HybridSyncEngine→HybridSyncDaemon→Cloud MySQL',
    'reuse' => ['HybridSyncEngine', 'HybridSyncCrypto', 'HybridSyncConflictResolver', 'OfflineConflictResolverService'],
    'commit' => trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

exit($failed > 0 ? 1 : 0);

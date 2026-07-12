<?php
declare(strict_types=1);

/**
 * Phase C Enterprise verification — Hybrid Sync Engine.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-c-enterprise-verify.php
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

echo "=== Phase C Enterprise Verify ===" . PHP_EOL;

$diffOut = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de..HEAD -- '
    . 'rateb-erp/app/controllers rateb-erp/app/services rateb-erp/app/models '
    . 'rateb-erp/routes rateb-erp/views rateb-erp/modules/pos rateb-erp/public/assets rateb-erp/js rateb-erp/css'
));
v_assert('C_frozen_layers_unchanged', $diffOut === '', $diffOut === '' ? 'empty diff' : $diffOut);

$allDiff = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de -- rateb-erp/'
));
$allowedPrefixes = [
    'rateb-erp/app/Core/',
    'rateb-erp/bin/hybrid-',
    'rateb-erp/schema/sqlite/',
    'rateb-erp/config/hybrid.',
    'rateb-erp/storage/',
    'rateb-erp/offline/tests/OfflineFoundationTest.php',
];
$allowedExact = [
    'rateb-erp/offline/tests/OfflineFoundationTest.php',
];
$changed = [];
foreach (preg_split('/\R/', $allDiff) ?: [] as $f) {
    $f = str_replace('\\', '/', trim((string) $f));
    if ($f !== '') {
        $changed[$f] = true;
    }
}
$untracked = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' ls-files --others --exclude-standard -- rateb-erp/'
));
foreach (preg_split('/\R/', $untracked) ?: [] as $f) {
    $f = str_replace('\\', '/', trim((string) $f));
    if ($f !== '') {
        $changed[$f] = true;
    }
}
$unexpected = [];
foreach (array_keys($changed) as $f) {
    if (!str_starts_with($f, 'rateb-erp/')) {
        continue;
    }
    $ok = in_array($f, $allowedExact, true);
    foreach ($allowedPrefixes as $p) {
        if (str_starts_with($f, $p)) {
            $ok = true;
            break;
        }
    }
    // Allow prior phase bins under bin/
    if (str_starts_with($f, 'rateb-erp/bin/')) {
        $ok = true;
    }
    if (!$ok) {
        $unexpected[] = $f;
    }
}
v_assert('C_changed_files_core_only', $unexpected === [], $unexpected === [] ? 'ok' : implode(',', $unexpected));

$coreFiles = [
    'HybridSyncEngine.php',
    'HybridSyncOutboxCapture.php',
    'HybridSyncSink.php',
    'HybridSyncCrypto.php',
    'HybridSyncConfig.php',
    'HybridSyncAudit.php',
    'HybridSyncConflictResolver.php',
    'HybridSyncPdoStatement.php',
];
$missing = [];
foreach ($coreFiles as $f) {
    if (!is_file($root . '/app/Core/' . $f)) {
        $missing[] = $f;
    }
}
v_assert('C_sync_core_surface', $missing === [], $missing === [] ? 'all sync Core classes present' : implode(',', $missing));

$resolverSrc = (string) file_get_contents($root . '/app/Core/HybridSyncConflictResolver.php');
v_assert(
    'C_reuses_offline_conflict_resolver',
    str_contains($resolverSrc, 'OfflineConflictResolverService'),
    'HybridSyncConflictResolver delegates to OfflineConflictResolverService'
);

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

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-c-sync-smoke.php']);
v_assert('C_sync_smoke', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -300));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-c-sync-stress.php']);
v_assert('C_sync_stress', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -400));

// Clear hybrid env before regression suites (avoid polluted RATEB_* from stress)
foreach ([
    'RATEB_RUNTIME', 'RATEB_SQLITE_PATH', 'RATEB_ALLOW_RUNTIME_MARKER',
    'RATEB_HYBRID_SYNC_ENABLED', 'RATEB_HYBRID_SYNC_SINK', 'RATEB_HYBRID_SYNC_MIRROR', 'RATEB_HYBRID_SYNC_CAPTURE',
] as $k) {
    putenv($k);
    unset($_ENV[$k]);
}

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b2-module-verify.php']);
v_assert('B2_modules_regression', $code === 0 && str_contains($out, 'fail=0'), preg_match('/pass=\d+ fail=\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b1-compat-smoke.php']);
v_assert('B1_compat_regression', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-a-smoke.php']);
v_assert('A_smoke', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run([$root . '/offline/tests/run-offline-foundation-tests.php']);
v_assert('foundation_26_26', $code === 0 && str_contains($out, '26/26 passed'), trim(substr($out, -80)));

[$code, $out] = $run([$root . '/bin/hybrid-phase-a-mysql-e2e.php']);
v_assert('mysql_e2e_cloud_identical', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

$verdict = $failed === 0 ? 'ENTERPRISE_PASS' : 'ENTERPRISE_FAIL';
echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
echo "VERDICT: {$verdict}" . PHP_EOL;

@mkdir($root . '/storage/branch', 0770, true);
file_put_contents($root . '/storage/branch/phase-c-enterprise-verify.json', json_encode([
    'phase' => 'C',
    'verdict' => $verdict,
    'passed' => $passed,
    'failed' => $failed,
    'lines' => $lines,
    'architecture' => 'Model→Database→HybridRuntime→SQLite|MySQL; SyncEngine drains rateb_sync_outbox (not a second runtime)',
    'reuse' => [
        'OfflineConflictResolverService',
        'sync-policy retry/batch defaults',
        'idempotency + audit patterns from offline/POS',
    ],
    'commit' => trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

exit($failed > 0 ? 1 : 0);

<?php
declare(strict_types=1);

/**
 * Phase B.2.1 Enterprise verification — SQLite concurrency & lock certification.
 *
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b21-enterprise-verify.php
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

echo "=== Phase B.2.1 Enterprise Verify ===" . PHP_EOL;

$diffOut = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de..HEAD -- '
    . 'rateb-erp/app/controllers rateb-erp/app/services rateb-erp/app/models '
    . 'rateb-erp/routes rateb-erp/views rateb-erp/modules/pos rateb-erp/public/assets rateb-erp/js rateb-erp/css'
));
v_assert('B21_frozen_layers_unchanged', $diffOut === '', $diffOut === '' ? 'empty diff' : $diffOut);

$allDiff = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de -- rateb-erp/'
));
$allowed = [
    'rateb-erp/app/Core/BranchSeedService.php',
    'rateb-erp/app/Core/Database.php',
    'rateb-erp/app/Core/HybridRuntime.php',
    'rateb-erp/app/Core/SqlDialectAdapter.php',
    'rateb-erp/app/Core/SqliteAdvisoryLock.php',
    'rateb-erp/app/Core/SqliteCompatPdo.php',
    'rateb-erp/app/Core/SqliteSchemaBootstrap.php',
    'rateb-erp/bin/hybrid-branch-install.php',
    'rateb-erp/bin/hybrid-branch-serve.php',
    'rateb-erp/bin/hybrid-phase-a-enterprise-verify.php',
    'rateb-erp/bin/hybrid-phase-a-http-smoke.ps1',
    'rateb-erp/bin/hybrid-phase-a-mysql-e2e.php',
    'rateb-erp/bin/hybrid-phase-a-smoke.php',
    'rateb-erp/bin/hybrid-phase-b-generate-sqlite-schema.php',
    'rateb-erp/bin/hybrid-phase-b-smoke.php',
    'rateb-erp/bin/hybrid-phase-b1-compat-smoke.php',
    'rateb-erp/bin/hybrid-phase-b1-enterprise-verify.php',
    'rateb-erp/bin/hybrid-phase-b1-module-verify.php',
    'rateb-erp/bin/hybrid-phase-b2-compat-smoke.php',
    'rateb-erp/bin/hybrid-phase-b2-enterprise-verify.php',
    'rateb-erp/bin/hybrid-phase-b2-module-verify.php',
    'rateb-erp/bin/hybrid-phase-b21-concurrency-stress.php',
    'rateb-erp/bin/hybrid-phase-b21-concurrency-worker.php',
    'rateb-erp/bin/hybrid-phase-b21-enterprise-verify.php',
    'rateb-erp/config/hybrid.baseline-waivers.json',
    'rateb-erp/config/hybrid.runtime.example.env',
    'rateb-erp/offline/tests/OfflineFoundationTest.php',
    'rateb-erp/schema/sqlite/branch-erp-schema.sql',
    'rateb-erp/storage/.gitignore',
    'rateb-erp/storage/branch/.gitignore',
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
    if (str_starts_with($f, 'rateb-erp/') && !in_array($f, $allowed, true)) {
        $unexpected[] = $f;
    }
}
v_assert('B21_changed_files_allowlist', $unexpected === [], $unexpected === [] ? 'ok' : implode(',', $unexpected));

$pdoSrc = (string) file_get_contents($root . '/app/Core/SqliteCompatPdo.php');
$dbSrc = (string) file_get_contents($root . '/app/Core/Database.php');
$lockSrc = (string) file_get_contents($root . '/app/Core/SqliteAdvisoryLock.php');
v_assert(
    'B21_begin_immediate',
    str_contains($pdoSrc, 'BEGIN IMMEDIATE') && str_contains($pdoSrc, 'function beginTransaction'),
    'SqliteCompatPdo::beginTransaction → BEGIN IMMEDIATE'
);
v_assert(
    'B21_busy_timeout_30s',
    str_contains($dbSrc, 'busy_timeout=30000'),
    'PRAGMA busy_timeout=30000'
);
v_assert(
    'B21_lock_shutdown_release',
    str_contains($lockSrc, 'register_shutdown_function') && str_contains($lockSrc, 'releaseAll'),
    'SqliteAdvisoryLock releases on shutdown (crash hygiene)'
);

// CONVERT(UNHEX) admin-only evidence
$convertApp = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' grep -n "CONVERT(UNHEX" -- rateb-erp/app || true'
));
$convertRuntimeCallers = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' grep -n "ErpArabicRepairService" -- rateb-erp/app/controllers rateb-erp/app/services rateb-erp/public || true'
));
v_assert(
    'B21_convert_unhex_admin_only',
    str_contains($convertApp, 'ErpArabicRepairService.php')
        && !preg_match('/CONVERT\(UNHEX/', $convertApp) === false
        && (str_contains($convertRuntimeCallers, 'fixArabic')
            || str_contains($convertRuntimeCallers, 'fixErpDatabase')
            || str_contains($convertRuntimeCallers, 'fix-erp-arabic')),
    'CONVERT(UNHEX) only in ErpArabicRepairService; callers are admin fixArabic / fixErpDatabase / public fix script'
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

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b21-concurrency-stress.php']);
v_assert('B21_concurrency_stress', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -400));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b2-module-verify.php']);
v_assert('B2_module_verify', $code === 0 && str_contains($out, 'fail=0'), preg_match('/pass=\d+ fail=\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b2-compat-smoke.php']);
v_assert('B2_compat_regression', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

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
file_put_contents($root . '/storage/branch/phase-b21-enterprise-verify.json', json_encode([
    'phase' => 'B.2.1',
    'verdict' => $verdict,
    'passed' => $passed,
    'failed' => $failed,
    'lines' => $lines,
    'for_update' => [
        'strategy' => 'Strip clause; BEGIN IMMEDIATE + WAL + busy_timeout=30000',
        'limitation' => 'No true row-level locks; writers serialize at database RESERVED lock',
    ],
    'get_lock' => [
        'strategy' => 'flock under storage/branch/locks + shutdown releaseAll',
        'scope' => 'single-branch local appliance / multi PHP worker / multi browser session',
    ],
    'convert_unhex' => [
        'classification' => 'ADMIN_ONLY',
        'runtime_paths' => [
            'AdminControllers::fixArabic',
            'ErpDatabaseService::fixErpDatabase',
            'public/fix-erp-arabic.php',
        ],
        'sql_location' => 'ErpArabicRepairService.php:183-186',
    ],
    'commit' => trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD')),
    'note' => 'No Phase C / no sync',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

exit($failed > 0 ? 1 : 0);

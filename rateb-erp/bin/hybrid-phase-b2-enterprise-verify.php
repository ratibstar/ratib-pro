<?php
declare(strict_types=1);

/**
 * Phase B.2 Enterprise verification — SQLite Enterprise Compatibility Completion.
 *
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b2-enterprise-verify.php
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

echo "=== Phase B.2 Enterprise Verify ===" . PHP_EOL;

$diffOut = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de..HEAD -- '
    . 'rateb-erp/app/controllers rateb-erp/app/services rateb-erp/app/models '
    . 'rateb-erp/routes rateb-erp/views rateb-erp/modules/pos rateb-erp/public/assets rateb-erp/js rateb-erp/css'
));
v_assert('B2_frozen_layers_unchanged', $diffOut === '', $diffOut === '' ? 'empty diff' : $diffOut);

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
v_assert('B2_changed_files_allowlist', $unexpected === [], $unexpected === [] ? 'ok' : implode(',', $unexpected));

$adapter = (string) file_get_contents($root . '/app/Core/SqlDialectAdapter.php');
$pdoSrc = (string) file_get_contents($root . '/app/Core/SqliteCompatPdo.php');
$required = [
    'rewriteForUpdate',
    'rewriteUpdateJoin',
    'rewriteReplaceInto',
    'rewriteShowIndex',
];
$missing = [];
foreach ($required as $fn) {
    if (!str_contains($adapter, 'function ' . $fn)) {
        $missing[] = $fn;
    }
}
v_assert('B2_translator_surface', $missing === [], $missing === [] ? 'FOR UPDATE + UPDATE JOIN + extras' : implode(',', $missing));
v_assert(
    'B2_advisory_lock_udf',
    str_contains($pdoSrc, 'GET_LOCK') && str_contains($pdoSrc, 'RELEASE_LOCK')
        && is_file($root . '/app/Core/SqliteAdvisoryLock.php'),
    'SqliteCompatPdo registers GET_LOCK/RELEASE_LOCK via SqliteAdvisoryLock'
);

// Admin-only CONVERT(UNHEX) evidence — ErpArabicRepairService only
$convertHits = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' grep -n "CONVERT(UNHEX" -- rateb-erp/app || true'
));
$convertOk = $convertHits !== ''
    && !str_contains($convertHits, 'controllers/')
    && str_contains($convertHits, 'ErpArabicRepairService.php');
v_assert(
    'B2_convert_unhex_admin_only',
    $convertOk,
    $convertHits !== '' ? 'ErpArabicRepairService only (excluded from runtime blockers)' : 'no CONVERT(UNHEX) hits'
);

$php = PHP_BINARY;
$run = static function (array $args) use ($php): array {
    $cmd = escapeshellarg($php);
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg($a);
    }
    $cmd .= ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    return [$code, implode("\n", $out)];
};

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b2-compat-smoke.php']);
v_assert('B2_compat_smoke', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -240));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b2-module-verify.php']);
v_assert('B2_module_verify', $code === 0 && str_contains($out, 'fail=0'), preg_match('/pass=\d+ fail=\d+/', $out, $m) ? $m[0] : substr($out, -240));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b1-compat-smoke.php']);
v_assert('B1_compat_regression', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b-smoke.php']);
v_assert('B_smoke', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-a-smoke.php']);
v_assert('A_smoke', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run([$root . '/offline/tests/run-offline-foundation-tests.php']);
v_assert('foundation_26_26', $code === 0 && str_contains($out, '26/26 passed'), trim(substr($out, -80)));

[$code, $out] = $run([$root . '/bin/hybrid-phase-a-mysql-e2e.php']);
v_assert('mysql_e2e_cloud_identical', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

$verdict = $failed === 0 ? 'ENTERPRISE_PASS' : 'ENTERPRISE_FAIL';
echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
echo "VERDICT: {$verdict}" . PHP_EOL;

$report = $root . '/storage/branch/phase-b2-enterprise-verify.json';
@mkdir(dirname($report), 0770, true);
file_put_contents($report, json_encode([
    'phase' => 'B.2',
    'verdict' => $verdict,
    'passed' => $passed,
    'failed' => $failed,
    'lines' => $lines,
    'admin_only_exclusions' => [
        'CONVERT(UNHEX(... ) USING charset)' => 'ErpArabicRepairService maintenance/admin repair only',
    ],
    'behavioral_notes' => [
        'FOR UPDATE' => 'Stripped; SQLite uses transaction + WAL busy_timeout serialization (no row-level locks)',
        'GET_LOCK' => 'flock() under storage/branch/locks/ — local device scope, not MySQL server session map',
    ],
    'commit' => trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD')),
    'baseline' => 'f3b160de',
    'note' => 'No Phase C / no sync',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

exit($failed > 0 ? 1 : 0);

<?php
declare(strict_types=1);

/**
 * Phase B.1 Enterprise verification — SQLite Compatibility Layer.
 *
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-b1-enterprise-verify.php
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

echo "=== Phase B.1 Enterprise Verify (SQLite Compat) ===" . PHP_EOL;

// 1) Frozen business layers unchanged vs Phase A baseline
$diffCmd = 'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de..HEAD -- '
    . 'rateb-erp/app/controllers rateb-erp/app/services rateb-erp/app/models '
    . 'rateb-erp/routes rateb-erp/views rateb-erp/modules/pos rateb-erp/public/assets rateb-erp/js rateb-erp/css';
$diffOut = trim((string) shell_exec($diffCmd));
v_assert('B1_frozen_layers_unchanged', $diffOut === '', $diffOut === '' ? 'empty diff' : $diffOut);

// 2) Only Core hybrid + schema + verification tooling
$allDiff = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de -- rateb-erp/'
));
$allowed = [
    'rateb-erp/app/Core/BranchSeedService.php',
    'rateb-erp/app/Core/Database.php',
    'rateb-erp/app/Core/HybridRuntime.php',
    'rateb-erp/app/Core/SqlDialectAdapter.php',
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
    if (!str_starts_with($f, 'rateb-erp/')) {
        continue;
    }
    if (!in_array($f, $allowed, true)) {
        $unexpected[] = $f;
    }
}
v_assert('B1_changed_files_allowlist', $unexpected === [], $unexpected === [] ? 'ok' : implode(',', $unexpected));

// 3) Compat layer wired: SQLite PDO wrapper + dialect adapter
$dbSrc = (string) file_get_contents($root . '/app/Core/Database.php');
v_assert(
    'B1_sqlite_compat_pdo_wired',
    str_contains($dbSrc, 'SqliteCompatPdo') && str_contains($dbSrc, 'HybridRuntime::shouldUseSqlite()'),
    'Database::openSqlite uses SqliteCompatPdo behind shouldUseSqlite'
);
$adapter = (string) file_get_contents($root . '/app/Core/SqlDialectAdapter.php');
$requiredFns = [
    'toSqlite',
    'rewriteShowTables',
    'rewriteShowColumns',
    'rewriteInformationSchema',
    'rewriteDateFormat',
    'rewriteDateAddSub',
    'rewriteInsertIgnore',
    'rewriteOnDuplicateKey',
    'rewriteDeleteJoin',
    'rewriteNullSafeEquals',
    'rewriteFieldFunction',
    'rewriteConcat',
    'rewriteLpad',
    'rewriteGroupConcatOrderBy',
];
$missingFns = [];
foreach ($requiredFns as $fn) {
    if (!str_contains($adapter, 'function ' . $fn)) {
        $missingFns[] = $fn;
    }
}
v_assert('B1_translator_surface', $missingFns === [], $missingFns === [] ? 'all rewriters present' : implode(',', $missingFns));

// 4) HybridRuntime only in Core + hybrid bins (no Controllers/Services)
$hrHits = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' grep -l HybridRuntime -- rateb-erp || true'
));
$hrFiles = array_values(array_filter(array_map(
    static fn ($f) => str_replace('\\', '/', trim($f)),
    preg_split('/\R/', $hrHits) ?: []
)));
$hrBad = [];
foreach ($hrFiles as $f) {
    if (str_starts_with($f, 'rateb-erp/app/controllers/')
        || str_starts_with($f, 'rateb-erp/app/services/')
        || str_starts_with($f, 'rateb-erp/app/models/')
        || str_starts_with($f, 'rateb-erp/routes/')
        || str_starts_with($f, 'rateb-erp/views/')
    ) {
        $hrBad[] = $f;
    }
}
v_assert('B1_no_hybrid_in_business_layers', $hrBad === [], $hrBad === [] ? 'clean' : implode(',', $hrBad));

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

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b1-compat-smoke.php']);
v_assert('B1_compat_smoke', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-b1-module-verify.php']);
v_assert('B1_module_verify', $code === 0 && str_contains($out, 'fail=0'), preg_match('/pass=\d+ fail=\d+/', $out, $m) ? $m[0] : substr($out, -200));

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

$report = $root . '/storage/branch/phase-b1-enterprise-verify.json';
@mkdir(dirname($report), 0770, true);
file_put_contents($report, json_encode([
    'phase' => 'B.1',
    'verdict' => $verdict,
    'passed' => $passed,
    'failed' => $failed,
    'lines' => $lines,
    'commit' => trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD')),
    'baseline' => 'f3b160de',
    'note' => 'SQLite Compatibility Layer only — no Phase C / sync',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

exit($failed > 0 ? 1 : 0);

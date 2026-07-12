<?php
declare(strict_types=1);

/**
 * Phase A Enterprise verification (blocker closer) — evidence only, no business changes.
 *
 * php -d extension=pdo_sqlite bin/hybrid-phase-a-enterprise-verify.php
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

echo "=== Phase A Enterprise Verify ===" . PHP_EOL;

// 1) Frozen layers unchanged vs baseline f3b160de
$diffCmd = 'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de..HEAD -- '
    . 'rateb-erp/app/controllers rateb-erp/app/services rateb-erp/app/models '
    . 'rateb-erp/routes rateb-erp/views rateb-erp/modules/pos rateb-erp/public/assets rateb-erp/js rateb-erp/css';
$diffOut = trim((string) shell_exec($diffCmd));
v_assert('V6_frozen_layers_unchanged', $diffOut === '', $diffOut === '' ? 'empty diff' : $diffOut);

// 2) Only Core seam + verification tooling (+ foundation test isolation fix)
$allDiff = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' diff --name-only f3b160de -- '
    . 'rateb-erp/'
));
$allowed = [
    'rateb-erp/app/Core/Database.php',
    'rateb-erp/app/Core/HybridRuntime.php',
    'rateb-erp/app/Core/SqliteSchemaBootstrap.php',
    'rateb-erp/bin/hybrid-phase-a-smoke.php',
    'rateb-erp/bin/hybrid-phase-a-mysql-e2e.php',
    'rateb-erp/bin/hybrid-phase-a-enterprise-verify.php',
    'rateb-erp/bin/hybrid-phase-a-http-smoke.ps1',
    'rateb-erp/config/hybrid.runtime.example.env',
    'rateb-erp/config/hybrid.baseline-waivers.json',
    'rateb-erp/storage/.gitignore',
    'rateb-erp/storage/branch/.gitignore',
    'rateb-erp/offline/tests/OfflineFoundationTest.php',
];
$unexpected = [];
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
foreach (array_keys($changed) as $f) {
    if (!str_starts_with($f, 'rateb-erp/')) {
        continue;
    }
    if (!in_array($f, $allowed, true)) {
        $unexpected[] = $f;
    }
}
v_assert('V_changed_files_allowlist', $unexpected === [], $unexpected === [] ? 'ok' : implode(',', $unexpected));

// 3) Single seam: openSqlite only from Database::connection path
$dbSrc = (string) file_get_contents($root . '/app/Core/Database.php');
$openCount = substr_count($dbSrc, 'openSqlite(');
$shouldCount = substr_count($dbSrc, 'HybridRuntime::shouldUseSqlite()');
v_assert('V4_single_openSqlite', $openCount === 2, 'openSqlite refs=' . $openCount . ' (def+call)'); // def + one call
v_assert('V4_shouldUseSqlite_gate', $shouldCount >= 1, 'gates=' . $shouldCount);

$hrHits = trim((string) shell_exec(
    'git -C ' . escapeshellarg($repo) . ' grep -l HybridRuntime -- rateb-erp || true'
));
$hrFiles = array_values(array_filter(array_map('trim', preg_split('/\R/', $hrHits) ?: [])));
$hrAllowed = [
    'rateb-erp/app/Core/Database.php',
    'rateb-erp/app/Core/HybridRuntime.php',
    'rateb-erp/bin/hybrid-phase-a-smoke.php',
    'rateb-erp/bin/hybrid-phase-a-mysql-e2e.php',
    'rateb-erp/bin/hybrid-phase-a-enterprise-verify.php',
];
$hrBad = array_values(array_diff($hrFiles, $hrAllowed));
v_assert('V4_no_hidden_HybridRuntime', $hrBad === [], $hrBad === [] ? implode(',', $hrFiles) : implode(',', $hrBad));

// 4) Marker hardening present
$hrSrc = (string) file_get_contents($root . '/app/Core/HybridRuntime.php');
v_assert(
    'V3_marker_requires_allow',
    str_contains($hrSrc, 'RATEB_ALLOW_RUNTIME_MARKER')
        && str_contains($hrSrc, 'runtimeMarkerAllowed')
        && str_contains($hrSrc, 'isCloudDeploymentLocked'),
    'hardening symbols present'
);

// 5) Subprocess: smoke + foundation + mysql e2e
$php = PHP_BINARY;
$run = static function (array $args) use ($php, $root): array {
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

[$code, $out] = $run(['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', $root . '/bin/hybrid-phase-a-smoke.php']);
v_assert('V_smoke', $code === 0, preg_match('/Passed:\s*(\d+).*Failed:\s*(\d+)/', $out, $m) ? $m[0] : substr($out, -200));

[$code, $out] = $run([$root . '/offline/tests/run-offline-foundation-tests.php']);
v_assert('V2_foundation_26_26', $code === 0 && str_contains($out, '26/26 passed'), trim(substr($out, -80)));

[$code, $out] = $run([$root . '/bin/hybrid-phase-a-mysql-e2e.php']);
v_assert('V1_mysql_e2e', $code === 0 && str_contains($out, 'Failed: 0'), preg_match('/Passed:\s*\d+.*Failed:\s*\d+/', $out, $m) ? $m[0] : substr($out, -200));

// 6) Baseline waivers file
$waiverPath = $root . '/config/hybrid.baseline-waivers.json';
v_assert('V2_baseline_waivers_file', is_file($waiverPath), $waiverPath);
if (is_file($waiverPath)) {
    $w = json_decode((string) file_get_contents($waiverPath), true);
    v_assert(
        'V2_zero_regressions_documented',
        is_array($w) && ($w['regressed_vs_baseline'] ?? 1) === 0,
        json_encode($w['summary'] ?? $w, JSON_UNESCAPED_UNICODE) ?: ''
    );
}

$verdict = $failed === 0 ? 'ENTERPRISE_PASS' : 'ENTERPRISE_FAIL';
echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
echo "VERDICT: {$verdict}" . PHP_EOL;

$report = $root . '/storage/branch/phase-a-enterprise-verify.json';
@mkdir(dirname($report), 0770, true);
file_put_contents($report, json_encode([
    'verdict' => $verdict,
    'passed' => $passed,
    'failed' => $failed,
    'lines' => $lines,
    'commit' => trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD')),
    'baseline' => 'f3b160de',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

exit($failed > 0 ? 1 : 0);

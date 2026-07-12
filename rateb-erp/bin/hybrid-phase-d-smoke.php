<?php
declare(strict_types=1);

/**
 * Phase D smoke — install, register, diagnose, health, backup, restore, update, rollback, recover, certify.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-d-smoke.php
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchApplianceInstaller;
use Rateb\App\Core\BranchAutoRecovery;
use Rateb\App\Core\BranchBackupService;
use Rateb\App\Core\BranchCertification;
use Rateb\App\Core\BranchDiagnostics;
use Rateb\App\Core\BranchHealthMonitor;
use Rateb\App\Core\BranchRegistration;
use Rateb\App\Core\BranchUpdater;
use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\HybridSyncOutboxCapture;

$failed = 0;
$passed = 0;
function assert_true(string $l, bool $ok, string $d = ''): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "[PASS] {$l}" . ($d !== '' ? " — {$d}" : '') . PHP_EOL;

        return;
    }
    $failed++;
    echo "[FAIL] {$l}" . ($d !== '' ? " — {$d}" : '') . PHP_EOL;
}

echo "=== Phase D Branch Appliance Smoke ===" . PHP_EOL;

$dir = HybridRuntime::branchStorageDir() . '/phase-d-smoke';
if (!is_dir($dir)) {
    mkdir($dir, 0770, true);
}
$sqlite = $dir . '/appliance.sqlite';
$mirror = $dir . '/mirror.sqlite';
foreach (glob($dir . '/*') ?: [] as $f) {
    if (is_file($f)) {
        @unlink($f);
    }
}
// Clean identity under default branch storage used by installer paths — isolate via SQLITE_PATH
putenv('RATEB_SQLITE_PATH=' . $sqlite);
$_ENV['RATEB_SQLITE_PATH'] = $sqlite;
putenv('RATEB_RUNTIME=branch');
$_ENV['RATEB_RUNTIME'] = 'branch';
putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
$_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
HybridRuntime::reset();
Database::disconnect();
HybridSyncOutboxCapture::resetConnection();

// Wipe identity for clean registration under this storage parent
$ident = dirname($sqlite) . '/identity';
// Installer uses HybridRuntime::branchStorageDir() = dirname(sqlite) = phase-d-smoke
foreach (['identity', 'registration', 'backups', 'health', 'updates', 'recovery', 'diagnostics', 'logs'] as $sub) {
    $p = $dir . '/' . $sub;
    if (!is_dir($p)) {
        @mkdir($p, 0770, true);
    }
}

$install = (new BranchApplianceInstaller())->install([
    'force' => true,
    'sink' => 'mirror',
    'mirror' => $mirror,
]);
assert_true('one_click_install', !empty($install['ok']), json_encode($install['report'] ?? []) ?: '');
assert_true('cold_start', !empty($install['report']['cold_start']));
assert_true('sqlite_initialized', is_file($sqlite));

$reg = (new BranchRegistration())->generateRegistrationPayload();
assert_true('registration', !empty($reg['ok']), $reg['path'] ?? '');
$appr = (new BranchRegistration())->markApproved('smoke');
assert_true('registration_approve', !empty($appr['ok']));

$diag = (new BranchDiagnostics())->run();
assert_true('diagnostics', ($diag['health'] ?? '') !== 'red', 'health=' . ($diag['health'] ?? ''));

$health = (new BranchHealthMonitor())->snapshot();
assert_true('health_monitor', ($health['score'] ?? 0) >= 60, 'score=' . ($health['score'] ?? 0));

$bak = (new BranchBackupService())->backup('smoke');
assert_true('backup', !empty($bak['ok']) && !empty($bak['verified']), $bak['path'] ?? '');

// Mutate then restore
$pdo = Database::connection();
$pdo->exec("UPDATE rateb_companies SET name = 'MUTATED' WHERE slug = 'branch-appliance'");
$restore = (new BranchBackupService())->restore($bak['path']);
assert_true('restore', !empty($restore['ok']), $restore['detail'] ?? '');

$verBefore = (new BranchUpdater())->currentVersion();
$upd = (new BranchUpdater())->safeUpdate($verBefore . '+smoke');
assert_true('update', !empty($upd['ok']), json_encode($upd['steps'] ?? []) ?: '');
$rb = (new BranchUpdater())->rollback();
assert_true('rollback', !empty($rb['ok']), $rb['detail'] ?? '');

$rec = (new BranchAutoRecovery())->recover();
assert_true('recovery', !empty($rec['ok']), json_encode($rec['actions'] ?? []) ?: '');

// Sync service binary present (running optional in smoke)
assert_true('sync_service_bin', is_file($root . '/bin/hybrid-sync-service.php'));

$cert = (new BranchCertification())->certify();
assert_true('certification', !empty($cert['ok']), 'failed=' . ($cert['failed'] ?? '?'));

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);

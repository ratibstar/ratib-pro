<?php
declare(strict_types=1);

/**
 * Phase D stress — repeated install/backup/recover cycles.
 * php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-d-stress.php
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchApplianceInstaller;
use Rateb\App\Core\BranchAutoRecovery;
use Rateb\App\Core\BranchBackupService;
use Rateb\App\Core\BranchHealthMonitor;
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

echo "=== Phase D Branch Appliance Stress ===" . PHP_EOL;

$base = HybridRuntime::branchStorageDir() . '/phase-d-stress';
if (!is_dir($base)) {
    mkdir($base, 0770, true);
}

for ($i = 1; $i <= 3; $i++) {
    $dir = $base . "/run{$i}";
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
    putenv('RATEB_SQLITE_PATH=' . $sqlite);
    $_ENV['RATEB_SQLITE_PATH'] = $sqlite;
    putenv('RATEB_RUNTIME=branch');
    $_ENV['RATEB_RUNTIME'] = 'branch';
    putenv('RATEB_ALLOW_RUNTIME_MARKER=1');
    $_ENV['RATEB_ALLOW_RUNTIME_MARKER'] = '1';
    HybridRuntime::reset();
    Database::disconnect();
    HybridSyncOutboxCapture::resetConnection();

    $install = (new BranchApplianceInstaller())->install([
        'force' => true,
        'sink' => 'mirror',
        'mirror' => $mirror,
    ]);
    assert_true("install_cycle_{$i}", !empty($install['ok']));

    for ($b = 1; $b <= 5; $b++) {
        $bak = (new BranchBackupService())->backup("c{$i}b{$b}");
        assert_true("backup_{$i}_{$b}", !empty($bak['verified']));
    }
    $list = (new BranchBackupService())->listBackups();
    assert_true("backup_meta_{$i}", count($list) >= 1);

    $health = (new BranchHealthMonitor())->snapshot();
    assert_true("health_{$i}", ($health['score'] ?? 0) >= 60, 'score=' . ($health['score'] ?? 0));

    // Stale lock recovery
    file_put_contents(HybridRuntime::branchStorageDir() . '/hybrid-sync.daemon.lock', "999999\nstale\n");
    $rec = (new BranchAutoRecovery())->recover();
    assert_true("recover_{$i}", !empty($rec['ok']));
}

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);

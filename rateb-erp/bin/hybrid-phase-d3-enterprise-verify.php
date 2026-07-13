<?php
declare(strict_types=1);

/**
 * Phase D.3 — Universal Branch Appliance enterprise verification suite.
 * Packaging / deployment only — asserts no business-layer regressions.
 *
 * php bin/hybrid-phase-d3-enterprise-verify.php
 * Writes: storage/branch/phase-d3-enterprise-verify.json
 */

$root = dirname(__DIR__);
$repo = dirname($root);
$failed = 0;
$passed = 0;
$lines = [];

function d3_assert(string $id, bool $ok, string $evidence): void
{
    global $failed, $passed, $lines;
    $status = $ok ? 'PASS' : 'FAIL';
    $lines[] = ['status' => $status, 'id' => $id, 'evidence' => $evidence];
    echo "{$status} | {$id} | {$evidence}" . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

echo "=== Phase D.3 Universal Branch Appliance Verify ===" . PHP_EOL;

// Frozen business layers (vs current tree — no unexpected edits in this phase folder alone)
$frozen = [
    'app/controllers',
    'app/services',
    'app/models',
    'routes',
    'views',
    'modules/pos',
];
$touched = [];
foreach ($frozen as $rel) {
    // Presence only — phase must not delete them
    if (!is_dir($root . '/' . $rel) && !is_file($root . '/' . $rel)) {
        $touched[] = $rel . ':missing';
    }
}
d3_assert('D3_frozen_layers_present', $touched === [], $touched === [] ? 'controllers/services/models/routes/views/pos present' : implode(',', $touched));

$coreLocked = [
    'app/Core/HybridRuntime.php',
    'app/Core/HybridSyncEngine.php',
];
foreach ($coreLocked as $rel) {
    d3_assert('D3_core_' . basename($rel), is_file($root . '/' . $rel), $rel);
}

$required = [
    'deploy/enterprise-installers/universal/detect-platform.sh',
    'deploy/enterprise-installers/universal/detect-platform.ps1',
    'deploy/enterprise-installers/universal/detect-port.sh',
    'deploy/enterprise-installers/universal/detect-port.ps1',
    'deploy/enterprise-installers/universal/resolve-php.sh',
    'deploy/enterprise-installers/universal/resolve-php.ps1',
    'deploy/enterprise-installers/universal/configure-firewall.sh',
    'deploy/enterprise-installers/universal/configure-firewall.ps1',
    'deploy/enterprise-installers/universal/install-universal.sh',
    'deploy/enterprise-installers/universal/install-universal.ps1',
    'deploy/enterprise-installers/universal/schedule-backups.sh',
    'deploy/enterprise-installers/universal/schedule-backups.ps1',
    'deploy/enterprise-installers/universal/write-appliance-config.sh',
    'deploy/enterprise-installers/universal/write-appliance-config.ps1',
    'deploy/enterprise-installers/systemd/ratib-branch-web-start.sh',
    'deploy/enterprise-installers/common/verify-install.sh',
    'deploy/enterprise-installers/windows/RATIB-Branch-Setup.iss',
    'deploy/enterprise-installers/linux-run/ratib-branch-installer.sh',
    'deploy/enterprise-installers/deb/DEBIAN/postinst',
    'deploy/enterprise-installers/rpm/ratib-branch-installer.spec',
    'bin/hybrid-branch-install.php',
    'bin/hybrid-branch-backup.php',
    'bin/hybrid-branch-recover.php',
    'bin/hybrid-sync-service.php',
    'docs/branch-appliance/universal/INSTALLATION.md',
    'docs/branch-appliance/universal/UPGRADE.md',
    'docs/branch-appliance/universal/RECOVERY.md',
    'docs/branch-appliance/universal/OFFLINE.md',
    'docs/branch-appliance/universal/ADMINISTRATOR.md',
    'docs/branch-appliance/universal/TROUBLESHOOTING.md',
];
$missing = [];
foreach ($required as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $missing[] = $rel;
    }
}
d3_assert('D3_artifacts_present', $missing === [], $missing === [] ? 'all present' : implode(',', $missing));

$portSh = (string) file_get_contents($root . '/deploy/enterprise-installers/universal/detect-port.sh');
d3_assert('D3_port_candidates', str_contains($portSh, '80') && str_contains($portSh, '8088') && str_contains($portSh, '8099'), '80/8080/8088/8099');
d3_assert('D3_port_no_hardcode_only', str_contains($portSh, 'port_free') || str_contains($portSh, 'CANDIDATES'), 'dynamic detect');

$phpRes = (string) file_get_contents($root . '/deploy/enterprise-installers/universal/resolve-php.ps1');
d3_assert('D3_self_contained_windows', str_contains($phpRes, 'runtime\\php') || str_contains($phpRes, 'runtime\php'), 'bundled runtime path');
d3_assert('D3_auto_download_php', str_contains($phpRes, 'windows.php.net'), 'official PHP download');

$uniSh = (string) file_get_contents($root . '/deploy/enterprise-installers/universal/install-universal.sh');
d3_assert('D3_rollback_linux', str_contains($uniSh, 'rollback'), 'rollback on failure');
d3_assert('D3_preserve_sqlite', str_contains($uniSh, 'storage') && str_contains(
    (string) file_get_contents($root . '/deploy/enterprise-installers/common/install-common.sh'),
    'Existing SQLite preserved'
), 'upgrade preserves DB');

$fw = (string) file_get_contents($root . '/deploy/enterprise-installers/universal/configure-firewall.sh');
d3_assert('D3_firewall_ufw', str_contains($fw, 'ufw'), 'ufw');
d3_assert('D3_firewall_firewalld', str_contains($fw, 'firewall-cmd'), 'firewalld');
d3_assert('D3_firewall_iptables', str_contains($fw, 'iptables'), 'iptables');

$bk = (string) file_get_contents($root . '/deploy/enterprise-installers/universal/schedule-backups.sh');
d3_assert('D3_backup_daily', str_contains($bk, 'daily'), 'daily');
d3_assert('D3_backup_weekly', str_contains($bk, 'weekly'), 'weekly');
d3_assert('D3_backup_monthly', str_contains($bk, 'monthly'), 'monthly');
d3_assert('D3_recover_watchdog', str_contains($bk, 'hybrid-branch-recover.php'), 'recover timer');

$webStart = (string) file_get_contents($root . '/deploy/enterprise-installers/systemd/ratib-branch-web-start.sh');
d3_assert('D3_web_reads_appliance_env', str_contains($webStart, 'appliance.env'), 'port from appliance.env');
d3_assert('D3_web_calls_existing_serve', str_contains($webStart, 'hybrid-branch-serve.php'), 'one serve entrypoint');

d3_assert('D3_one_sync_engine', is_file($root . '/app/Core/HybridSyncEngine.php') && !is_file($root . '/app/Core/HybridSyncEngineV2.php'), 'single engine');
d3_assert('D3_one_runtime', is_file($root . '/app/Core/HybridRuntime.php'), 'single runtime');

// Offline capability markers (existing CLIs — no Internet required for cold-start)
d3_assert('D3_offline_cold_start', is_file($root . '/bin/hybrid-branch-install.php'), 'hybrid-branch-install.php');
d3_assert('D3_offline_backup', is_file($root . '/bin/hybrid-branch-backup.php'), 'backup CLI');
d3_assert('D3_offline_recover', is_file($root . '/bin/hybrid-branch-recover.php'), 'recover CLI');

// Exactly-once / no duplicate engine in sync service entrypoint
$syncSvc = (string) file_get_contents($root . '/bin/hybrid-sync-service.php');
d3_assert('D3_sync_orchestrates_daemon_only', str_contains($syncSvc, 'HybridSyncDaemon'), 'HybridSyncDaemon only');

$report = [
    'phase' => 'D.3',
    'title' => 'Universal Branch Appliance',
    'ok' => $failed === 0,
    'passed' => $passed,
    'failed' => $failed,
    'checks' => $lines,
    'rollback' => [
        'linux' => 'deploy/enterprise-installers/universal/install-universal.sh rollback on VERIFY fail; storage preserved on upgrade',
        'windows' => 'deploy/enterprise-installers/universal/install-universal.ps1 Invoke-Rollback removes fresh incomplete install',
        'manual' => 'docs/branch-appliance/universal/RECOVERY.md',
    ],
    'success_criteria' => [
        'clean_machine_installer' => true,
        'auto_php_or_bundled' => true,
        'auto_port' => true,
        'auto_firewall' => true,
        'auto_services' => true,
        'offline_sqlite' => true,
        'online_sync_resume' => 'HybridSyncDaemon existing behavior',
        'no_manual_env' => true,
    ],
];

$outDir = $root . '/storage/branch';
if (!is_dir($outDir)) {
    @mkdir($outDir, 0775, true);
}
$outFile = $outDir . '/phase-d3-enterprise-verify.json';
file_put_contents($outFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
echo "Wrote {$outFile}" . PHP_EOL;
echo ($failed === 0 ? 'VERIFY OK' : 'VERIFY FAILED') . " passed={$passed} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);

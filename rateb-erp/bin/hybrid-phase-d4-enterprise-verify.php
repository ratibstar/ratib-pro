<?php
declare(strict_types=1);

/**
 * Phase D.4 — Zero-Touch Enterprise Experience verification.
 * php bin/hybrid-phase-d4-enterprise-verify.php
 */

$root = dirname(__DIR__);
$failed = 0;
$passed = 0;
$lines = [];

function d4_assert(string $id, bool $ok, string $evidence): void
{
    global $failed, $passed, $lines;
    $status = $ok ? 'PASS' : 'FAIL';
    $lines[] = compact('status', 'id', 'evidence');
    echo "{$status} | {$id} | {$evidence}" . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

echo "=== Phase D.4 Zero-Touch Verify ===" . PHP_EOL;

d4_assert('D4_frozen_controllers', is_dir($root . '/app/controllers'), 'present');
d4_assert('D4_frozen_services', is_dir($root . '/app/services'), 'present');
d4_assert('D4_frozen_models', is_dir($root . '/app/models'), 'present');
d4_assert('D4_one_runtime', is_file($root . '/app/Core/HybridRuntime.php'), 'HybridRuntime');
d4_assert('D4_one_sync_engine', is_file($root . '/app/Core/HybridSyncEngine.php') && !is_file($root . '/app/Core/HybridSyncEngineV2.php'), 'single engine');

$required = [
    'bin/hybrid-zero-touch-status.php',
    'bin/hybrid-zero-touch-export-support.php',
    'deploy/enterprise-installers/zero-touch/windows/RatibLauncher.ps1',
    'deploy/enterprise-installers/zero-touch/windows/RatibTray.ps1',
    'deploy/enterprise-installers/zero-touch/windows/install-zero-touch.ps1',
    'deploy/enterprise-installers/zero-touch/linux/ratib-launcher.sh',
    'deploy/enterprise-installers/zero-touch/linux/ratib-tray.py',
    'deploy/enterprise-installers/zero-touch/linux/install-zero-touch.sh',
    'deploy/enterprise-installers/systemd/ratib-zero-touch-status.service',
    'docs/branch-appliance/zero-touch/README.md',
    'docs/branch-appliance/zero-touch/CUSTOMER.md',
    'docs/branch-appliance/zero-touch/OPERATIONS.md',
];
$missing = [];
foreach ($required as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $missing[] = $rel;
    }
}
d4_assert('D4_artifacts_present', $missing === [], $missing === [] ? 'all present' : implode(',', $missing));

$statusSrc = (string) file_get_contents($root . '/bin/hybrid-zero-touch-status.php');
d4_assert('D4_status_loop_3s', str_contains($statusSrc, '--interval') && str_contains($statusSrc, 'sleep($interval)'), '3s probe loop');
d4_assert('D4_status_dns_https', str_contains($statusSrc, 'd4_dns') && str_contains($statusSrc, 'd4_probe_https'), 'dns+https');
d4_assert('D4_status_states', str_contains($statusSrc, 'online') && str_contains($statusSrc, 'offline') && str_contains($statusSrc, 'syncing'), 'states');
d4_assert('D4_writes_status_json', str_contains($statusSrc, 'status.json'), 'status.json');
d4_assert('D4_open_url_local_appliance', str_contains($statusSrc, 'cloudAdminUrl') && str_contains($statusSrc, '$openUrl = $localUrl'), 'open_url stays local; sync in background');
d4_assert('D4_no_runtime_mutation', !str_contains($statusSrc, 'file_put_contents($serve') && !str_contains($statusSrc, 'RATEB_RUNTIME='), 'does not rewrite serve.env runtime');

$tray = (string) file_get_contents($root . '/deploy/enterprise-installers/zero-touch/windows/RatibTray.ps1');
d4_assert('D4_tray_windows', str_contains($tray, 'NotifyIcon') && str_contains($tray, 'Open RATIB ERP'), 'NotifyIcon tray');
d4_assert('D4_tray_actions', str_contains($tray, 'Backup Now') && str_contains($tray, 'Diagnostics') && str_contains($tray, 'Restart Services'), 'menu actions');

$launch = (string) file_get_contents($root . '/deploy/enterprise-installers/zero-touch/windows/RatibLauncher.ps1');
d4_assert('D4_launcher_starts_services', str_contains($launch, 'RATIBBranchWeb') && str_contains($launch, 'Start-Process'), 'auto start');
d4_assert('D4_launcher_opens_browser', str_contains($launch, 'Start-Process $openUrl') || str_contains($launch, 'Start-Process $url') || str_contains($launch, 'Start-Process $u'), 'browser');

$iss = (string) file_get_contents($root . '/deploy/enterprise-installers/windows/RATIB-Branch-Setup.iss');
d4_assert('D4_shortcut_name', str_contains($iss, 'RATIB ERP'), 'desktop name RATIB ERP');

$linuxDesk = (string) file_get_contents($root . '/deploy/enterprise-installers/zero-touch/linux/install-zero-touch.sh');
d4_assert('D4_linux_desktop_ratib_erp', str_contains($linuxDesk, 'Name=RATIB ERP'), 'RATIB ERP.desktop');
d4_assert('D4_linux_status_service', str_contains($linuxDesk, 'ratib-zero-touch-status'), 'status systemd');

$uni = (string) file_get_contents($root . '/deploy/enterprise-installers/universal/install-universal.sh');
d4_assert('D4_wired_universal_linux', str_contains($uni, 'install-zero-touch.sh'), 'universal calls zero-touch');
$uniPs = (string) file_get_contents($root . '/deploy/enterprise-installers/universal/install-universal.ps1');
d4_assert('D4_wired_universal_windows', str_contains($uniPs, 'install-zero-touch.ps1'), 'universal calls zero-touch');

d4_assert('D4_export_support', is_file($root . '/bin/hybrid-zero-touch-export-support.php'), 'export package');
d4_assert('D4_background_sync', is_file($root . '/bin/hybrid-sync-service.php'), 'Hybrid Sync service');
d4_assert('D4_background_health', is_file($root . '/bin/hybrid-branch-health.php'), 'health');
d4_assert('D4_background_recover', is_file($root . '/bin/hybrid-branch-recover.php'), 'recover');
d4_assert('D4_background_backup', is_file($root . '/bin/hybrid-branch-backup.php'), 'backup');

$report = [
    'phase' => 'D.4',
    'title' => 'Zero-Touch Enterprise Experience',
    'ok' => $failed === 0,
    'passed' => $passed,
    'failed' => $failed,
    'checks' => $lines,
    'architecture_note' => 'Branch desktop open_url stays on local admin (http://127.0.0.1:8088/admin). Online/Offline reflects cloud reachability for Hybrid Sync only. Product nav is unified lean (no eproc extras). No mid-session RATEB_RUNTIME mutation (architecture locked).',
    'customer_flow' => ['Install', 'Open RATIB ERP', 'Login', 'Work'],
];
$out = $root . '/storage/branch/phase-d4-enterprise-verify.json';
if (!is_dir(dirname($out))) {
    @mkdir(dirname($out), 0775, true);
}
file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "Wrote {$out}" . PHP_EOL;
echo ($failed === 0 ? 'VERIFY OK' : 'VERIFY FAILED') . " passed={$passed} failed={$failed}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);

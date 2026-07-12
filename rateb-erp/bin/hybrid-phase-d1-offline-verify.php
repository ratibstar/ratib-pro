<?php
declare(strict_types=1);

/**
 * Phase D.1 — Enterprise Offline verification (Branch Appliance).
 * php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd bin/hybrid-phase-d1-offline-verify.php
 */

$root = dirname(__DIR__);
$failed = 0;
$passed = 0;
$lines = [];

function d1_assert(string $id, bool $ok, string $evidence): void
{
    global $failed, $passed, $lines;
    $status = $ok ? 'PASS' : 'FAIL';
    $lines[] = compact('status', 'id', 'evidence');
    echo "{$status} | {$id} | {$evidence}" . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

echo "=== Phase D.1 Offline Verify ===" . PHP_EOL;

// --- A: Branch serve.env bootstrap ---
$serveEnv = $root . '/storage/branch/serve.env';
$hadServe = is_file($serveEnv);
$serveBackup = $hadServe ? (string) file_get_contents($serveEnv) : null;

$testServe = implode("\n", [
    '# test',
    'RATEB_RUNTIME=branch',
    'RATEB_ALLOW_RUNTIME_MARKER=1',
    'RATEB_SQLITE_PATH=' . $root . '/storage/branch/rateb-branch.sqlite',
    'RATEB_HYBRID_SYNC_ENABLED=1',
    'RATEB_HYBRID_SYNC_SINK=mysql',
    '',
]);
@mkdir($root . '/storage/branch', 0770, true);
file_put_contents($serveEnv, $testServe);

foreach (['RATEB_RUNTIME', 'RATEB_ALLOW_RUNTIME_MARKER', 'RATEB_HYBRID_SYNC_ENABLED', 'RATEB_HYBRID_SYNC_SINK'] as $k) {
    putenv($k);
    unset($_ENV[$k]);
}

require_once $root . '/app/Core/BranchServeEnvBootstrap.php';
\Rateb\App\Core\BranchServeEnvBootstrap::reset();
\Rateb\App\Core\BranchServeEnvBootstrap::apply($root);

d1_assert('A_serve_env_runtime', (getenv('RATEB_RUNTIME') ?: '') === 'branch', 'RATEB_RUNTIME=' . (getenv('RATEB_RUNTIME') ?: ''));
d1_assert('A_serve_env_sync', (getenv('RATEB_HYBRID_SYNC_ENABLED') ?: '') === '1', 'sync=' . (getenv('RATEB_HYBRID_SYNC_ENABLED') ?: ''));

define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);
\Rateb\App\Core\HybridRuntime::reset();

d1_assert('A_hybrid_runtime_branch', \Rateb\App\Core\HybridRuntime::isBranchMode(), 'mode=' . \Rateb\App\Core\HybridRuntime::mode());
d1_assert('A_sqlite_selected', \Rateb\App\Core\HybridRuntime::shouldUseSqlite(), 'driver=' . \Rateb\App\Core\HybridRuntime::driver());

// Cloud unchanged when serve.env absent
@unlink($serveEnv);
foreach (['RATEB_RUNTIME', 'RATEB_ALLOW_RUNTIME_MARKER', 'RATEB_HYBRID_SYNC_ENABLED'] as $k) {
    putenv($k);
    unset($_ENV[$k]);
}
\Rateb\App\Core\BranchServeEnvBootstrap::reset();
\Rateb\App\Core\BranchServeEnvBootstrap::apply($root);
\Rateb\App\Core\HybridRuntime::reset();
d1_assert('A_cloud_no_serve_env', \Rateb\App\Core\HybridRuntime::isCloudMode(), 'mode=' . \Rateb\App\Core\HybridRuntime::mode());

if ($hadServe && $serveBackup !== null) {
    file_put_contents($serveEnv, $serveBackup);
} elseif (!$hadServe) {
    @unlink($serveEnv);
}

// --- B: Local vendor assets ---
$vendorFiles = [
    'public/assets/vendor/bootstrap/5.3.3/bootstrap.min.css',
    'public/assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css',
    'public/assets/vendor/bootstrap/5.3.3/bootstrap.bundle.min.js',
    'public/assets/vendor/fontawesome/6.5.2/css/all.min.css',
    'public/assets/vendor/chartjs/4.4.3/chart.umd.min.js',
    'public/assets/vendor/fonts/tajawal/tajawal.css',
    'public/assets/vendor/qrcodejs/qrcode.min.js',
];
$missingVendor = [];
foreach ($vendorFiles as $rel) {
    if (!is_file($root . '/' . $rel)) {
        $missingVendor[] = $rel;
    }
}
d1_assert('B_vendor_assets_present', $missingVendor === [], $missingVendor === [] ? 'all present' : implode(',', $missingVendor));

$cdnHits = [];
$scanRoots = [$root . '/views', $root . '/modules/pos/views', $root . '/public/assets/js'];
$cdnPatterns = ['cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'fonts.googleapis.com', 'api.qrserver.com'];
foreach ($scanRoots as $scanRoot) {
    if (!is_dir($scanRoot)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'js', 'css', 'html'], true)) {
            continue;
        }
        $content = (string) file_get_contents($file->getPathname());
        foreach ($cdnPatterns as $pat) {
            if (str_contains($content, $pat)) {
                $cdnHits[] = str_replace($root . '/', '', $file->getPathname()) . ':' . $pat;
            }
        }
    }
}
// CMS admin tinymce/sortable remain CDN-only (admin CMS route — not branch core modules)
$cdnHits = array_values(array_filter($cdnHits, static fn (string $h): bool => !str_contains($h, 'cms-admin.js')));
d1_assert('B_no_cdn_in_core_ui', $cdnHits === [], $cdnHits === [] ? 'clean' : implode('; ', array_slice($cdnHits, 0, 5)));

d1_assert('B_helper_functions', function_exists('rateb_vendor_asset') && function_exists('rateb_bootstrap_css'), 'helpers ok');

// --- C: Local QR ---
require_once $root . '/app/Core/LocalQrRenderer.php';
$qrOk = false;
$qrDetail = 'gd missing';
if (extension_loaded('gd')) {
    try {
        $png = \Rateb\App\Core\LocalQrRenderer::png('RATEB-OFFLINE-TEST', 200);
        $qrOk = $png !== '' && str_starts_with($png, "\x89PNG");
        $qrDetail = $qrOk ? 'png=' . strlen($png) : 'empty';
    } catch (Throwable $e) {
        $qrDetail = $e->getMessage();
    }
} else {
    d1_assert('C_gd_extension', false, 'gd required for local QR PNG');
}
if (extension_loaded('gd')) {
    d1_assert('C_local_qr_png', $qrOk, $qrDetail);
}

$svcFiles = [
    $root . '/app/services/BarcodeLoginService.php',
    $root . '/app/services/DocumentBarcodeService.php',
];
$qrRemote = false;
foreach ($svcFiles as $f) {
    if (is_file($f) && str_contains((string) file_get_contents($f), 'api.qrserver.com')) {
        $qrRemote = true;
    }
}
d1_assert('C_no_qrserver_in_services', !$qrRemote, $qrRemote ? 'still remote' : 'local urls');

$ctrl = (string) file_get_contents($root . '/app/controllers/Shared/BarcodeQrController.php');
d1_assert('C_controller_local', str_contains($ctrl, 'LocalQrRenderer') && !str_contains($ctrl, 'fetchRemote'), 'controller ok');

// --- D: Installer serve.env ---
$installSrc = (string) file_get_contents($root . '/bin/hybrid-branch-install.php');
d1_assert('D_install_sync_enabled', str_contains($installSrc, 'RATEB_HYBRID_SYNC_ENABLED=1'), 'sync enabled');
d1_assert('D_install_sync_sink', str_contains($installSrc, 'RATEB_HYBRID_SYNC_SINK=mysql'), 'sink mysql');

$applianceSrc = (string) file_get_contents($root . '/app/Core/BranchApplianceInstaller.php');
d1_assert('D_appliance_installer_sync', str_contains($applianceSrc, 'RATEB_HYBRID_SYNC_ENABLED=1'), 'appliance ok');

// --- Readiness score ---
$totalChecks = $passed + $failed;
$readiness = $totalChecks > 0 ? (int) round(($passed / $totalChecks) * 100) : 0;
$verdict = $failed === 0 ? 'ENTERPRISE_PASS' : 'ENTERPRISE_FAIL';

echo PHP_EOL . "Passed: {$passed}  Failed: {$failed}" . PHP_EOL;
echo "Offline Readiness: {$readiness}%" . PHP_EOL;
echo "VERDICT: {$verdict}" . PHP_EOL;

@mkdir($root . '/storage/branch', 0770, true);
file_put_contents($root . '/storage/branch/phase-d1-offline-verify.json', json_encode([
    'phase' => 'D.1',
    'verdict' => $verdict,
    'readiness_pct' => $readiness,
    'passed' => $passed,
    'failed' => $failed,
    'lines' => $lines,
    'rollback' => 'git revert HEAD~N  # one commit per atomic change below',
    'commits' => [
        'A' => 'feat(hybrid): auto-load branch serve.env in HTTP bootstrap',
        'B' => 'feat(offline): self-host Bootstrap, FA, Chart.js, fonts',
        'C' => 'feat(offline): local PHP QR generation (no qrserver)',
        'D' => 'feat(hybrid): hybrid-branch-install writes sync env',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

exit($failed > 0 ? 1 : 0);

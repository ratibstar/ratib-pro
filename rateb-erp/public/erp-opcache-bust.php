<?php
declare(strict_types=1);

/**
 * One-shot opcache bust after deploys where validate_timestamps is off.
 * Safe to leave: only invalidates/resets opcache, no auth secrets.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$targets = [
    __DIR__ . '/index.php',
    __DIR__ . '/agency-commercial-lock.php',
    dirname(__DIR__) . '/app/Core/Bootstrap.php',
    dirname(__DIR__) . '/app/Core/AgencyCommercialLock.php',
    __DIR__ . '/erp-build-probe.php',
    __DIR__ . '/erp-sa-gate-probe.php',
    dirname(__DIR__) . '/app/Core/Middleware/Middleware.php',
    dirname(__DIR__) . '/app/services/PlanLimitService.php',
    dirname(__DIR__) . '/app/services/ModuleAddonDemoPreviewService.php',
    dirname(__DIR__) . '/app/controllers/Company/ModuleAddonDemoPreviewController.php',
    dirname(__DIR__) . '/routes/modules/module-addons.php',
    dirname(__DIR__) . '/config/app.php',
];

foreach ($targets as $file) {
    $name = basename($file);
    if (!is_file($file)) {
        echo $name . '=missing' . PHP_EOL;
        continue;
    }
    $ok = false;
    if (function_exists('opcache_invalidate')) {
        $ok = @opcache_invalidate($file, true) ? true : false;
    }
    echo $name . '_invalidate=' . ($ok ? 'yes' : 'no') . PHP_EOL;
}

$reset = false;
if (function_exists('opcache_reset')) {
    $reset = @opcache_reset() ? true : false;
}
echo 'opcache_reset=' . ($reset ? 'yes' : 'no') . PHP_EOL;

$index = __DIR__ . '/index.php';
$raw = is_file($index) ? (string) file_get_contents($index) : '';
echo 'index_has_degraded_stub=' . (str_contains($raw, "'degraded' => true") ? 'yes' : 'no') . PHP_EOL;
echo 'bust_ok' . PHP_EOL;

<?php
declare(strict_types=1);

/**
 * Temporary FPM-facing AE probe — DELETE after measurement.
 * Hit: /rateb-erp/public/_ae_fpm_probe.php?path=/admin/hr
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$path = (string) ($_GET['path'] ?? '/admin/hr');
$path = '/' . trim(str_replace('\\', '/', $path), '/');
if ($path !== '/') {
    $path = rtrim($path, '/') ?: '/';
}

$ROOT = dirname(__DIR__);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public' . $path
    . ((str_contains($path, '/ops/') || str_contains($path, '/hr') || str_contains($path, '/crm'))
        ? '?company_id=22' : '');
if (str_contains($_SERVER['REQUEST_URI'], 'company_id=')) {
    $_GET['company_id'] = '22';
}

$t0 = hrtime(true);
$marks = [];
$mark = static function (string $name) use (&$marks, $t0): void {
    $marks[$name] = round((hrtime(true) - $t0) / 1e6, 3);
};

require_once $ROOT . '/app/Core/Bootstrap.php';
$mark('after_bootstrap_require');

\Rateb\App\Core\Bootstrap::init($ROOT);
$mark('after_bootstrap_init');

$pos = $ROOT . '/modules/pos/PosModule.php';
if (is_file($pos)) {
    require_once $pos;
    \Rateb\App\Pos\PosModule::init();
}
$mark('after_pos');
$off = $ROOT . '/offline/OfflineModule.php';
if (is_file($off)) {
    require_once $off;
    \Rateb\App\Offline\OfflineModule::init();
}
$mark('after_offline');

\Rateb\App\Core\Auth::bootstrapFromSession();
$mark('after_auth_bootstrap');

if (!\Rateb\App\Core\Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'not_authenticated', 'marks' => $marks]);
    exit;
}

require_once RATEB_ROOT . '/app/helpers/Request.php';
$router = new \Rateb\App\Core\Router();
$selected = \Rateb\App\Core\RouteModuleLoader::selectModuleIds($path);
$mark('after_select');

\Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
$mark('after_routes_load');

$loaded = \Rateb\App\Core\RouteModuleLoader::lastLoadedIds();
$routeCount = $router->routeCount();

$mw = new \Rateb\App\Core\Middleware\ErpAuthMiddleware();
$mwOk = $mw->handle();
$mark('after_middleware');

ob_start();
$router->dispatch('GET', $path);
$html = (string) ob_get_clean();
$mark('after_dispatch');

$total = round((hrtime(true) - $t0) / 1e6, 3);

// Derive stage durations
$order = array_keys($marks);
$durs = [];
$prev = 0.0;
foreach ($marks as $name => $abs) {
    $durs[$name] = round($abs - $prev, 3);
    $prev = $abs;
}

echo json_encode([
    'ok' => true,
    'sapi' => PHP_SAPI,
    'path' => $path,
    'total_ms' => $total,
    'marks_abs_ms' => $marks,
    'stage_ms' => $durs,
    'selected' => $selected,
    'loaded' => $loaded,
    'route_count' => $routeCount,
    'middleware_ok' => $mwOk,
    'html_bytes' => strlen($html),
    'opcache_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
    'included_files' => count(get_included_files()),
    'mem_peak' => memory_get_peak_usage(true),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

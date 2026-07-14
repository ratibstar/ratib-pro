<?php
declare(strict_types=1);

$mode = $argv[1] ?? 'all';
$path = $argv[2] ?? '/admin';
$method = 'GET';
$root = dirname(__DIR__, 2);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public' . ($path === '/' ? '/' : $path);

require $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

$posModule = $root . '/modules/pos/PosModule.php';
if (is_file($posModule)) {
    require_once $posModule;
    \Rateb\App\Pos\PosModule::init();
}
$offlineModule = $root . '/offline/OfflineModule.php';
if (is_file($offlineModule)) {
    require_once $offlineModule;
    \Rateb\App\Offline\OfflineModule::init();
}
\Rateb\App\Core\Auth::bootstrapFromSession();

$router = new \Rateb\App\Core\Router();
$t0 = hrtime(true);
$m0 = memory_get_usage(true);
if ($mode === 'selective') {
    \Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
    if (
        \Rateb\App\Core\RouteModuleLoader::lastMode() === 'selective'
        && !$router->hasMatch($method, $path)
    ) {
        $router = new \Rateb\App\Core\Router();
        \Rateb\App\Core\RouteModuleLoader::loadAll($router);
        \Rateb\App\Core\RouteModuleLoader::markFallbackAll();
    }
} else {
    \Rateb\App\Core\RouteModuleLoader::loadAll($router);
}
$regMs = (hrtime(true) - $t0) / 1e6;

echo json_encode([
    'path' => $path,
    'mode_requested' => $mode,
    'mode' => \Rateb\App\Core\RouteModuleLoader::lastMode(),
    'modules' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
    'route_count' => $router->routeCount(),
    'registration_ms' => round($regMs, 3),
    'memory_peak_bytes' => memory_get_peak_usage(true),
    'memory_delta_bytes' => memory_get_peak_usage(true) - $m0,
    'has_match' => $router->hasMatch($method, $path),
], JSON_UNESCAPED_SLASHES) . "\n";

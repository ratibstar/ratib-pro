<?php
declare(strict_types=1);

/**
 * Phase AA.2 — measure selective vs loadAll for given path.
 * Usage: php phase-aa2-measure.php /admin
 */
$pathArg = $argv[1] ?? '/admin';
$root = dirname(__DIR__, 2);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public' . ($pathArg === '/' ? '/' : $pathArg);
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? dirname($root);

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

$path = $pathArg;
$method = 'GET';

$run = static function (string $label, callable $loader) use ($path, $method): array {
    $router = new \Rateb\App\Core\Router();
    $mem0 = memory_get_usage(true);
    $t0 = hrtime(true);
    $loader($router);
    $regMs = (hrtime(true) - $t0) / 1e6;
    $has = $router->hasMatch($method, $path);
    return [
        'label' => $label,
        'mode' => \Rateb\App\Core\RouteModuleLoader::lastMode(),
        'modules' => \Rateb\App\Core\RouteModuleLoader::lastLoadedIds(),
        'files' => \Rateb\App\Core\RouteModuleLoader::lastLoadedFiles(),
        'route_count' => $router->routeCount(),
        'registration_ms' => round($regMs, 3),
        'memory_peak_bytes' => memory_get_peak_usage(true),
        'memory_delta_bytes' => memory_get_peak_usage(true) - $mem0,
        'has_match' => $has,
    ];
};

// Fresh processes would be cleaner; same-process: loadAll first (re-require ok with new router)
$all = $run('aa1_loadAll', static function (\Rateb\App\Core\Router $r): void {
    \Rateb\App\Core\RouteModuleLoader::loadAll($r);
});

$sel = $run('aa2_selective', static function (\Rateb\App\Core\Router $r) use ($path): void {
    \Rateb\App\Core\RouteModuleLoader::loadForPath($r, $path);
});

$fallbackUsed = false;
if ($sel['mode'] === 'selective' && !$sel['has_match']) {
    $fallbackUsed = true;
    $sel = $run('aa2_fallback', static function (\Rateb\App\Core\Router $r): void {
        \Rateb\App\Core\RouteModuleLoader::loadAll($r);
        \Rateb\App\Core\RouteModuleLoader::markFallbackAll();
    });
}

$out = [
    'path' => $path,
    'method' => $method,
    'selected_ids' => \Rateb\App\Core\RouteModuleLoader::selectModuleIds($path),
    'aa1' => $all,
    'aa2' => $sel,
    'fallback_used' => $fallbackUsed,
    'route_count_reduction' => $all['route_count'] - $sel['route_count'],
    'success_fewer_routes' => $sel['route_count'] < $all['route_count'],
    'admin_still_matches' => $sel['has_match'],
];

$dir = __DIR__ . '/reports';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$slug = trim(str_replace(['/', '\\'], '-', $path), '-') ?: 'root';
$file = $dir . '/phase-aa2-' . $slug . '.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wrote $file\n";

<?php
declare(strict_types=1);

$mode = $argv[1] ?? 'loader';
$root = dirname(__DIR__, 2);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/rateb-erp/public/admin/';
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

$router = new \Rateb\App\Core\Router();
$loadedIds = [];
$loadedFiles = [];

if ($mode === 'legacy') {
    $manifest = require RATEB_ROOT . '/routes/manifest.php';
    foreach ($manifest as $module) {
        $id = (string) ($module['id'] ?? '');
        $rel = (string) ($module['file'] ?? '');
        $optional = !empty($module['optional']);
        if ($id === '' || $rel === '') {
            continue;
        }
        $filePath = RATEB_ROOT . '/' . ltrim(str_replace('\\', '/', $rel), '/');
        if (!is_file($filePath)) {
            if ($optional) {
                continue;
            }
            throw new RuntimeException('Missing route module: ' . $filePath);
        }
        require $filePath;
        $loadedIds[] = $id;
        $loadedFiles[] = $rel;
    }
} else {
    $loadedIds = \Rateb\App\Core\RouteModuleLoader::loadAll($router);
    $loadedFiles = \Rateb\App\Core\RouteModuleLoader::lastLoadedFiles();
}

$ref = new ReflectionClass($router);
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
$routes = $prop->getValue($router);

$normalizeHandler = static function ($handler): string {
    if ($handler instanceof Closure) {
        return 'Closure';
    }
    if (is_array($handler) && isset($handler[0], $handler[1])) {
        $class = is_object($handler[0]) ? get_class($handler[0]) : (string) $handler[0];
        return $class . '::' . (string) $handler[1];
    }
    return is_string($handler) ? $handler : gettype($handler);
};
$normalizeMiddleware = static function (array $middleware): array {
    $out = [];
    foreach ($middleware as $item) {
        if (is_string($item)) {
            $out[] = $item;
        } elseif (is_array($item) && isset($item[0])) {
            $out[] = (string) $item[0] . (isset($item[1]) ? ':' . (string) $item[1] : '');
        } else {
            $out[] = gettype($item);
        }
    }
    return $out;
};

$signatures = [];
foreach ($routes as $route) {
    $signatures[] = [
        'method' => (string) $route['method'],
        'pattern' => (string) $route['pattern'],
        'handler' => $normalizeHandler($route['handler']),
        'middleware' => $normalizeMiddleware($route['middleware'] ?? []),
    ];
}

$canonical = json_encode($signatures, JSON_UNESCAPED_SLASHES);
$hash = hash('sha256', (string) $canonical);
$output = [
    'loaded_modules' => $loadedIds,
    'loaded_files' => $loadedFiles,
    'route_count' => count($signatures),
    'admin_matches' => array_values(array_filter(
        $signatures,
        static fn (array $signature): bool =>
            $signature['method'] === 'GET' && $signature['pattern'] === '/admin'
    )),
];

$dir = __DIR__ . '/reports';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
file_put_contents($dir . '/phase-aa1-' . $mode . '-latest.json', json_encode($output, JSON_PRETTY_PRINT) . "\n");
file_put_contents($dir . '/phase-aa1-' . $mode . '-routes.sha256', $hash . "\n");

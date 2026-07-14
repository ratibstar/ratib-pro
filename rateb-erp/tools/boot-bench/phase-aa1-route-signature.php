<?php
declare(strict_types=1);

/**
 * Phase AA.1 — capture route table signature for identity verification.
 * Usage:
 *   php tools/boot-bench/phase-aa1-route-signature.php legacy|loader
 */
$mode = $argv[1] ?? 'loader';
$root = dirname(__DIR__, 2);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/rateb-erp/public/admin/';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? dirname($root);

$t0 = hrtime(true);
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
$memBefore = memory_get_usage(true);
$regStart = hrtime(true);

$loadedIds = [];
$loadedFiles = [];

if ($mode === 'legacy') {
    require RATEB_ROOT . '/routes/web.php';
    require RATEB_ROOT . '/routes/marketing.php';
    require RATEB_ROOT . '/routes/cms.php';
    require RATEB_ROOT . '/routes/company.php';
    require RATEB_ROOT . '/routes/api.php';
    require RATEB_ROOT . '/modules/pos/routes/pos.php';
    $loadedIds = ['web', 'marketing', 'cms', 'company', 'api', 'pos'];
    $loadedFiles = [
        'routes/web.php',
        'routes/marketing.php',
        'routes/cms.php',
        'routes/company.php',
        'routes/api.php',
        'modules/pos/routes/pos.php',
    ];
    if (is_file(RATEB_ROOT . '/modules/pos/routes/pos-v2.php')) {
        require RATEB_ROOT . '/modules/pos/routes/pos-v2.php';
        $loadedIds[] = 'pos_v2';
        $loadedFiles[] = 'modules/pos/routes/pos-v2.php';
    }
} else {
    $loadedIds = \Rateb\App\Core\RouteModuleLoader::loadAll($router);
    $loadedFiles = \Rateb\App\Core\RouteModuleLoader::lastLoadedFiles();
}

$regMs = (hrtime(true) - $regStart) / 1e6;
$wallMs = (hrtime(true) - $t0) / 1e6;
$memAfter = memory_get_peak_usage(true);

$ref = new ReflectionClass($router);
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
/** @var list<array{method:string,pattern:string,handler:mixed,middleware:array}> $routes */
$routes = $prop->getValue($router);

$normalizeHandler = static function ($handler): string {
    if ($handler instanceof Closure) {
        return 'Closure';
    }
    if (is_array($handler) && isset($handler[0], $handler[1])) {
        $cls = is_object($handler[0]) ? get_class($handler[0]) : (string) $handler[0];
        return $cls . '::' . (string) $handler[1];
    }
    if (is_string($handler)) {
        return $handler;
    }
    return gettype($handler);
};

$normalizeMw = static function (array $mw): array {
    $out = [];
    foreach ($mw as $item) {
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

$sigs = [];
foreach ($routes as $r) {
    $sigs[] = [
        'method' => (string) $r['method'],
        'pattern' => (string) $r['pattern'],
        'handler' => $normalizeHandler($r['handler']),
        'middleware' => $normalizeMw($r['middleware'] ?? []),
    ];
}

$canonical = json_encode($sigs, JSON_UNESCAPED_SLASHES);
$hash = hash('sha256', (string) $canonical);

$out = [
    'mode' => $mode,
    'ok' => true,
    'loaded_modules' => $loadedIds,
    'loaded_files' => $loadedFiles,
    'route_count' => count($sigs),
    'route_table_sha256' => $hash,
    'registration_ms' => round($regMs, 3),
    'bootstrap_wall_ms' => round($wallMs, 3),
    'memory_bytes_before_routes' => $memBefore,
    'memory_peak_bytes' => $memAfter,
    'first_5' => array_slice($sigs, 0, 5),
    'admin_matches' => array_values(array_filter(
        $sigs,
        static fn ($s) => $s['method'] === 'GET' && $s['pattern'] === '/admin'
    )),
];

$dir = __DIR__ . '/reports';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$file = $dir . '/phase-aa1-' . $mode . '-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($dir . '/phase-aa1-' . $mode . '-latest.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($dir . '/phase-aa1-' . $mode . '-routes.sha256', $hash . "\n");
file_put_contents($dir . '/phase-aa1-' . $mode . '-routes.json', $canonical . "\n");

echo json_encode([
    'wrote' => $file,
    'mode' => $mode,
    'route_count' => count($sigs),
    'route_table_sha256' => $hash,
    'registration_ms' => $out['registration_ms'],
    'memory_peak_bytes' => $memAfter,
    'loaded_modules' => $loadedIds,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

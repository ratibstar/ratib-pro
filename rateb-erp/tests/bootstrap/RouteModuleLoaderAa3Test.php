<?php
declare(strict_types=1);

/**
 * Phase AA.3 selection / dashboard-minimal smoke tests (CLI).
 */
$root = dirname(__DIR__, 2);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
require $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

$fail = 0;
$check = static function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $name\n";
    if (!$ok) {
        $fail++;
    }
};

$adminIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin');
$check('GET /admin → auth+dashboard only', $adminIds === ['auth', 'dashboard']);

$execIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin/executive-dashboard');
$check('executive-dashboard → auth+dashboard', $execIds === ['auth', 'dashboard']);

$loginIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/login');
$check('GET /login → auth only', $loginIds === ['auth']);

$logoutIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/logout');
$check('GET /logout → auth only', $logoutIds === ['auth']);

$usersIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin/users');
$check('GET /admin/users → auth+platform', $usersIds === ['auth', 'platform']);

$opsIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin/ops/inventory');
$check('GET /admin/ops/inventory → auth+ops', $opsIds === ['auth', 'ops']);

$hrIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin/hr');
$check('GET /admin/hr → auth+ops', $hrIds === ['auth', 'ops']);

$cmsIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin/cms');
$check('GET /admin/cms → auth+cms', $cmsIds === ['auth', 'cms']);

$apiIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/api/v1/dashboard');
$check('GET /api → auth+api', $apiIds === ['auth', 'api']);

$siteIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/site/login');
$check('GET /site → auth+marketing', $siteIds === ['auth', 'marketing']);

$posIds = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/admin/ops/pos/register');
$check('POS UI → auth+pos+pos_v2', $posIds === ['auth', 'pos', 'pos_v2']);

$unknown = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/totally-unknown-xyz');
$check('unknown path → null (loadAll)', $unknown === null);

$router = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadForPath($router, '/admin');
$check('selective mode for /admin', \Rateb\App\Core\RouteModuleLoader::lastMode() === 'selective');
$check('/admin hasMatch after selective', $router->hasMatch('GET', '/admin'));
$check('GET /admin route count < 150', $router->routeCount() < 150);
$check('GET /admin route count > 0', $router->routeCount() > 0);

$matched = null;
$ref = new ReflectionClass($router);
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
foreach ($prop->getValue($router) as $route) {
    if (($route['method'] ?? '') === 'GET' && ($route['pattern'] ?? '') === '/admin') {
        $h = $route['handler'] ?? null;
        if (is_array($h)) {
            $matched = (is_object($h[0]) ? get_class($h[0]) : (string) $h[0]) . '::' . (string) $h[1];
        }
        break;
    }
}
$check(
    'matched controller DashboardController::index',
    $matched === \Rateb\App\Controllers\Admin\DashboardController::class . '::index'
);

$check('marketing not registered on /admin selective', !$router->hasMatch('GET', '/site/login'));
$check('ops inventory not registered on /admin selective', !$router->hasMatch('GET', '/admin/ops/inventory'));
$check('platform users not registered on /admin selective', !$router->hasMatch('GET', '/admin/users'));

$routerAll = new \Rateb\App\Core\Router();
\Rateb\App\Core\RouteModuleLoader::loadAll($routerAll);
$check('loadAll has /admin', $routerAll->hasMatch('GET', '/admin'));
$check('loadAll has more routes than dashboard minimal', $routerAll->routeCount() > $router->routeCount());

echo 'admin_route_count=' . $router->routeCount() . "\n";
echo 'loadAll_route_count=' . $routerAll->routeCount() . "\n";
echo $fail === 0 ? "GATE: CLEAR\n" : "GATE: FAIL ($fail)\n";
exit($fail === 0 ? 0 : 1);

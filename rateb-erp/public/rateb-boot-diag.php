<?php
declare(strict_types=1);
/**
 * Temporary boot diagnostic — remove after cloud 500 is resolved.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$out = [];
$step = 'start';

try {
    $step = 'fullInit';
    require_once $root . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init($root);
    $out[] = 'fullInit=ok';

    $step = 'posModule';
    require_once $root . '/modules/pos/PosModule.php';
    \Rateb\App\Pos\PosModule::init();
    $out[] = 'pos=ok';

    $step = 'offlineModule';
    require_once $root . '/offline/OfflineModule.php';
    \Rateb\App\Offline\OfflineModule::init();
    $out[] = 'offline=ok';

    $step = 'authBootstrap';
    \Rateb\App\Core\Auth::bootstrapFromSession();
    $out[] = 'auth=ok check=' . (\Rateb\App\Core\Auth::check() ? '1' : '0');

    $step = 'routerLoad';
    $router = new \Rateb\App\Core\Router();
    require $root . '/routes/web.php';
    require $root . '/routes/marketing.php';
    require $root . '/routes/cms.php';
    require $root . '/routes/company.php';
    require $root . '/routes/api.php';
    require $root . '/modules/pos/routes/pos.php';
    $out[] = 'routes=ok';

    $step = 'resolvePath';
    require_once $root . '/app/helpers/Request.php';
    $_SERVER['REQUEST_URI'] = '/';
    $path = \Rateb\App\Helpers\Request::resolvePath();
    $out[] = 'path=' . $path;

    $step = 'cmsRedirect';
    (new \Rateb\App\Services\CmsService())->applyRedirectIfAny($path);
    $out[] = 'cmsRedirect=ok';

    $step = 'dispatchRoot';
    ob_start();
    $router->dispatch('GET', '/');
    $body = ob_get_clean();
    $out[] = 'dispatchRootLen=' . strlen((string) $body);
    $out[] = 'hasMkt=' . (str_contains((string) $body, 'rateb-marketing') ? '1' : '0');
    $out[] = 'hasErr=' . (str_contains((string) $body, 'تعذّر') ? '1' : '0');

    $step = 'done';
    $out[] = 'ALL_OK';
} catch (Throwable $e) {
    $out[] = 'FAIL_STEP=' . $step;
    $out[] = 'FAIL ' . $e->getMessage();
    $out[] = 'at ' . $e->getFile() . ':' . $e->getLine();
    $out[] = substr($e->getTraceAsString(), 0, 2000);
}

echo implode("\n", $out) . "\n";

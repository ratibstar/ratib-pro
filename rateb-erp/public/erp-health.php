<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

define('RATEB_ENV_NO_SESSION', true);

$ratebRoot = realpath(dirname(__FILE__, 2));
if ($ratebRoot === false) {
    $ratebRoot = dirname(__FILE__, 2);
}
if (!defined('RATEB_ROOT')) {
    define('RATEB_ROOT', str_replace('\\', '/', $ratebRoot));
}

$probe = isset($_GET['probe']) ? (string) $_GET['probe'] : '';
$dispatchRoute = isset($_GET['dispatch']) ? (string) $_GET['dispatch'] : '';

$steps = [];
try {
    $steps[] = 'PHP ' . PHP_VERSION;
    $steps[] = 'RATEB_ROOT=' . RATEB_ROOT;

    require_once RATEB_ROOT . '/config/app.php';
    $steps[] = 'app.php OK';

    require_once RATEB_ROOT . '/config/database.php';
    $steps[] = 'database.php OK (DB_HOST=' . (defined('DB_HOST') ? DB_HOST : '?') . ', ERP DB=' . (defined('RATEB_DB_NAME') ? RATEB_DB_NAME : '?') . ')';

    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
    $steps[] = 'Bootstrap OK';

    $pdo = \Rateb\App\Core\Database::connection();
    $steps[] = 'DB connection OK (' . \Rateb\App\Core\Database::resolvedDatabaseName() . ')';

    if ($probe === 'routes' || $probe === 'dispatch' || $dispatchRoute !== '') {
        \Rateb\App\Core\Auth::bootstrapFromSession();
        $router = new \Rateb\App\Core\Router();
        require RATEB_ROOT . '/routes/web.php';
        $steps[] = 'routes/web.php OK';
        require RATEB_ROOT . '/routes/company.php';
        $steps[] = 'routes/company.php OK';
        require RATEB_ROOT . '/routes/api.php';
        $steps[] = 'routes/api.php OK';
    }

    if ($probe === 'dispatch' || $dispatchRoute !== '') {
        require_once RATEB_ROOT . '/app/helpers/Request.php';
        $route = $dispatchRoute !== '' ? $dispatchRoute : 'login';
        $_GET['route'] = ltrim($route, '/');
        $path = \Rateb\App\Helpers\Request::resolvePath();
        $steps[] = 'resolved path=' . $path;

        ob_start();
        $router->dispatch('GET', $path);
        $body = (string) ob_get_clean();
        $steps[] = 'dispatch OK body_len=' . strlen($body);
    }

    echo "RATEB ERP health: OK\n";
    foreach ($steps as $line) {
        echo '- ' . $line . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "RATEB ERP health: FAIL\n";
    foreach ($steps as $line) {
        echo '- ' . $line . "\n";
    }
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}

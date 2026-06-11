<?php
declare(strict_types=1);

define('RATEB_ROOT', str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)));

try {
    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';

    Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

    Rateb\App\Core\Auth::bootstrapFromSession();

    $router = new Rateb\App\Core\Router();

    require RATEB_ROOT . '/routes/web.php';
    require RATEB_ROOT . '/routes/company.php';
    require RATEB_ROOT . '/routes/api.php';

    require_once RATEB_ROOT . '/app/helpers/Request.php';

    $path = \Rateb\App\Helpers\Request::resolvePath();
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (Throwable $e) {
    error_log('RATEB ERP bootstrap error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    $debug = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');
    if ($debug) {
        echo '<h1>RATEB ERP Error</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'RATEB ERP is temporarily unavailable. Please check server logs or run migrations.';
    }
}

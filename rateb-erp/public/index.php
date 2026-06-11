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
    $cpMode = defined('RATEB_CP_ENTRY') && RATEB_CP_ENTRY;
    if ($debug || $cpMode) {
        echo '<div style="font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;padding:1rem 1.25rem;">';
        echo '<h1 style="font-size:1.25rem;">RATEB ERP Error</h1>';
        echo '<pre style="background:#f5f5f5;padding:1rem;overflow:auto;">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
        if ($cpMode && function_exists('control_rateb_erp_migrate_page_url')) {
            echo '<p><a href="' . htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8') . '">Open ERP Database Setup</a> to run migrations.</p>';
        }
        echo '</div>';
    } else {
        echo 'RATEB ERP is temporarily unavailable. Please check server logs or run migrations.';
    }
}

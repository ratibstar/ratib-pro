<?php
declare(strict_types=1);

if (!defined('RATEB_ENV_NO_SESSION')) {
    define('RATEB_ENV_NO_SESSION', true);
}

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    $title = function_exists('__') ? __('db_error_title') : 'خطأ في النظام';
    $body = function_exists('__') ? __('system_error_generic') : 'حدث خطأ. يرجى المحاولة لاحقاً أو التواصل مع الدعم.';
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head><body style="font-family:Tajawal,sans-serif;padding:2rem;text-align:center"><p>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
});

try {
    $ratebRootHint = realpath(dirname(__FILE__, 2));
    if ($ratebRootHint === false) {
        $ratebRootHint = dirname(__FILE__, 2);
    }

    require_once dirname(__FILE__, 2) . '/app/Core/Bootstrap.php';

    Rateb\App\Core\Bootstrap::init($ratebRootHint);

    Rateb\App\Core\Auth::bootstrapFromSession();

    $router = new Rateb\App\Core\Router();

    require RATEB_ROOT . '/routes/web.php';
    require RATEB_ROOT . '/routes/marketing.php';
    require RATEB_ROOT . '/routes/cms.php';
    require RATEB_ROOT . '/routes/company.php';
    require RATEB_ROOT . '/routes/api.php';

    require_once RATEB_ROOT . '/app/helpers/Request.php';

    $path = \Rateb\App\Helpers\Request::resolvePath();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        try {
            (new \Rateb\App\Services\CmsService())->applyRedirectIfAny($path);
        } catch (Throwable $redirectEx) {
            error_log('CMS redirect check: ' . $redirectEx->getMessage());
        }
    }
    $router->dispatch($method, $path);
} catch (Throwable $e) {
    error_log('RATEB ERP error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (class_exists(\Rateb\App\Services\DatabaseErrorService::class)) {
        \Rateb\App\Services\DatabaseErrorService::renderHttpError($e);
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'RATEB ERP is temporarily unavailable.';
    }
}

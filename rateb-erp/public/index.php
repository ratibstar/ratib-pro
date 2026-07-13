<?php
declare(strict_types=1);

// PHP built-in server (Branch Appliance): static files bypass the front controller.
// Missing assets must NOT boot ERP (each miss was ~1.5–2s and queued on single-thread -S).
if (PHP_SAPI === 'cli-server') {
    $cliPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    if ($cliPath !== '' && $cliPath !== '/') {
        $cliFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $cliPath);
        $ext = strtolower(pathinfo($cliFile, PATHINFO_EXTENSION));
        $staticExt = [
            'css' => true, 'js' => true, 'mjs' => true, 'map' => true,
            'png' => true, 'jpg' => true, 'jpeg' => true, 'gif' => true, 'webp' => true, 'svg' => true, 'ico' => true,
            'woff' => true, 'woff2' => true, 'ttf' => true, 'eot' => true, 'otf' => true,
            'json' => true, 'webmanifest' => true, 'txt' => true, 'xml' => true, 'pdf' => true,
        ];
        if (is_file($cliFile)) {
            if ($ext !== 'php') {
                return false;
            }
        } elseif (isset($staticExt[$ext])
            || str_starts_with($cliPath, '/assets/')
            || str_starts_with($cliPath, '/favicon')) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: public, max-age=60');
            echo 'Not Found';
            return true;
        }
    }
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
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

    $posModule = dirname(__FILE__, 2) . '/modules/pos/PosModule.php';
    if (is_file($posModule)) {
        require_once $posModule;
        \Rateb\App\Pos\PosModule::init();
    }

    // Soft-load: production must not fatal if offline/ not yet deployed.
    $offlineModule = dirname(__FILE__, 2) . '/offline/OfflineModule.php';
    if (is_file($offlineModule)) {
        require_once $offlineModule;
        \Rateb\App\Offline\OfflineModule::init();
    }

    Rateb\App\Core\Auth::bootstrapFromSession();

    $router = new Rateb\App\Core\Router();

    require RATEB_ROOT . '/routes/web.php';
    require RATEB_ROOT . '/routes/marketing.php';
    require RATEB_ROOT . '/routes/cms.php';
    require RATEB_ROOT . '/routes/company.php';
    require RATEB_ROOT . '/routes/api.php';
    require RATEB_ROOT . '/modules/pos/routes/pos.php';
    if (is_file(RATEB_ROOT . '/modules/pos/routes/pos-v2.php')) {
        require RATEB_ROOT . '/modules/pos/routes/pos-v2.php';
    }

    require_once RATEB_ROOT . '/app/helpers/Request.php';

    $path = \Rateb\App\Helpers\Request::resolvePath();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    // Skip CMS redirect DB hit on admin/api/POS — saves a query on every ERP page.
    if ($method === 'GET'
        && !str_starts_with($path, '/admin')
        && !str_starts_with($path, '/api/')
        && !str_starts_with($path, '/pos')) {
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
        $apiPath = class_exists(\Rateb\App\Helpers\Request::class)
            ? \Rateb\App\Helpers\Request::resolvePath()
            : (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $wantsJson = str_contains($apiPath, '/api/')
            || str_contains($accept, 'application/json')
            || isset($_SERVER['HTTP_X_CSRF_TOKEN']);
        if ($wantsJson && class_exists(\Rateb\App\Core\Response::class)) {
            $message = class_exists(\Rateb\App\Services\DatabaseErrorService::class)
                ? \Rateb\App\Services\DatabaseErrorService::userMessage($e)
                : 'Server error';
            $status = class_exists(\Rateb\App\Services\DatabaseErrorService::class)
                && \Rateb\App\Services\DatabaseErrorService::isSchemaIssue($e)
                ? 503
                : 500;
            \Rateb\App\Core\Response::json(['ok' => false, 'error' => $message], $status);
            return;
        }
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

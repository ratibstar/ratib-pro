<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-02 — ERP/website front controller.
 * Public paths → Website Kernel (no POS/Offline/ERP ops).
 * /admin, /api, /pos, /login, … → ERP bootstrap (unchanged).
 */

$ratebRootHint = realpath(dirname(__FILE__, 2));
if ($ratebRootHint === false) {
    $ratebRootHint = dirname(__FILE__, 2);
}

// Early static bypass for PHP built-in server (unchanged).
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

$kernelFile = $ratebRootHint . '/app/Website/WebsiteKernel.php';
if (is_file($kernelFile)) {
    require_once $kernelFile;
    if (\Rateb\App\Website\WebsiteKernel::shouldHandle()) {
        \Rateb\App\Website\WebsiteKernel::run($ratebRootHint);
        return;
    }
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Rateb-Erp-Build: ecb6a6f0-device-registry');
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
    require_once dirname(__FILE__, 2) . '/app/Core/Bootstrap.php';

    if (is_file(dirname(__FILE__, 2) . '/app/Core/ServerTiming.php')) {
        require_once dirname(__FILE__, 2) . '/app/Core/ServerTiming.php';
        if (class_exists(\Rateb\App\Core\ServerTiming::class)) {
            \Rateb\App\Core\ServerTiming::arm();
            \Rateb\App\Core\ServerTiming::mark('controller', 'router+controller');
        }
    }

    Rateb\App\Core\Bootstrap::init($ratebRootHint);

    // ESS login registers devices after /api/mobile/config — create table if migrations lag.
    // Lives in public/index.php (PX-managed) because rateb-erp/app uploads were not always live.
    try {
        $earlyPath = class_exists(\Rateb\App\Helpers\Request::class)
            ? \Rateb\App\Helpers\Request::resolvePath()
            : (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if (
            str_contains($earlyPath, '/api/mobile/config')
            || str_contains($earlyPath, '/api/v1/mobile/devices')
        ) {
            if (class_exists(\Rateb\App\Services\MobileDeviceSchemaBootstrap::class)) {
                \Rateb\App\Services\MobileDeviceSchemaBootstrap::ensure();
            } elseif (class_exists(\Rateb\App\Core\Database::class)) {
                \Rateb\App\Core\Database::connection()->exec(
                    'CREATE TABLE IF NOT EXISTS rateb_mobile_devices (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        company_id INT UNSIGNED NOT NULL,
                        user_id INT UNSIGNED NOT NULL,
                        client_app VARCHAR(32) NOT NULL,
                        platform VARCHAR(16) NOT NULL DEFAULT \'other\',
                        device_id VARCHAR(64) NOT NULL,
                        push_token VARCHAR(512) NULL,
                        push_provider VARCHAR(16) NOT NULL DEFAULT \'none\',
                        locale VARCHAR(16) NULL,
                        app_version VARCHAR(64) NULL,
                        last_seen_at DATETIME NULL,
                        status ENUM(\'active\', \'inactive\', \'revoked\') NOT NULL DEFAULT \'active\',
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_mobile_device_identity (company_id, client_app, device_id),
                        KEY idx_mobile_device_user (company_id, user_id, status),
                        KEY idx_mobile_device_seen (company_id, last_seen_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }
        }
    } catch (Throwable $registryBootEx) {
        error_log('Mobile device registry boot: ' . $registryBootEx->getMessage());
    }

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

    require_once RATEB_ROOT . '/app/helpers/Request.php';

    $path = \Rateb\App\Helpers\Request::resolvePath();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    $router = new Rateb\App\Core\Router();

    // Phase AA.3 — dashboard-minimal modules; unknown / miss → loadAll (full set).
    Rateb\App\Core\RouteModuleLoader::loadForPath($router, $path);
    if (
        Rateb\App\Core\RouteModuleLoader::lastMode() === 'selective'
        && !$router->hasMatch($method, $path)
    ) {
        $router = new Rateb\App\Core\Router();
        Rateb\App\Core\RouteModuleLoader::loadAll($router);
        Rateb\App\Core\RouteModuleLoader::markFallbackAll();
    }

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
            $isSchema = class_exists(\Rateb\App\Services\DatabaseErrorService::class)
                && \Rateb\App\Services\DatabaseErrorService::isSchemaIssue($e);
            // Do not hard-fail ESS login when rateb_mobile_devices is missing.
            if (
                $isSchema
                && (
                    str_contains($apiPath, '/api/v1/mobile/devices/register')
                    || str_contains($apiPath, '/api/v1/mobile/devices/heartbeat')
                    || str_contains($apiPath, '/api/v1/mobile/devices/push-token')
                )
            ) {
                \Rateb\App\Core\Response::json([
                    'success' => true,
                    'data' => [
                        'device' => [
                            'id' => 0,
                            'device_id' => '',
                            'client_app' => 'ess',
                            'platform' => 'other',
                            'status' => 'active',
                            'app_version' => null,
                            'last_seen_at' => null,
                            'push_provider' => 'none',
                            'locale' => null,
                        ],
                        'degraded' => true,
                    ],
                ], 200);
                return;
            }
            $status = $isSchema ? 503 : 500;
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

<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Bootstrap;
use Rateb\App\Core\RouteModuleLoader;
use Rateb\App\Core\Router;
use Rateb\App\Helpers\Request;
use Throwable;

/**
 * Phase WEBSITE-02 — Public website entry (no ERP modules / POS / Offline).
 */
final class WebsiteKernel
{
    public static function isPublicPath(string $path): bool
    {
        $path = '/' . trim(str_replace('\\', '/', $path), '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        if (
            str_starts_with($path, '/admin')
            || str_starts_with($path, '/api')
            || str_starts_with($path, '/pos')
            || str_starts_with($path, '/login')
            || str_starts_with($path, '/logout')
            || str_starts_with($path, '/password')
            || str_starts_with($path, '/barcode')
            || str_starts_with($path, '/scan')
            || str_starts_with($path, '/documents')
            || str_starts_with($path, '/company')
            || str_starts_with($path, '/accounting')
            || $path === '/rateb-erp'
        ) {
            return false;
        }

        if ($path === '/' || $path === '/favicon.ico') {
            return true;
        }

        return str_starts_with($path, '/site')
            || str_starts_with($path, '/locale');
    }

    /**
     * Early path guess before Bootstrap (query route + URI).
     */
    public static function peekRequestPath(): string
    {
        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            $route = '/' . trim($_GET['route'], '/');

            return $route === '/' ? '/' : rtrim($route, '/');
        }

        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        if (preg_match('#/rateb-erp/public(/.*)?$#', $uri, $m)) {
            $uri = $m[1] ?? '/';
        }
        $uri = '/' . trim(str_replace('\\', '/', $uri), '/');

        return $uri === '/' ? '/' : (rtrim($uri, '/') ?: '/');
    }

    public static function shouldHandle(): bool
    {
        if (!empty($_GET['rateb_website_kernel'])) {
            return true;
        }
        if (defined('RATEB_WEBSITE_KERNEL') && RATEB_WEBSITE_KERNEL) {
            return true;
        }

        return self::isPublicPath(self::peekRequestPath());
    }

    public static function markActive(): void
    {
        if (!defined('RATEB_WEBSITE_KERNEL')) {
            define('RATEB_WEBSITE_KERNEL', true);
        }
        // Domain-root public URLs (same model as rateb.sa marketing).
        putenv('RATEB_ERP_PUBLIC_PREFIX=');
        $_ENV['RATEB_ERP_PUBLIC_PREFIX'] = '';
        $_SERVER['RATEB_ERP_PUBLIC_PREFIX'] = '';
    }

    public static function run(string $ratebRoot): void
    {
        self::markActive();

        $tenantFile = $ratebRoot . '/app/Website/TenantContext.php';
        if (is_file($tenantFile)) {
            require_once $tenantFile;
        }
        TenantContext::resolveFromRequest();

        if (!headers_sent()) {
            // Public HTML may be cached at the edge later; avoid ERP no-store default.
            header('Cache-Control: public, max-age=0, must-revalidate');
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
            echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>Error</title></head>'
                . '<body style="font-family:sans-serif;padding:2rem;text-align:center"><p>Website temporarily unavailable.</p></body></html>';
        });

        try {
            require_once $ratebRoot . '/app/Core/Bootstrap.php';
            Bootstrap::initWebsite($ratebRoot);

            if (class_exists(\Rateb\App\Core\Auth::class)) {
                \Rateb\App\Core\Auth::bootstrapFromSession();
            }

            $path = Request::resolvePath();
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

            // Canonical domain root → marketing home (keeps /site routes too).
            if ($path === '/' && $method === 'GET') {
                $_GET['route'] = 'site';
                $path = '/site';
            }

            $router = new Router();
            RouteModuleLoader::loadForPath($router, $path);

            // Never fall back to full ERP route table on the public website.
            if (!$router->hasMatch($method, $path)) {
                http_response_code(404);
                header('Content-Type: text/plain; charset=UTF-8');
                echo 'Not found';
                return;
            }

            if ($method === 'GET') {
                try {
                    if (class_exists(\Rateb\App\Services\CmsService::class)) {
                        (new \Rateb\App\Services\CmsService())->applyRedirectIfAny($path);
                    }
                } catch (Throwable $redirectEx) {
                    error_log('Website CMS redirect: ' . $redirectEx->getMessage());
                }
            }

            $router->dispatch($method, $path);
        } catch (Throwable $e) {
            error_log('RATEB Website Kernel error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
            }
            if (class_exists(\Rateb\App\Services\DatabaseErrorService::class)) {
                \Rateb\App\Services\DatabaseErrorService::renderHttpError($e);
                return;
            }
            echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>Error</title></head>'
                . '<body style="font-family:sans-serif;padding:2rem;text-align:center"><p>Website temporarily unavailable.</p></body></html>';
        }
    }
}

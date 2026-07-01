<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class Request
{
    public static function resolvePath(): string
    {
        if (defined('RATEB_CP_ROUTE')) {
            $cpRoute = trim((string) constant('RATEB_CP_ROUTE'), '/');
            if ($cpRoute !== '') {
                return self::normalize('/' . $cpRoute);
            }
        }

        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            if (defined('RATEB_CP_ENTRY') || str_contains($script, 'rateb-erp-app.php')) {
                return self::normalize('/' . ltrim($_GET['route'], '/'));
            }
        }

        $uriPath = self::extractUriPath();
        if ($uriPath !== '/' && $uriPath !== '') {
            return self::normalize($uriPath);
        }

        // Control Panel / legacy front controller: ?route=admin/ops/...
        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            return self::normalize('/' . ltrim($_GET['route'], '/'));
        }

        if (!empty($_SERVER['PATH_INFO'])) {
            return self::normalize((string) $_SERVER['PATH_INFO']);
        }

        return '/';
    }

    private static function extractUriPath(): string
    {
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptBase = rtrim(str_replace('/index.php', '', $scriptName), '/');

        if ($scriptBase !== '' && strpos($uri, $scriptBase) === 0) {
            $uri = substr($uri, strlen($scriptBase)) ?: '/';
        }

        if (defined('RATEB_BASE_URL')) {
            $erpBase = (string) RATEB_BASE_URL;
            if ($erpBase !== '' && strpos($uri, $erpBase) === 0) {
                $uri = substr($uri, strlen($erpBase)) ?: '/';
            }
        }

        if (preg_match('#/rateb-erp/public(/.*)?$#', $uri, $m)) {
            $uri = $m[1] ?? '/';
        }

        // Folder alias without /public (bookmarks, old links)
        if (preg_match('#^/rateb-erp/?$#', $uri)) {
            $uri = '/';
        }

        if ($uri === '/index.php' || substr($uri, -10) === '/index.php') {
            return '/';
        }

        return $uri;
    }

    private static function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            return '/';
        }
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}

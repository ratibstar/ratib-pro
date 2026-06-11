<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class Request
{
    public static function resolvePath(): string
    {
        // Set by root .htaccess: rateb-erp/public/index.php?route=admin/settings
        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            return self::normalize('/' . ltrim($_GET['route'], '/'));
        }

        if (!empty($_SERVER['PATH_INFO'])) {
            return self::normalize((string) $_SERVER['PATH_INFO']);
        }

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

        if ($uri === '/index.php' || substr($uri, -10) === '/index.php') {
            return '/';
        }

        return self::normalize($uri);
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

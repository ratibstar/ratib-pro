<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class Request
{
    public static function resolvePath(): string
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            $path = (string) $_SERVER['PATH_INFO'];
            return self::normalize($path);
        }

        if (!empty($_GET['route']) && is_string($_GET['route'])) {
            return self::normalize('/' . ltrim($_GET['route'], '/'));
        }

        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptBase = rtrim(str_replace('/index.php', '', $scriptName), '/');

        if ($scriptBase !== '' && strpos($uri, $scriptBase) === 0) {
            $uri = substr($uri, strlen($scriptBase)) ?: '/';
        } elseif (defined('RATEB_BASE_URL')) {
            $erpBase = (string) RATEB_BASE_URL;
            if ($erpBase !== '' && strpos($uri, $erpBase) === 0) {
                $uri = substr($uri, strlen($erpBase)) ?: '/';
            }
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

<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Support;

final class Request
{
    public static function resolvePath(): string
    {
        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            $route = '/' . ltrim($_GET['route'], '/');

            return self::normalize($route);
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        if (defined('RATEB_PLATFORM_CATALOG_BASE_URL')) {
            $baseUrl = rtrim((string) RATEB_PLATFORM_CATALOG_BASE_URL, '/');
            if ($baseUrl !== '' && str_starts_with($path, $baseUrl)) {
                $path = substr($path, strlen($baseUrl)) ?: '/';
            }
        }

        return self::normalize($path);
    }

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isJson(): bool
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        return str_contains($contentType, 'application/json');
    }

    public static function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public static function jsonBody(): array
    {
        if (!self::isJson()) {
            return [];
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
     */
    public static function uploadedFile(string $field = 'file'): ?array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return null;
        }

        $file = $_FILES[$field];
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        return [
            'name' => (string) ($file['name'] ?? ''),
            'type' => (string) ($file['type'] ?? 'application/octet-stream'),
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'error' => (int) $file['error'],
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    private static function normalize(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        return $path;
    }
}

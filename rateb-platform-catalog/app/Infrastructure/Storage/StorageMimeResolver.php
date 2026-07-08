<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class StorageMimeResolver
{
    /** @var array<string, string> */
    private const EXTENSION_MAP = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'txt' => 'text/plain',
        'json' => 'application/json',
        'zip' => 'application/zip',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public static function resolve(string $storageKey, ?string $mimeHint = null): string
    {
        if ($mimeHint !== null && trim($mimeHint) !== '') {
            return trim($mimeHint);
        }

        $extension = strtolower(pathinfo($storageKey, PATHINFO_EXTENSION));
        if ($extension !== '' && isset(self::EXTENSION_MAP[$extension])) {
            return self::EXTENSION_MAP[$extension];
        }

        $root = defined('RATEB_PLATFORM_CATALOG_STORAGE_PATH')
            ? (string) RATEB_PLATFORM_CATALOG_STORAGE_PATH
            : (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/storage' : '');

        if ($root !== '') {
            $normalized = ltrim(str_replace('\\', '/', $storageKey), '/');
            if ($normalized !== '' && !str_contains($normalized, '..')) {
                $absolute = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
                if (is_file($absolute) && function_exists('mime_content_type')) {
                    $detected = mime_content_type($absolute);
                    if (is_string($detected) && $detected !== '') {
                        return $detected;
                    }
                }
            }
        }

        return 'application/octet-stream';
    }

    public static function sniffBuffer(string $buffer): ?string
    {
        if ($buffer === '') {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $buffer);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream') {
                    return $detected;
                }
            }
        }

        return null;
    }

    public static function sanitizeForHeader(string $mimeType): string
    {
        $parts = preg_split('/\R/', $mimeType, 2);
        $mimeType = trim((string) ($parts[0] ?? ''));

        return $mimeType !== '' ? $mimeType : 'application/octet-stream';
    }
}

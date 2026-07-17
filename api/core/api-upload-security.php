<?php
declare(strict_types=1);

require_once __DIR__ . '/api-permission-helper.php';
require_once __DIR__ . '/api-mutation-security.php';

if (!function_exists('requireApiUploadSecurity')) {
    function requireApiUploadSecurity(string $module, string $action): void
    {
        enforceApiPermission($module, $action);
        requireApiMutationSecurity();
    }
}

if (!function_exists('rateb_safe_upload_extension')) {
    /**
     * @param list<string> $allowedExtensions
     */
    function rateb_safe_upload_extension(string $tmpPath, string $originalName, array $allowedExtensions): string
    {
        $allowed = array_values(array_unique(array_map(
            static fn (string $ext): string => strtolower(ltrim($ext, '.')),
            $allowedExtensions
        )));
        $requested = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $detectedMime = function_exists('mime_content_type') ? (string) @mime_content_type($tmpPath) : '';
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        $fromMime = $mimeMap[$detectedMime] ?? '';
        if ($fromMime !== '' && in_array($fromMime, $allowed, true)) {
            return $fromMime;
        }
        if ($requested !== '' && in_array($requested, $allowed, true) && !str_contains($detectedMime, 'php')) {
            return $requested;
        }
        throw new InvalidArgumentException('Unsupported or unsafe upload type.');
    }
}

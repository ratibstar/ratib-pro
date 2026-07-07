<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class MediaUploadHelper
{
    /**
     * @param array<string, mixed> $payload
     * @return array{content: string, mime_type: string, size: int, extension: string}
     */
    public static function resolveBinary(array $payload, ?array $uploadedFile = null): array
    {
        if ($uploadedFile !== null && is_readable($uploadedFile['tmp_name'])) {
            $content = file_get_contents($uploadedFile['tmp_name']);
            if ($content === false) {
                throw new \InvalidArgumentException('Unable to read uploaded file');
            }

            return [
                'content' => $content,
                'mime_type' => (string) ($uploadedFile['type'] ?: 'application/octet-stream'),
                'size' => (int) $uploadedFile['size'],
                'extension' => self::extensionFromFilename((string) $uploadedFile['name']),
            ];
        }

        if (isset($payload['content_base64']) && is_string($payload['content_base64'])) {
            $decoded = base64_decode($payload['content_base64'], true);
            if ($decoded === false) {
                throw new \InvalidArgumentException('Invalid base64 content');
            }

            return [
                'content' => $decoded,
                'mime_type' => (string) ($payload['mime_type'] ?? 'application/octet-stream'),
                'size' => strlen($decoded),
                'extension' => (string) ($payload['extension'] ?? 'bin'),
            ];
        }

        throw new \InvalidArgumentException('File upload or content_base64 is required');
    }

    public static function sha256(string $content): string
    {
        return hash('sha256', $content);
    }

    public static function imageDimensions(string $content): array
    {
        $info = @getimagesizefromstring($content);
        if (!is_array($info)) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    private static function extensionFromFilename(string $filename): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        return $ext !== '' ? strtolower($ext) : 'bin';
    }
}

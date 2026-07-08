<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\FileService;
use Rateb\PlatformCatalog\Application\Services\MediaService;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageMimeResolver;

final class MediaServeController
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly FileService $fileService
    ) {
    }

    /** @param array<string, string> $params */
    public function serveImage(array $params): void
    {
        try {
            $resolved = $this->mediaService->resolveImageStream($params['uuid'], $params['variant']);
            $this->stream($resolved['stream'], $resolved['mime_type'], 'public, max-age=31536000, immutable');
        } catch (\RuntimeException $e) {
            http_response_code((int) ($e->getCode() >= 400 ? $e->getCode() : 404));
            echo $e->getMessage();
        }
    }

    /** @param array<string, string> $params */
    public function serveFile(array $params): void
    {
        try {
            $resolved = $this->fileService->resolveFileStream($params['uuid']);
            $this->stream($resolved['stream'], $resolved['mime_type'], 'private, max-age=3600');
        } catch (\RuntimeException $e) {
            http_response_code((int) ($e->getCode() >= 400 ? $e->getCode() : 404));
            echo $e->getMessage();
        }
    }

    /** @param resource $stream */
    private function stream($stream, string $mimeType, string $cacheControl): void
    {
        try {
            if (!headers_sent()) {
                header('Content-Type: ' . StorageMimeResolver::sanitizeForHeader($mimeType));
                header('Cache-Control: ' . $cacheControl);
            }

            fpassthru($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        exit;
    }
}

<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Infrastructure\Storage\SignedUrlVerifier;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;

final class SignedStorageController
{
    public function __construct(
        private readonly StorageAdapterInterface $storage,
        private readonly SignedUrlVerifier $signedUrlVerifier
    ) {
    }

    public function serve(): void
    {
        $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
        $expires = isset($_GET['expires']) ? (int) $_GET['expires'] : 0;
        $signature = isset($_GET['sig']) ? (string) $_GET['sig'] : '';

        if ($key === '' || $expires <= 0 || $signature === '') {
            http_response_code(400);
            echo 'Invalid signed URL';
            exit;
        }

        if (!$this->signedUrlVerifier->verify($key, $expires, $signature)) {
            http_response_code(403);
            echo 'Signed URL is invalid or expired';
            exit;
        }

        try {
            $stream = $this->storage->get($key);
            if (!headers_sent()) {
                header('Content-Type: application/octet-stream');
                header('Cache-Control: private, max-age=3600');
            }
            fpassthru($stream);
            fclose($stream);
        } catch (\RuntimeException $e) {
            http_response_code((int) ($e->getCode() >= 400 ? $e->getCode() : 404));
            echo $e->getMessage();
        }
        exit;
    }
}

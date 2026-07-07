<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\FileService;
use Rateb\PlatformCatalog\Application\Services\MediaService;
use Rateb\PlatformCatalog\Application\Services\VideoService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class ProductMediaController
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly FileService $fileService,
        private readonly VideoService $videoService
    ) {
    }

    /** @param array<string, string> $params */
    public function listImages(array $params): void
    {
        try {
            $result = $this->mediaService->listImages($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function storeImage(array $params): void
    {
        try {
            $payload = $this->mergeUploadPayload();
            $result = $this->mediaService->uploadImage($params['uuid'], $payload, Request::uploadedFile());
            ApiEnvelope::success($result['item'], $result['meta'], [], 201);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function destroyImage(array $params): void
    {
        try {
            $deleted = $this->mediaService->deleteImage($params['uuid'], $params['imageUuid']);
            if (!$deleted) {
                ApiEnvelope::error([['message' => 'Image not found']], 404);

                return;
            }
            ApiEnvelope::success(['uuid' => $params['imageUuid'], 'deleted' => true]);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function listFiles(array $params): void
    {
        try {
            $result = $this->fileService->list($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function storeFile(array $params): void
    {
        try {
            $payload = $this->mergeUploadPayload();
            $result = $this->fileService->upload($params['uuid'], $payload, Request::uploadedFile());
            ApiEnvelope::success($result['item'], $result['meta'], [], 201);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function destroyFile(array $params): void
    {
        try {
            $deleted = $this->fileService->delete($params['uuid'], $params['fileUuid']);
            if (!$deleted) {
                ApiEnvelope::error([['message' => 'File not found']], 404);

                return;
            }
            ApiEnvelope::success(['uuid' => $params['fileUuid'], 'deleted' => true]);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function listVideos(array $params): void
    {
        try {
            $result = $this->videoService->list($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function storeVideo(array $params): void
    {
        try {
            $result = $this->videoService->create($params['uuid'], Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 201);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeUploadPayload(): array
    {
        $payload = Request::jsonBody();
        if ($payload === [] && isset($_POST['payload']) && is_string($_POST['payload'])) {
            $decoded = json_decode($_POST['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        foreach ($_POST as $key => $value) {
            if ($key === 'payload' || is_array($value)) {
                continue;
            }
            if (!isset($payload[$key])) {
                $payload[$key] = $value;
            }
        }

        if (isset($payload['translations']) && is_string($payload['translations'])) {
            $decoded = json_decode($payload['translations'], true);
            if (is_array($decoded)) {
                $payload['translations'] = $decoded;
            }
        }

        return $payload;
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
        ApiEnvelope::error([['message' => $e->getMessage()]], $status);
    }
}

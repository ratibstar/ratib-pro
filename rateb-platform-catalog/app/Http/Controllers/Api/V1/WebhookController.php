<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\WebhookService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class WebhookController
{
    public function __construct(
        private readonly WebhookService $webhookService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            $result = $this->webhookService->list($limit, $offset);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $result = $this->webhookService->getByUuid($params['uuid']);
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Webhook subscription not found']], 404);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->webhookService->create(Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 201);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function update(array $params): void
    {
        try {
            $result = $this->webhookService->update($params['uuid'], Request::jsonBody());
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Webhook subscription not found']], 404);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function destroy(array $params): void
    {
        try {
            $deleted = $this->webhookService->delete($params['uuid']);
            if (!$deleted) {
                ApiEnvelope::error([['message' => 'Webhook subscription not found']], 404);

                return;
            }
            ApiEnvelope::success(['uuid' => $params['uuid'], 'deleted' => true]);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
        ApiEnvelope::error([['message' => $e->getMessage()]], $status);
    }
}

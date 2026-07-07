<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\ProductSeoService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class ProductSeoController
{
    public function __construct(
        private readonly ProductSeoService $seoService
    ) {
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $result = $this->seoService->get($params['uuid']);
            if ($result['item'] === null) {
                ApiEnvelope::success(null, $result['meta']);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function update(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $result = $this->seoService->upsert($params['uuid'], $payload);
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) $e->getCode();
        if ($status < 400 || $status > 599) {
            $status = 400;
        }
        ApiEnvelope::error([['message' => $e->getMessage()]], $status);
    }
}

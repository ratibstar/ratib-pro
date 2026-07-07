<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\CompletenessService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class AdminCompletenessController
{
    public function __construct(
        private readonly CompletenessService $completenessService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $items = $this->completenessService->listRules();
            ApiEnvelope::success($items);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function update(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $item = $this->completenessService->updateRule($params['code'], $payload);
            ApiEnvelope::success($item);
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

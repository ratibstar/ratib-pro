<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\CompletenessService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;

final class CompletenessController
{
    public function __construct(
        private readonly CompletenessService $completenessService
    ) {
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $result = $this->completenessService->getScores($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 599) {
                $status = 400;
            }
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }
}

<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\SupplierService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;

final class SupplierController
{
    public function __construct(
        private readonly SupplierService $supplierService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 100;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            $result = $this->supplierService->list($limit, $offset);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $result = $this->supplierService->getByUuid($params['uuid']);
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Supplier not found']], 404, $result['meta']);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }
}

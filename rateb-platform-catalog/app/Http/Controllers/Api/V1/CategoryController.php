<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\CategoryService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;

final class CategoryController
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {
    }

  /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $result = $this->categoryService->getTree();
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
            $result = $this->categoryService->getByUuid($params['uuid']);
            if ($result['item'] === null) {
                ApiEnvelope::error([['message' => 'Category not found']], 404, $result['meta']);

                return;
            }
            ApiEnvelope::success($result['item'], $result['meta']);
        } catch (\RuntimeException $e) {
            $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
            ApiEnvelope::error([['message' => $e->getMessage()]], $status);
        }
    }
}

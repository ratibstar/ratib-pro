<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\ProductAttributeService;
use Rateb\PlatformCatalog\Application\Services\ProductBarcodeService;
use Rateb\PlatformCatalog\Application\Services\ProductBundleService;
use Rateb\PlatformCatalog\Application\Services\ProductRelationService;
use Rateb\PlatformCatalog\Application\Services\ProductVariantService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class ProductRelationshipController
{
    public function __construct(
        private readonly ProductVariantService $variantService,
        private readonly ProductBarcodeService $barcodeService,
        private readonly ProductAttributeService $attributeService,
        private readonly ProductBundleService $bundleService,
        private readonly ProductRelationService $relationService
    ) {
    }

    /** @param array<string, string> $params */
    public function listVariants(array $params): void
    {
        try {
            $result = $this->variantService->list($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function storeVariant(array $params): void
    {
        try {
            $result = $this->variantService->create($params['uuid'], Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 201);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function listBarcodes(array $params): void
    {
        try {
            $result = $this->barcodeService->list($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function storeBarcode(array $params): void
    {
        try {
            $result = $this->barcodeService->add($params['uuid'], Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 201);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function destroyBarcode(array $params): void
    {
        try {
            $deleted = $this->barcodeService->delete($params['uuid'], $params['barcodeUuid']);
            if (!$deleted) {
                ApiEnvelope::error([['message' => 'Barcode not found']], 404);

                return;
            }
            ApiEnvelope::success(['uuid' => $params['barcodeUuid'], 'deleted' => true]);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function listAttributes(array $params): void
    {
        try {
            $result = $this->attributeService->list($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function replaceAttributes(array $params): void
    {
        try {
            $result = $this->attributeService->replace($params['uuid'], Request::jsonBody());
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function showBundle(array $params): void
    {
        try {
            $result = $this->bundleService->get($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function replaceBundle(array $params): void
    {
        try {
            $result = $this->bundleService->replace($params['uuid'], Request::jsonBody());
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    /** @param array<string, string> $params */
    public function listRelations(array $params): void
    {
        try {
            $result = $this->relationService->list($params['uuid']);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function storeRelation(array $params): void
    {
        try {
            $result = $this->relationService->add($params['uuid'], Request::jsonBody());
            ApiEnvelope::success($result['item'], $result['meta'], [], 201);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        }
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) ($e->getCode() >= 400 ? $e->getCode() : 403);
        ApiEnvelope::error([['message' => $e->getMessage()]], $status);
    }
}

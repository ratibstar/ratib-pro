<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchResponse;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogProductDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2PaginationDto;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogProductPortInterface;
use Rateb\App\Pos\Services\Bridge\PosBarcodeLookupBridgeService;
use Rateb\App\Pos\Services\Bridge\PosInventoryBridgeService;
use Rateb\App\Pos\Services\PosSellPriceService;
use Rateb\App\Pos\Services\V2\Catalog\PosV2CatalogProductMapper;

/** V1 inventory bridge adapter for catalog product reads. */
final class V1CatalogProductAdapter implements PosV2CatalogProductPortInterface
{
    private const LIST_FETCH_LIMIT = 500;

    /**
     * @param (callable(int, int, ?int, ?int, ?int): ?array)|null $listEnrichProduct
     *                                                                 Test seam for catalog list enrichment (defaults to bridge).
     */
    public function __construct(
        private readonly PosInventoryBridgeService $inventoryBridge = new PosInventoryBridgeService(),
        private readonly PosBarcodeLookupBridgeService $barcodeBridge = new PosBarcodeLookupBridgeService(),
        private readonly PosV2CatalogProductMapper $mapper = new PosV2CatalogProductMapper(),
        private readonly ?\Closure $listEnrichProduct = null,
    ) {
    }

    public function search(PosV2CatalogScope $scope, CatalogSearchRequest $request): CatalogSearchResponse
    {
        $warehouseId = $scope->warehouseId > 0 ? $scope->warehouseId : null;
        $branchId = $scope->branchId > 0 ? $scope->branchId : null;
        $sessionId = $scope->sessionId > 0 ? $scope->sessionId : null;

        if ($request->query !== '') {
            return $this->searchByQuery($scope, $request, $warehouseId, $branchId, $sessionId);
        }

        return $this->listByCategory($scope, $request, $warehouseId, $branchId, $sessionId);
    }

    public function findById(PosV2CatalogScope $scope, int $productId): ?PosV2CatalogProductDto
    {
        if ($productId < 1 || $scope->companyId < 1) {
            return null;
        }

        $row = $this->inventoryBridge->getProduct(
            $productId,
            $scope->companyId,
            $scope->warehouseId > 0 ? $scope->warehouseId : null,
            $scope->branchId > 0 ? $scope->branchId : null,
            $scope->sessionId > 0 ? $scope->sessionId : null,
        );

        if ($row === null) {
            return null;
        }

        return $this->mapper->fromV1Product($row, $scope->currency);
    }

    public function lookupBarcode(PosV2CatalogScope $scope, string $code): ?PosV2CatalogProductDto
    {
        $term = trim($code);
        if ($term === '' || $scope->companyId < 1) {
            return null;
        }

        $row = $this->barcodeBridge->lookupInventoryBarcode(
            $term,
            $scope->companyId,
            $scope->warehouseId > 0 ? $scope->warehouseId : null,
            $scope->branchId > 0 ? $scope->branchId : null,
            $scope->sessionId > 0 ? $scope->sessionId : null,
        );

        if ($row === null) {
            return null;
        }

        return $this->mapper->fromV1Product($row, $scope->currency);
    }

    private function searchByQuery(
        PosV2CatalogScope $scope,
        CatalogSearchRequest $request,
        ?int $warehouseId,
        ?int $branchId,
        ?int $sessionId,
    ): CatalogSearchResponse {
        $fetchLimit = min(500, max($request->perPage, $request->page * $request->perPage));
        $rows = $this->inventoryBridge->searchProducts(
            $request->query,
            $scope->companyId,
            $warehouseId,
            $branchId,
            $sessionId,
            $fetchLimit,
        );

        if ($request->categoryId !== null) {
            $rows = $this->filterRowsByCategory($rows, $request->categoryId);
        }

        $total = count($rows);
        $offset = ($request->page - 1) * $request->perPage;
        $pageRows = array_slice($rows, $offset, $request->perPage);

        return new CatalogSearchResponse(
            products: $this->mapRows($pageRows, $scope->currency),
            pagination: $this->buildPagination($request->page, $request->perPage, $total),
        );
    }

    private function listByCategory(
        PosV2CatalogScope $scope,
        CatalogSearchRequest $request,
        ?int $warehouseId,
        ?int $branchId,
        ?int $sessionId,
    ): CatalogSearchResponse {
        TenantContext::setCompanyId($scope->companyId);

        $filters = [];
        if ($warehouseId !== null && $warehouseId > 0) {
            $filters['warehouse_id'] = $warehouseId;
        }
        if ($request->categoryId !== null) {
            $filters['category_id'] = $request->categoryId;
        }

        $inventory = new Inventory();
        $rows = $inventory->all(self::LIST_FETCH_LIMIT, 0, $filters, '');
        $usedWarehouseFallback = false;

        if ($rows === [] && $warehouseId !== null && $warehouseId > 0) {
            unset($filters['warehouse_id']);
            $rows = $inventory->all(self::LIST_FETCH_LIMIT, 0, $filters, '');
            $usedWarehouseFallback = true;
        }

        $mapped = [];
        foreach ($rows as $row) {
            if (!$this->rowMatchesScope($row, $warehouseId, $branchId, $usedWarehouseFallback)) {
                continue;
            }

            $product = $this->resolveCatalogProductFromListRow(
                $row,
                $scope,
                $usedWarehouseFallback ? null : $warehouseId,
                $branchId,
                $sessionId,
            );
            if ($product !== null) {
                $mapped[] = $product;
            }
        }

        $total = count($mapped);
        $offset = ($request->page - 1) * $request->perPage;
        $products = array_slice($mapped, $offset, $request->perPage);

        return new CatalogSearchResponse(
            products: $products,
            pagination: $this->buildPagination($request->page, $request->perPage, $total),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveCatalogProductFromListRow(
        array $row,
        PosV2CatalogScope $scope,
        ?int $warehouseId,
        ?int $branchId,
        ?int $sessionId,
    ): ?PosV2CatalogProductDto {
        $inventoryId = (int) ($row['id'] ?? 0);
        if ($inventoryId < 1) {
            return null;
        }

        $enriched = $this->enrichListRow(
            $inventoryId,
            $scope->companyId,
            $warehouseId,
            $branchId,
            $sessionId,
        );

        if ($enriched !== null) {
            return $this->mapper->fromV1Product($enriched, $scope->currency);
        }

        $this->logEnrichmentFallback($inventoryId, $scope->companyId);

        return $this->mapFromInventoryRow($row, $scope);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enrichListRow(
        int $inventoryId,
        int $companyId,
        ?int $warehouseId,
        ?int $branchId,
        ?int $sessionId,
    ): ?array {
        if ($this->listEnrichProduct !== null) {
            return ($this->listEnrichProduct)($inventoryId, $companyId, $warehouseId, $branchId, $sessionId);
        }

        return $this->inventoryBridge->getProduct(
            $inventoryId,
            $companyId,
            $warehouseId,
            $branchId,
            $sessionId,
        );
    }

    /**
     * Mirrors V1 register bootstrap catalogSeed mapping when bridge enrichment is unavailable.
     *
     * @param array<string, mixed> $row
     */
    private function mapFromInventoryRow(array $row, PosV2CatalogScope $scope): PosV2CatalogProductDto
    {
        $onHand = (float) ($row['quantity'] ?? 0);
        $unitPrice = $this->resolveUnitPriceFromRow($row, $scope->companyId);

        return $this->mapper->fromV1Product([
            'id' => (int) ($row['id'] ?? 0),
            'item_code' => (string) ($row['item_code'] ?? ''),
            'sku' => (string) ($row['sku'] ?? ''),
            'item_name' => (string) ($row['item_name'] ?? ''),
            'unit' => (string) ($row['unit'] ?? ''),
            'unit_price' => $unitPrice,
            'availability' => [
                'on_hand' => $onHand,
                'available' => max(0, $onHand),
                'can_add' => $onHand > 0,
            ],
        ], $scope->currency);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveUnitPriceFromRow(array $row, int $companyId): float
    {
        $inventoryId = (int) ($row['id'] ?? 0);
        if ($inventoryId < 1 || $companyId < 1) {
            return 0.0;
        }

        try {
            $branchId = (int) ($row['branch_id'] ?? 0);
            $resolved = (new PosSellPriceService())->resolveLine(
                ['product_id' => $inventoryId, 'quantity' => 1],
                $companyId,
                $branchId > 0 ? $branchId : 0,
                null,
            );

            return (float) ($resolved['unit_price'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function logEnrichmentFallback(int $inventoryId, int $companyId): void
    {
        error_log(sprintf(
            '[POS V2 catalog] enrichment unavailable for inventory_id=%d company_id=%d; using inventory row fallback',
            $inventoryId,
            $companyId,
        ));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function filterRowsByCategory(array $rows, int $categoryId): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => (int) ($row['category_id'] ?? 0) === $categoryId,
        ));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<PosV2CatalogProductDto>
     */
    private function mapRows(array $rows, string $currency): array
    {
        $products = [];
        foreach ($rows as $row) {
            $products[] = $this->mapper->fromV1Product($row, $currency);
        }

        return $products;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowMatchesScope(
        array $row,
        ?int $warehouseId,
        ?int $branchId,
        bool $usedWarehouseFallback = false,
    ): bool {
        if ($branchId !== null && $branchId > 0) {
            $rowBranch = (int) ($row['branch_id'] ?? 0);
            if ($rowBranch > 0 && $rowBranch !== $branchId) {
                return false;
            }
        }

        if (!$usedWarehouseFallback && $warehouseId !== null && $warehouseId > 0) {
            $rowWarehouse = (int) ($row['warehouse_id'] ?? 0);
            if ($rowWarehouse > 0 && $rowWarehouse !== $warehouseId) {
                return false;
            }
        }

        return true;
    }

    private function buildPagination(int $page, int $perPage, int $total): PosV2PaginationDto
    {
        $lastPage = $perPage > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;

        return new PosV2PaginationDto(
            page: $page,
            perPage: $perPage,
            total: $total,
            lastPage: $lastPage,
        );
    }
}

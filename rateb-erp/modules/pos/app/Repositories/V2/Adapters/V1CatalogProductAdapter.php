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
use Rateb\App\Pos\Services\V2\Catalog\PosV2CatalogProductMapper;

/** V1 inventory bridge adapter for catalog product reads. */
final class V1CatalogProductAdapter implements PosV2CatalogProductPortInterface
{
    /**
     * @param (callable(int, int, ?int, ?int, ?int): ?array)|null $listEnrichProduct
     *                                                                 Test seam for catalog list enrichment (defaults to bridge).
     * @param (callable(int, int, array<string, mixed>, string): list<array<string, mixed>>)|null $listRows
     *                                                                 Test seam for paginated list rows.
     * @param (callable(array<string, mixed>, string): int)|null $countRows
     *                                                                 Test seam for paginated total count.
     */
    public function __construct(
        private readonly PosInventoryBridgeService $inventoryBridge = new PosInventoryBridgeService(),
        private readonly PosBarcodeLookupBridgeService $barcodeBridge = new PosBarcodeLookupBridgeService(),
        private readonly PosV2CatalogProductMapper $mapper = new PosV2CatalogProductMapper(),
        private readonly ?\Closure $listEnrichProduct = null,
        private readonly ?\Closure $listRows = null,
        private readonly ?\Closure $countRows = null,
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
        $this->bootstrapTenant($scope->companyId);
        unset($sessionId);

        $filters = [];
        if ($warehouseId !== null && $warehouseId > 0) {
            $filters['warehouse_id'] = $warehouseId;
        }
        if ($branchId !== null && $branchId > 0) {
            $filters['branch_id'] = $branchId;
        }
        if ($request->categoryId !== null) {
            $filters['category_id'] = $request->categoryId;
        }
        [$pageRows, $total] = $this->paginateRows(
            $filters,
            $request->query,
            $request->page,
            $request->perPage,
        );

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
        $this->bootstrapTenant($scope->companyId);
        unset($sessionId);

        $filters = [];
        if ($warehouseId !== null && $warehouseId > 0) {
            $filters['warehouse_id'] = $warehouseId;
        }
        if ($branchId !== null && $branchId > 0) {
            $filters['branch_id'] = $branchId;
        }
        if ($request->categoryId !== null) {
            $filters['category_id'] = $request->categoryId;
        }
        [$rows, $total] = $this->paginateRows($filters, '', $request->page, $request->perPage);

        return new CatalogSearchResponse(
            products: $this->mapRows($rows, $scope->currency),
            pagination: $this->buildPagination($request->page, $request->perPage, $total),
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function paginateRows(array $filters, string $search, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = $this->countInventoryRows($filters, $search);
        $rows = $this->listInventoryRows($perPage, $offset, $filters, $search);

        if ($rows === [] && $total === 0 && isset($filters['warehouse_id'])) {
            unset($filters['warehouse_id']);
            $total = $this->countInventoryRows($filters, $search);
            $rows = $this->listInventoryRows($perPage, $offset, $filters, $search);
        }

        return [$rows, $total];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function listInventoryRows(int $limit, int $offset, array $filters, string $search): array
    {
        if ($this->listRows !== null) {
            return ($this->listRows)($limit, $offset, $filters, $search);
        }

        return (new Inventory())->all($limit, $offset, $filters, $search);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function countInventoryRows(array $filters, string $search): int
    {
        if ($this->countRows !== null) {
            return (int) ($this->countRows)($filters, $search);
        }

        return (new Inventory())->count($filters, $search);
    }

    private function bootstrapTenant(int $companyId): void
    {
        if (class_exists(TenantContext::class)) {
            TenantContext::setCompanyId($companyId);
        }
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

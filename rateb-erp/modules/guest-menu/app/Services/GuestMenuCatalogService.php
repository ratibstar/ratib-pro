<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use Rateb\App\Core\BranchContext;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CatalogCategoryAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CatalogProductAdapter;
use Rateb\App\Pos\Repositories\V2\InMemoryCatalogCategoryCache;

/** Read-only catalog for public guest menu — reuses POS V2 adapters. */
final class GuestMenuCatalogService
{
    private V1CatalogCategoryAdapter $categories;
    private V1CatalogProductAdapter $products;

    public function __construct(
        ?V1CatalogCategoryAdapter $categories = null,
        ?V1CatalogProductAdapter $products = null,
    ) {
        $this->categories = $categories ?? new V1CatalogCategoryAdapter(new InMemoryCatalogCategoryCache());
        $this->products = $products ?? new V1CatalogProductAdapter();
    }

    /** @return list<array<string, mixed>> */
    public function listCategories(int $companyId, bool $rtl): array
    {
        if ($companyId < 1) {
            return [];
        }
        $items = [];
        foreach ($this->categories->listActive($companyId, $rtl) as $cat) {
            $items[] = $cat->toArray();
        }

        return $items;
    }

    /**
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    /** @return array{product_count:int, category_count:int} */
    public function statsForCompany(int $companyId, ?int $branchId): array
    {
        $catalog = $this->browse($companyId, $branchId, null, 1, true);

        return [
            'product_count' => (int) ($catalog['pagination']['total'] ?? 0),
            'category_count' => count($catalog['categories'] ?? []),
        ];
    }

    public function browse(int $companyId, ?int $branchId, ?int $categoryId, int $page, bool $rtl): array
    {
        $this->bootstrapPublicCatalog($companyId, $branchId);

        $scope = new PosV2CatalogScope(
            companyId: $companyId,
            warehouseId: 0,
            branchId: $branchId ?? 0,
            sessionId: 0,
            currency: 'SAR',
            rtl: $rtl,
        );

        $request = new CatalogSearchRequest(
            query: '',
            categoryId: $categoryId,
            page: max(1, $page),
            perPage: 24,
        );

        $response = $this->products->search($scope, $request);
        $products = [];
        foreach ($response->products as $product) {
            $products[] = $product->toArray();
        }

        return [
            'categories' => $this->listCategories($companyId, $rtl),
            'products' => $products,
            'pagination' => $response->pagination->toArray(),
        ];
    }

    /** Public menu must ignore admin session branch locks; optional menu branch_id only. */
    private function bootstrapPublicCatalog(int $companyId, ?int $branchId): void
    {
        if ($companyId < 1) {
            return;
        }
        TenantContext::setCompanyId($companyId);
        BranchContext::reset();
        if ($branchId !== null && $branchId > 0) {
            BranchContext::setBootstrapped($companyId, false, [$branchId]);
        } else {
            BranchContext::setBootstrapped($companyId, true, []);
        }
    }
}

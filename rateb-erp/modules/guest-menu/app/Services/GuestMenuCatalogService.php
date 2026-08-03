<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

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
    public function browse(int $companyId, ?int $branchId, ?int $categoryId, int $page, bool $rtl): array
    {
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
}

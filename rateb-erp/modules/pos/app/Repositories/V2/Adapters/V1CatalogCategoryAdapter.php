<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\ProductCategory;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogCategoryDto;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogCategoryCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogCategoryPortInterface;

/** V1 product category adapter (read-only). */
final class V1CatalogCategoryAdapter implements PosV2CatalogCategoryPortInterface
{
    public function __construct(
        private readonly PosV2CatalogCategoryCacheInterface $cache,
    ) {
    }

    public function listActive(int $companyId, bool $rtl): array
    {
        if ($companyId < 1) {
            return [];
        }

        $cacheKey = $companyId . ':' . ($rtl ? 'ar' : 'en');
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        TenantContext::setCompanyId($companyId);
        $rows = (new ProductCategory())->all(300, 0, ['is_active' => 1], '');
        $categories = [];

        foreach ($rows as $row) {
            if (array_key_exists('is_visible', $row) && !(int) ($row['is_visible'] ?? 1)) {
                continue;
            }

            $name = $rtl && trim((string) ($row['name_ar'] ?? '')) !== ''
                ? (string) $row['name_ar']
                : (string) ($row['name'] ?? '');

            $categories[] = new PosV2CatalogCategoryDto(
                id: (int) ($row['id'] ?? 0),
                name: $name,
                sortOrder: (int) ($row['sort_order'] ?? 0),
            );
        }

        usort(
            $categories,
            static function (PosV2CatalogCategoryDto $a, PosV2CatalogCategoryDto $b): int {
                $cmp = $a->sortOrder <=> $b->sortOrder;

                return $cmp !== 0 ? $cmp : strcasecmp($a->name, $b->name);
            },
        );

        $this->cache->set($cacheKey, $categories);

        return $categories;
    }
}

<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogCategoryDto;

/** Request-scoped cache for catalog categories. */
interface PosV2CatalogCategoryCacheInterface
{
    /**
     * @return list<PosV2CatalogCategoryDto>|null
     */
    public function get(string $cacheKey): ?array;

    /**
     * @param list<PosV2CatalogCategoryDto> $categories
     */
    public function set(string $cacheKey, array $categories): void;
}

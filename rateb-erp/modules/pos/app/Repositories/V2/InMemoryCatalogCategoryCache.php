<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogCategoryDto;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogCategoryCacheInterface;

/** Request-scoped in-memory cache for catalog categories. */
final class InMemoryCatalogCategoryCache implements PosV2CatalogCategoryCacheInterface
{
    /** @var array<string, list<PosV2CatalogCategoryDto>> */
    private array $store = [];

    public function get(string $cacheKey): ?array
    {
        return $this->store[$cacheKey] ?? null;
    }

    public function set(string $cacheKey, array $categories): void
    {
        $this->store[$cacheKey] = $categories;
    }
}

<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogCategoryDto;

/** Loads active POS catalog categories (V1 adapter). */
interface PosV2CatalogCategoryPortInterface
{
    /**
     * @return list<PosV2CatalogCategoryDto>
     */
    public function listActive(int $companyId, bool $rtl): array;
}

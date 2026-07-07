<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductListFilter
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $categoryUuid = null,
        public readonly ?string $brandUuid = null,
        public readonly ?string $familyUuid = null,
        public readonly ?string $sku = null
    ) {
    }
}

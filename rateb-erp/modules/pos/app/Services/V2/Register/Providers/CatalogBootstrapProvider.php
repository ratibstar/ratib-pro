<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogBootstrapDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogCategoryPortInterface;

/** Loads catalog bootstrap categories for register bootstrap (T08). */
final class CatalogBootstrapProvider
{
    public function __construct(
        private readonly PosV2CatalogCategoryPortInterface $categories,
    ) {
    }

    public function provide(PosV2RequestContext $context): PosV2CatalogBootstrapDto
    {
        $register = $context->register;

        return new PosV2CatalogBootstrapDto(
            categories: $this->categories->listActive($register->companyId, $register->rtl),
        );
    }
}

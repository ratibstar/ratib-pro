<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Catalog;

use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\Services\V2\Catalog\PosV2CatalogAccessValidator;

/** Wires catalog use cases from the shared composition root (T08). */
final class CatalogUseCaseFactory
{
    public function createSearch(): SearchCatalogUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new SearchCatalogUseCase(
            new PosV2CatalogAccessValidator(),
            $root->repositories->catalogProducts,
        );
    }

    public function createGetProduct(): GetCatalogProductUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new GetCatalogProductUseCase(
            new PosV2CatalogAccessValidator(),
            $root->repositories->catalogProducts,
        );
    }

    public function createBarcodeLookup(): LookupCatalogBarcodeUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new LookupCatalogBarcodeUseCase(
            new PosV2CatalogAccessValidator(),
            $root->repositories->catalogProducts,
        );
    }
}

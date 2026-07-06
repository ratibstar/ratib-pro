<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchResponse;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogProductDto;

/** Loads POS catalog products via V1 inventory bridge. */
interface PosV2CatalogProductPortInterface
{
    public function search(PosV2CatalogScope $scope, CatalogSearchRequest $request): CatalogSearchResponse;

    public function findById(PosV2CatalogScope $scope, int $productId): ?PosV2CatalogProductDto;

    public function lookupBarcode(PosV2CatalogScope $scope, string $code): ?PosV2CatalogProductDto;
}

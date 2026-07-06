<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Catalog;

use Rateb\App\Pos\Domain\V2\Exceptions\PosV2CatalogNotFoundException;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogProductResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogProductPortInterface;
use Rateb\App\Pos\Services\V2\Catalog\PosV2CatalogAccessValidator;

/** Load a single catalog product by inventory id (T08). */
final class GetCatalogProductUseCase
{
    public function __construct(
        private readonly PosV2CatalogAccessValidator $access,
        private readonly PosV2CatalogProductPortInterface $catalog,
    ) {
    }

    public function execute(PosV2RequestContext $context, int $productId): CatalogProductResponse
    {
        $this->access->assertCanView($context);

        $product = $this->catalog->findById(
            PosV2CatalogScope::fromRequestContext($context),
            $productId,
        );

        if ($product === null) {
            throw new PosV2CatalogNotFoundException(
                'PRODUCT_NOT_FOUND',
                'POS catalog product was not found for this scope.',
            );
        }

        return new CatalogProductResponse($product);
    }
}

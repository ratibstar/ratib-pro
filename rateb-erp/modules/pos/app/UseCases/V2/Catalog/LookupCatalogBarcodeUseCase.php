<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Catalog;

use Rateb\App\Pos\Domain\V2\Exceptions\PosV2CatalogNotFoundException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2CatalogValidationException;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogProductResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogProductPortInterface;
use Rateb\App\Pos\Services\V2\Catalog\PosV2CatalogAccessValidator;

/** Resolve a catalog product by barcode / SKU / serial (T08). */
final class LookupCatalogBarcodeUseCase
{
    public function __construct(
        private readonly PosV2CatalogAccessValidator $access,
        private readonly PosV2CatalogProductPortInterface $catalog,
    ) {
    }

    public function execute(PosV2RequestContext $context, string $code): CatalogProductResponse
    {
        $this->access->assertCanView($context);

        $term = trim($code);
        if ($term === '') {
            throw new PosV2CatalogValidationException(
                'BARCODE_REQUIRED',
                'Barcode or scan code is required.',
            );
        }

        $product = $this->catalog->lookupBarcode(
            PosV2CatalogScope::fromRequestContext($context),
            $term,
        );

        if ($product === null) {
            throw new PosV2CatalogNotFoundException(
                'PRODUCT_NOT_FOUND',
                'No catalog product matched the supplied barcode.',
            );
        }

        return new CatalogProductResponse($product);
    }
}

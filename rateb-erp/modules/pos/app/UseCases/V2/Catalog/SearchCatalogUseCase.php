<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Catalog;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogProductPortInterface;
use Rateb\App\Pos\Services\V2\Catalog\PosV2CatalogAccessValidator;

/** Search and list catalog products (T08). */
final class SearchCatalogUseCase
{
    public function __construct(
        private readonly PosV2CatalogAccessValidator $access,
        private readonly PosV2CatalogProductPortInterface $catalog,
    ) {
    }

    public function execute(PosV2RequestContext $context, CatalogSearchRequest $request): CatalogSearchResponse
    {
        $this->access->assertCanView($context);

        return $this->catalog->search(
            PosV2CatalogScope::fromRequestContext($context),
            $request,
        );
    }
}

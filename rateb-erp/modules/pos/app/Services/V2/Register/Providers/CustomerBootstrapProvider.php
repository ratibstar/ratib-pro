<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\UseCases\V2\Customer\GetAttachedCustomerUseCase;

/** Loads currently attached session customer for register bootstrap (T10). */
final class CustomerBootstrapProvider
{
    public function __construct(
        private readonly GetAttachedCustomerUseCase $getAttachedCustomer,
    ) {
    }

    public function provide(PosV2RequestContext $context): ?PosV2CustomerSummaryDto
    {
        return $this->getAttachedCustomer->execute($context);
    }
}

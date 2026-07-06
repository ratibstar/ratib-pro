<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Customer;

use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CustomerPortInterface;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerAccessValidator;

/** Returns currently attached session customer for bootstrap (T10). */
final class GetAttachedCustomerUseCase
{
    public function __construct(
        private readonly PosV2CustomerAccessValidator $access,
        private readonly PosV2CustomerPortInterface $customers,
    ) {
    }

    public function execute(PosV2RequestContext $context): ?PosV2CustomerSummaryDto
    {
        $this->access->assertCanSearch($context);

        return $this->customers->getAttached();
    }
}

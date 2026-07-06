<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Customer;

use Rateb\App\Pos\Domain\V2\Customer\PosV2CustomerScope;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchRequest;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchResponse;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CustomerPortInterface;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerAccessValidator;

/** Search active customers (T10). */
final class SearchCustomerUseCase
{
    public function __construct(
        private readonly PosV2CustomerAccessValidator $access,
        private readonly PosV2CustomerPortInterface $customers,
    ) {
    }

    public function execute(PosV2RequestContext $context, CustomerSearchRequest $request): CustomerSearchResponse
    {
        $this->access->assertCanSearch($context);

        return $this->customers->search(
            PosV2CustomerScope::fromRequestContext($context),
            $request,
        );
    }
}

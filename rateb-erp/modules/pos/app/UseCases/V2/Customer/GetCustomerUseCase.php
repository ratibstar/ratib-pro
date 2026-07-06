<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Customer;

use Rateb\App\Pos\Domain\V2\Customer\Exceptions\PosV2CustomerNotFoundException;
use Rateb\App\Pos\Domain\V2\Customer\PosV2CustomerScope;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CustomerPortInterface;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerAccessValidator;

/** Get customer by ID (T10). */
final class GetCustomerUseCase
{
    public function __construct(
        private readonly PosV2CustomerAccessValidator $access,
        private readonly PosV2CustomerPortInterface $customers,
    ) {
    }

    public function execute(PosV2RequestContext $context, int $customerId): PosV2CustomerSummaryDto
    {
        $this->access->assertCanSearch($context);

        $customer = $this->customers->findById(
            PosV2CustomerScope::fromRequestContext($context),
            $customerId,
        );

        if ($customer === null) {
            throw new PosV2CustomerNotFoundException(
                'CUSTOMER_NOT_FOUND',
                sprintf('Customer %d was not found.', $customerId),
                $customerId,
            );
        }

        return $customer;
    }
}

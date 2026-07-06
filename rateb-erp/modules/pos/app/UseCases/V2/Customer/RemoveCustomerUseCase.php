<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Customer;

use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CustomerPortInterface;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerAccessValidator;
use Rateb\App\Pos\UseCases\V2\Cart\GetCartUseCase;

/** Remove attached customer from current cart session (T10). */
final class RemoveCustomerUseCase
{
    public function __construct(
        private readonly PosV2CustomerAccessValidator $access,
        private readonly PosV2CustomerPortInterface $customers,
        private readonly GetCartUseCase $getCart,
    ) {
    }

    public function execute(PosV2RequestContext $context): CartResponse
    {
        $this->access->assertCanAttach($context);

        $this->customers->detach();

        return $this->getCart->execute($context);
    }
}

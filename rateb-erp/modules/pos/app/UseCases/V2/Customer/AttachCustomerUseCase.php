<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Customer;

use Rateb\App\Pos\Domain\V2\Customer\PosV2CustomerScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Customer\AttachCustomerRequest;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CustomerPortInterface;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerAccessValidator;
use Rateb\App\Pos\UseCases\V2\Cart\GetCartUseCase;

/** Attach or replace customer on current cart session (T10). */
final class AttachCustomerUseCase
{
    public function __construct(
        private readonly PosV2CustomerAccessValidator $access,
        private readonly PosV2CustomerPortInterface $customers,
        private readonly GetCartUseCase $getCart,
    ) {
    }

    public function execute(PosV2RequestContext $context, AttachCustomerRequest $request): CartResponse
    {
        $this->access->assertCanAttach($context);

        $this->customers->attach(
            PosV2CustomerScope::fromRequestContext($context),
            $request->customerId,
        );

        return $this->getCart->execute($context);
    }
}

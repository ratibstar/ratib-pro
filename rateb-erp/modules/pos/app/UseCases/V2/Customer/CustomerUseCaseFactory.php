<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Customer;

use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAccessValidator;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerAccessValidator;
use Rateb\App\Pos\UseCases\V2\Cart\GetCartUseCase;

/** Wires customer use cases from the shared composition root (T10). */
final class CustomerUseCaseFactory
{
    public function createSearch(): SearchCustomerUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new SearchCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $root->repositories->customers,
        );
    }

    public function createGet(): GetCustomerUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new GetCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $root->repositories->customers,
        );
    }

    public function createAttach(): AttachCustomerUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new AttachCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $root->repositories->customers,
            $this->createGetCart(),
        );
    }

    public function createRemove(): RemoveCustomerUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new RemoveCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $root->repositories->customers,
            $this->createGetCart(),
        );
    }

    public function createGetAttached(): GetAttachedCustomerUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new GetAttachedCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $root->repositories->customers,
        );
    }

    private function createGetCart(): GetCartUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new GetCartUseCase(
            new PosV2CartAccessValidator(),
            $root->repositories->cart,
        );
    }
}

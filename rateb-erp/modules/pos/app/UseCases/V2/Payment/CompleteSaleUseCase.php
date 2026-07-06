<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Payment;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleRequest;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleResponse;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CheckoutPortInterface;
use Rateb\App\Pos\Services\V2\Checkout\PosV2CheckoutAccessValidator;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;

final class CompleteSaleUseCase
{
    public function __construct(
        private readonly PosV2CheckoutAccessValidator $access,
        private readonly PaymentValidator $validator,
        private readonly PosV2CheckoutPortInterface $checkout,
    ) {
    }

    public function execute(
        PosV2RequestContext $context,
        CompleteSaleRequest $request,
        string $idempotencyKey,
    ): CompleteSaleResponse {
        $this->access->assertCanComplete($context);
        $this->validator->assertRegisterReady($context);

        $key = trim($idempotencyKey);
        if ($key === '') {
            throw new PosV2PaymentValidationException(
                'IDEMPOTENCY_KEY_REQUIRED',
                'Idempotency-Key header is required.',
            );
        }

        $scope = PosV2CartScope::fromRequestContext($context);

        return $this->checkout->completeSale($scope, $context, $request, $key);
    }
}

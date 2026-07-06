<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleRequest;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleResponse;
use Rateb\App\Pos\DTO\V2\Payment\InitiateChargeRequest;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSheetResponse;

/** Checkout completion port — delegates to V1 PosCheckoutService. */
interface PosV2CheckoutPortInterface
{
    public function initiateCharge(
        PosV2CartScope $scope,
        InitiateChargeRequest $request,
    ): PaymentSheetResponse;

    public function completeSale(
        PosV2CartScope $scope,
        PosV2RequestContext $context,
        CompleteSaleRequest $request,
        string $idempotencyKey,
    ): CompleteSaleResponse;
}

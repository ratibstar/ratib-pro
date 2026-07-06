<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Payment\CashPaymentRequest;
use Rateb\App\Pos\DTO\V2\Payment\PaymentBalanceResponse;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto;
use Rateb\App\Pos\DTO\V2\Payment\RecordPaymentRequest;

/** Session-scoped cash payment port (T12). */
interface PosV2PaymentPortInterface
{
    public function getSummary(PosV2CartScope $scope): PaymentSummaryDto;

    public function addCash(PosV2CartScope $scope, CashPaymentRequest $request): CartResponse;

    public function remove(PosV2CartScope $scope, string $paymentId): CartResponse;

    public function record(PosV2CartScope $scope, RecordPaymentRequest $request): PaymentBalanceResponse;

    /** @return array<int, array<string, mixed>> */
    public function readPayments(): array;
}

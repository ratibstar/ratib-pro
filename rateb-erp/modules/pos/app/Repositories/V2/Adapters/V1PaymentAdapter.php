<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Payment\CashPaymentRequest;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PaymentPortInterface;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\V2\Payment\PaymentAssembler;

/** Session cash payment adapter (no V1 checkout, T12). */
final class V1PaymentAdapter implements PosV2PaymentPortInterface
{
    public function __construct(
        private readonly PosSessionService $session = new PosSessionService(),
        private readonly PosV2CartPortInterface $cartPort,
        private readonly PaymentAssembler $assembler = new PaymentAssembler(),
    ) {
    }

    public function getSummary(PosV2CartScope $scope): PaymentSummaryDto
    {
        $cart = $this->cartPort->load($scope);

        return $this->assembler->buildSummary(
            $this->readPayments(),
            $cart->totals->total->amount,
            $scope->currency,
        );
    }

    public function addCash(PosV2CartScope $scope, CashPaymentRequest $request): CartResponse
    {
        $payments = $this->readPayments();
        $payments[] = [
            'id' => bin2hex(random_bytes(8)),
            'method' => 'cash',
            'amount' => round((float) $request->amount, 2),
            'created_at' => date('c'),
        ];
        $this->session->patch(['payments' => $payments]);

        return $this->cartPort->load($scope);
    }

    public function remove(PosV2CartScope $scope, string $paymentId): CartResponse
    {
        $trimmedId = trim($paymentId);
        if ($trimmedId === '') {
            throw new PosV2PaymentValidationException('PAYMENT_ID_INVALID', 'Payment id is required.');
        }

        $payments = $this->readPayments();
        $found = false;
        $remaining = [];

        foreach ($payments as $payment) {
            if ((string) ($payment['id'] ?? '') === $trimmedId) {
                $found = true;
                continue;
            }
            $remaining[] = $payment;
        }

        if (!$found) {
            throw new PosV2PaymentValidationException(
                'PAYMENT_NOT_FOUND',
                sprintf('Payment %s was not found.', $trimmedId),
            );
        }

        $this->session->patch(['payments' => $remaining]);

        return $this->cartPort->load($scope);
    }

    public function readPayments(): array
    {
        $raw = $this->session->current()['payments'] ?? [];

        return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
    }
}

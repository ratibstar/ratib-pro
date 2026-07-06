<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleRequest;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleResponse;
use Rateb\App\Pos\DTO\V2\Payment\InitiateChargeRequest;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSheetResponse;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CheckoutPortInterface;
use Rateb\App\Pos\Services\PosCheckoutService;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\V2\Checkout\CheckoutPaymentMethodCatalog;
use Rateb\App\Pos\Services\V2\Checkout\CheckoutPaymentNormalizer;
use Rateb\App\Pos\Services\V2\Checkout\CheckoutScopeMapper;
use Rateb\App\Pos\Services\V2\Payment\PaymentCalculator;

/** V1 checkout adapter — single delegation point to PosCheckoutService (T12). */
final class V1CheckoutAdapter implements PosV2CheckoutPortInterface
{
    public function __construct(
        private readonly PosV2CartPortInterface $cartPort,
        private readonly PosSessionService $session = new PosSessionService(),
        private readonly PosCheckoutService $checkout = new PosCheckoutService(),
        private readonly CheckoutScopeMapper $scopeMapper = new CheckoutScopeMapper(),
        private readonly CheckoutPaymentNormalizer $paymentNormalizer = new CheckoutPaymentNormalizer(),
        private readonly CheckoutPaymentMethodCatalog $methodCatalog = new CheckoutPaymentMethodCatalog(),
        private readonly PaymentCalculator $paymentCalculator = new PaymentCalculator(),
    ) {
    }

    public function initiateCharge(
        PosV2CartScope $scope,
        InitiateChargeRequest $request,
    ): PaymentSheetResponse {
        $cart = $this->cartPort->load($scope);
        if ($cart->itemCount < 1) {
            throw new PosV2PaymentValidationException(
                'CART_EMPTY',
                'Cart must contain at least one line before initiating charge.',
            );
        }

        $summary = $this->paymentCalculator->summarize(
            $this->readSessionPayments(),
            $cart->totals->total->amount,
            $scope->currency,
        );

        return new PaymentSheetResponse(
            totals: $cart->totals,
            allowedMethods: $this->methodCatalog->allowedMethods(),
            balanceDue: $summary->remaining,
        );
    }

    public function completeSale(
        PosV2CartScope $scope,
        PosV2RequestContext $context,
        CompleteSaleRequest $request,
        string $idempotencyKey,
    ): CompleteSaleResponse {
        $shiftId = $context->register->shift?->id ?? 0;
        if ($shiftId < 1) {
            throw new PosV2PaymentValidationException(
                'SHIFT_REQUIRED',
                __('pos_no_shift_warning'),
            );
        }

        $cart = $this->cartPort->load($scope);
        if ($cart->itemCount < 1) {
            throw new PosV2PaymentValidationException(
                'CART_EMPTY',
                __('pos_cart_empty'),
            );
        }

        $lines = $this->session->getCartLines();
        if ($lines === []) {
            throw new PosV2PaymentValidationException(
                'CART_EMPTY',
                __('pos_cart_empty'),
            );
        }

        $invoiceDiscount = $this->readInvoiceDiscount();
        $customer = $this->session->getCustomer();
        $sessionData = $this->session->current();
        $couponCode = trim((string) ($sessionData['coupon_code'] ?? ''));
        $pointsRedeem = (float) ($sessionData['points_redeem'] ?? 0);

        $rawPayments = $this->mapRequestPayments($request);
        $normalized = $this->paymentNormalizer->normalizeForV1(
            $rawPayments,
            (float) $cart->totals->total->amount,
        );

        $v1Scope = $this->scopeMapper->map(
            $context,
            $idempotencyKey,
            $couponCode !== '' ? $couponCode : null,
            $pointsRedeem,
            $request->giftReceipt,
        );

        $result = $this->checkout->complete(
            $lines,
            $normalized['payments'],
            $invoiceDiscount,
            $v1Scope,
            $customer,
            $request->taxRate,
            $request->giftReceipt,
        );

        if (empty($result['idempotent'])) {
            $this->clearSessionAfterSale();
        }

        $changeDue = new PosV2MoneyDto(
            number_format($normalized['change_due'], 2, '.', ''),
            $scope->currency,
        );

        return CompleteSaleResponse::fromV1Result($result, $changeDue, $scope->currency);
    }

    /** @return array<string, mixed> */
    private function readInvoiceDiscount(): array
    {
        $raw = $this->session->current()['invoice_discount'] ?? null;

        return is_array($raw) ? $raw : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapRequestPayments(CompleteSaleRequest $request): array
    {
        if ($request->payments !== []) {
            $rows = [];
            foreach ($request->payments as $payment) {
                $rows[] = [
                    'method' => $payment['method'],
                    'amount' => (float) $payment['amount']->amount,
                    'reference' => $payment['reference'] ?? '',
                ];
            }

            return $rows;
        }

        return $this->readSessionPayments();
    }

    /** @return array<int, array<string, mixed>> */
    private function readSessionPayments(): array
    {
        $raw = $this->session->current()['payments'] ?? [];

        return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
    }

    private function clearSessionAfterSale(): void
    {
        $this->session->setCartLines([]);
        $this->session->setCustomer(null);
        $this->session->patch([
            'payments' => [],
            'invoice_discount' => null,
            'coupon_code' => null,
            'points_redeem' => 0,
        ]);
    }
}

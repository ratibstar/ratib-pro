<?php

declare(strict_types=1);

/**
 * POS V2 checkout tests (T12).
 *
 * Run: php modules/pos/tests/run-checkout-tests.php
 */

use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\Domain\V2\Payment\PosV2PaymentMethod;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2ShiftContext;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleRequest;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleResponse;
use Rateb\App\Pos\DTO\V2\Payment\InitiateChargeRequest;
use Rateb\App\Pos\DTO\V2\Payment\RecordPaymentRequest;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CheckoutPortInterface;
use Rateb\App\Pos\Services\V2\Checkout\CheckoutPaymentNormalizer;
use Rateb\App\Pos\Services\V2\Checkout\CheckoutScopeMapper;
use Rateb\App\Pos\Services\V2\Checkout\PosV2CheckoutAccessValidator;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;
use Rateb\App\Pos\UseCases\V2\Payment\CompleteSaleUseCase;
use Rateb\App\Pos\UseCases\V2\Payment\InitiateChargeUseCase;
use Rateb\App\Pos\UseCases\V2\Payment\RecordPaymentUseCase;

final class PosV2CheckoutTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testPaymentNormalizerExact();
        $this->testPaymentNormalizerOverpay();
        $this->testPaymentNormalizerInsufficient();
        $this->testInitiateChargeUseCase();
        $this->testRecordPaymentUseCase();
        $this->testCompleteSaleRejectsRegisterOnly();
        $this->testCompleteSaleRequiresIdempotency();
        $this->testCompleteSaleSuccess();
        $this->testCompleteSaleIdempotentResponse();
        $this->testCompleteSaleEnvelope();
        $this->testCheckoutScopeMapperUsesCashierUserId();

        return $this->results;
    }

    private function testPaymentNormalizerExact(): void
    {
        $result = (new CheckoutPaymentNormalizer())->normalizeForV1(
            [['method' => 'cash', 'amount' => 10.0]],
            10.0,
        );

        $ok = count($result['payments']) === 1
            && $result['payments'][0]['amount'] === 10.0
            && $result['change_due'] === 0.0;

        $this->record('normalizer exact payment', $ok, 'expected single 10.00 payment');
    }

    private function testPaymentNormalizerOverpay(): void
    {
        $result = (new CheckoutPaymentNormalizer())->normalizeForV1(
            [['method' => 'cash', 'amount' => 15.0]],
            10.0,
        );

        $ok = $result['payments'][0]['amount'] === 10.0 && $result['change_due'] === 5.0;
        $this->record('normalizer cash overpay', $ok, 'expected change 5.00');
    }

    private function testPaymentNormalizerInsufficient(): void
    {
        try {
            (new CheckoutPaymentNormalizer())->normalizeForV1(
                [['method' => 'cash', 'amount' => 5.0]],
                10.0,
            );
            $this->record('normalizer insufficient rejected', false, 'expected exception');
        } catch (PosV2PaymentValidationException $exception) {
            $ok = $exception->errorCode === 'PAYMENT_INSUFFICIENT';
            $this->record('normalizer insufficient rejected', $ok, 'expected insufficient payment');
        }
    }

    private function testInitiateChargeUseCase(): void
    {
        $cartPort = new CheckoutTestCartPort();
        $checkoutPort = new CheckoutTestCheckoutPort($cartPort);

        $sheet = (new InitiateChargeUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $checkoutPort,
            $cartPort,
        ))->execute(
            $this->requestContext(['pos.register']),
            new InitiateChargeRequest(9),
        );

        $ok = $sheet->balanceDue->amount === '10.00'
            && count($sheet->allowedMethods) === 5;

        $this->record('initiate charge use case', $ok, 'expected balance due 10.00');
    }

    private function testRecordPaymentUseCase(): void
    {
        $cartPort = new CheckoutTestCartPort();
        $paymentPort = new CheckoutTestPaymentPort($cartPort);

        $balance = (new RecordPaymentUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $paymentPort,
            $cartPort,
        ))->execute(
            $this->requestContext(['pos.payment.record']),
            new RecordPaymentRequest(PosV2PaymentMethod::Card, '10.00', 'AUTH1'),
        );

        $ok = $balance->paid->amount === '10.00' && $balance->balanceDue->amount === '0.00';
        $this->record('record payment use case', $ok, 'expected card payment recorded');
    }

    private function testCompleteSaleRequiresIdempotency(): void
    {
        try {
            (new CompleteSaleUseCase(
                new PosV2CheckoutAccessValidator(),
                new PaymentValidator(),
                new CheckoutTestCheckoutPort(new CheckoutTestCartPort()),
            ))->execute(
                $this->requestContext(['pos.sale.complete']),
                new CompleteSaleRequest(9, [], null, false, 0.15),
                '',
            );
            $this->record('complete requires idempotency key', false, 'expected exception');
        } catch (PosV2PaymentValidationException $exception) {
            $ok = $exception->errorCode === 'IDEMPOTENCY_KEY_REQUIRED';
            $this->record('complete requires idempotency key', $ok, 'expected idempotency error');
        }
    }

    private function testCompleteSaleRejectsRegisterOnly(): void
    {
        try {
            (new CompleteSaleUseCase(
                new PosV2CheckoutAccessValidator(),
                new PaymentValidator(),
                new CheckoutTestCheckoutPort(new CheckoutTestCartPort()),
            ))->execute(
                $this->requestContext(['pos.register']),
                new CompleteSaleRequest(9, [], null, false, 0.15),
                '33333333-3333-3333-3333-333333333333',
            );
            $this->record('complete sale rejects register-only permission', false, 'expected forbidden');
        } catch (PosV2ForbiddenException) {
            $this->record('complete sale rejects register-only permission', true, 'dedicated permission required');
        }
    }

    private function testCompleteSaleSuccess(): void
    {
        $cartPort = new CheckoutTestCartPort();
        $checkoutPort = new CheckoutTestCheckoutPort($cartPort);

        $response = (new CompleteSaleUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $checkoutPort,
        ))->execute(
            $this->requestContext(['pos.sale.complete']),
            new CompleteSaleRequest(9, [], null, false, 0.15),
            '11111111-1111-1111-1111-111111111111',
        );

        $ok = $response->orderId === 42
            && $response->orderNo === 'POS-0042'
            && $checkoutPort->cleared;

        $this->record('complete sale success', $ok, 'expected order and session clear');
    }

    private function testCompleteSaleIdempotentResponse(): void
    {
        $cartPort = new CheckoutTestCartPort();
        $checkoutPort = new CheckoutTestCheckoutPort($cartPort, idempotent: true);

        $response = (new CompleteSaleUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $checkoutPort,
        ))->execute(
            $this->requestContext(['pos.sale.complete']),
            new CompleteSaleRequest(9, [], null, false, 0.15),
            '22222222-2222-2222-2222-222222222222',
        );

        $ok = $response->idempotent === true && $response->orderId === 99;
        $this->record('complete sale idempotent flag', $ok, 'expected idempotent response');
    }

    private function testCompleteSaleEnvelope(): void
    {
        $response = new CompleteSaleResponse(
            orderId: 1,
            orderNo: 'POS-0001',
            totals: new PosV2CartTotalsDto(
                new PosV2MoneyDto('10.00', 'SAR'),
                new PosV2MoneyDto('0.00', 'SAR'),
                new PosV2MoneyDto('1.50', 'SAR'),
                new PosV2MoneyDto('11.50', 'SAR'),
            ),
            receipt: ['order_no' => 'POS-0001'],
            changeDue: new PosV2MoneyDto('0.00', 'SAR'),
        );

        $body = (new PosV2ResponseFactory())->completeSaleSuccess($response)->body;
        $ok = ($body['success'] ?? null) === true
            && ($body['data']['order_id'] ?? null) === 1
            && is_array($body['data']['receipt'] ?? null);

        $this->record('complete sale envelope', $ok, 'expected success/data envelope');
    }

    private function testCheckoutScopeMapperUsesCashierUserId(): void
    {
        $mapped = (new CheckoutScopeMapper())->map(
            $this->requestContext(['pos.sale.complete']),
            'abc-idempotency',
        );

        $ok = (int) ($mapped['user_id'] ?? 0) === 7
            && (string) ($mapped['idempotency_key'] ?? '') === 'abc-idempotency';

        $this->record('checkout scope mapper uses cashier userId', $ok, 'expected user_id from context cashier');
    }

    /**
     * @param list<string> $permissions
     */
    private function requestContext(array $permissions): PosV2RequestContext
    {
        return new PosV2RequestContext(
            httpMethod: 'POST',
            requestPath: '/api/v2/pos/payment/complete',
            channel: 'api',
            register: new PosV2RegisterContext(
                companyId: 1,
                branchId: 2,
                warehouseId: 3,
                sessionId: 9,
                terminal: null,
                shift: new PosV2ShiftContext(5, 'SH-1', 'open'),
                branch: null,
                cashier: new PosV2CashierContext(7, 'Cashier'),
                locale: 'en',
                timezone: 'Asia/Riyadh',
                currency: 'SAR',
                rtl: false,
                featureFlags: new PosV2FeatureFlagsContext(true, 'retail', false, false, false),
                permissions: $permissions,
                registerReady: true,
            ),
        );
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}

final class CheckoutTestCartPort implements \Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface
{
    public function load(PosV2CartScope $scope): CartResponse
    {
        return new CartResponse(
            lines: [],
            totals: new PosV2CartTotalsDto(
                new PosV2MoneyDto('8.70', 'SAR'),
                new PosV2MoneyDto('0.00', 'SAR'),
                new PosV2MoneyDto('1.30', 'SAR'),
                new PosV2MoneyDto('10.00', 'SAR'),
            ),
            itemCount: 1,
        );
    }

    public function addLine(PosV2CartScope $scope, int $productId, string $qty): CartResponse
    {
        return $this->load($scope);
    }

    public function updateLine(PosV2CartScope $scope, string $lineId, string $qty): CartResponse
    {
        return $this->load($scope);
    }

    public function removeLine(PosV2CartScope $scope, string $lineId): CartResponse
    {
        return $this->load($scope);
    }

    public function clear(PosV2CartScope $scope): CartResponse
    {
        return $this->load($scope);
    }
}

final class CheckoutTestPaymentPort implements \Rateb\App\Pos\Repositories\V2\Contracts\PosV2PaymentPortInterface
{
    public function __construct(
        private readonly CheckoutTestCartPort $cart,
    ) {
    }

    /** @var array<int, array<string, mixed>> */
    private array $payments = [];

    public function getSummary(PosV2CartScope $scope): \Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto
    {
        return (new \Rateb\App\Pos\Services\V2\Payment\PaymentCalculator())->summarize(
            $this->payments,
            $this->cart->load($scope)->totals->total->amount,
            $scope->currency,
        );
    }

    public function addCash(PosV2CartScope $scope, \Rateb\App\Pos\DTO\V2\Payment\CashPaymentRequest $request): CartResponse
    {
        return $this->cart->load($scope);
    }

    public function remove(PosV2CartScope $scope, string $paymentId): CartResponse
    {
        return $this->cart->load($scope);
    }

    public function record(PosV2CartScope $scope, RecordPaymentRequest $request): \Rateb\App\Pos\DTO\V2\Payment\PaymentBalanceResponse
    {
        $this->payments[] = [
            'id' => 'p1',
            'method' => $request->method->value,
            'amount' => (float) $request->amount,
        ];
        $summary = $this->getSummary($scope);

        return new \Rateb\App\Pos\DTO\V2\Payment\PaymentBalanceResponse(
            $summary->payments,
            $summary->remaining,
            $summary->changeDue,
            $summary->paid,
        );
    }

    public function readPayments(): array
    {
        return $this->payments;
    }
}

final class CheckoutTestCheckoutPort implements PosV2CheckoutPortInterface
{
    public bool $cleared = false;

    public function __construct(
        private readonly CheckoutTestCartPort $cart,
        private readonly bool $idempotent = false,
    ) {
    }

    public function initiateCharge(PosV2CartScope $scope, InitiateChargeRequest $request): \Rateb\App\Pos\DTO\V2\Payment\PaymentSheetResponse
    {
        $cart = $this->cart->load($scope);

        return new \Rateb\App\Pos\DTO\V2\Payment\PaymentSheetResponse(
            totals: $cart->totals,
            allowedMethods: (new \Rateb\App\Pos\Services\V2\Checkout\CheckoutPaymentMethodCatalog())->allowedMethods(),
            balanceDue: $cart->totals->total,
        );
    }

    public function completeSale(
        PosV2CartScope $scope,
        PosV2RequestContext $context,
        CompleteSaleRequest $request,
        string $idempotencyKey,
    ): CompleteSaleResponse {
        if ($this->idempotent) {
            return new CompleteSaleResponse(
                orderId: 99,
                orderNo: 'POS-0099',
                totals: $this->cart->load($scope)->totals,
                receipt: ['idempotent' => true],
                changeDue: new PosV2MoneyDto('0.00', $scope->currency),
                idempotent: true,
            );
        }

        $this->cleared = true;

        return CompleteSaleResponse::fromV1Result(
            [
                'order_id' => 42,
                'order_no' => 'POS-0042',
                'pricing' => [
                    'subtotal' => 8.7,
                    'invoice_discount' => 0,
                    'tax' => 1.3,
                    'total' => 10.0,
                ],
                'receipt' => ['order_no' => 'POS-0042'],
            ],
            new PosV2MoneyDto('0.00', $scope->currency),
            $scope->currency,
        );
    }
}

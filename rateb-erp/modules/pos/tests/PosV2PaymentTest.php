<?php

declare(strict_types=1);

/**
 * POS V2 payment tests (T12).
 *
 * Run: php modules/pos/tests/run-payment-tests.php
 */

use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Payment\CashPaymentRequest;
use Rateb\App\Pos\DTO\V2\Payment\PaymentBalanceResponse;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto;
use Rateb\App\Pos\DTO\V2\Payment\RecordPaymentRequest;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PaymentPortInterface;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAssembler;
use Rateb\App\Pos\Services\V2\Payment\PaymentCalculator;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;
use Rateb\App\Pos\Services\V2\Checkout\PosV2CheckoutAccessValidator;
use Rateb\App\Pos\UseCases\V2\Payment\CashPaymentUseCase;
use Rateb\App\Pos\UseCases\V2\Payment\GetPaymentsUseCase;
use Rateb\App\Pos\UseCases\V2\Payment\RemovePaymentUseCase;

final class PosV2PaymentTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testPaymentCalculatorMultipleCash();
        $this->testPaymentCalculatorChangeDue();
        $this->testCashPaymentUseCase();
        $this->testRemovePaymentUseCase();
        $this->testGetPaymentsUseCase();
        $this->testPermissionDenied();
        $this->testNegativeAmountRejected();
        $this->testEmptyCartRejected();
        $this->testRegisterNotReadyRejected();
        $this->testBootstrapPaymentSnapshot();
        $this->testPaymentSummaryEnvelope();

        return $this->results;
    }

    private function testPaymentCalculatorMultipleCash(): void
    {
        $summary = (new PaymentCalculator())->summarize(
            [
                ['id' => 'p1', 'method' => 'cash', 'amount' => 4.0],
                ['id' => 'p2', 'method' => 'cash', 'amount' => 6.0],
            ],
            '10.00',
            'SAR',
        );

        $ok = $summary->paid->amount === '10.00'
            && $summary->remaining->amount === '0.00'
            && count($summary->payments) === 2;

        $this->record('multiple cash entries summed', $ok, 'expected paid 10.00');
    }

    private function testPaymentCalculatorChangeDue(): void
    {
        $summary = (new PaymentCalculator())->summarize(
            [['id' => 'p1', 'method' => 'cash', 'amount' => 15.0]],
            '10.00',
            'SAR',
        );

        $ok = $summary->changeDue->amount === '5.00'
            && $summary->remaining->amount === '0.00';

        $this->record('change due on overpay', $ok, 'expected change 5.00');
    }

    private function testCashPaymentUseCase(): void
    {
        $cartPort = new InMemoryPaymentCartPort($this->sampleLines());
        $paymentPort = new InMemoryPaymentPort($cartPort);

        $result = (new CashPaymentUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $paymentPort,
            $cartPort,
        ))->execute($this->requestContext(['pos.payment.record']), new CashPaymentRequest('5.00'));

        $ok = $result->payments?->paid->amount === '5.00'
            && $result->payments->remaining->amount === '6.50';

        $this->record('cash payment use case', $ok, 'expected partial payment');
    }

    private function testRemovePaymentUseCase(): void
    {
        $cartPort = new InMemoryPaymentCartPort($this->sampleLines());
        $paymentPort = new InMemoryPaymentPort($cartPort);
        $paymentPort->addCash(new PosV2CartScope(1, 2, 3, 4, 'SAR'), new CashPaymentRequest('5'));
        $id = $paymentPort->readPayments()[0]['id'];

        $result = (new RemovePaymentUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $paymentPort,
        ))->execute($this->requestContext(['pos.payment.record']), (string) $id);

        $ok = $result->payments?->paid->amount === '0.00';

        $this->record('remove payment use case', $ok, 'expected zero paid');
    }

    private function testGetPaymentsUseCase(): void
    {
        $cartPort = new InMemoryPaymentCartPort($this->sampleLines());
        $paymentPort = new InMemoryPaymentPort($cartPort);
        $paymentPort->addCash(new PosV2CartScope(1, 2, 3, 4, 'SAR'), new CashPaymentRequest('3'));

        $summary = (new GetPaymentsUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $paymentPort,
        ))->execute($this->requestContext(['pos.payment.record']));

        $ok = $summary->paid->amount === '3.00' && $summary->totalDue->amount === '11.50';

        $this->record('get payments use case', $ok, 'expected summary totals');
    }

    private function testPermissionDenied(): void
    {
        $cartPort = new InMemoryPaymentCartPort($this->sampleLines());
        $paymentPort = new InMemoryPaymentPort($cartPort);

        try {
            (new CashPaymentUseCase(
                new PosV2CheckoutAccessValidator(),
                new PaymentValidator(),
                $paymentPort,
                $cartPort,
            ))->execute($this->requestContext(['pos.view']), new CashPaymentRequest('1'));
            $this->record('permission denied for payment', false, 'expected exception');
        } catch (PosV2ForbiddenException) {
            $this->record('permission denied for payment', true, '');
        }
    }

    private function testNegativeAmountRejected(): void
    {
        try {
            CashPaymentRequest::fromPayload(['amount' => '-1']);
            $this->record('negative amount rejected', false, 'expected exception');
        } catch (PosV2PaymentValidationException) {
            $this->record('negative amount rejected', true, '');
        }
    }

    private function testEmptyCartRejected(): void
    {
        $cartPort = new InMemoryPaymentCartPort([]);
        $paymentPort = new InMemoryPaymentPort($cartPort);

        try {
            (new CashPaymentUseCase(
                new PosV2CheckoutAccessValidator(),
                new PaymentValidator(),
                $paymentPort,
                $cartPort,
            ))->execute($this->requestContext(['pos.payment.record']), new CashPaymentRequest('1'));
            $this->record('empty cart payment rejected', false, 'expected exception');
        } catch (PosV2PaymentValidationException $exception) {
            $ok = $exception->errorCode === 'CART_EMPTY';
            $this->record('empty cart payment rejected', $ok, 'expected cart empty');
        }
    }

    private function testRegisterNotReadyRejected(): void
    {
        $cartPort = new InMemoryPaymentCartPort($this->sampleLines());
        $paymentPort = new InMemoryPaymentPort($cartPort);
        $context = $this->requestContext(['pos.payment.record'], registerReady: false);

        try {
            (new CashPaymentUseCase(
                new PosV2CheckoutAccessValidator(),
                new PaymentValidator(),
                $paymentPort,
                $cartPort,
            ))->execute($context, new CashPaymentRequest('1'));
            $this->record('disabled register rejected', false, 'expected exception');
        } catch (PosV2PaymentValidationException $exception) {
            $ok = $exception->errorCode === 'REGISTER_NOT_READY';
            $this->record('disabled register rejected', $ok, 'expected register not ready');
        }
    }

    private function testBootstrapPaymentSnapshot(): void
    {
        $response = PosV2TestPricingSupport::cartAssembler()->assemble(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            $this->sampleLines(),
            null,
            [],
            [['id' => 'p1', 'method' => 'cash', 'amount' => 4.0]],
        );

        $array = $response->toArray();
        $ok = is_array($array['payments'] ?? null)
            && ($array['payments']['paid']['amount'] ?? '') === '4.00'
            && ($array['payments']['remaining']['amount'] ?? '') === '7.50';

        $this->record('bootstrap payment snapshot', $ok, 'expected payments on cart');
    }

    private function testPaymentSummaryEnvelope(): void
    {
        $summary = new PaymentSummaryDto(
            payments: [],
            totalDue: new PosV2MoneyDto('10.00', 'SAR'),
            paid: new PosV2MoneyDto('0.00', 'SAR'),
            remaining: new PosV2MoneyDto('10.00', 'SAR'),
            changeDue: new PosV2MoneyDto('0.00', 'SAR'),
        );

        $body = (new PosV2ResponseFactory())->paymentSummarySuccess($summary)->body;
        $ok = ($body['success'] ?? null) === true && is_array($body['data']['payments'] ?? null);

        $this->record('payment summary envelope', $ok, 'expected success/data envelope');
    }

    /** @return array<int, array<string, mixed>> */
    private function sampleLines(): array
    {
        return [
            [
                'id' => 'line-1',
                'product_id' => 1,
                'item_name' => 'Coffee',
                'quantity' => 2,
                'unit_price' => 5.0,
                'price_source' => 'manual',
                'line_total' => 10.0,
            ],
        ];
    }

    /**
     * @param list<string> $permissions
     */
    private function requestContext(array $permissions, bool $registerReady = true): PosV2RequestContext
    {
        return new PosV2RequestContext(
            httpMethod: 'POST',
            requestPath: '/api/v2/pos/payments/cash',
            channel: 'api',
            register: new PosV2RegisterContext(
                companyId: 1,
                branchId: 2,
                warehouseId: 3,
                sessionId: 9,
                terminal: null,
                shift: null,
                branch: null,
                cashier: new PosV2CashierContext(7, 'Cashier'),
                locale: 'ar',
                timezone: 'Asia/Riyadh',
                currency: 'SAR',
                rtl: true,
                featureFlags: new PosV2FeatureFlagsContext(true, 'retail', false, false, false),
                permissions: $permissions,
                registerReady: $registerReady,
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

final class InMemoryPaymentCartPort implements PosV2CartPortInterface
{
    /** @param array<int, array<string, mixed>> $lines */
    public function __construct(
        private array $lines,
        /** @var array<int, array<string, mixed>> */
        private array $payments = [],
    ) {
    }

    public function setPayments(array $payments): void
    {
        $this->payments = $payments;
    }

    public function load(PosV2CartScope $scope): CartResponse
    {
        return PosV2TestPricingSupport::cartAssembler()->assemble($scope, $this->lines, null, [], $this->payments);
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
        $this->lines = [];
        $this->payments = [];

        return $this->load($scope);
    }
}

final class InMemoryPaymentPort implements PosV2PaymentPortInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $payments = [];

    public function __construct(
        private readonly InMemoryPaymentCartPort $cart,
    ) {
    }

    public function getSummary(PosV2CartScope $scope): PaymentSummaryDto
    {
        $cart = $this->cart->load($scope);

        return $cart->payments ?? new PaymentSummaryDto(
            [],
            new PosV2MoneyDto('0.00', $scope->currency),
            new PosV2MoneyDto('0.00', $scope->currency),
            new PosV2MoneyDto('0.00', $scope->currency),
            new PosV2MoneyDto('0.00', $scope->currency),
        );
    }

    public function addCash(PosV2CartScope $scope, CashPaymentRequest $request): CartResponse
    {
        $this->payments[] = [
            'id' => 'pay-' . count($this->payments) + 1,
            'method' => 'cash',
            'amount' => (float) $request->amount,
        ];
        $this->cart->setPayments($this->payments);

        return $this->cart->load($scope);
    }

    public function remove(PosV2CartScope $scope, string $paymentId): CartResponse
    {
        $this->payments = array_values(array_filter(
            $this->payments,
            static fn (array $row): bool => (string) ($row['id'] ?? '') !== $paymentId,
        ));
        $this->cart->setPayments($this->payments);

        return $this->cart->load($scope);
    }

    public function record(PosV2CartScope $scope, RecordPaymentRequest $request): PaymentBalanceResponse
    {
        $this->payments[] = [
            'id' => 'pay-' . (count($this->payments) + 1),
            'method' => $request->method->value,
            'amount' => (float) $request->amount,
            'reference_no' => $request->reference ?? '',
        ];
        $this->cart->setPayments($this->payments);
        $summary = $this->getSummary($scope);

        return new PaymentBalanceResponse(
            payments: $summary->payments,
            balanceDue: $summary->remaining,
            changeDue: $summary->changeDue,
            paid: $summary->paid,
        );
    }

    public function readPayments(): array
    {
        return $this->payments;
    }
}

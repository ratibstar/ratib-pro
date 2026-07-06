<?php

declare(strict_types=1);

/**
 * POS V2 discount tests (T11).
 *
 * Run: php modules/pos/tests/run-discount-tests.php
 */

use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\Discount\Exceptions\PosV2DiscountValidationException;
use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Discount\DiscountRequest;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2DiscountPortInterface;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAssembler;
use Rateb\App\Pos\Services\V2\Discount\DiscountCalculator;
use Rateb\App\Pos\Services\V2\Discount\DiscountValidator;
use Rateb\App\Pos\Services\V2\Discount\PosV2DiscountAccessValidator;
use Rateb\App\Pos\UseCases\V2\Discount\ApplyCartDiscountUseCase;
use Rateb\App\Pos\UseCases\V2\Discount\ApplyLineDiscountUseCase;
use Rateb\App\Pos\UseCases\V2\Discount\RemoveCartDiscountUseCase;
use Rateb\App\Pos\UseCases\V2\Discount\RemoveLineDiscountUseCase;

final class PosV2DiscountTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testPercentLineDiscountCalculation();
        $this->testFixedLineDiscountCalculation();
        $this->testCartPercentDiscountCalculation();
        $this->testRemoveLineDiscount();
        $this->testRemoveCartDiscount();
        $this->testPermissionDenied();
        $this->testInvalidDiscountAmount();
        $this->testDiscountExceedsSubtotal();
        $this->testEmptyCartDiscountRejected();
        $this->testBootstrapDiscountSnapshot();
        $this->testDiscountErrorEnvelope();

        return $this->results;
    }

    private function testPercentLineDiscountCalculation(): void
    {
        $port = new InMemoryDiscountPort();
        $port->seedLines($this->sampleLines());

        $result = (new ApplyLineDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            new DiscountValidator(),
            $port,
        ))->execute(
            $this->requestContext(['pos.discount.apply']),
            'line-1',
            new DiscountRequest(PosV2DiscountType::Percent, '10'),
        );

        $ok = $result->discounts?->lineDiscountTotal->amount === '1.00'
            && $result->totals->discount->amount === '1.00'
            && $result->totals->total->amount === '10.35';

        $this->record('percentage line discount', $ok, 'expected 10% off line gross');
    }

    private function testFixedLineDiscountCalculation(): void
    {
        $port = new InMemoryDiscountPort();
        $port->seedLines($this->sampleLines());

        $result = (new ApplyLineDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            new DiscountValidator(),
            $port,
        ))->execute(
            $this->requestContext(['pos.discount.apply']),
            'line-1',
            new DiscountRequest(PosV2DiscountType::Fixed, '2.50'),
        );

        $ok = $result->discounts?->lineDiscountTotal->amount === '2.50'
            && $result->totals->total->amount === '8.63';

        $this->record('fixed line discount', $ok, 'expected fixed amount off line');
    }

    private function testCartPercentDiscountCalculation(): void
    {
        $port = new InMemoryDiscountPort();
        $port->seedLines($this->sampleLines());

        $result = (new ApplyCartDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            new DiscountValidator(),
            $port,
        ))->execute(
            $this->requestContext(['pos.discount.manage']),
            new DiscountRequest(PosV2DiscountType::Percent, '20'),
        );

        $ok = $result->discounts?->cartDiscountTotal->amount === '2.00'
            && $result->totals->total->amount === '9.20';

        $this->record('percentage cart discount', $ok, 'expected 20% cart discount');
    }

    private function testRemoveLineDiscount(): void
    {
        $port = new InMemoryDiscountPort();
        $port->seedLines($this->sampleLines());
        $port->applyLineDiscount(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            'line-1',
            new DiscountRequest(PosV2DiscountType::Fixed, '2'),
        );

        $result = (new RemoveLineDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            $port,
        ))->execute($this->requestContext(['pos.discount.apply']), 'line-1');

        $ok = $result->discounts?->lineDiscountTotal->amount === '0.00'
            && $result->totals->discount->amount === '0.00';

        $this->record('remove line discount', $ok, 'expected zero line discount');
    }

    private function testRemoveCartDiscount(): void
    {
        $port = new InMemoryDiscountPort();
        $port->seedLines($this->sampleLines());
        $port->applyCartDiscount(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            new DiscountRequest(PosV2DiscountType::Fixed, '3'),
        );

        $result = (new RemoveCartDiscountUseCase(
            new PosV2DiscountAccessValidator(),
            $port,
        ))->execute($this->requestContext(['pos.discount.apply']));

        $ok = $result->discounts?->cartDiscountTotal->amount === '0.00'
            && $result->discounts?->cartDiscount === null;

        $this->record('remove cart discount', $ok, 'expected cleared cart discount');
    }

    private function testPermissionDenied(): void
    {
        $port = new InMemoryDiscountPort();
        $port->seedLines($this->sampleLines());

        try {
            (new ApplyLineDiscountUseCase(
                new PosV2DiscountAccessValidator(),
                new DiscountValidator(),
                $port,
            ))->execute(
                $this->requestContext(['pos.register.access']),
                'line-1',
                new DiscountRequest(PosV2DiscountType::Percent, '5'),
            );
            $this->record('permission denied for discount', false, 'expected exception');
        } catch (PosV2ForbiddenException) {
            $this->record('permission denied for discount', true, '');
        }
    }

    private function testInvalidDiscountAmount(): void
    {
        try {
            DiscountRequest::fromPayload(['type' => 'percent', 'value' => '-1']);
            $this->record('invalid discount amount rejected', false, 'expected exception');
        } catch (PosV2DiscountValidationException) {
            $this->record('invalid discount amount rejected', true, '');
        }
    }

    private function testDiscountExceedsSubtotal(): void
    {
        $port = new InMemoryDiscountPort();
        $port->seedLines($this->sampleLines());

        try {
            (new ApplyLineDiscountUseCase(
                new PosV2DiscountAccessValidator(),
                new DiscountValidator(),
                $port,
            ))->execute(
                $this->requestContext(['pos.discount.apply']),
                'line-1',
                new DiscountRequest(PosV2DiscountType::Fixed, '15'),
            );
            $this->record('discount exceeds subtotal rejected', false, 'expected exception');
        } catch (PosV2DiscountValidationException $exception) {
            $ok = $exception->errorCode === 'DISCOUNT_EXCEEDS_SUBTOTAL';
            $this->record('discount exceeds subtotal rejected', $ok, 'expected exceeds subtotal code');
        }
    }

    private function testEmptyCartDiscountRejected(): void
    {
        $port = new InMemoryDiscountPort();

        try {
            (new ApplyCartDiscountUseCase(
                new PosV2DiscountAccessValidator(),
                new DiscountValidator(),
                $port,
            ))->execute(
                $this->requestContext(['pos.discount.apply']),
                new DiscountRequest(PosV2DiscountType::Percent, '10'),
            );
            $this->record('empty cart discount rejected', false, 'expected exception');
        } catch (PosV2DiscountValidationException $exception) {
            $ok = $exception->errorCode === 'CART_EMPTY';
            $this->record('empty cart discount rejected', $ok, 'expected cart empty code');
        }
    }

    private function testBootstrapDiscountSnapshot(): void
    {
        $lines = $this->sampleLines();
        $lines[0]['discount_percent'] = 10.0;

        $response = PosV2TestPricingSupport::cartAssembler()->assemble(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            $lines,
            null,
            ['type' => 'amount', 'value' => 1.0],
        );

        $array = $response->toArray();
        $ok = is_array($array['discounts'] ?? null)
            && ($array['discounts']['line_discount_total']['amount'] ?? '') === '1.00'
            && ($array['discounts']['cart_discount_total']['amount'] ?? '') === '1.00'
            && ($array['lines'][0]['discount']['type'] ?? '') === 'percent';

        $this->record('bootstrap discount snapshot shape', $ok, 'expected discounts summary on cart');
    }

    private function testDiscountErrorEnvelope(): void
    {
        $response = (new PosV2ResponseFactory())->discountError(
            'DISCOUNT_EXCEEDS_SUBTOTAL',
            'Discount cannot exceed the applicable subtotal.',
            422,
        );

        $body = $response->body;
        $ok = ($body['success'] ?? null) === false
            && ($body['error']['code'] ?? '') === 'DISCOUNT_EXCEEDS_SUBTOTAL';

        $this->record('discount error envelope', $ok, 'expected success=false error envelope');
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
    private function requestContext(array $permissions): PosV2RequestContext
    {
        return new PosV2RequestContext(
            httpMethod: 'POST',
            requestPath: '/api/v2/pos/cart/discounts/line',
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

final class InMemoryDiscountPort implements PosV2DiscountPortInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $lines = [];

    /** @var array<string, mixed> */
    private array $invoiceDiscount = [];

    /** @param array<int, array<string, mixed>> $lines */
    public function seedLines(array $lines): void
    {
        $this->lines = $lines;
    }

    public function applyLineDiscount(PosV2CartScope $scope, string $lineId, DiscountRequest $request): CartResponse
    {
        foreach ($this->lines as &$line) {
            if ((string) ($line['id'] ?? '') !== $lineId) {
                continue;
            }
            unset($line['discount_amount'], $line['discount_percent']);
            if ($request->type === PosV2DiscountType::Percent) {
                $line['discount_percent'] = (float) $request->value;
            } else {
                $line['discount_amount'] = (float) $request->value;
            }
        }
        unset($line);

        return $this->load($scope);
    }

    public function removeLineDiscount(PosV2CartScope $scope, string $lineId): CartResponse
    {
        foreach ($this->lines as &$line) {
            if ((string) ($line['id'] ?? '') !== $lineId) {
                continue;
            }
            unset($line['discount_amount'], $line['discount_percent']);
        }
        unset($line);

        return $this->load($scope);
    }

    public function applyCartDiscount(PosV2CartScope $scope, DiscountRequest $request): CartResponse
    {
        $this->invoiceDiscount = [
            'type' => $request->type === PosV2DiscountType::Percent ? 'percent' : 'amount',
            'value' => (float) $request->value,
        ];

        return $this->load($scope);
    }

    public function removeCartDiscount(PosV2CartScope $scope): CartResponse
    {
        $this->invoiceDiscount = [];

        return $this->load($scope);
    }

    public function readLines(): array
    {
        return $this->lines;
    }

    public function readCartDiscount(): array
    {
        return $this->invoiceDiscount;
    }

    private function load(PosV2CartScope $scope): CartResponse
    {
        return PosV2TestPricingSupport::cartAssembler()->assemble(
            $scope,
            $this->lines,
            null,
            $this->invoiceDiscount,
        );
    }
}

<?php

declare(strict_types=1);

/**
 * POS V2 cart tests (T09).
 *
 * Run: php modules/pos/tests/run-cart-tests.php
 */

use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartInvalidQuantityException;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartProductInactiveException;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartProductNotFoundException;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Quantity;
use Rateb\App\Pos\DTO\V2\Cart\AddLineRequest;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Cart\UpdateLineRequest;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogProductDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAssembler;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartLineMapper;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartTotalsCalculator;
use Rateb\App\Pos\Services\PosPricingService;
use Rateb\App\Pos\UseCases\V2\Cart\AddCartLineUseCase;
use Rateb\App\Pos\UseCases\V2\Cart\ClearCartUseCase;
use Rateb\App\Pos\UseCases\V2\Cart\GetCartUseCase;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogProductPortInterface;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchResponse;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2PaginationDto;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAccessValidator;

final class PosV2CartTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testMoneyValueObjectArithmetic();
        $this->testQuantityValidation();
        $this->testCartLineMapper();
        $this->testCartTotalsCalculator();
        $this->testAddLineRequestValidation();
        $this->testCartScopeFromContext();
        $this->testCartSuccessEnvelope();
        $this->testAddLineProductNotFound();
        $this->testAddLineProductInactive();
        $this->testGetCartUseCase();

        return $this->results;
    }

    private function testMoneyValueObjectArithmetic(): void
    {
        $left = PosV2Money::fromDecimalString('10.50', 'SAR');
        $right = PosV2Money::fromDecimalString('2.25', 'SAR');
        $sum = $left->add($right);
        $product = $left->multiply('3');

        $ok = $sum->amount === '12.75' && $product->amount === '31.50';

        $this->record('money value object arithmetic', $ok, 'expected bcmath-safe totals');
    }

    private function testQuantityValidation(): void
    {
        try {
            new PosV2Quantity('0');
            $this->record('quantity rejects zero', false, 'expected exception');
        } catch (\InvalidArgumentException) {
            $this->record('quantity rejects zero', true, '');
        }

        $qty = new PosV2Quantity('2.5');
        $this->record('quantity accepts decimals', $qty->value === '2.5', 'expected 2.5');
    }

    private function testCartLineMapper(): void
    {
        $mapper = new PosV2CartLineMapper();
        $lines = $mapper->fromV1Lines([
            [
                'id' => 'line-1',
                'product_id' => 7,
                'item_name' => 'Coffee',
                'quantity' => 2,
                'unit_price' => 5.5,
                'line_total' => 11,
            ],
        ], 'SAR');

        $ok = count($lines) === 1
            && $lines[0]->lineId === 'line-1'
            && $lines[0]->qty === '2'
            && $lines[0]->unitPrice->amount === '5.50'
            && $lines[0]->lineTotal->amount === '11.00';

        $this->record('cart line mapper maps V1 lines', $ok, 'expected mapped line dto');
    }

    private function testCartTotalsCalculator(): void
    {
        $v1Lines = [
            [
                'id' => 'a',
                'product_id' => 1,
                'item_name' => 'A',
                'quantity' => 2,
                'unit_price' => 4,
                'line_total' => 8,
            ],
            [
                'id' => 'b',
                'product_id' => 2,
                'item_name' => 'B',
                'quantity' => 1,
                'unit_price' => 3.5,
                'line_total' => 3.5,
            ],
        ];
        $assembler = new PosV2CartAssembler();
        $response = $assembler->assemble(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            $v1Lines,
        );
        $expected = (new PosPricingService())->calculate($v1Lines, [], 0.15);

        $ok = $response->totals->subtotal->amount === number_format((float) $expected['subtotal'], 2, '.', '')
            && $response->totals->discount->amount === number_format((float) $expected['discount_total'], 2, '.', '')
            && $response->totals->tax->amount === number_format((float) $expected['tax'], 2, '.', '')
            && $response->totals->total->amount === number_format((float) $expected['total'], 2, '.', '')
            && $response->itemCount === 3;

        $this->record('cart totals calculator uses v1 pricing pipeline', $ok, 'expected V1 VAT-inclusive totals');
    }

    private function testAddLineRequestValidation(): void
    {
        try {
            AddLineRequest::fromPayload(['product_id' => 1, 'qty' => '0']);
            $this->record('add line rejects invalid qty', false, 'expected exception');
        } catch (PosV2CartInvalidQuantityException) {
            $this->record('add line rejects invalid qty', true, '');
        }

        $request = AddLineRequest::fromPayload(['product_id' => 5, 'qty' => '2']);
        $this->record('add line parses payload', $request->productId === 5 && $request->qty === '2', 'expected parsed request');
    }

    private function testCartScopeFromContext(): void
    {
        $scope = PosV2CartScope::fromRequestContext($this->requestContext());

        $ok = $scope->companyId === 1
            && $scope->branchId === 2
            && $scope->warehouseId === 3
            && $scope->sessionId === 9
            && $scope->currency === 'SAR';

        $this->record('cart scope from request context', $ok, 'expected scoped ids');
    }

    private function testCartSuccessEnvelope(): void
    {
        $zero = new PosV2MoneyDto('0.00', 'SAR');
        $response = (new PosV2ResponseFactory())->cartSuccess(
            new CartResponse([], new PosV2CartTotalsDto($zero, $zero, $zero, $zero), 0),
        );

        $body = $response->body;
        $ok = $response->statusCode === 200
            && ($body['success'] ?? null) === true
            && is_array($body['data']['lines'] ?? null)
            && is_array($body['data']['totals'] ?? null);

        $this->record('cart success envelope', $ok, 'expected success/data envelope');
    }

    private function testAddLineProductNotFound(): void
    {
        $useCase = new AddCartLineUseCase(
            new PosV2CartAccessValidator(),
            new InMemoryCatalogProductPort(null),
            new InMemoryCartPort(),
        );

        try {
            $useCase->execute(
                $this->requestContext(),
                new AddLineRequest(99, '1'),
            );
            $this->record('add line throws when product missing', false, 'expected exception');
        } catch (PosV2CartProductNotFoundException) {
            $this->record('add line throws when product missing', true, '');
        }
    }

    private function testAddLineProductInactive(): void
    {
        $product = new PosV2CatalogProductDto(
            id: 5,
            sku: 'SKU',
            name: 'Inactive',
            price: new PosV2MoneyDto('1.00', 'SAR'),
            imageUrl: null,
            inStock: false,
            requiresWeight: false,
        );

        $useCase = new AddCartLineUseCase(
            new PosV2CartAccessValidator(),
            new InMemoryCatalogProductPort($product),
            new InMemoryCartPort(),
        );

        try {
            $useCase->execute(
                $this->requestContext(),
                new AddLineRequest(5, '1'),
            );
            $this->record('add line throws when product inactive', false, 'expected exception');
        } catch (PosV2CartProductInactiveException) {
            $this->record('add line throws when product inactive', true, '');
        }
    }

    private function testGetCartUseCase(): void
    {
        $cartPort = new InMemoryCartPort();
        $useCase = new GetCartUseCase(new PosV2CartAccessValidator(), $cartPort);
        $result = $useCase->execute($this->requestContext());

        $ok = $result->itemCount === 0 && $result->totals->total->amount === '0.00';
        $this->record('get cart use case returns snapshot', $ok, 'expected empty cart');
    }

    private function requestContext(): PosV2RequestContext
    {
        return new PosV2RequestContext(
            httpMethod: 'POST',
            requestPath: '/api/v2/pos/cart/lines',
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
                permissions: ['pos.register'],
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

final class InMemoryCatalogProductPort implements PosV2CatalogProductPortInterface
{
    public function __construct(
        private readonly ?PosV2CatalogProductDto $product,
    ) {
    }

    public function search(PosV2CatalogScope $scope, CatalogSearchRequest $request): CatalogSearchResponse
    {
        return new CatalogSearchResponse([], new PosV2PaginationDto(1, 24, 0, 0));
    }

    public function findById(PosV2CatalogScope $scope, int $productId): ?PosV2CatalogProductDto
    {
        if ($this->product === null || $this->product->id !== $productId) {
            return null;
        }

        return $this->product;
    }

    public function lookupBarcode(PosV2CatalogScope $scope, string $code): ?PosV2CatalogProductDto
    {
        return null;
    }
}

final class InMemoryCartPort implements PosV2CartPortInterface
{
    /** @var list<array<string, mixed>> */
    private array $lines = [];

    public function load(PosV2CartScope $scope): CartResponse
    {
        return (new PosV2CartAssembler())->assemble($scope, $this->lines);
    }

    public function addLine(PosV2CartScope $scope, int $productId, string $qty): CartResponse
    {
        $this->lines[] = [
            'id' => 'line-' . count($this->lines) + 1,
            'product_id' => $productId,
            'item_name' => 'Product',
            'quantity' => (float) $qty,
            'unit_price' => 1,
            'line_total' => (float) $qty,
        ];

        return $this->load($scope);
    }

    public function updateLine(PosV2CartScope $scope, string $lineId, string $qty): CartResponse
    {
        foreach ($this->lines as &$line) {
            if ((string) ($line['id'] ?? '') === $lineId) {
                $line['quantity'] = (float) $qty;
                $line['line_total'] = (float) $qty;
            }
        }
        unset($line);

        return $this->load($scope);
    }

    public function removeLine(PosV2CartScope $scope, string $lineId): CartResponse
    {
        $this->lines = array_values(array_filter(
            $this->lines,
            static fn (array $line): bool => (string) ($line['id'] ?? '') !== $lineId,
        ));

        return $this->load($scope);
    }

    public function clear(PosV2CartScope $scope): CartResponse
    {
        $this->lines = [];

        return $this->load($scope);
    }
}

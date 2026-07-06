<?php

declare(strict_types=1);

/**
 * POS V2 pricing consistency regression tests.
 *
 * Run: php modules/pos/tests/run-pricing-consistency-tests.php
 */

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CatalogProductAdapter;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Services\PosCheckoutPricingResolver;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAssembler;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartTotalsCalculator;

require_once __DIR__ . '/PosV2TestPricingSupport.php';

final class PosV2PricingConsistencyTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testCouponCheckoutTotalsMatchCart();
        $this->testRewardPointsTotalsMatchCart();
        $this->testCustomerPriceListTotalsMatchCart();
        $this->testCustomTaxRateTotalsMatchCart();
        $this->testBatchCatalogEnrichmentCalledOnce();
        $this->testCatalogStockCorrectnessAfterBatchEnrichment();
        $this->testCatalogSellPriceCorrectnessAfterBatchEnrichment();

        return $this->results;
    }

    private function testCouponCheckoutTotalsMatchCart(): void
    {
        $lines = $this->sampleLines();
        $scope = ['company_id' => 1, 'branch_id' => 2, 'coupon_code' => 'SAVE10', 'points_redeem' => 0.0];
        $resolver = PosV2TestPricingSupport::resolverWithSellPrices(
            PosV2TestPricingSupport::passThroughSellPrices(),
            PosV2TestPricingSupport::couponRewardService(2.0),
        );
        $expected = $resolver->resolve($lines, [], $scope, null, 0.15)['pricing'];

        $cart = $this->assembler($resolver)->assemble(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            $lines,
            pricingSession: ['coupon_code' => 'SAVE10', 'tax_rate' => 0.15],
        );

        $ok = $cart->totals->total->amount === number_format((float) $expected['total'], 2, '.', '');
        $this->record('coupon checkout totals match cart', $ok, 'expected coupon-adjusted total');
    }

    private function testRewardPointsTotalsMatchCart(): void
    {
        $lines = $this->sampleLines();
        $customer = ['id' => 42, 'price_group_id' => 0];
        $scope = ['company_id' => 1, 'branch_id' => 2, 'coupon_code' => '', 'points_redeem' => 50.0];
        $resolver = PosV2TestPricingSupport::resolverWithSellPrices(
            PosV2TestPricingSupport::passThroughSellPrices(),
            PosV2TestPricingSupport::pointsRewardService(50.0, 5.0),
        );
        $expected = $resolver->resolve($lines, [], $scope, $customer, 0.15)['pricing'];

        $cart = $this->assembler($resolver)->assemble(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            $lines,
            pricingSession: [
                'points_redeem' => 50.0,
                'customer' => $customer,
                'tax_rate' => 0.15,
            ],
        );

        $ok = $cart->totals->total->amount === number_format((float) $expected['total'], 2, '.', '');
        $this->record('reward points totals match cart', $ok, 'expected points-adjusted total');
    }

    private function testCustomerPriceListTotalsMatchCart(): void
    {
        $lines = $this->sampleLines();
        $customer = ['id' => 7, 'price_group_id' => 3];
        $resolver = PosV2TestPricingSupport::resolverWithSellPrices(
            PosV2TestPricingSupport::groupPriceSellPrices(7.0),
        );
        $expected = $resolver->resolve(
            $lines,
            [],
            ['company_id' => 1, 'branch_id' => 2, 'coupon_code' => '', 'points_redeem' => 0.0],
            $customer,
            0.15,
        )['pricing'];

        $cart = $this->assembler($resolver)->assemble(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            $lines,
            pricingSession: ['customer' => $customer, 'tax_rate' => 0.15],
        );

        $ok = $cart->totals->total->amount === number_format((float) $expected['total'], 2, '.', '');
        $this->record('customer price list totals match cart', $ok, 'expected group price total');
    }

    private function testCustomTaxRateTotalsMatchCart(): void
    {
        $lines = $this->sampleLines();
        $resolver = PosV2TestPricingSupport::passThroughResolver();
        $expected = $resolver->resolve(
            $lines,
            [],
            ['company_id' => 1, 'branch_id' => 2, 'coupon_code' => '', 'points_redeem' => 0.0],
            null,
            0.05,
        )['pricing'];

        $cart = $this->assembler($resolver)->assemble(
            new PosV2CartScope(1, 2, 3, 4, 'SAR'),
            $lines,
            pricingSession: ['tax_rate' => 0.05],
        );

        $ok = $cart->totals->tax->amount === number_format((float) $expected['tax'], 2, '.', '')
            && $cart->totals->total->amount === number_format((float) $expected['total'], 2, '.', '');
        $this->record('custom tax rate totals match cart', $ok, 'expected 5% VAT totals');
    }

    private function testBatchCatalogEnrichmentCalledOnce(): void
    {
        $calls = 0;
        $adapter = new V1CatalogProductAdapter(
            enrichCatalogRows: static function (array $rows, PosV2CatalogScope $scope) use (&$calls): array {
                $calls++;

                return array_map(static fn (array $row): array => array_merge($row, [
                    'unit_price' => 9.99,
                    'availability' => ['can_add' => true, 'available' => 3],
                ]), $rows);
            },
            listRows: static fn (): array => [[
                'id' => 21,
                'sku' => 'SKU-21',
                'item_name' => 'Batch Item',
                'quantity' => 3,
                'unit' => 'ea',
            ]],
            countRows: static fn (): int => 1,
        );

        $adapter->search(
            PosV2CatalogScope::fromRequestContext($this->requestContext()),
            new CatalogSearchRequest(query: '', categoryId: null, page: 1, perPage: 24),
        );

        $this->record('batch catalog enrichment called once', $calls === 1, 'expected single batch enrich call');
    }

    private function testCatalogStockCorrectnessAfterBatchEnrichment(): void
    {
        $adapter = new V1CatalogProductAdapter(
            enrichCatalogRows: static fn (array $rows): array => array_map(static fn (array $row): array => array_merge($row, [
                'unit_price' => 4.5,
                'availability' => ['can_add' => false, 'available' => 0],
            ]), $rows),
            listRows: static fn (): array => [[
                'id' => 31,
                'sku' => 'SKU-31',
                'item_name' => 'Out of Stock',
                'quantity' => 0,
                'unit' => 'ea',
            ]],
            countRows: static fn (): int => 1,
        );

        $response = $adapter->search(
            PosV2CatalogScope::fromRequestContext($this->requestContext()),
            new CatalogSearchRequest(query: '', categoryId: null, page: 1, perPage: 24),
        );

        $ok = count($response->products) === 1 && $response->products[0]->inStock === false;
        $this->record('catalog stock correctness after batch enrichment', $ok, 'expected in_stock=false');
    }

    private function testCatalogSellPriceCorrectnessAfterBatchEnrichment(): void
    {
        $adapter = new V1CatalogProductAdapter(
            enrichCatalogRows: static fn (array $rows): array => array_map(static fn (array $row): array => array_merge($row, [
                'unit_price' => 18.75,
                'availability' => ['can_add' => true, 'available' => 12],
            ]), $rows),
            listRows: static fn (): array => [[
                'id' => 41,
                'sku' => 'SKU-41',
                'item_name' => 'Priced Item',
                'quantity' => 12,
                'unit' => 'ea',
            ]],
            countRows: static fn (): int => 1,
        );

        $response = $adapter->search(
            PosV2CatalogScope::fromRequestContext($this->requestContext()),
            new CatalogSearchRequest(query: '', categoryId: null, page: 1, perPage: 24),
        );

        $ok = count($response->products) === 1
            && $response->products[0]->price->amount === '18.75'
            && $response->products[0]->inStock === true;
        $this->record('catalog sell price correctness after batch enrichment', $ok, 'expected enriched sell price');
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

    private function assembler(PosCheckoutPricingResolver $resolver): PosV2CartAssembler
    {
        return new PosV2CartAssembler(
            totalsCalculator: new PosV2CartTotalsCalculator(pricingResolver: $resolver),
        );
    }

    private function requestContext(): PosV2RequestContext
    {
        return new PosV2RequestContext(
            httpMethod: 'GET',
            requestPath: '/api/v2/pos/catalog/search',
            channel: 'api',
            register: new PosV2RegisterContext(
                companyId: 7,
                branchId: 4,
                warehouseId: 11,
                sessionId: 99,
                terminal: null,
                shift: null,
                branch: null,
                cashier: new PosV2CashierContext(3, 'Cashier'),
                locale: 'ar',
                timezone: 'Asia/Riyadh',
                currency: 'SAR',
                rtl: true,
                featureFlags: new PosV2FeatureFlagsContext(true, 'retail', false, false, false),
                permissions: ['pos.catalog.view'],
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

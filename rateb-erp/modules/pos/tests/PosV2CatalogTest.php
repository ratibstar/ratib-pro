<?php

declare(strict_types=1);

/**
 * POS V2 catalog tests (T08).
 *
 * Run: php modules/pos/tests/run-catalog-tests.php
 */

use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogProductResponse;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchResponse;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogBootstrapDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogCategoryDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogProductDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2PaginationDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2CatalogValidationException;
use Rateb\App\Pos\Services\V2\Catalog\PosV2CatalogProductMapper;

final class PosV2CatalogTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testCatalogSearchRequestValidation();
        $this->testCatalogProductMapper();
        $this->testCatalogBootstrapDtoShape();
        $this->testCatalogSearchSuccessEnvelope();
        $this->testCatalogScopeFromRequestContext();
        $this->testCatalogPaginationMath();

        return $this->results;
    }

    private function testCatalogSearchRequestValidation(): void
    {
        try {
            CatalogSearchRequest::fromQueryParams(['per_page' => '99']);
            $this->record('catalog search rejects per_page > 48', false, 'expected exception');
        } catch (PosV2CatalogValidationException) {
            $this->record('catalog search rejects per_page > 48', true, '');
        }

        $request = CatalogSearchRequest::fromQueryParams([
            'query' => 'coffee',
            'category_id' => '3',
            'page' => '2',
            'per_page' => '24',
        ]);

        $ok = $request->query === 'coffee'
            && $request->categoryId === 3
            && $request->page === 2
            && $request->perPage === 24;

        $this->record('catalog search parses query params', $ok, 'expected parsed request');
    }

    private function testCatalogProductMapper(): void
    {
        $mapper = new PosV2CatalogProductMapper();
        $dto = $mapper->fromV1Product([
            'id' => 10,
            'sku' => 'SKU-10',
            'item_code' => 'IC-10',
            'item_name' => 'Arabica Beans',
            'unit_price' => 12.5,
            'unit' => 'kg',
            'availability' => ['can_add' => true, 'available' => 5],
        ], 'SAR');

        $ok = $dto->id === 10
            && $dto->sku === 'SKU-10'
            && $dto->name === 'Arabica Beans'
            && $dto->price->amount === '12.50'
            && $dto->price->currency === 'SAR'
            && $dto->inStock === true
            && $dto->requiresWeight === true;

        $this->record('catalog product mapper maps V1 row', $ok, 'expected catalog product dto');
    }

    private function testCatalogBootstrapDtoShape(): void
    {
        $dto = new PosV2CatalogBootstrapDto([
            new PosV2CatalogCategoryDto(1, 'Beverages', 0),
            new PosV2CatalogCategoryDto(2, 'Snacks', 1),
        ]);

        $array = $dto->toArray();
        $ok = isset($array['categories'])
            && count($array['categories']) === 2
            && $array['categories'][0]['name'] === 'Beverages';

        $this->record('catalog bootstrap dto shape', $ok, 'expected categories array');
    }

    private function testCatalogSearchSuccessEnvelope(): void
    {
        $response = (new PosV2ResponseFactory())->catalogSuccess(
            new CatalogSearchResponse(
                products: [
                    new PosV2CatalogProductDto(
                        id: 1,
                        sku: 'A1',
                        name: 'Item',
                        price: new PosV2MoneyDto('1.00', 'SAR'),
                        imageUrl: null,
                        inStock: true,
                        requiresWeight: false,
                    ),
                ],
                pagination: new PosV2PaginationDto(1, 24, 1, 1),
            ),
        );

        $body = $response->body;
        $ok = ($body['success'] ?? null) === true
            && is_array($body['data']['products'] ?? null)
            && is_array($body['data']['pagination'] ?? null);

        $this->record('catalog search success envelope', $ok, 'expected success/data envelope');
    }

    private function testCatalogScopeFromRequestContext(): void
    {
        $scope = PosV2CatalogScope::fromRequestContext($this->requestContext());

        $ok = $scope->companyId === 7
            && $scope->warehouseId === 11
            && $scope->branchId === 4
            && $scope->sessionId === 99
            && $scope->currency === 'SAR';

        $this->record('catalog scope from request context', $ok, 'expected scope ids');
    }

    private function testCatalogPaginationMath(): void
    {
        $pagination = new PosV2PaginationDto(2, 24, 50, 3);
        $array = $pagination->toArray();

        $ok = $array['page'] === 2
            && $array['per_page'] === 24
            && $array['total'] === 50
            && $array['last_page'] === 3;

        $this->record('catalog pagination dto', $ok, 'expected pagination fields');
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

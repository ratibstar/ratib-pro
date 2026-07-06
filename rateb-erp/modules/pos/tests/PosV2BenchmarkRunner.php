<?php

declare(strict_types=1);

/**
 * POS V2 performance benchmarks (bootstrap, catalog, cart, checkout).
 *
 * Run: php modules/pos/tests/run-benchmarks.php
 */

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CatalogProductAdapter;
use Rateb\App\Pos\Services\PosCheckoutPricingResolver;
use Rateb\App\Pos\Services\PosPricingService;
use Rateb\App\Pos\Services\PosSessionService;

require_once __DIR__ . '/pos-v2-test-bootstrap.php';
require_once __DIR__ . '/PosV2IntegrationFixture.php';

final class PosV2BenchmarkRunner
{
    /** @var list<int> */
    private array $scales = [1000, 10000, 100000];

    /** @return array<string, mixed> */
    public function run(): array
    {
        return [
            'generated_at' => gmdate('c'),
            'php_version' => PHP_VERSION,
            'scales' => $this->scales,
            'bootstrap' => $this->benchmarkBootstrap(),
            'catalog' => $this->benchmarkCatalog(),
            'cart' => $this->benchmarkCart(),
            'checkout' => $this->benchmarkCheckout(),
        ];
    }

    /** @return array<string, mixed> */
    private function benchmarkBootstrap(): array
    {
        $start = microtime(true);
        $memBefore = memory_get_usage(true);
        require_once __DIR__ . '/pos-v2-test-bootstrap.php';
        $elapsedMs = (microtime(true) - $start) * 1000;
        $memPeakMb = memory_get_peak_usage(true) / 1048576;

        return [
            'execution_ms' => round($elapsedMs, 3),
            'memory_mb' => round($memPeakMb, 3),
            'memory_delta_mb' => round((memory_get_usage(true) - $memBefore) / 1048576, 3),
            'query_count' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function benchmarkCatalog(): array
    {
        $scope = new PosV2CatalogScope(companyId: 1, warehouseId: 1, branchId: 1, sessionId: 1, currency: 'SAR', rtl: false);
        $results = [];

        foreach ($this->scales as $totalProducts) {
            $queryCount = 0;
            $rows = $this->syntheticRows(min(24, $totalProducts));

            $listRows = static function (int $limit, int $offset, array $filters, string $orderBy) use ($totalProducts, $rows, &$queryCount): array {
                $queryCount++;
                if ($offset >= $totalProducts) {
                    return [];
                }

                return array_slice($rows, 0, min($limit, $totalProducts - $offset));
            };

            $countRows = static function (array $filters) use ($totalProducts, &$queryCount): int {
                $queryCount++;

                return $totalProducts;
            };

            $enrichRows = static function (array $pageRows, PosV2CatalogScope $catalogScope) use (&$queryCount): array {
                $queryCount++;
                foreach ($pageRows as $i => $row) {
                    $pageRows[$i]['unit_price'] = 10.0;
                    $pageRows[$i]['availability'] = ['can_add' => true, 'available' => 100];
                }

                return $pageRows;
            };

            $adapter = new V1CatalogProductAdapter(
                enrichCatalogRows: \Closure::fromCallable($enrichRows),
                listRows: \Closure::fromCallable($listRows),
                countRows: \Closure::fromCallable($countRows),
            );

            $memBefore = memory_get_usage(true);
            $start = microtime(true);
            $response = $adapter->search(
                $scope,
                CatalogSearchRequest::fromQueryParams(['category_id' => '1', 'page' => '1', 'per_page' => '24']),
            );
            $elapsedMs = (microtime(true) - $start) * 1000;

            $results[(string) $totalProducts] = [
                'execution_ms' => round($elapsedMs, 3),
                'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 3),
                'memory_delta_mb' => round((memory_get_usage(true) - $memBefore) / 1048576, 3),
                'query_count' => $queryCount,
                'items_returned' => count($response->products),
                'total_reported' => $response->pagination->total,
            ];
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function benchmarkCart(): array
    {
        $session = new PosSessionService();
        $session->setCartLines([]);
        $scope = new PosV2CartScope(companyId: 1, branchId: 1, warehouseId: 1, sessionId: 1, currency: 'SAR');
        $queryCount = 0;

        $memBefore = memory_get_usage(true);
        $start = microtime(true);

        for ($i = 1; $i <= 50; $i++) {
            $lines = $session->getCartLines();
            $lines[] = [
                'id' => 'bench-line-' . $i,
                'product_id' => $i,
                'item_name' => 'Bench Item ' . $i,
                'quantity' => 1.0,
                'unit_price' => 10.0,
                'price_source' => 'manual',
                'line_total' => 10.0,
            ];
            $session->setCartLines($lines);
            $queryCount++;
        }

        $loaded = count($session->getCartLines());
        $elapsedMs = (microtime(true) - $start) * 1000;

        return [
            'execution_ms' => round($elapsedMs, 3),
            'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 3),
            'memory_delta_mb' => round((memory_get_usage(true) - $memBefore) / 1048576, 3),
            'query_count' => $queryCount,
            'lines_in_session' => $loaded,
            'scope_company_id' => $scope->companyId,
        ];
    }

    /** @return array<string, mixed> */
    private function benchmarkCheckout(): array
    {
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = [
                'id' => 'chk-' . $i,
                'product_id' => $i,
                'item_name' => 'Checkout Item ' . $i,
                'quantity' => 2.0,
                'unit_price' => 15.0,
                'price_source' => 'manual',
                'line_total' => 30.0,
            ];
        }

        $dbOk = PosV2IntegrationFixture::isDatabaseAvailable();
        $mode = $dbOk ? 'full-checkout-pricing-resolver' : 'pricing-service-offline';

        $memBefore = memory_get_usage(true);
        $start = microtime(true);
        $iterations = 100;

        if ($dbOk) {
            $resolver = new PosCheckoutPricingResolver();
            for ($n = 0; $n < $iterations; $n++) {
                $resolver->resolve($lines, [], ['company_id' => 1, 'branch_id' => 1], null, 0.15);
            }
        } else {
            $pricing = new PosPricingService();
            for ($n = 0; $n < $iterations; $n++) {
                $pricing->calculate($lines, [], 0.15);
            }
        }

        $elapsedMs = (microtime(true) - $start) * 1000;

        return [
            'execution_ms' => round($elapsedMs, 3),
            'execution_ms_per_iteration' => round($elapsedMs / $iterations, 4),
            'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 3),
            'memory_delta_mb' => round((memory_get_usage(true) - $memBefore) / 1048576, 3),
            'query_count' => $dbOk ? null : 0,
            'iterations' => $iterations,
            'lines_per_iteration' => count($lines),
            'mode' => $mode,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function syntheticRows(int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'id' => $i,
                'sku' => 'BENCH-SKU-' . $i,
                'item_code' => 'BENCH-' . $i,
                'item_name' => 'Benchmark Product ' . $i,
                'unit' => 'ea',
                'category_id' => 1,
            ];
        }

        return $rows;
    }
}

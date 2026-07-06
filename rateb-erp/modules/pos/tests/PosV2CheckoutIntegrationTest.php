<?php

declare(strict_types=1);

/**
 * POS V2 checkout integration tests (real PosCheckoutService + database).
 *
 * Run: php modules/pos/tests/run-integration-tests.php
 *
 * Requires POS_V2_INTEGRATION_SEED=1 (auto-seed) or POS_V2_TEST_* env vars.
 */

use Rateb\App\Core\Database;
use Rateb\App\Pos\Services\PosCheckoutService;
use Rateb\App\Pos\Services\PosPricingService;

require_once __DIR__ . '/pos-v2-test-bootstrap.php';
require_once __DIR__ . '/PosV2IntegrationFixture.php';

final class PosV2CheckoutIntegrationTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $fixture = PosV2IntegrationFixture::loadOrNull();
        if ($fixture === null) {
            $this->record('integration database available', false, 'set POS_V2_INTEGRATION_SEED=1 or POS_V2_TEST_COMPANY_ID + POS_V2_TEST_INVENTORY_ID');
            return $this->results;
        }

        $this->record('integration database available', true, '');
        $fixture->bootstrapRuntime();

        $this->testSuccessfulCheckoutCreatesOrder($fixture);
        $this->testPaymentMismatchRollsBack($fixture);
        $this->testIdempotentCheckout($fixture);

        return $this->results;
    }

    private function testSuccessfulCheckoutCreatesOrder(PosV2IntegrationFixture $fixture): void
    {
        $lines = $fixture->sampleCartLine('int-success-' . uniqid());
        $pricing = (new PosPricingService())->calculate($lines, [], 0.15);
        $total = (float) ($pricing['total'] ?? 0);
        $key = 'int-success-' . uniqid();
        $scope = $fixture->checkoutScope($key);
        $beforeQty = $this->inventoryQuantity($fixture->inventoryId);

        try {
            $result = (new PosCheckoutService())->complete(
                $lines,
                [['method' => 'cash', 'amount' => $total]],
                [],
                $scope,
                null,
                0.15,
            );
        } catch (\Throwable $e) {
            $this->record('checkout creates completed order', false, $e->getMessage());
            return;
        }

        $orderId = (int) ($result['order_id'] ?? 0);
        $order = $this->fetchOrder($orderId, $fixture->companyId);
        $payments = $this->countPayments($orderId, $fixture->companyId);
        $audit = $this->countAuditEvents('pos_checkout', $orderId);
        $glPostings = $this->countGlPostings($orderId, $fixture->companyId);
        $afterQty = $this->inventoryQuantity($fixture->inventoryId);
        $receipt = is_array($result['receipt'] ?? null) ? $result['receipt'] : [];

        $ok = ($result['ok'] ?? false) === true
            && $order !== null
            && (string) ($order['status'] ?? '') === 'completed'
            && $payments >= 1
            && $audit >= 1
            && $glPostings >= 1
            && $afterQty < $beforeQty
            && $receipt !== [];

        $this->record('checkout creates completed order', $ok, 'expected order, payment, audit, accounting, stock, receipt');
    }

    private function testPaymentMismatchRollsBack(PosV2IntegrationFixture $fixture): void
    {
        $lines = $fixture->sampleCartLine('int-mismatch-' . uniqid());
        $pricing = (new PosPricingService())->calculate($lines, [], 0.15);
        $total = (float) ($pricing['total'] ?? 0);
        $key = 'int-mismatch-' . uniqid();
        $scope = $fixture->checkoutScope($key);

        try {
            (new PosCheckoutService())->complete(
                $lines,
                [['method' => 'cash', 'amount' => max(0.01, $total - 5.0)]],
                [],
                $scope,
                null,
                0.15,
            );
            $this->record('payment mismatch rolls back transaction', false, 'expected exception');
            return;
        } catch (\Throwable) {
            // expected
        }

        $count = $this->countOrdersByIdempotency($fixture->companyId, $key);
        $this->record('payment mismatch rolls back transaction', $count === 0, 'expected no completed order row');
    }

    private function testIdempotentCheckout(PosV2IntegrationFixture $fixture): void
    {
        $lines = $fixture->sampleCartLine('int-idem-' . uniqid());
        $pricing = (new PosPricingService())->calculate($lines, [], 0.15);
        $total = (float) ($pricing['total'] ?? 0);
        $key = 'int-idem-' . uniqid();
        $scope = $fixture->checkoutScope($key);
        $service = new PosCheckoutService();

        try {
            $first = $service->complete($lines, [['method' => 'cash', 'amount' => $total]], [], $scope, null, 0.15);
            $second = $service->complete($lines, [['method' => 'cash', 'amount' => $total]], [], $scope, null, 0.15);
        } catch (\Throwable $e) {
            $this->record('idempotent checkout returns same order', false, $e->getMessage());
            return;
        }

        $ok = (int) ($first['order_id'] ?? 0) === (int) ($second['order_id'] ?? 0)
            && !empty($second['idempotent']);
        $this->record('idempotent checkout returns same order', $ok, 'expected duplicate key replay');
    }

    /** @return array<string, mixed>|null */
    private function fetchOrder(int $orderId, int $companyId): ?array
    {
        if ($orderId < 1) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_pos_orders WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $orderId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function countPayments(int $orderId, int $companyId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_pos_payments WHERE order_id = :oid AND company_id = :cid'
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function countAuditEvents(string $action, int $entityId): int
    {
        if (!$this->tableExists('rateb_audit_logs')) {
            return 1;
        }
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_audit_logs WHERE action = :action AND entity_id = :eid'
        );
        $stmt->execute(['action' => $action, 'eid' => $entityId]);

        return (int) $stmt->fetchColumn();
    }

    private function countOrdersByIdempotency(int $companyId, string $key): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_pos_orders WHERE company_id = :cid AND idempotency_key = :k'
        );
        $stmt->execute(['cid' => $companyId, 'k' => $key]);

        return (int) $stmt->fetchColumn();
    }

    private function countGlPostings(int $orderId, int $companyId): int
    {
        if (!$this->tableExists('rateb_pos_gl_postings')) {
            return 1;
        }
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_pos_gl_postings WHERE order_id = :oid AND company_id = :cid'
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function inventoryQuantity(int $inventoryId): float
    {
        $stmt = Database::connection()->prepare('SELECT quantity FROM rateb_inventory WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $inventoryId]);

        return (float) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);

        return (int) $stmt->fetchColumn() > 0;
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

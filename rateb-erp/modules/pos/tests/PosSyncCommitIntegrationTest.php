<?php

declare(strict_types=1);

/**
 * Phase 14.1 — Accept → Commit → PosCheckoutService real-DB certification.
 *
 * Run: php modules/pos/tests/run-pos-sync-commit-integration-tests.php
 * Requires POS_V2_INTEGRATION_SEED=1 or POS_V2_TEST_* env vars.
 *
 * Does not modify PosCheckoutService. Uses PosSyncCommitService only.
 */

use Rateb\App\Core\Database;
use Rateb\App\Pos\Services\PosCheckoutService;
use Rateb\App\Pos\Services\PosPricingService;
use Rateb\App\Pos\Services\PosSyncAcceptanceLifecycle;
use Rateb\App\Pos\Services\PosSyncAcceptanceReconcileService;
use Rateb\App\Pos\Services\PosSyncAcceptanceService;
use Rateb\App\Pos\Services\PosSyncCommitService;

require_once __DIR__ . '/pos-v2-test-bootstrap.php';
require_once __DIR__ . '/PosV2IntegrationFixture.php';

final class PosSyncCommitIntegrationTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $fixture = PosV2IntegrationFixture::loadOrNull();
        if ($fixture === null) {
            $this->record(
                'integration database available',
                false,
                'set POS_V2_INTEGRATION_SEED=1 or POS_V2_TEST_COMPANY_ID + POS_V2_TEST_INVENTORY_ID'
            );

            return $this->results;
        }

        $this->record('integration database available', true, '');
        if (!(new PosSyncCommitService())->isAvailable()) {
            $this->record('migration 217 commit columns', false, 'rateb_pos_sync_acceptances.commit_token missing');

            return $this->results;
        }
        $this->record('migration 217 commit columns', true, '');
        $fixture->bootstrapRuntime();

        $this->testAAcceptCommitPostsOrderStockGl($fixture);
        $this->testBDuplicateCommit($fixture);
        $this->testCCrashRecoveryReconcile($fixture);

        return $this->results;
    }

    private function testAAcceptCommitPostsOrderStockGl(PosV2IntegrationFixture $fixture): void
    {
        $syncKey = 'p141-a-' . uniqid('', true);
        $payload = $this->offlinePayload($fixture, $syncKey);
        $beforeQty = $this->inventoryQuantity($fixture->inventoryId);

        $accept = (new PosSyncAcceptanceService())->accept($payload, [
            'company_id' => $fixture->companyId,
        ]);
        if (($accept['accepted'] ?? false) !== true) {
            $this->record('A accept→commit posts order/stock/GL', false, 'accept failed: ' . json_encode($accept));

            return;
        }

        $commit = (new PosSyncCommitService())->commit(
            $fixture->companyId,
            ['sync_key' => $syncKey],
            [
                'user_id' => $fixture->userId,
                'branch_id' => $fixture->branchId,
                'device_id' => (string) $payload['device_id'],
            ]
        );

        $orderId = (int) ($commit['order_id'] ?? 0);
        $acceptance = $this->fetchAcceptance($fixture->companyId, $syncKey);
        $order = $this->fetchOrder($orderId, $fixture->companyId);
        $movements = $this->countStockMovements($orderId);
        $gl = $this->countGlPostings($orderId, $fixture->companyId);
        $afterQty = $this->inventoryQuantity($fixture->inventoryId);

        $ok = ($commit['ok'] ?? false) === true
            && ($commit['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED
            && $orderId > 0
            && $order !== null
            && (string) ($order['status'] ?? '') === 'completed'
            && (string) ($order['idempotency_key'] ?? '') === $syncKey
            && $acceptance !== null
            && (string) ($acceptance['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED
            && (int) ($acceptance['order_id'] ?? 0) === $orderId
            && $movements >= 1
            && $gl >= 1
            && $afterQty < $beforeQty;

        $this->record(
            'A accept→commit posts order/stock/GL',
            $ok,
            'order=' . $orderId . ' mv=' . $movements . ' gl=' . $gl . ' status=' . ($acceptance['status'] ?? '')
        );
    }

    private function testBDuplicateCommit(PosV2IntegrationFixture $fixture): void
    {
        $syncKey = 'p141-b-' . uniqid('', true);
        $payload = $this->offlinePayload($fixture, $syncKey);
        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fixture->companyId]);
        $auth = [
            'user_id' => $fixture->userId,
            'branch_id' => $fixture->branchId,
            'device_id' => (string) $payload['device_id'],
        ];
        $svc = new PosSyncCommitService();
        $first = $svc->commit($fixture->companyId, ['sync_key' => $syncKey], $auth);
        $second = $svc->commit($fixture->companyId, ['sync_key' => $syncKey], $auth);

        $orders = $this->countOrdersByIdempotency($fixture->companyId, $syncKey);
        $ok = ($first['ok'] ?? false) === true
            && ($second['ok'] ?? false) === true
            && (int) ($first['order_id'] ?? 0) === (int) ($second['order_id'] ?? 0)
            && (int) ($first['order_id'] ?? 0) > 0
            && $orders === 1
            && !empty($second['already_committed']);

        $this->record(
            'B duplicate commit no second order',
            $ok,
            'orders=' . $orders . ' first=' . (int) ($first['order_id'] ?? 0) . ' second=' . (int) ($second['order_id'] ?? 0)
        );
    }

    private function testCCrashRecoveryReconcile(PosV2IntegrationFixture $fixture): void
    {
        $syncKey = 'p141-c-' . uniqid('', true);
        $payload = $this->offlinePayload($fixture, $syncKey);
        $accept = (new PosSyncAcceptanceService())->accept($payload, [
            'company_id' => $fixture->companyId,
        ]);
        if (($accept['accepted'] ?? false) !== true) {
            $this->record('C crash recovery reconcile', false, 'accept failed');

            return;
        }

        /* Simulate checkout success without acceptance COMMITTED update. */
        $lines = $fixture->sampleCartLine('p141-c-line-' . uniqid());
        $pricing = (new PosPricingService())->calculate($lines, [], 0.15);
        $total = (float) ($pricing['total'] ?? 0);
        $scope = $fixture->checkoutScope($syncKey);
        try {
            $checkout = (new PosCheckoutService())->complete(
                $lines,
                [['method' => 'cash', 'amount' => $total]],
                [],
                $scope,
                null,
                0.15
            );
        } catch (Throwable $e) {
            $this->record('C crash recovery reconcile', false, 'checkout: ' . $e->getMessage());

            return;
        }
        $orderId = (int) ($checkout['order_id'] ?? 0);

        /* Force acceptance into stale COMMITTING (crash after checkout). */
        $db = Database::connection();
        $db->prepare(
            'UPDATE rateb_pos_sync_acceptances
             SET status = :st, committing_at = :at, commit_token = :tok, order_id = NULL
             WHERE company_id = :cid AND sync_key = :sk'
        )->execute([
            'st' => PosSyncAcceptanceLifecycle::COMMITTING,
            'at' => '2000-01-01 00:00:00',
            'tok' => 'crash_token_' . uniqid(),
            'cid' => $fixture->companyId,
            'sk' => $syncKey,
        ]);

        $out = (new PosSyncAcceptanceReconcileService())->reconcileCompany($fixture->companyId, 60);
        $acceptance = $this->fetchAcceptance($fixture->companyId, $syncKey);
        $ok = ($out['ok'] ?? false) === true
            && ($out['reconciled'] ?? 0) >= 1
            && $acceptance !== null
            && (string) ($acceptance['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED
            && (int) ($acceptance['order_id'] ?? 0) === $orderId
            && $this->countOrdersByIdempotency($fixture->companyId, $syncKey) === 1;

        $this->record(
            'C crash recovery reconcile',
            $ok,
            'reconciled=' . (int) ($out['reconciled'] ?? 0) . ' order=' . $orderId . ' status=' . ($acceptance['status'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function offlinePayload(PosV2IntegrationFixture $fixture, string $syncKey): array
    {
        $lines = $fixture->sampleCartLine('line-' . substr($syncKey, -8));
        $pricing = (new PosPricingService())->calculate($lines, [], 0.15);
        $total = (float) ($pricing['total'] ?? 0);

        return [
            'device_id' => 'dev-p141-' . $fixture->companyId,
            'installation_id' => 'inst-p141-' . $fixture->companyId,
            'sync_key' => $syncKey,
            'sale_id' => 'sale-' . $syncKey,
            'created_at' => date('c'),
            'branch_id' => $fixture->branchId,
            'warehouse_id' => $fixture->warehouseId,
            'terminal_id' => $fixture->terminalId,
            'shift_id' => $fixture->shiftId,
            'lines' => [[
                'product_id' => $fixture->inventoryId,
                'qty' => 1,
                'unit_price' => 10,
                'line_total' => 10,
            ]],
            'totals' => [
                'line_count' => 1,
                'subtotal' => $total > 0 ? $total : 10,
                'total' => $total > 0 ? $total : 10,
                'currency' => 'SAR',
            ],
            'metadata' => [
                'branch_id' => $fixture->branchId,
                'warehouse_id' => $fixture->warehouseId,
                'terminal_id' => $fixture->terminalId,
                'shift_id' => $fixture->shiftId,
                'source' => 'pos_offline_v2',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function fetchAcceptance(int $companyId, string $syncKey): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_pos_sync_acceptances WHERE company_id = :cid AND sync_key = :sk LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $syncKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
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
            return 0;
        }
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_pos_gl_postings WHERE order_id = :oid AND company_id = :cid'
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function countStockMovements(int $orderId): int
    {
        if ($orderId < 1) {
            return 0;
        }
        foreach (['rateb_stock_movements', 'rateb_inventory_movements'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            try {
                $stmt = Database::connection()->prepare(
                    'SELECT COUNT(*) FROM ' . $table . '
                     WHERE reference_type = :rt AND reference_id = :rid'
                );
                $stmt->execute(['rt' => 'pos_order', 'rid' => $orderId]);

                return (int) $stmt->fetchColumn();
            } catch (Throwable) {
                continue;
            }
        }

        return 0;
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

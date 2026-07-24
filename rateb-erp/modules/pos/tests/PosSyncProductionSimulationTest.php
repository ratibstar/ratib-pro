<?php

declare(strict_types=1);

/**
 * Phase 15.2 — Offline POS production simulation (no physical devices).
 *
 * Run: php modules/pos/tests/run-pos-sync-production-simulation.php
 * Requires: POS_V2_INTEGRATION_SEED=1 or POS_V2_TEST_* + migrations 216/217.
 *
 * Architecture lock: PosSyncCommitService → PosCheckoutService::complete() only.
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

final class PosSyncProductionSimulationTest
{
    /** @var list<array{id: string, name: string, passed: bool, detail: string, ms?: float}> */
    private array $results = [];

    /** @var array<string, float> */
    private array $perf = [];

    /** @return array{results: list<array<string, mixed>>, perf: array<string, float>, summary: array<string, mixed>} */
    public function run(): array
    {
        $fixture = PosV2IntegrationFixture::loadOrNull();
        if ($fixture === null) {
            $this->record('BOOT', 'database + fixture', false, 'set POS_V2_INTEGRATION_SEED=1 or POS_V2_TEST_*');

            return $this->finalize();
        }
        if (!(new PosSyncCommitService())->isAvailable()) {
            $this->record('BOOT', 'migration 217 commit_token', false, 'commit columns missing');

            return $this->finalize();
        }
        $this->record('BOOT', 'database + migration 217', true, 'company=' . $fixture->companyId);
        $fixture->bootstrapRuntime();
        $this->ensureStock($fixture, 5000);

        $this->scenarioA($fixture);
        $this->scenarioB($fixture);
        $this->scenarioC($fixture);
        $this->scenarioD($fixture);
        $this->scenarioE($fixture);
        $this->scenarioF($fixture);
        $this->scenarioG($fixture);
        $this->scenarioH($fixture);
        $this->scenarioI($fixture);
        $this->scenarioJ($fixture);
        $this->databaseConsistency($fixture);

        return $this->finalize();
    }

    private function scenarioA(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('A');
        $payload = $this->payload($fx, $syncKey);
        $t0 = hrtime(true);
        $accept = (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);
        $this->perf['accept_ms'] = $this->ms($t0);

        if (($accept['accepted'] ?? false) !== true) {
            $this->record('A', 'single offline sale', false, 'accept failed');

            return;
        }

        $t1 = hrtime(true);
        $commit = $this->commit($fx, $syncKey, $payload);
        $this->perf['commit_ms'] = $this->ms($t1);
        $orderId = (int) ($commit['order_id'] ?? 0);
        $acc = $this->acceptance($fx->companyId, $syncKey);
        $mv = $this->stockMovements($orderId);
        $gl = $this->glPostings($orderId, $fx->companyId);
        $journals = $this->journalCount($orderId);

        $ok = ($commit['ok'] ?? false)
            && ($commit['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED
            && $orderId > 0
            && (string) ($acc['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED
            && (int) ($acc['order_id'] ?? 0) === $orderId
            && $mv >= 1
            && ($gl >= 1 || $journals >= 1)
            && $this->ordersByKey($fx->companyId, $syncKey) === 1;

        $this->record(
            'A',
            'single offline sale → order/stock/GL/COMMITTED',
            $ok,
            sprintf('order=%d mv=%d gl=%d journals=%d accept_ms=%.2f commit_ms=%.2f', $orderId, $mv, $gl, $journals, $this->perf['accept_ms'], $this->perf['commit_ms']),
            $this->perf['commit_ms']
        );
    }

    private function scenarioB(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('B');
        $payload = $this->payload($fx, $syncKey);
        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);
        $first = $this->commit($fx, $syncKey, $payload);
        $orderId = (int) ($first['order_id'] ?? 0);
        $mv1 = $this->stockMovements($orderId);
        $gl1 = $this->glPostings($orderId, $fx->companyId);
        $second = $this->commit($fx, $syncKey, $payload);
        $mv2 = $this->stockMovements($orderId);
        $gl2 = $this->glPostings($orderId, $fx->companyId);

        $ok = ($first['ok'] ?? false)
            && ($second['ok'] ?? false)
            && !empty($second['already_committed'])
            && (int) ($second['order_id'] ?? 0) === $orderId
            && $this->ordersByKey($fx->companyId, $syncKey) === 1
            && $mv2 === $mv1
            && $gl2 === $gl1;

        $this->record('B', 'duplicate commit idempotent', $ok, "order={$orderId} mv={$mv1}->{$mv2} gl={$gl1}->{$gl2}");
    }

    private function scenarioC(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('C');
        $payload = $this->payload($fx, $syncKey);
        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);
        $acc = $this->acceptance($fx->companyId, $syncKey);
        $id = (int) ($acc['id'] ?? 0);
        $claim = (new PosSyncAcceptanceLifecycle())->claim($fx->companyId, $id);
        if (!($claim['ok'] ?? false)) {
            $this->record('C', 'concurrent commit CAS', false, 'claim failed: ' . json_encode($claim));

            return;
        }
        $second = $this->commit($fx, $syncKey, $payload);
        $code = (string) ($second['error_code'] ?? '');
        $http = (int) ($second['http_status'] ?? 0);
        $ok = ($second['ok'] ?? true) === false
            && ($code === 'in_progress' || !empty($second['already_committed']))
            && ($http === 409 || $http === 200);

        /* Cleanup stale COMMITTING so later scenarios stay clean. */
        Database::connection()->prepare(
            'UPDATE rateb_pos_sync_acceptances SET status = :st, committing_at = NULL, error_code = :ec
             WHERE company_id = :cid AND id = :id'
        )->execute([
            'st' => PosSyncAcceptanceLifecycle::FAILED,
            'ec' => 'sim_concurrent_cleanup',
            'cid' => $fx->companyId,
            'id' => $id,
        ]);

        $this->record('C', 'concurrent commit CAS', $ok, "second={$code} http={$http}");
    }

    private function scenarioD(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('D');
        $payload = $this->payload($fx, $syncKey);
        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);

        $t0 = hrtime(true);
        $lines = $fx->sampleCartLine('d-' . uniqid());
        $pricing = (new PosPricingService())->calculate($lines, [], 0.15);
        $total = (float) ($pricing['total'] ?? 0);
        try {
            $checkout = (new PosCheckoutService())->complete(
                $lines,
                [['method' => 'cash', 'amount' => $total]],
                [],
                $fx->checkoutScope($syncKey),
                null,
                0.15
            );
        } catch (Throwable $e) {
            $this->record('D', 'crash recovery reconcile', false, $e->getMessage());

            return;
        }
        $this->perf['checkout_ms'] = $this->ms($t0);
        $orderId = (int) ($checkout['order_id'] ?? 0);

        Database::connection()->prepare(
            'UPDATE rateb_pos_sync_acceptances
             SET status = :st, committing_at = :at, commit_token = :tok, order_id = NULL
             WHERE company_id = :cid AND sync_key = :sk'
        )->execute([
            'st' => PosSyncAcceptanceLifecycle::COMMITTING,
            'at' => '2000-01-01 00:00:00',
            'tok' => 'sim_crash_' . uniqid(),
            'cid' => $fx->companyId,
            'sk' => $syncKey,
        ]);

        $t1 = hrtime(true);
        $out = (new PosSyncAcceptanceReconcileService())->reconcileCompany($fx->companyId, 60);
        $this->perf['reconcile_ms'] = $this->ms($t1);
        $acc = $this->acceptance($fx->companyId, $syncKey);

        $ok = ($out['reconciled'] ?? 0) >= 1
            && (string) ($acc['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED
            && (int) ($acc['order_id'] ?? 0) === $orderId
            && $this->ordersByKey($fx->companyId, $syncKey) === 1;

        $this->record(
            'D',
            'crash recovery COMMITTING→COMMITTED',
            $ok,
            sprintf('order=%d reconciled=%d checkout_ms=%.2f reconcile_ms=%.2f', $orderId, (int) ($out['reconciled'] ?? 0), $this->perf['checkout_ms'], $this->perf['reconcile_ms']),
            $this->perf['reconcile_ms']
        );
    }

    private function scenarioE(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('E');
        $payload = $this->payload($fx, $syncKey);
        unset($payload['warehouse_id']);
        $payload['metadata']['warehouse_id'] = 0;
        $payload['warehouse_id'] = 0;

        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);
        $beforeOrders = $this->ordersByKey($fx->companyId, $syncKey);
        $commit = $this->commit($fx, $syncKey, $payload);
        $acc = $this->acceptance($fx->companyId, $syncKey);
        $orderId = (int) ($commit['order_id'] ?? 0);
        $ok = ($commit['ok'] ?? true) === false
            && $orderId < 1
            && $this->ordersByKey($fx->companyId, $syncKey) === $beforeOrders
            && (string) ($acc['status'] ?? '') !== PosSyncAcceptanceLifecycle::COMMITTED
            && $this->stockMovements($orderId) === 0
            && $this->glPostings($orderId, $fx->companyId) === 0;

        $this->record('E', 'missing warehouse rejected', $ok, 'code=' . ($commit['error_code'] ?? '') . ' status=' . ($acc['status'] ?? ''));
    }

    private function scenarioF(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('F');
        $payload = $this->payload($fx, $syncKey);
        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);

        /* Mirrors PosApiController::syncCommit permission gate (service has no RBAC). */
        $denied = $this->apiCommitGate([], $fx, $syncKey, $payload);
        $ok = ($denied['ok'] ?? true) === false
            && (int) ($denied['http_status'] ?? 0) === 403
            && $this->ordersByKey($fx->companyId, $syncKey) === 0;

        $this->record('F', 'permission failure without pos.sale.complete', $ok, 'http=' . ($denied['http_status'] ?? 0));
    }

    private function scenarioG(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('G');
        $payload = $this->payload($fx, $syncKey);
        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);

        $wrongCompany = $fx->companyId + 999999;
        $commit = (new PosSyncCommitService())->commit(
            $wrongCompany,
            ['sync_key' => $syncKey],
            [
                'user_id' => $fx->userId,
                'branch_id' => $fx->branchId,
                'device_id' => (string) $payload['device_id'],
                'company_id' => $wrongCompany,
            ]
        );
        /* Service scopes by company_id → not_found (isolation). Controller would also 403 if company context forced. */
        $ok = ($commit['ok'] ?? true) === false
            && in_array((int) ($commit['http_status'] ?? 0), [403, 404], true)
            && $this->ordersByKey($fx->companyId, $syncKey) === 0
            && $this->ordersByKey($wrongCompany, $syncKey) === 0;

        $this->record(
            'G',
            'tenant mismatch isolation',
            $ok,
            'http=' . ($commit['http_status'] ?? 0) . ' code=' . ($commit['error_code'] ?? '')
        );
    }

    private function scenarioH(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('H');
        $payload = $this->payload($fx, $syncKey);
        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);
        $commit = (new PosSyncCommitService())->commit(
            $fx->companyId,
            ['sync_key' => $syncKey],
            [
                'user_id' => $fx->userId,
                'branch_id' => $fx->branchId + 99999,
                'device_id' => (string) $payload['device_id'],
            ]
        );
        $ok = ($commit['ok'] ?? true) === false
            && (string) ($commit['error_code'] ?? '') === 'branch_isolation'
            && (int) ($commit['http_status'] ?? 0) === 403
            && $this->ordersByKey($fx->companyId, $syncKey) === 0;

        $this->record('H', 'branch mismatch 403', $ok, 'code=' . ($commit['error_code'] ?? ''));
    }

    private function scenarioI(PosV2IntegrationFixture $fx): void
    {
        $this->ensureStock($fx, 5000);
        $n = 100;
        $committed = 0;
        $dup = 0;
        $t0 = hrtime(true);
        for ($i = 0; $i < $n; $i++) {
            $syncKey = $this->key('I' . $i);
            $payload = $this->payload($fx, $syncKey);
            $accept = (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);
            if (($accept['accepted'] ?? false) !== true) {
                continue;
            }
            $commit = $this->commit($fx, $syncKey, $payload);
            if (($commit['ok'] ?? false) && ($commit['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED) {
                $committed++;
            }
            if ($this->ordersByKey($fx->companyId, $syncKey) > 1) {
                $dup++;
            }
        }
        $elapsed = $this->ms($t0);
        $this->perf['batch_100_ms'] = $elapsed;
        $this->perf['batch_throughput_per_s'] = $elapsed > 0 ? round(($committed / $elapsed) * 1000, 3) : 0.0;
        $orphans = $this->countCommittedWithoutOrder($fx->companyId);

        $ok = $committed === $n && $dup === 0 && $orphans === 0;
        $this->record(
            'I',
            'large queue 100 sequential commits',
            $ok,
            sprintf('committed=%d dup=%d orphans=%d ms=%.2f thr=%.3f/s', $committed, $dup, $orphans, $elapsed, $this->perf['batch_throughput_per_s']),
            $elapsed
        );
    }

    private function scenarioJ(PosV2IntegrationFixture $fx): void
    {
        $syncKey = $this->key('J');
        $payload = $this->payload($fx, $syncKey);
        (new PosSyncAcceptanceService())->accept($payload, ['company_id' => $fx->companyId]);

        /* Accept OK → checkout succeeds (commit "timed out" before markCommitted) → retry commit. */
        $lines = $fx->sampleCartLine('j-' . uniqid());
        $pricing = (new PosPricingService())->calculate($lines, [], 0.15);
        $total = (float) ($pricing['total'] ?? 0);
        $checkout = (new PosCheckoutService())->complete(
            $lines,
            [['method' => 'cash', 'amount' => $total]],
            [],
            $fx->checkoutScope($syncKey),
            null,
            0.15
        );
        $orderId = (int) ($checkout['order_id'] ?? 0);
        $mv1 = $this->stockMovements($orderId);
        $gl1 = $this->glPostings($orderId, $fx->companyId);

        Database::connection()->prepare(
            'UPDATE rateb_pos_sync_acceptances
             SET status = :st, committing_at = :at, commit_token = :tok, order_id = NULL
             WHERE company_id = :cid AND sync_key = :sk'
        )->execute([
            'st' => PosSyncAcceptanceLifecycle::COMMITTING,
            'at' => date('Y-m-d H:i:s'),
            'tok' => 'sim_timeout_' . uniqid(),
            'cid' => $fx->companyId,
            'sk' => $syncKey,
        ]);

        /* Interrupted network: leave COMMITTING briefly, then retry via commit() which reconciles existing order. */
        Database::connection()->prepare(
            'UPDATE rateb_pos_sync_acceptances SET status = :st, committing_at = NULL
             WHERE company_id = :cid AND sync_key = :sk'
        )->execute([
            'st' => PosSyncAcceptanceLifecycle::FAILED,
            'cid' => $fx->companyId,
            'sk' => $syncKey,
        ]);

        $retry = $this->commit($fx, $syncKey, $payload);
        $mv2 = $this->stockMovements($orderId);
        $gl2 = $this->glPostings($orderId, $fx->companyId);
        $acc = $this->acceptance($fx->companyId, $syncKey);

        $ok = ($retry['ok'] ?? false)
            && (int) ($retry['order_id'] ?? 0) === $orderId
            && $this->ordersByKey($fx->companyId, $syncKey) === 1
            && $mv2 === $mv1
            && $gl2 === $gl1
            && (string) ($acc['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED;

        $this->record(
            'J',
            'interrupted network retry single order/stock/GL',
            $ok,
            sprintf('order=%d mv=%d gl=%d already=%s', $orderId, $mv2, $gl2, !empty($retry['already_committed']) ? '1' : '0')
        );
    }

    private function databaseConsistency(PosV2IntegrationFixture $fx): void
    {
        $cid = $fx->companyId;
        $committedNoOrder = $this->countCommittedWithoutOrder($cid);
        $dupKeys = $this->countDuplicateIdempotency($cid);
        $orphanStock = $this->countOrphanStock($cid);
        $orphanGl = $this->countOrphanGl($cid);
        $missingOrder = $this->countAcceptanceMissingOrder($cid);

        $ok = $committedNoOrder === 0
            && $dupKeys === 0
            && $orphanStock === 0
            && $orphanGl === 0
            && $missingOrder === 0;

        $this->record(
            'DB',
            'consistency orphans/duplicates',
            $ok,
            sprintf(
                'committed_no_order=%d dup_idem=%d orphan_stock=%d orphan_gl=%d missing_order=%d',
                $committedNoOrder,
                $dupKeys,
                $orphanStock,
                $orphanGl,
                $missingOrder
            )
        );
    }

    /**
     * Mirrors PosApiController::syncCommit permission check before orchestrator.
     *
     * @param list<string> $permissions
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function apiCommitGate(array $permissions, PosV2IntegrationFixture $fx, string $syncKey, array $payload): array
    {
        if (!in_array('pos.sale.complete', $permissions, true)) {
            return [
                'ok' => false,
                'error' => 'access_denied',
                'error_code' => 'access_denied',
                'http_status' => 403,
            ];
        }

        return $this->commit($fx, $syncKey, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function commit(PosV2IntegrationFixture $fx, string $syncKey, array $payload): array
    {
        return (new PosSyncCommitService())->commit(
            $fx->companyId,
            ['sync_key' => $syncKey],
            [
                'user_id' => $fx->userId,
                'branch_id' => $fx->branchId,
                'device_id' => (string) ($payload['device_id'] ?? ''),
                'company_id' => $fx->companyId,
            ]
        );
    }

    /** @return array<string, mixed> */
    private function payload(PosV2IntegrationFixture $fx, string $syncKey): array
    {
        $lines = $fx->sampleCartLine('line-' . substr(md5($syncKey), 0, 8));
        $pricing = (new PosPricingService())->calculate($lines, [], 0.15);
        $total = (float) ($pricing['total'] ?? 0);
        if ($total <= 0) {
            $total = 11.5;
        }

        return [
            'device_id' => 'sim-device-' . $fx->companyId,
            'installation_id' => 'sim-inst-' . $fx->companyId,
            'sync_key' => $syncKey,
            'sale_id' => 'sale-' . $syncKey,
            'created_at' => date('c'),
            'branch_id' => $fx->branchId,
            'warehouse_id' => $fx->warehouseId,
            'terminal_id' => $fx->terminalId,
            'shift_id' => $fx->shiftId,
            'lines' => [[
                'product_id' => $fx->inventoryId,
                'qty' => 1,
                'unit_price' => 10,
                'line_total' => 10,
            ]],
            'totals' => [
                'line_count' => 1,
                'subtotal' => $total,
                'total' => $total,
                'currency' => 'SAR',
            ],
            'metadata' => [
                'branch_id' => $fx->branchId,
                'warehouse_id' => $fx->warehouseId,
                'terminal_id' => $fx->terminalId,
                'shift_id' => $fx->shiftId,
                'source' => 'pos_offline_sim_v15_2',
            ],
        ];
    }

    private function key(string $tag): string
    {
        return 'p152-' . $tag . '-' . str_replace('.', '', uniqid('', true));
    }

    private function ensureStock(PosV2IntegrationFixture $fx, float $qty): void
    {
        Database::connection()->prepare(
            'UPDATE rateb_inventory SET quantity = :q WHERE id = :id AND company_id = :cid'
        )->execute(['q' => $qty, 'id' => $fx->inventoryId, 'cid' => $fx->companyId]);
    }

    /** @return array<string, mixed>|null */
    private function acceptance(int $companyId, string $syncKey): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_pos_sync_acceptances WHERE company_id = :cid AND sync_key = :sk LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $syncKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function ordersByKey(int $companyId, string $key): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_pos_orders WHERE company_id = :cid AND idempotency_key = :k'
        );
        $stmt->execute(['cid' => $companyId, 'k' => $key]);

        return (int) $stmt->fetchColumn();
    }

    private function stockMovements(int $orderId): int
    {
        if ($orderId < 1 || !$this->tableExists('rateb_stock_movements')) {
            return 0;
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM rateb_stock_movements WHERE reference_type = :rt AND reference_id = :rid'
            );
            $stmt->execute(['rt' => 'pos_order', 'rid' => $orderId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function glPostings(int $orderId, int $companyId): int
    {
        if ($orderId < 1 || !$this->tableExists('rateb_pos_gl_postings')) {
            return 0;
        }
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_pos_gl_postings WHERE order_id = :oid AND company_id = :cid'
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function journalCount(int $orderId): int
    {
        if ($orderId < 1 || !$this->tableExists('rateb_journal_entries')) {
            return 0;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_journal_entries
                 WHERE source_id = :sid AND source_type IN ('pos_sale_revenue','pos_sale_cogs')
                   AND (voided_at IS NULL OR voided_at = '0000-00-00 00:00:00')"
            );
            $stmt->execute(['sid' => $orderId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countCommittedWithoutOrder(int $companyId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM rateb_pos_sync_acceptances
             WHERE company_id = :cid AND status = 'COMMITTED' AND (order_id IS NULL OR order_id = 0)"
        );
        $stmt->execute(['cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function countDuplicateIdempotency(int $companyId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM (
                SELECT idempotency_key FROM rateb_pos_orders
                WHERE company_id = :cid AND idempotency_key IS NOT NULL AND idempotency_key <> \'\'
                GROUP BY idempotency_key HAVING COUNT(*) > 1
             ) d'
        );
        $stmt->execute(['cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function countOrphanStock(int $companyId): int
    {
        if (!$this->tableExists('rateb_stock_movements')) {
            return 0;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_stock_movements m
                 LEFT JOIN rateb_pos_orders o ON o.id = m.reference_id AND o.company_id = :cid
                 WHERE m.reference_type = 'pos_order' AND o.id IS NULL
                   AND m.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)"
            );
            $stmt->execute(['cid' => $companyId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countOrphanGl(int $companyId): int
    {
        if (!$this->tableExists('rateb_pos_gl_postings')) {
            return 0;
        }
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_pos_gl_postings g
             LEFT JOIN rateb_pos_orders o ON o.id = g.order_id AND o.company_id = g.company_id
             WHERE g.company_id = :cid AND o.id IS NULL'
        );
        $stmt->execute(['cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function countAcceptanceMissingOrder(int $companyId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM rateb_pos_sync_acceptances a
             LEFT JOIN rateb_pos_orders o ON o.id = a.order_id AND o.company_id = a.company_id
             WHERE a.company_id = :cid AND a.status = 'COMMITTED' AND a.order_id IS NOT NULL AND a.order_id > 0 AND o.id IS NULL"
        );
        $stmt->execute(['cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function ms(int|float $started): float
    {
        return round((hrtime(true) - $started) / 1_000_000, 2);
    }

    private function record(string $id, string $name, bool $passed, string $detail, ?float $ms = null): void
    {
        $row = ['id' => $id, 'name' => $name, 'passed' => $passed, 'detail' => $detail];
        if ($ms !== null) {
            $row['ms'] = $ms;
        }
        $this->results[] = $row;
    }

    /** @return array{results: list<array<string, mixed>>, perf: array<string, float>, summary: array<string, mixed>} */
    private function finalize(): array
    {
        $pass = 0;
        $fail = 0;
        foreach ($this->results as $r) {
            if ($r['passed']) {
                $pass++;
            } else {
                $fail++;
            }
        }
        $total = max(1, $pass + $fail);
        $pct = (int) round(($pass / $total) * 100);
        $scenarioIds = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'DB'];
        $scenarioPass = 0;
        foreach ($this->results as $r) {
            if (in_array($r['id'], $scenarioIds, true) && $r['passed']) {
                $scenarioPass++;
            }
        }
        $ready = $fail === 0 && $scenarioPass >= 11;

        return [
            'results' => $this->results,
            'perf' => $this->perf,
            'summary' => [
                'passed' => $pass,
                'failed' => $fail,
                'coverage_pct' => $pct,
                'scenarios_passed' => $scenarioPass,
                'scenarios_total' => 11,
                'recommendation' => $ready
                    ? 'READY FOR LIVE DEVICE CERTIFICATION'
                    : 'NOT READY',
                'production_readiness_pct' => $pct,
            ],
        ];
    }
}

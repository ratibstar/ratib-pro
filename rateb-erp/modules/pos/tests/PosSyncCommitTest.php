<?php

declare(strict_types=1);

/**
 * Phase 13 — sync commit certification suite (no real inventory/GL SQL).
 * Run: php modules/pos/tests/run-pos-sync-commit-tests.php
 */

use Rateb\App\Pos\Services\PosCheckoutCompletePort;
use Rateb\App\Pos\Services\PosOrderIdempotencyLookup;
use Rateb\App\Pos\Services\PosSyncAcceptanceLifecycle;
use Rateb\App\Pos\Services\PosSyncAcceptanceMapper;
use Rateb\App\Pos\Services\PosSyncAcceptanceReconcileService;
use Rateb\App\Pos\Services\PosSyncCommitService;
use Rateb\App\Pos\Services\PosSyncValidateService;
use Rateb\App\Pos\Services\PosTaxSettingsService;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;

final class PosSyncCommitTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testLifecycleTransitions();
        $this->testPaymentDefaultCash();
        $this->testMissingWarehouse();
        $this->testTenantIsolationCompanyFromAuth();
        $this->testQtyMapsToQuantity();
        $this->testDuplicateCommit();
        $this->testConcurrentCommit();
        $this->testBranchIsolation();
        $this->testCheckoutIdempotency();
        $this->testCrashRecoveryReconcile();

        return $this->results;
    }

    private function testLifecycleTransitions(): void
    {
        $life = new PosSyncAcceptanceLifecycle();
        $ok = true;
        foreach ([
            [PosSyncAcceptanceLifecycle::WAITING_COMMIT, PosSyncAcceptanceLifecycle::COMMITTING],
            [PosSyncAcceptanceLifecycle::FAILED, PosSyncAcceptanceLifecycle::COMMITTING],
            [PosSyncAcceptanceLifecycle::COMMITTING, PosSyncAcceptanceLifecycle::COMMITTED],
            [PosSyncAcceptanceLifecycle::COMMITTING, PosSyncAcceptanceLifecycle::FAILED],
        ] as [$from, $to]) {
            try {
                $life->assertTransition($from, $to);
            } catch (Throwable) {
                $ok = false;
            }
        }
        $rejected = false;
        try {
            $life->assertTransition(PosSyncAcceptanceLifecycle::COMMITTED, PosSyncAcceptanceLifecycle::COMMITTING);
        } catch (Throwable) {
            $rejected = true;
        }
        $this->record('lifecycle guarded transitions', $ok && $rejected, $rejected ? 'reject ok' : 'reject missing');
    }

    private function testPaymentDefaultCash(): void
    {
        $mapper = new PosSyncAcceptanceMapper();
        $mapped = $mapper->map($this->basePayload(['payments' => []]), [
            'company_id' => 9,
            'user_id' => 3,
        ]);
        $ok = ($mapped['payment_policy'] ?? null) === PosSyncAcceptanceMapper::PAYMENT_POLICY_DEFAULT_CASH
            && ($mapped['payments'][0]['method'] ?? '') === 'cash'
            && (float) ($mapped['payments'][0]['amount'] ?? 0) === 50.0
            && ($mapped['scope']['idempotency_key'] ?? '') === 'sk-1';
        $this->record('payment default cash', $ok, (string) ($mapped['payment_policy'] ?? ''));
    }

    private function testMissingWarehouse(): void
    {
        $mapper = new PosSyncAcceptanceMapper();
        $threw = false;
        try {
            $mapper->map($this->basePayload(['warehouse_id' => 0, 'metadata' => ['branch_id' => 2]]), [
                'company_id' => 1,
                'user_id' => 1,
            ]);
        } catch (InvalidArgumentException $e) {
            $threw = str_contains($e->getMessage(), 'warehouse_id');
        }
        $this->record('missing warehouse rejected', $threw, $threw ? 'warehouse_id' : 'no throw');
    }

    private function testTenantIsolationCompanyFromAuth(): void
    {
        $mapper = new PosSyncAcceptanceMapper();
        $mapped = $mapper->map($this->basePayload(['company_id' => 999]), [
            'company_id' => 7,
            'user_id' => 2,
        ]);
        $ok = (int) ($mapped['scope']['company_id'] ?? 0) === 7;
        $this->record('tenant isolation company from auth', $ok, (string) ($mapped['scope']['company_id'] ?? 0));
    }

    private function testQtyMapsToQuantity(): void
    {
        $mapper = new PosSyncAcceptanceMapper();
        $mapped = $mapper->map($this->basePayload(), ['company_id' => 1, 'user_id' => 1]);
        $ok = (float) ($mapped['lines'][0]['quantity'] ?? 0) === 2.0;
        $this->record('qty maps to quantity', $ok, (string) ($mapped['lines'][0]['quantity'] ?? ''));
    }

    private function testDuplicateCommit(): void
    {
        $life = new class extends PosSyncAcceptanceLifecycle {
            public function fetchBySyncKey(int $companyId, string $syncKey): ?array
            {
                return [
                    'id' => 11,
                    'company_id' => $companyId,
                    'sync_key' => $syncKey,
                    'server_sync_id' => 'ss-1',
                    'status' => self::COMMITTED,
                    'order_id' => 55,
                    'payload' => '{}',
                    'retry_count' => 1,
                ];
            }
        };
        $svc = $this->commitService($life, new class implements PosCheckoutCompletePort {
            public function complete(
                array $cartLines,
                array $payments,
                array $invoiceDiscount,
                array $scope,
                ?array $customer = null,
                float $taxRate = 0.15,
                bool $giftReceipt = false
            ): array {
                throw new RuntimeException('checkout must not run on duplicate');
            }
        });
        $result = $svc->commit(1, ['sync_key' => 'sk-dup'], ['user_id' => 1, 'branch_id' => 2]);
        $ok = ($result['ok'] ?? false) === true
            && ($result['already_committed'] ?? false) === true
            && (int) ($result['order_id'] ?? 0) === 55;
        $this->record('duplicate commit', $ok, (string) ($result['status'] ?? ''));
    }

    private function testConcurrentCommit(): void
    {
        $life = new class extends PosSyncAcceptanceLifecycle {
            public function fetchBySyncKey(int $companyId, string $syncKey): ?array
            {
                return [
                    'id' => 12,
                    'company_id' => $companyId,
                    'sync_key' => $syncKey,
                    'server_sync_id' => 'ss-2',
                    'status' => self::WAITING_COMMIT,
                    'payload' => '{}',
                    'retry_count' => 0,
                ];
            }

            public function claim(int $companyId, int $acceptanceId): array
            {
                return [
                    'ok' => false,
                    'reason' => 'in_progress',
                    'in_progress' => true,
                    'row' => ['id' => $acceptanceId, 'status' => self::COMMITTING],
                ];
            }
        };
        $svc = $this->commitService($life, new class implements PosCheckoutCompletePort {
            public function complete(
                array $cartLines,
                array $payments,
                array $invoiceDiscount,
                array $scope,
                ?array $customer = null,
                float $taxRate = 0.15,
                bool $giftReceipt = false
            ): array {
                throw new RuntimeException('checkout must not run while in progress');
            }
        });
        $result = $svc->commit(1, ['sync_key' => 'sk-race'], ['user_id' => 1]);
        $ok = ($result['ok'] ?? true) === false
            && ($result['error_code'] ?? '') === 'in_progress'
            && (int) ($result['http_status'] ?? 0) === 409;
        $this->record('concurrent commit', $ok, (string) ($result['error_code'] ?? ''));
    }

    private function testBranchIsolation(): void
    {
        $payload = $this->basePayload(['branch_id' => 2, 'warehouse_id' => 5]);
        $life = new class($payload) extends PosSyncAcceptanceLifecycle {
            /** @param array<string, mixed> $payload */
            public function __construct(private array $payload)
            {
            }

            public function fetchBySyncKey(int $companyId, string $syncKey): ?array
            {
                return [
                    'id' => 13,
                    'company_id' => $companyId,
                    'sync_key' => $syncKey,
                    'server_sync_id' => 'ss-3',
                    'status' => self::WAITING_COMMIT,
                    'payload' => json_encode($this->payload),
                    'retry_count' => 0,
                ];
            }

            public function claim(int $companyId, int $acceptanceId): array
            {
                return [
                    'ok' => true,
                    'commit_token' => 'tok',
                    'retried' => false,
                    'row' => [
                        'id' => $acceptanceId,
                        'sync_key' => 'sk-1',
                        'server_sync_id' => 'ss-3',
                        'status' => self::COMMITTING,
                        'payload' => json_encode($this->payload),
                        'retry_count' => 1,
                    ],
                ];
            }

            public function markFailed(int $companyId, int $acceptanceId, string $commitToken, array $fields): bool
            {
                return true;
            }
        };
        $svc = $this->commitService($life, new class implements PosCheckoutCompletePort {
            public function complete(
                array $cartLines,
                array $payments,
                array $invoiceDiscount,
                array $scope,
                ?array $customer = null,
                float $taxRate = 0.15,
                bool $giftReceipt = false
            ): array {
                throw new RuntimeException('checkout must not run on branch mismatch');
            }
        });
        $result = $svc->commit(1, ['sync_key' => 'sk-1'], [
            'user_id' => 1,
            'branch_id' => 99,
        ]);
        $ok = ($result['ok'] ?? true) === false
            && ($result['error_code'] ?? '') === 'branch_isolation';
        $this->record('branch isolation', $ok, (string) ($result['error_code'] ?? ''));
    }

    private function testCheckoutIdempotency(): void
    {
        $payload = $this->basePayload();
        $life = new class($payload) extends PosSyncAcceptanceLifecycle {
            /** @param array<string, mixed> $payload */
            public function __construct(private array $payload)
            {
            }

            public function fetchBySyncKey(int $companyId, string $syncKey): ?array
            {
                return [
                    'id' => 14,
                    'company_id' => $companyId,
                    'sync_key' => $syncKey,
                    'server_sync_id' => 'ss-4',
                    'status' => self::WAITING_COMMIT,
                    'payload' => json_encode($this->payload),
                    'retry_count' => 0,
                ];
            }

            public function claim(int $companyId, int $acceptanceId): array
            {
                return [
                    'ok' => true,
                    'commit_token' => 'tok',
                    'retried' => false,
                    'row' => [
                        'id' => $acceptanceId,
                        'sync_key' => 'sk-1',
                        'server_sync_id' => 'ss-4',
                        'status' => self::COMMITTING,
                        'payload' => json_encode($this->payload),
                        'retry_count' => 1,
                    ],
                ];
            }

            public function markCommitted(int $companyId, int $acceptanceId, string $commitToken, array $fields): bool
            {
                return true;
            }
        };
        $calls = 0;
        $seenKey = '';
        $checkout = new class($calls, $seenKey) implements PosCheckoutCompletePort {
            public function __construct(private int &$calls, private string &$seenKey)
            {
            }

            public function complete(
                array $cartLines,
                array $payments,
                array $invoiceDiscount,
                array $scope,
                ?array $customer = null,
                float $taxRate = 0.15,
                bool $giftReceipt = false
            ): array {
                $this->calls++;
                $this->seenKey = (string) ($scope['idempotency_key'] ?? '');
                return [
                    'ok' => true,
                    'order_id' => 77,
                    'order_no' => 'POS-77',
                    'idempotent' => true,
                ];
            }
        };
        $svc = $this->commitService($life, $checkout);
        $result = $svc->commit(1, ['sync_key' => 'sk-1'], [
            'user_id' => 1,
            'branch_id' => 2,
        ]);
        $ok = ($result['ok'] ?? false) === true
            && (int) ($result['order_id'] ?? 0) === 77
            && $calls === 1
            && $seenKey === 'sk-1'
            && !empty($result['already_committed']);
        $this->record('checkout idempotency', $ok, 'key=' . $seenKey . ' calls=' . $calls);
    }

    private function testCrashRecoveryReconcile(): void
    {
        $life = new class extends PosSyncAcceptanceLifecycle {
            /** @var list<array{0:int,1:int}> */
            public array $committed = [];
            /** @var list<array{0:int,1:string}> */
            public array $interrupted = [];

            public function listStaleCommitting(int $companyId, int $ttlSeconds, int $limit = 50): array
            {
                return [[
                    'id' => 21,
                    'sync_key' => 'sk-crash',
                    'status' => self::COMMITTING,
                    'committing_at' => '2000-01-01 00:00:00',
                ]];
            }

            public function listFailedWithoutOrder(int $companyId, int $limit = 50): array
            {
                return [[
                    'id' => 22,
                    'sync_key' => 'sk-failed-has-order',
                    'status' => self::FAILED,
                    'order_id' => null,
                ]];
            }

            public function reconcileCommitted(int $companyId, int $acceptanceId, int $orderId): bool
            {
                $this->committed[] = [$acceptanceId, $orderId];
                return true;
            }

            public function reconcileInterrupted(int $companyId, int $acceptanceId, string $message): bool
            {
                $this->interrupted[] = [$acceptanceId, $message];
                return true;
            }
        };
        $orders = new class extends PosOrderIdempotencyLookup {
            public function findRaw(int $companyId, string $key, bool $forUpdate = false): ?array
            {
                if ($key === 'sk-failed-has-order') {
                    return ['id' => 90, 'status' => 'completed'];
                }
                return null;
            }
        };
        $svc = new PosSyncAcceptanceReconcileService($life, $orders, new PosAuditBridgeService());
        $out = $svc->reconcileCompany(1, 60);
        $ok = ($out['ok'] ?? false) === true
            && ($out['reconciled'] ?? 0) === 1
            && ($out['interrupted'] ?? 0) === 1
            && $life->committed === [[22, 90]]
            && count($life->interrupted) === 1
            && str_contains((string) $life->interrupted[0][1], 'commit_interrupted');
        $this->record('crash recovery reconcile', $ok, 'r=' . ($out['reconciled'] ?? 0) . ',i=' . ($out['interrupted'] ?? 0));
    }

    /**
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private function basePayload(array $over = []): array
    {
        return array_merge([
            'device_id' => 'dev-1',
            'installation_id' => 'inst-1',
            'sync_key' => 'sk-1',
            'sale_id' => 'sale-1',
            'created_at' => '2026-01-01T00:00:00Z',
            'branch_id' => 2,
            'warehouse_id' => 5,
            'lines' => [['product_id' => 10, 'qty' => 2, 'unit_price' => 25]],
            'totals' => ['total' => 50],
            'payments' => [],
        ], $over);
    }

    private function commitService(
        PosSyncAcceptanceLifecycle $life,
        PosCheckoutCompletePort $checkout
    ): PosSyncCommitService {
        $orders = new class extends PosOrderIdempotencyLookup {
            public function findRaw(int $companyId, string $key, bool $forUpdate = false): ?array
            {
                return null;
            }
        };

        return new class($life, $checkout, $orders) extends PosSyncCommitService {
            public function __construct(
                PosSyncAcceptanceLifecycle $life,
                PosCheckoutCompletePort $checkout,
                PosOrderIdempotencyLookup $orders
            ) {
                parent::__construct(
                    $life,
                    new PosSyncAcceptanceMapper(),
                    new PosSyncValidateService(),
                    $checkout,
                    $orders,
                    new PosAuditBridgeService(),
                    new PosTaxSettingsService()
                );
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}

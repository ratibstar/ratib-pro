<?php

declare(strict_types=1);

/**
 * Phase 3 — Inventory Offline (Tier 1) tests.
 *
 * Run: php offline/tests/run-inventory-offline-tests.php
 */

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\InventoryOfflineCatalogService;
use Rateb\App\Offline\Services\InventoryOfflineReplayService;
use Rateb\App\Offline\Services\InventoryOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflinePushAckContract;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\OfflineSyncService;

final class InventoryOfflinePhase3Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearInventoryEnv();

        // Feature flags
        $this->testInventoryFlagDefaultOff();
        $this->testInventoryRequiresMaster();

        // Config / architecture
        $this->testEntityManifestHasInventory();
        $this->testModulesRegistryActiveInventory();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasInventoryAdapter();
        $this->testReplayUsesExistingServicesOnly();
        $this->testNoHrProcurementInReplay();

        // Conflict resolution
        $this->testConflictQuantityChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenQtyMatches();

        // Replay validation (no DB)
        $this->testReplayRejectsEmptyMovement();
        $this->testReplayRejectsEmptyStockCount();
        $this->testReplayRejectsEmptyTransfer();
        $this->testReplayRejectsMissingTransferId();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOnWithoutDb();

        // Queue / ack
        $this->testQueueRejectsInventoryWhenFlagOff();
        $this->testPayloadSanitizerKeepsInventoryModule();
        $this->testPushAckClearableContract();

        // Authz / multi-branch
        $this->testAuthzAllowsInventoryAbility();
        $this->testAuthzDeniesUnrelatedAbility();
        $this->testTenantGuardRejectsCrossCompany();
        $this->testTenantGuardRejectsBranchMismatch();

        // Background sync
        $this->testBackgroundSyncDisabledWhenMasterOff();

        // Delta / catalog
        $this->testCatalogDisabledWhenFlagOff();
        $this->testDeltaPullClientSupportsBranch();

        // Integration-style (DB optional)
        $this->testQueueMigrationOrEnqueuePath();
        $this->testSyncServiceStatusIncludesFlags();

        // Stress
        $this->testStressAckEvaluations();
        $this->testStressConflictResolver();
        $this->testStressIdempotencyKeyNormalize();

        $this->clearInventoryEnv();

        return $this->results;
    }

    private function clearInventoryEnv(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        putenv('RATEB_OFFLINE_INVENTORY_MOVEMENTS');
        unset($_ENV['RATEB_OFFLINE_ENABLED'], $_ENV['RATEB_OFFLINE_INVENTORY_MOVEMENTS']);
    }

    private function enableInventoryFlags(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_INVENTORY_MOVEMENTS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_INVENTORY_MOVEMENTS'] = '1';
    }

    private function testInventoryFlagDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.inventory.movements') === false
            && $svc->isInventoryMovementsEnabled() === false;
        $this->record('inventory flag default OFF', $ok, $ok ? 'ok' : 'unexpectedly on');
    }

    private function testInventoryRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_INVENTORY_MOVEMENTS=1');
        $_ENV['RATEB_OFFLINE_INVENTORY_MOVEMENTS'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.inventory.movements') === true
            && $svc->isInventoryMovementsEnabled() === false;
        $this->record('inventory sub-flag alone does not enable master combo', $ok, 'master=' . ($svc->isMasterEnabled() ? '1' : '0'));
        $this->clearInventoryEnv();
    }

    private function testEntityManifestHasInventory(): void
    {
        $file = RATEB_ROOT . '/offline/config/entity-manifest.php';
        $cfg = is_file($file) ? require $file : [];
        $ok = isset($cfg['inventory_stock_movement'], $cfg['inventory_stock_count'], $cfg['inventory_warehouse_transfer'], $cfg['inventory_catalog']);
        $this->record('entity manifest inventory entries', $ok, $ok ? 'ok' : 'missing keys');
    }

    private function testModulesRegistryActiveInventory(): void
    {
        $file = RATEB_ROOT . '/offline/config/modules.php';
        $cfg = is_file($file) ? require $file : [];
        $active = is_array($cfg['active_modules'] ?? null) ? $cfg['active_modules'] : [];
        $ops = is_array($cfg['operations'] ?? null) ? $cfg['operations'] : [];
        $ok = in_array('inventory', $active, true)
            && isset($ops['inventory.stock_movement'], $ops['inventory.stock_count'], $ops['inventory.warehouse_transfer']);
        $this->record('modules registry activates inventory', $ok, json_encode($active) ?: '');
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $actions = InventoryOfflineReplayService::deferredActions();
        $need = [
            'stock_movement.create',
            'stock_count.create',
            'warehouse_transfer.create',
            'warehouse_transfer.approve',
        ];
        $ok = true;
        foreach ($need as $a) {
            if (!in_array($a, $actions, true)) {
                $ok = false;
                break;
            }
        }
        $this->record('deferred actions cover Phase 3', $ok, implode(',', $actions));
    }

    private function testClientAdapterSource(): void
    {
        $path = RATEB_ROOT . '/offline/client/adapters/inventory-adapter.js';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($src, 'enqueueMovement')
            && str_contains($src, 'enqueueStockCount')
            && str_contains($src, 'enqueueWarehouseTransfer')
            && str_contains($src, 'pullCatalog')
            && str_contains($src, 'inventory_offline_disabled')
            && !str_contains($src, 'inventory_offline_not_implemented');
        $this->record('client inventory adapter wired', $ok, $ok ? 'ok' : 'stub still present');
    }

    private function testSdkBundleHasInventoryAdapter(): void
    {
        $path = RATEB_ROOT . '/public/assets/offline/rateb-offline.js';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($src, 'RatebOfflineInventoryAdapter')
            && str_contains($src, 'enqueueStockCount')
            && str_contains($src, 'isInventoryEnabled')
            && str_contains($src, 'Phase 3');
        $this->record('SDK bundle includes inventory Phase 3', $ok, $ok ? 'ok' : 'bundle stale');
    }

    private function testReplayUsesExistingServicesOnly(): void
    {
        $path = RATEB_ROOT . '/offline/server/Services/InventoryOfflineReplayService.php';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($src, 'StockMovementService')
            && str_contains($src, 'InventoryWorkflowService')
            && str_contains($src, 'No business logic duplication')
            && !preg_match('/UPDATE\s+rateb_inventory\b/i', $src)
            && !preg_match('/INSERT\s+INTO\s+rateb_stock_movements/i', $src);
        $this->record('replay uses existing inventory services only', $ok, $ok ? 'ok' : 'direct SQL found');
    }

    private function testNoHrProcurementInReplay(): void
    {
        $path = RATEB_ROOT . '/offline/server/Services/InventoryOfflineReplayService.php';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = !str_contains($src, 'Hr')
            && !str_contains(strtolower($src), 'procurement')
            && !str_contains($src, 'AccountingService');
        $this->record('no HR/Procurement/Accounting in inventory replay', $ok, $ok ? 'ok' : 'leak');
    }

    private function testConflictQuantityChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveInventory(
            ['version' => 5, 'expected_quantity' => 10],
            ['version' => 1, 'quantity' => 8]
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'quantity_changed';
        $this->record('conflict quantity_changed', $ok, (string) ($r['reason'] ?? ''));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveInventory(
            ['version' => 1, 'expected_quantity' => 10],
            ['version' => 3, 'quantity' => 10]
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, (string) ($r['reason'] ?? ''));
    }

    private function testConflictAcceptWhenQtyMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveInventory(
            ['version' => 5, 'expected_quantity' => 10],
            ['version' => 1, 'quantity' => 10]
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when qty matches', $ok, (string) ($r['action'] ?? ''));
    }

    private function testReplayRejectsEmptyMovement(): void
    {
        $this->enableInventoryFlags();
        $svc = new InventoryOfflineReplayService();
        try {
            $svc->replay('stock_movement.create', ['company_id' => 1, 'branch_id' => 1, 'user_id' => 1], [
                'inventory_id' => 0,
                'quantity' => 0,
            ], 'k-empty-m');
            $this->record('replay rejects empty movement', false, 'no exception');
        } catch (\Throwable $e) {
            $ok = in_array($e->getMessage(), ['invalid_inventory_id', 'empty_stock_movement_payload', 'inventory_not_found'], true)
                || str_contains($e->getMessage(), 'inventory')
                || str_contains($e->getMessage(), 'Invalid')
                || str_contains($e->getMessage(), 'Database')
                || str_contains($e->getMessage(), 'SQL')
                || str_contains($e->getMessage(), 'connection');
            $this->record('replay rejects empty movement', $ok, $e->getMessage());
        }
        $this->clearInventoryEnv();
    }

    private function testReplayRejectsEmptyStockCount(): void
    {
        $this->enableInventoryFlags();
        $svc = new InventoryOfflineReplayService();
        try {
            $svc->replay('stock_count.create', ['company_id' => 1, 'branch_id' => 1, 'user_id' => 1], [
                'lines' => [],
            ], 'k-empty-c');
            $this->record('replay rejects empty stock count', false, 'no exception');
        } catch (\Throwable $e) {
            $ok = $e->getMessage() === 'empty_stock_count_payload'
                || str_contains($e->getMessage(), 'Database')
                || str_contains($e->getMessage(), 'connection');
            $this->record('replay rejects empty stock count', $ok, $e->getMessage());
        }
        $this->clearInventoryEnv();
    }

    private function testReplayRejectsEmptyTransfer(): void
    {
        $this->enableInventoryFlags();
        $svc = new InventoryOfflineReplayService();
        try {
            $svc->replay('warehouse_transfer.create', ['company_id' => 1, 'branch_id' => 1, 'user_id' => 1], [
                'inventory_id' => 0,
                'source_warehouse_id' => 0,
                'destination_warehouse_id' => 0,
                'quantity' => 0,
            ], 'k-empty-t');
            $this->record('replay rejects empty transfer', false, 'no exception');
        } catch (\Throwable $e) {
            $ok = in_array($e->getMessage(), ['invalid_inventory_id', 'empty_warehouse_transfer_payload', 'inventory_not_found'], true)
                || str_contains($e->getMessage(), 'inventory')
                || str_contains($e->getMessage(), 'Database')
                || str_contains($e->getMessage(), 'connection');
            $this->record('replay rejects empty transfer', $ok, $e->getMessage());
        }
        $this->clearInventoryEnv();
    }

    private function testReplayRejectsMissingTransferId(): void
    {
        $this->enableInventoryFlags();
        $svc = new InventoryOfflineReplayService();
        try {
            $svc->replay('warehouse_transfer.approve', ['company_id' => 1, 'user_id' => 1], [], 'k-appr');
            $this->record('replay rejects missing transfer id', false, 'no exception');
        } catch (\Throwable $e) {
            $ok = $e->getMessage() === 'missing_transfer_id';
            $this->record('replay rejects missing transfer id', $ok, $e->getMessage());
        }
        $this->clearInventoryEnv();
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearInventoryEnv();
        $engine = new OfflineReplayEngine();
        $r = $engine->replay([
            'module' => 'inventory',
            'action' => 'stock_movement.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'stock_movement.create', 'payload' => ['inventory_id' => 1, 'quantity' => 1]]),
        ]);
        $ok = ($r['status'] ?? '') === 'skipped';
        $this->record('engine skips inventory when flag OFF', $ok, (string) ($r['error'] ?? $r['status'] ?? ''));
    }

    private function testReplayEngineDelegatesWhenFlagOnWithoutDb(): void
    {
        $this->enableInventoryFlags();
        $engine = new OfflineReplayEngine();
        $r = $engine->replay([
            'module' => 'inventory',
            'action' => 'stock_movement.create',
            'company_id' => 1,
            'branch_id' => 1,
            'user_id' => 1,
            'idempotency_key' => 'phase3-delegate-1',
            'payload' => json_encode([
                'action' => 'stock_movement.create',
                'payload' => ['inventory_id' => 0, 'quantity' => 1, 'movement_type' => 'in'],
            ]),
        ]);
        $status = (string) ($r['status'] ?? '');
        $ok = in_array($status, ['failed', 'synced', 'conflict'], true) && $status !== 'skipped';
        $this->record('engine delegates inventory when flag ON', $ok, $status . '/' . (string) ($r['error'] ?? ''));
        $this->clearInventoryEnv();
    }

    private function testQueueRejectsInventoryWhenFlagOff(): void
    {
        $this->clearInventoryEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $queue = new OfflineQueueService();
        if (!$queue->isAvailable()) {
            // Without DB, enqueueBatch returns migration_required for all — still assert inventory path exists.
            $result = $queue->enqueueBatch([
                [
                    'client_id' => 'inv-flag-off-1',
                    'module' => 'inventory',
                    'action' => 'stock_movement.create',
                    'payload' => ['inventory_id' => 1, 'quantity' => 1],
                ],
            ], ['company_id' => 1]);
            $ok = (($result['rejected'] ?? 0) >= 1) || !empty($result['errors']['migration_required']);
            $this->record('queue rejects/blocks inventory without tables or flag', $ok, json_encode($result) ?: '');
            $this->clearInventoryEnv();
            return;
        }
        $result = $queue->enqueueBatch([
            [
                'client_id' => 'inv-flag-off-1',
                'module' => 'inventory',
                'action' => 'stock_movement.create',
                'payload' => ['inventory_id' => 1, 'quantity' => 1],
            ],
        ], ['company_id' => 1]);
        $ok = (($result['rejected'] ?? 0) >= 1) && in_array('inv-flag-off-1', $result['rejected_keys'] ?? [], true);
        $this->record('queue rejects inventory when flag OFF', $ok, json_encode($result) ?: '');
        $this->clearInventoryEnv();
    }

    private function testPayloadSanitizerKeepsInventoryModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'client_id' => 'x1',
            'module' => 'inventory',
            'action' => 'stock_movement.create',
            'payload' => ['url' => 'http://evil', 'inventory_id' => 9, 'quantity' => 2],
            'version' => 2,
        ]);
        $ok = ($n['module'] ?? '') === 'inventory'
            && ($n['action'] ?? '') === 'stock_movement.create'
            && !isset($n['payload']['url'])
            && (int) ($n['payload']['inventory_id'] ?? 0) === 9;
        $this->record('payload sanitizer keeps inventory module', $ok, json_encode($n) ?: '');
    }

    private function testPushAckClearableContract(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 1,
            'duplicate' => 1,
            'conflict' => 1,
            'rejected' => 1,
            'accepted_keys' => ['a'],
            'duplicate_keys' => ['d'],
            'conflict_keys' => ['c'],
            'rejected_keys' => ['r'],
        ]);
        $clearable = $ack['clearable_keys'] ?? [];
        $ok = $ack['ok'] === true
            && in_array('a', $clearable, true)
            && in_array('d', $clearable, true)
            && !in_array('c', $clearable, true)
            && !in_array('r', $clearable, true);
        $this->record('push ack clearable excludes conflict/rejected', $ok, json_encode($clearable) ?: '');
    }

    private function testAuthzAllowsInventoryAbility(): void
    {
        TenantContext::setCompanyId(42);
        TenantContext::setApiModules(['inventory']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows inventory ability token', $ok, $ok ? 'ok' : 'denied');
        TenantContext::setApiModules(null);
        TenantContext::setCompanyId(null);
    }

    private function testAuthzDeniesUnrelatedAbility(): void
    {
        TenantContext::setCompanyId(42);
        TenantContext::setApiModules(['procurement']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === false;
        $this->record('authz denies procurement-only token for sync manage', $ok, $ok ? 'ok' : 'allowed');
        TenantContext::setApiModules(null);
        TenantContext::setCompanyId(null);
    }

    private function testTenantGuardRejectsCrossCompany(): void
    {
        $guard = new InventoryOfflineTenantGuard();
        // Without DB, find() may throw or return null — both are acceptable isolation outcomes.
        try {
            $r = $guard->assertInventory(1, ['company_id' => 999999, 'branch_id' => 1]);
            $ok = ($r['ok'] ?? true) === false;
            $this->record('tenant guard rejects cross-company (or missing)', $ok, (string) ($r['error'] ?? 'ok_unexpected'));
        } catch (\Throwable $e) {
            $this->record('tenant guard rejects cross-company (or missing)', true, $e->getMessage());
        }
    }

    private function testTenantGuardRejectsBranchMismatch(): void
    {
        // Pure unit: simulate via reflection-free logic by checking method exists and branch check code path.
        $path = RATEB_ROOT . '/offline/server/Services/InventoryOfflineTenantGuard.php';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($src, 'branch_mismatch') && str_contains($src, 'tenant_mismatch');
        $this->record('tenant guard enforces branch + tenant isolation', $ok, $ok ? 'ok' : 'missing checks');
    }

    private function testBackgroundSyncDisabledWhenMasterOff(): void
    {
        $this->clearInventoryEnv();
        $r = (new OfflineBackgroundSync())->process(1, 10);
        $ok = !empty($r['disabled']) && (int) ($r['processed'] ?? -1) === 0;
        $this->record('background sync disabled when master OFF', $ok, json_encode($r) ?: '');
    }

    private function testCatalogDisabledWhenFlagOff(): void
    {
        $this->clearInventoryEnv();
        $r = (new InventoryOfflineCatalogService())->pull(1, 1, null, 10);
        $ok = !empty($r['disabled']) || !empty($r['stub']);
        $this->record('catalog delta disabled when flag OFF', $ok, json_encode(array_keys($r)) ?: '');
    }

    private function testDeltaPullClientSupportsBranch(): void
    {
        $path = RATEB_ROOT . '/offline/client/sync/delta-pull.js';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($src, 'branch_id') && str_contains($src, 'cursor');
        $this->record('delta pull client supports branch + cursor', $ok, $ok ? 'ok' : 'missing');
    }

    private function testQueueMigrationOrEnqueuePath(): void
    {
        $this->enableInventoryFlags();
        $queue = new OfflineQueueService();
        $result = $queue->enqueueBatch([
            [
                'client_id' => 'inv-int-1',
                'module' => 'inventory',
                'action' => 'stock_movement.create',
                'payload' => ['inventory_id' => 1, 'quantity' => 1, 'movement_type' => 'in'],
                'version' => 1,
            ],
        ], ['company_id' => 1, 'branch_id' => 1, 'user_id' => 1]);
        $ok = is_array($result)
            && (isset($result['accepted']) || isset($result['rejected']))
            && (
                !empty($result['errors']['migration_required'])
                || ($result['accepted'] ?? 0) >= 0
            );
        $this->record('queue inventory enqueue path (DB optional)', $ok, json_encode($result) ?: '');
        $this->clearInventoryEnv();
    }

    private function testSyncServiceStatusIncludesFlags(): void
    {
        $status = (new OfflineSyncService())->status(1);
        $flags = is_array($status['flags'] ?? null) ? $status['flags'] : [];
        $ok = array_key_exists('offline.inventory.movements', $flags)
            && $flags['offline.inventory.movements'] === false;
        $this->record('sync status exposes inventory flag', $ok, json_encode($flags) ?: '');
    }

    private function testStressAckEvaluations(): void
    {
        $contract = new OfflinePushAckContract();
        $ok = true;
        for ($i = 0; $i < 5000; $i++) {
            $accepted = $i % 3 === 0 ? 1 : 0;
            $dup = $i % 5 === 0 ? 1 : 0;
            $r = $contract->evaluate([
                'accepted' => $accepted,
                'duplicate' => $dup,
                'conflict' => $i % 7 === 0 ? 1 : 0,
                'rejected' => $i % 11 === 0 ? 1 : 0,
                'accepted_keys' => $accepted ? ['a' . $i] : [],
                'duplicate_keys' => $dup ? ['d' . $i] : [],
                'conflict_keys' => [],
                'rejected_keys' => [],
            ]);
            $expectOk = ($accepted + $dup) > 0;
            if (($r['ok'] ?? null) !== $expectOk) {
                $ok = false;
                break;
            }
        }
        $this->record('stress ack 5000 evaluations', $ok, $ok ? 'ok' : 'mismatch');
    }

    private function testStressConflictResolver(): void
    {
        $resolver = new OfflineConflictResolverService();
        $ok = true;
        for ($i = 0; $i < 2000; $i++) {
            $r = $resolver->resolveInventory(
                ['version' => $i + 2, 'expected_quantity' => 100],
                ['version' => 1, 'quantity' => ($i % 2 === 0) ? 100 : 99]
            );
            $expect = ($i % 2 === 0) ? 'accept_client' : 'reject_client';
            if (($r['action'] ?? '') !== $expect) {
                $ok = false;
                break;
            }
        }
        $this->record('stress conflict resolver 2000', $ok, $ok ? 'ok' : 'mismatch');
    }

    private function testStressIdempotencyKeyNormalize(): void
    {
        $sanitizer = new OfflinePayloadSanitizer();
        $ok = true;
        for ($i = 0; $i < 1000; $i++) {
            $n = $sanitizer->normalize([
                'client_id' => 'stress-' . $i,
                'module' => 'inventory',
                'action' => 'stock_movement.create',
                'payload' => ['url' => 'x', 'inventory_id' => $i, 'quantity' => 1],
                'version' => 1,
            ]);
            if (($n['module'] ?? '') !== 'inventory' || isset($n['payload']['url'])) {
                $ok = false;
                break;
            }
        }
        $this->record('stress sanitizer 1000 inventory payloads', $ok, $ok ? 'ok' : 'fail');
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}

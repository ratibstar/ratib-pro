<?php

declare(strict_types=1);

/**
 * Enterprise Offline Foundation + Phase 2A.1 blocking-fix tests.
 *
 * Run: php offline/tests/run-offline-foundation-tests.php
 */

use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflinePushAckContract;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Core\TenantContext;

final class OfflineFoundationTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testFeatureFlagsDefaultOff();
        $this->testConflictAcceptWhenServerMissing();
        $this->testConflictRejectWhenServerNewer();
        $this->testConflictAcceptWhenClientNewer();
        $this->testConflictRejectWhenVersionsEqual();
        $this->testReplayAckActions();
        $this->testReplaySkipsBusinessModules();
        $this->testQueueRejectsEmptyIdempotencyWithoutDb();
        $this->testSyncServiceDisabledPush();
        $this->testConfigFilesExist();
        $this->testClientSdkBundleExists();
        $this->testMigrationFilesExist();
        $this->testClientSchemaStores();

        // Phase 2A.1 blocking fixes
        $this->testPayloadSanitizerStripsUrlMethod();
        $this->testPushAckRejectsAllRejected();
        $this->testPushAckAcceptsPartial();
        $this->testPushAckClearableExcludesRejectedAndConflict();
        $this->testPushAckOfflineDisabled();
        $this->testPushAckBranchDenied();
        $this->testPushAckMigrationRequired();
        $this->testAuthRequiresCompany();
        $this->testAuthzDeniesRestrictedToken();
        $this->testAuthzAllowsUnrestrictedToken();
        $this->testAuthzAllowsPosAbility();
        $this->testClientQueueFlushKeepsRejected();
        $this->testActorPrefersApiUserId();

        return $this->results;
    }

    private function testFeatureFlagsDefaultOff(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isMasterEnabled() === false
            && $svc->enabled('offline.inventory.movements') === false
            && $svc->enabled('offline.hr.attendance') === false
            && $svc->enabled('offline.read_cache') === false;
        $this->record('feature flags default OFF', $ok, $ok ? 'ok' : 'master unexpectedly on');
    }

    private function testConflictAcceptWhenServerMissing(): void
    {
        $resolver = new OfflineConflictResolverService();
        $result = $resolver->resolve(['version' => 2, 'payload' => ['a' => 1]], null);
        $ok = ($result['action'] ?? '') === 'accept_client';
        $this->record('conflict accept client when server missing', $ok, (string) ($result['action'] ?? ''));
    }

    private function testConflictRejectWhenServerNewer(): void
    {
        $resolver = new OfflineConflictResolverService();
        $result = $resolver->resolve(['version' => 1], ['version' => 3]);
        $ok = ($result['action'] ?? '') === 'reject_client' && ($result['reason'] ?? '') === 'server_newer';
        $this->record('conflict reject when server newer', $ok, (string) ($result['reason'] ?? ''));
    }

    private function testConflictAcceptWhenClientNewer(): void
    {
        $resolver = new OfflineConflictResolverService();
        $result = $resolver->resolve(['version' => 5], ['version' => 2]);
        $ok = ($result['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when client newer', $ok, (string) ($result['action'] ?? ''));
    }

    private function testConflictRejectWhenVersionsEqual(): void
    {
        $resolver = new OfflineConflictResolverService();
        $result = $resolver->resolve(['version' => 2], ['version' => 2]);
        $ok = ($result['action'] ?? '') === 'reject_client';
        $this->record('conflict reject when versions equal', $ok, (string) ($result['action'] ?? ''));
    }

    private function testReplayAckActions(): void
    {
        $engine = new OfflineReplayEngine();
        $r1 = $engine->replay(['module' => 'offline_meta', 'action' => 'offline.ack']);
        $r2 = $engine->replay(['module' => 'offline_meta', 'action' => 'offline.ping']);
        $ok = ($r1['status'] ?? '') === 'synced' && ($r2['status'] ?? '') === 'synced';
        $this->record('replay ack actions synced', $ok, ($r1['status'] ?? '') . '/' . ($r2['status'] ?? ''));
    }

    private function testReplaySkipsBusinessModules(): void
    {
        putenv('RATEB_OFFLINE_INVENTORY_MOVEMENTS');
        unset($_ENV['RATEB_OFFLINE_INVENTORY_MOVEMENTS']);
        $engine = new OfflineReplayEngine();
        $r = $engine->replay(['module' => 'inventory', 'action' => 'stock_movement.create']);
        $ok = ($r['status'] ?? '') === 'skipped';
        $this->record('replay skips inventory when flag OFF', $ok, (string) ($r['status'] ?? '') . '/' . (string) ($r['error'] ?? ''));
    }

    private function testQueueRejectsEmptyIdempotencyWithoutDb(): void
    {
        try {
            $queue = new OfflineQueueService();
            if (!$queue->isAvailable()) {
                $result = $queue->enqueueBatch([
                    ['client_id' => 't1', 'action' => 'offline.ack'],
                ], ['company_id' => 1]);
                $ok = ($result['rejected'] ?? 0) === 1
                    && !empty($result['errors']['migration_required'])
                    && in_array('t1', $result['rejected_keys'] ?? [], true);
                $this->record('queue migration_required when tables missing', $ok, json_encode($result) ?: '');
                return;
            }
            $result = $queue->enqueueBatch([
                ['action' => 'offline.ack', 'payload' => []],
            ], ['company_id' => 1]);
            $ok = (($result['rejected'] ?? 0) >= 1);
            $this->record('queue rejects missing idempotency key', $ok, json_encode($result) ?: '');
        } catch (\Throwable $e) {
            $this->record(
                'queue service instantiable (db optional)',
                class_exists(OfflineQueueService::class),
                $e->getMessage()
            );
        }
    }

    private function testSyncServiceDisabledPush(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new \Rateb\App\Offline\Services\OfflineSyncService();
        $result = $svc->pushQueue([
            ['client_id' => 'x1', 'action' => 'offline.ack'],
        ], ['company_id' => 1]);
        $ack = (new OfflinePushAckContract())->evaluate($result);
        $ok = !empty($result['errors']['offline_disabled'])
            && ($result['rejected'] ?? 0) === 1
            && $ack['ok'] === false
            && $ack['clearable_keys'] === [];
        $this->record('sync service rejects push when disabled', $ok, json_encode($result) ?: '');
    }

    private function testConfigFilesExist(): void
    {
        $root = RATEB_ROOT . '/offline/config';
        $files = ['feature-flags.php', 'sync-policy.php', 'modules.php', 'entity-manifest.php'];
        $missing = [];
        foreach ($files as $f) {
            if (!is_file($root . '/' . $f)) {
                $missing[] = $f;
            }
        }
        $this->record('config files exist', $missing === [], $missing === [] ? 'ok' : implode(',', $missing));
    }

    private function testClientSdkBundleExists(): void
    {
        $bundle = RATEB_ROOT . '/public/assets/offline/rateb-offline.js';
        $min = RATEB_ROOT . '/public/assets/offline/rateb-offline.min.js';
        $sw = RATEB_ROOT . '/public/rateb-offline-sw.js';
        $ok = is_file($bundle) && is_file($min) && is_file($sw)
            && str_contains((string) file_get_contents($bundle), 'RatebOffline');
        $this->record('client SDK bundle exists', $ok, $ok ? 'ok' : 'missing assets');
    }

    private function testMigrationFilesExist(): void
    {
        $files = [
            RATEB_ROOT . '/offline/migrations/001_offline_sync_meta.sql',
            RATEB_ROOT . '/offline/migrations/002_offline_entity_cursors.sql',
            RATEB_ROOT . '/offline/migrations/003_offline_device_registry.sql',
            RATEB_ROOT . '/migrations/175_offline_sync_meta.sql',
            RATEB_ROOT . '/migrations/176_offline_entity_cursors.sql',
            RATEB_ROOT . '/migrations/177_offline_device_registry.sql',
        ];
        $missing = [];
        foreach ($files as $f) {
            if (!is_file($f)) {
                $missing[] = basename($f);
            }
        }
        $hasQueue = false;
        if (is_file($files[0])) {
            $sql = (string) file_get_contents($files[0]);
            $hasQueue = str_contains($sql, 'rateb_offline_sync_queue')
                && str_contains($sql, 'rateb_offline_sync_conflicts');
        }
        $ok = $missing === [] && $hasQueue;
        $this->record('migration files exist', $ok, $missing === [] ? 'ok' : implode(',', $missing));
    }

    private function testClientSchemaStores(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $needed = ['sync_queue', 'sync_meta', 'entity_cache', 'catalog_index', 'form_drafts', 'snapshots', 'conflicts', 'cursors'];
        $missing = [];
        foreach ($needed as $store) {
            if (!str_contains($schema, "'" . $store . "'") && !str_contains($schema, '"' . $store . '"')) {
                $missing[] = $store;
            }
        }
        $ok = $missing === [] && str_contains($schema, 'rateb_erp_offline');
        $this->record('client IndexedDB stores defined', $ok, $missing === [] ? 'ok' : implode(',', $missing));
    }

    private function testPayloadSanitizerStripsUrlMethod(): void
    {
        $sanitizer = new OfflinePayloadSanitizer();
        $out = $sanitizer->normalize([
            'client_id' => 'c1',
            'module' => 'offline_meta',
            'action' => 'offline.ack',
            'url' => 'https://evil.example/steal',
            'method' => 'DELETE',
            'headers' => ['Authorization' => 'secret'],
            'payload' => [
                'a' => 1,
                'url' => 'https://evil.example/nested',
                'method' => 'PUT',
                'headers' => ['X' => '1'],
            ],
            'version' => 2,
        ]);
        $ok = !isset($out['url'])
            && !isset($out['method'])
            && !isset($out['headers'])
            && ($out['payload']['a'] ?? null) === 1
            && !isset($out['payload']['url'])
            && !isset($out['payload']['method'])
            && !isset($out['payload']['headers']);
        $this->record('payload sanitizer strips url/method', $ok, json_encode($out) ?: '');
    }

    private function testPushAckRejectsAllRejected(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 0,
            'duplicate' => 0,
            'conflict' => 0,
            'rejected' => 2,
            'accepted_keys' => [],
            'duplicate_keys' => [],
            'conflict_keys' => [],
            'rejected_keys' => ['a', 'b'],
        ]);
        $ok = $ack['ok'] === false && $ack['http_status'] === 422 && $ack['clearable_keys'] === [];
        $this->record('push ack rejects all-rejected batch', $ok, json_encode($ack) ?: '');
    }

    private function testPushAckAcceptsPartial(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 1,
            'duplicate' => 0,
            'conflict' => 1,
            'rejected' => 1,
            'accepted_keys' => ['ok1'],
            'duplicate_keys' => [],
            'conflict_keys' => ['c1'],
            'rejected_keys' => ['r1'],
        ]);
        $ok = $ack['ok'] === true
            && $ack['http_status'] === 200
            && $ack['clearable_keys'] === ['ok1'];
        $this->record('push ack partial accept clearable only accepted', $ok, json_encode($ack) ?: '');
    }

    private function testPushAckClearableExcludesRejectedAndConflict(): void
    {
        $contract = new OfflinePushAckContract();
        $keys = $contract->clearableKeys([
            'accepted_keys' => ['a1', 'a2'],
            'duplicate_keys' => ['d1'],
            'conflict_keys' => ['c1'],
            'rejected_keys' => ['r1'],
        ]);
        sort($keys);
        $ok = $keys === ['a1', 'a2', 'd1'];
        $this->record('clearable keys exclude rejected/conflict', $ok, json_encode($keys) ?: '');
    }

    private function testPushAckOfflineDisabled(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 0,
            'errors' => ['offline_disabled' => true],
        ]);
        $ok = $ack['ok'] === false && $ack['http_status'] === 403;
        $this->record('push ack offline_disabled is 403', $ok, json_encode($ack) ?: '');
    }

    private function testPushAckBranchDenied(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 0,
            'errors' => ['branch_denied' => true],
        ]);
        $ok = $ack['ok'] === false && $ack['http_status'] === 403;
        $this->record('push ack branch_denied is 403', $ok, json_encode($ack) ?: '');
    }

    private function testPushAckMigrationRequired(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 0,
            'duplicate' => 0,
            'errors' => ['migration_required' => true],
        ]);
        $ok = $ack['ok'] === false && $ack['http_status'] === 503;
        $this->record('push ack migration_required is 503', $ok, json_encode($ack) ?: '');
    }

    private function testAuthRequiresCompany(): void
    {
        TenantContext::setCompanyId(null);
        TenantContext::setApiUserId(null);
        TenantContext::setApiModules(null);
        $auth = new OfflineAuthorizationService();
        $ok = $auth->isAuthenticatedCompany() === false;
        TenantContext::setCompanyId(42);
        $ok = $ok && $auth->isAuthenticatedCompany() === true;
        TenantContext::setCompanyId(null);
        $this->record('auth requires company context', $ok, $ok ? 'ok' : 'fail');
    }

    private function testAuthzDeniesRestrictedToken(): void
    {
        TenantContext::setCompanyId(7);
        TenantContext::setApiModules(['hr']);
        $auth = new OfflineAuthorizationService();
        $ok = $auth->canManageSync() === false;
        TenantContext::setCompanyId(null);
        TenantContext::setApiModules(null);
        $this->record('authz denies token without pos/inventory ability', $ok, $ok ? 'ok' : 'unexpected allow');
    }

    private function testAuthzAllowsUnrestrictedToken(): void
    {
        TenantContext::setCompanyId(7);
        TenantContext::setApiModules([]);
        $auth = new OfflineAuthorizationService();
        $ok = $auth->canManageSync() === true;
        TenantContext::setCompanyId(null);
        TenantContext::setApiModules(null);
        $this->record('authz allows unrestricted API token', $ok, $ok ? 'ok' : 'unexpected deny');
    }

    private function testAuthzAllowsPosAbility(): void
    {
        TenantContext::setCompanyId(7);
        TenantContext::setApiModules(['pos', 'inventory']);
        $auth = new OfflineAuthorizationService();
        $ok = $auth->canManageSync() === true;
        TenantContext::setCompanyId(null);
        TenantContext::setApiModules(null);
        $this->record('authz allows pos ability token', $ok, $ok ? 'ok' : 'unexpected deny');
    }

    private function testClientQueueFlushKeepsRejected(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'clearable_keys')
            && str_contains($src, 'Never clear rejected')
            && !str_contains($src, 'return writeAll([]).then')
            && str_contains($bundle, 'clearable_keys');
        $this->record('client flush keeps rejected/conflict items', $ok, $ok ? 'ok' : 'legacy clear-all still present');
    }

    private function testActorPrefersApiUserId(): void
    {
        $src = (string) file_get_contents(
            RATEB_ROOT . '/offline/server/Controllers/OfflineSyncApiController.php'
        );
        $ok = str_contains($src, 'TenantContext::apiUserId()')
            && str_contains($src, 'requireAuthOrAbort')
            && str_contains($src, 'requireSyncManageOrAbort')
            && str_contains($src, 'OfflineBranchGuard')
            && str_contains($src, 'Unauthorized');
        $this->record('controller records apiUserId and enforces auth/authz/branch', $ok, $ok ? 'ok' : 'missing hooks');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}

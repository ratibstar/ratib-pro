<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineSyncQueueItem;
use Rateb\App\Offline\OfflineModule;

/**
 * Server-side enterprise offline sync queue (rateb_offline_sync_queue).
 * Phase 3: enqueue + ack + Inventory Tier-1 replay (flag-gated).
 */
final class OfflineQueueService
{
    /** Actions that are safe to mark synced without business replay. */
    private const ACK_ACTIONS = ['offline.ack', 'offline.ping', 'ack', 'ping'];

    private ?OfflineSyncQueueItem $model = null;
    private ?OfflineConflictResolverService $resolver = null;
    private ?OfflineConflictService $conflicts = null;
    private ?OfflinePayloadSanitizer $sanitizer = null;
    private ?OfflineFeatureFlagService $flags = null;
    private ?OfflineReplayEngine $replay = null;

    private function model(): OfflineSyncQueueItem
    {
        return $this->model ??= new OfflineSyncQueueItem();
    }

    private function resolver(): OfflineConflictResolverService
    {
        return $this->resolver ??= new OfflineConflictResolverService();
    }

    private function conflicts(): OfflineConflictService
    {
        return $this->conflicts ??= new OfflineConflictService();
    }

    private function sanitizer(): OfflinePayloadSanitizer
    {
        return $this->sanitizer ??= new OfflinePayloadSanitizer();
    }

    private function flags(): OfflineFeatureFlagService
    {
        return $this->flags ??= new OfflineFeatureFlagService();
    }

    private function replay(): OfflineReplayEngine
    {
        return $this->replay ??= new OfflineReplayEngine();
    }

    public function isAvailable(): bool
    {
        return Database::liveTableHasColumn('rateb_offline_sync_queue', 'id');
    }

    /** @return array<string, mixed> */
    public function statusSummary(?int $companyId = null): array
    {
        if (!$this->isAvailable()) {
            return [
                'pending' => 0,
                'synced' => 0,
                'conflict' => 0,
                'failed' => 0,
                'last_sync' => null,
                'migration_required' => true,
            ];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return [
                'pending' => 0,
                'synced' => 0,
                'conflict' => 0,
                'failed' => 0,
                'last_sync' => null,
            ];
        }

        $row = $this->model()->queryOne(
            'SELECT
                SUM(CASE WHEN status = :pending THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = :synced THEN 1 ELSE 0 END) AS synced,
                SUM(CASE WHEN status = :conflict THEN 1 ELSE 0 END) AS conflict,
                SUM(CASE WHEN status = :failed THEN 1 ELSE 0 END) AS failed,
                MAX(synced_at) AS last_sync
             FROM rateb_offline_sync_queue
             WHERE company_id = :cid',
            [
                'cid' => $companyId,
                'pending' => 'pending',
                'synced' => 'synced',
                'conflict' => 'conflict',
                'failed' => 'failed',
            ]
        );

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'synced' => (int) ($row['synced'] ?? 0),
            'conflict' => (int) ($row['conflict'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'last_sync' => $row['last_sync'] ?? null,
            'migration_required' => false,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function enqueueBatch(array $items, array $context = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'accepted' => 0,
                'duplicate' => 0,
                'conflict' => 0,
                'rejected' => count($items),
                'accepted_keys' => [],
                'duplicate_keys' => [],
                'conflict_keys' => [],
                'rejected_keys' => $this->collectKeys($items),
                'errors' => ['migration_required' => true],
            ];
        }

        $companyId = $this->resolveCompanyId((int) ($context['company_id'] ?? 0));
        if ($companyId < 1) {
            return [
                'accepted' => 0,
                'duplicate' => 0,
                'conflict' => 0,
                'rejected' => count($items),
                'accepted_keys' => [],
                'duplicate_keys' => [],
                'conflict_keys' => [],
                'rejected_keys' => $this->collectKeys($items),
                'errors' => ['company_required' => true],
            ];
        }

        $branchId = (int) ($context['branch_id'] ?? 0);
        $deviceId = substr(trim((string) ($context['device_id'] ?? '')), 0, 64);
        $userId = (int) ($context['user_id'] ?? 0);

        $accepted = 0;
        $duplicate = 0;
        $conflict = 0;
        $rejected = 0;
        /** @var list<string> $acceptedKeys */
        $acceptedKeys = [];
        /** @var list<string> $duplicateKeys */
        $duplicateKeys = [];
        /** @var list<string> $conflictKeys */
        $conflictKeys = [];
        /** @var list<string> $rejectedKeys */
        $rejectedKeys = [];
        /** @var array<int, array<string, mixed>> $conflictRows */
        $conflictRows = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                $rejected++;
                continue;
            }

            $idempotencyKey = $this->resolveIdempotencyKey($item);
            if ($idempotencyKey === '') {
                $rejected++;
                continue;
            }

            $existing = $this->findByIdempotency($companyId, $idempotencyKey);
            if ($existing !== null) {
                $outcome = $this->handleDuplicate($existing, $item, $companyId);
                if ($outcome === 'duplicate') {
                    $duplicate++;
                    $duplicateKeys[] = $idempotencyKey;
                } elseif ($outcome === 'conflict') {
                    $conflict++;
                    $conflictKeys[] = $idempotencyKey;
                    $conflictRows[] = [
                        'client_id' => $idempotencyKey,
                        'queue_id' => (int) ($existing['id'] ?? 0),
                        'reason' => 'server_newer',
                    ];
                } else {
                    $accepted++;
                    $acceptedKeys[] = $idempotencyKey;
                }
                continue;
            }

            $module = substr(trim((string) ($item['module'] ?? 'offline_meta')), 0, 32);
            $action = substr(trim((string) ($item['action'] ?? 'offline.ack')), 0, 64);
            if ($module === '') {
                $module = 'offline_meta';
            }
            if ($action === '') {
                $action = 'offline.ack';
            }

            if (in_array($module, ['hr', 'procurement'], true)) {
                $rejected++;
                $rejectedKeys[] = $idempotencyKey;
                continue;
            }

            if ($module === 'inventory') {
                if (!$this->flags()->enabled('offline.inventory.movements')) {
                    $rejected++;
                    $rejectedKeys[] = $idempotencyKey;
                    continue;
                }
                $normalizedAction = $this->normalizeInventoryAction($action);
                if ($normalizedAction === '') {
                    $rejected++;
                    $rejectedKeys[] = $idempotencyKey;
                    continue;
                }
                $action = $normalizedAction;
            }

            $this->model()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId > 0 ? $branchId : null,
                'device_id' => $deviceId !== '' ? $deviceId : null,
                'user_id' => $userId > 0 ? $userId : null,
                'module' => $module,
                'action' => $action,
                'idempotency_key' => $idempotencyKey,
                'payload' => json_encode($this->sanitizer()->normalize($item), JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'version' => max(1, (int) ($item['version'] ?? 1)),
                'retry_count' => 0,
            ]);
            $accepted++;
            $acceptedKeys[] = $idempotencyKey;
        }

        return [
            'accepted' => $accepted,
            'duplicate' => $duplicate,
            'conflict' => $conflict,
            'rejected' => $rejected,
            'accepted_keys' => $acceptedKeys,
            'duplicate_keys' => $duplicateKeys,
            'conflict_keys' => $conflictKeys,
            'rejected_keys' => $rejectedKeys,
            'conflicts' => $conflictRows,
        ];
    }

    /** @return array<string, int> */
    public function processPending(?int $companyId = null, int $limit = 50): array
    {
        if (!$this->isAvailable()) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        }

        $policy = OfflineModule::syncPolicy();
        $maxRetry = (int) ($policy['max_retries'] ?? 5);
        $safeLimit = max(1, min(50, $limit));

        $rows = $this->model()->query(
            'SELECT * FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND status IN (:pending, :failed) AND retry_count < :max_retry
             ORDER BY created_at ASC, id ASC
             LIMIT ' . $safeLimit,
            [
                'cid' => $companyId,
                'pending' => 'pending',
                'failed' => 'failed',
                'max_retry' => $maxRetry,
            ]
        );

        $stats = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $stats['processed']++;
            $action = (string) ($row['action'] ?? '');
            $module = (string) ($row['module'] ?? '');
            $queueId = (int) ($row['id'] ?? 0);
            $retryCount = (int) ($row['retry_count'] ?? 0);

            if (in_array($action, self::ACK_ACTIONS, true) || $module === 'offline_meta') {
                $this->markSynced($queueId);
                $stats['synced']++;
                continue;
            }

            if ($module === 'inventory') {
                if (!$this->flags()->enabled('offline.inventory.movements')) {
                    $stats['skipped']++;
                    continue;
                }
                $outcome = $this->replay()->replay($row);
                $status = (string) ($outcome['status'] ?? 'skipped');
                if ($status === 'synced') {
                    $this->markSynced($queueId);
                    $stats['synced']++;
                } elseif ($status === 'conflict') {
                    $this->markConflict($queueId, $row, $outcome);
                    $stats['conflicts']++;
                } elseif ($status === 'failed') {
                    $this->markFailed($queueId, $retryCount, (string) ($outcome['error'] ?? 'replay_failed'));
                    $stats['failed']++;
                } else {
                    $stats['skipped']++;
                }
                continue;
            }

            $stats['skipped']++;
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $outcome
     */
    private function markConflict(int $queueId, array $row, array $outcome): void
    {
        if ($queueId < 1) {
            return;
        }
        $companyId = (int) ($row['company_id'] ?? 0);
        $this->model()->update($queueId, [
            'status' => 'conflict',
            'last_error' => substr((string) ($outcome['error'] ?? $outcome['reason'] ?? 'conflict'), 0, 255),
        ]);
        $payload = $row['payload'] ?? null;
        $client = is_string($payload) ? (json_decode($payload, true) ?: []) : (is_array($payload) ? $payload : []);
        $this->conflicts()->record(
            $companyId,
            $queueId,
            (string) ($row['idempotency_key'] ?? ''),
            (string) ($outcome['reason'] ?? $outcome['error'] ?? 'conflict'),
            is_array($client) ? $client : [],
            ['error' => $outcome['error'] ?? null]
        );
    }

    private function markFailed(int $queueId, int $retryCount, string $error): void
    {
        if ($queueId < 1) {
            return;
        }
        $this->model()->update($queueId, [
            'status' => 'failed',
            'retry_count' => $retryCount + 1,
            'last_error' => substr($error, 0, 255),
        ]);
    }

    private function normalizeInventoryAction(string $action): string
    {
        $action = trim($action);
        $allowed = InventoryOfflineReplayService::deferredActions();
        if (in_array($action, $allowed, true)) {
            return $action;
        }
        $aliases = [
            'create_stock_movement' => 'stock_movement.create',
            'create_stock_count' => 'stock_count.create',
            'create_warehouse_transfer' => 'warehouse_transfer.create',
            'approve_warehouse_transfer' => 'warehouse_transfer.approve',
        ];
        $mapped = $aliases[$action] ?? '';

        return in_array($mapped, $allowed, true) ? $mapped : '';
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }

    /** @param array<string, mixed> $item */
    private function resolveIdempotencyKey(array $item): string
    {
        foreach (['client_id', 'idempotency_key'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                return substr($value, 0, 64);
            }
        }

        return '';
    }

    /** @return array<string, mixed>|null */
    private function findByIdempotency(int $companyId, string $key): ?array
    {
        return $this->model()->queryOne(
            'SELECT * FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND idempotency_key = :k LIMIT 1',
            ['cid' => $companyId, 'k' => $key]
        );
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $item
     */
    private function handleDuplicate(array $existing, array $item, int $companyId): string
    {
        $decision = $this->resolver()->resolve($item, [
            'version' => (int) ($existing['version'] ?? 1),
        ]);
        if (($decision['action'] ?? '') === 'reject_client') {
            $queueId = (int) ($existing['id'] ?? 0);
            $this->model()->update($queueId, ['status' => 'conflict']);
            $this->conflicts()->record(
                $companyId,
                $queueId,
                (string) ($existing['idempotency_key'] ?? ''),
                (string) ($decision['reason'] ?? 'server_newer'),
                $item,
                ['version' => (int) ($existing['version'] ?? 1), 'status' => $existing['status'] ?? null]
            );

            return 'conflict';
        }

        return 'duplicate';
    }

    /**
     * @param array<int, mixed> $items
     * @return list<string>
     */
    private function collectKeys(array $items): array
    {
        $keys = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $this->resolveIdempotencyKey($item);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function markSynced(int $queueId): void
    {
        if ($queueId < 1) {
            return;
        }
        $this->model()->update($queueId, [
            'status' => 'synced',
            'synced_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);
    }
}

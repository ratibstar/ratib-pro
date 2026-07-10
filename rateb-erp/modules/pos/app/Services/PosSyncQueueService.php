<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosSyncQueueItem;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;

/** Server-side offline sync queue backed by rateb_pos_sync_queue. */
final class PosSyncQueueService
{
    public function __construct(
        private PosSyncQueueItem $model = new PosSyncQueueItem(),
        private PosOfflineConflictResolverService $conflicts = new PosOfflineConflictResolverService(),
        private PosSyncConflictService $conflictStore = new PosSyncConflictService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    public function isAvailable(): bool
    {
        return Database::liveTableHasColumn('rateb_pos_sync_queue', 'id');
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

        $row = $this->model->queryOne(
            'SELECT
                SUM(CASE WHEN status = :pending THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = :synced THEN 1 ELSE 0 END) AS synced,
                SUM(CASE WHEN status = :conflict THEN 1 ELSE 0 END) AS conflict,
                SUM(CASE WHEN status = :failed THEN 1 ELSE 0 END) AS failed,
                MAX(synced_at) AS last_sync
             FROM rateb_pos_sync_queue
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
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listRecent(int $limit = 50, ?int $companyId = null): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return [];
        }

        $safeLimit = max(1, min(100, $limit));
        $rows = $this->model->query(
            'SELECT id, branch_id, terminal_id, idempotency_key, status, version, retry_count,
                    last_error, created_at, synced_at
             FROM rateb_pos_sync_queue
             WHERE company_id = :cid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $safeLimit,
            ['cid' => $companyId]
        );

        return array_map([$this, 'formatListRow'], $rows);
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
                'rejected_keys' => [],
                'errors' => ['migration_required' => true],
                'clearable_keys' => [],
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
                'rejected_keys' => [],
                'errors' => ['company_required' => true],
                'clearable_keys' => [],
            ];
        }

        $terminalId = (int) ($context['terminal_id'] ?? 0);
        $branchId = (int) ($context['branch_id'] ?? 0);
        if ($branchId < 1 && function_exists('rateb_resolve_create_branch_id')) {
            $branchId = rateb_resolve_create_branch_id();
        }

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
        /** @var array<int, array<string, mixed>> $conflicts */
        $conflicts = [];

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
                $outcome = $this->handleDuplicate($existing, $item);
                if ($outcome === 'duplicate') {
                    $duplicate++;
                    $duplicateKeys[] = $idempotencyKey;
                } elseif ($outcome === 'conflict') {
                    $conflict++;
                    $conflictKeys[] = $idempotencyKey;
                    $conflicts[] = [
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

            $queueId = $this->model->create([
                'company_id' => $companyId,
                'branch_id' => $branchId > 0 ? $branchId : null,
                'terminal_id' => $terminalId > 0 ? $terminalId : null,
                'idempotency_key' => $idempotencyKey,
                'payload' => json_encode($this->normalizePayload($item), JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'version' => max(1, (int) ($item['version'] ?? 1)),
            ]);

            $processed = $this->attemptProcess($queueId, $item, null);
            if ($processed['status'] === 'synced') {
                $accepted++;
                $acceptedKeys[] = $idempotencyKey;
            } elseif ($processed['status'] === 'conflict') {
                $conflict++;
                $conflictKeys[] = $idempotencyKey;
                $conflicts[] = [
                    'client_id' => $idempotencyKey,
                    'queue_id' => $queueId,
                    'reason' => (string) ($processed['reason'] ?? 'conflict'),
                ];
            } else {
                $accepted++;
                $acceptedKeys[] = $idempotencyKey;
            }
        }

        if ($accepted > 0) {
            $this->audit->log('pos.sync.batch', 'pos_sync_queue', null, [
                'accepted' => $accepted,
                'duplicate' => $duplicate,
                'conflict' => $conflict,
                'terminal_id' => $terminalId > 0 ? $terminalId : null,
            ]);
        }

        $result = [
            'accepted' => $accepted,
            'duplicate' => $duplicate,
            'conflict' => $conflict,
            'rejected' => $rejected,
            'accepted_keys' => $acceptedKeys,
            'duplicate_keys' => $duplicateKeys,
            'conflict_keys' => $conflictKeys,
            'rejected_keys' => $rejectedKeys,
            'conflicts' => $conflicts,
        ];
        $ack = (new PosPushAckContract())->evaluate($result);
        $result['clearable_keys'] = $ack['clearable_keys'];
        $result['ack_ok'] = $ack['ok'];
        $result['http_status'] = $ack['http_status'];

        return $result;
    }

    /** @param array<string, mixed> $row */
    private function formatListRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'branch_id' => isset($row['branch_id']) ? (int) $row['branch_id'] : null,
            'terminal_id' => isset($row['terminal_id']) ? (int) $row['terminal_id'] : null,
            'idempotency_key' => (string) ($row['idempotency_key'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'version' => (int) ($row['version'] ?? 1),
            'retry_count' => (int) ($row['retry_count'] ?? 0),
            'last_error' => $row['last_error'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'synced_at' => $row['synced_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $item */
    private function resolveIdempotencyKey(array $item): string
    {
        foreach (['client_id', 'idempotency_key'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                return mb_substr($value, 0, 64);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function normalizePayload(array $item): array
    {
        return [
            'action' => (string) ($item['action'] ?? 'unknown'),
            'payload' => is_array($item['payload'] ?? null) ? $item['payload'] : [],
            'occurred_at' => $item['occurred_at'] ?? null,
            'version' => max(1, (int) ($item['version'] ?? 1)),
        ];
    }

    /** @return array<string, mixed>|null */
    private function findByIdempotency(int $companyId, string $key): ?array
    {
        return $this->model->queryOne(
            'SELECT * FROM rateb_pos_sync_queue WHERE company_id = :cid AND idempotency_key = :key LIMIT 1',
            ['cid' => $companyId, 'key' => $key]
        );
    }

    /** @param array<string, mixed> $existing @param array<string, mixed> $item */
    private function handleDuplicate(array $existing, array $item): string
    {
        $resolution = $this->conflicts->resolve(
            ['version' => (int) ($item['version'] ?? 1), 'payload' => $item],
            ['version' => (int) ($existing['version'] ?? 1), 'payload' => $this->decodePayload($existing)]
        );

        if (($resolution['action'] ?? '') === 'reject_client') {
            if (($existing['status'] ?? '') !== 'conflict') {
                $this->model->update((int) $existing['id'], [
                    'status' => 'conflict',
                    'last_error' => (string) ($resolution['reason'] ?? 'server_newer'),
                ]);
                $this->recordConflictRow(
                    $existing,
                    $item,
                    (string) ($resolution['reason'] ?? 'server_newer')
                );
            }

            return 'conflict';
        }

        if (($existing['status'] ?? '') === 'synced') {
            return 'duplicate';
        }

        $this->attemptProcess((int) $existing['id'], $item, $existing);

        return 'duplicate';
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed>|null $existing
     * @return array{status: string, reason?: string}
     */
    private function attemptProcess(int $queueId, array $item, ?array $existing): array
    {
        if ($existing !== null) {
            $resolution = $this->conflicts->resolve(
                ['version' => (int) ($item['version'] ?? 1), 'payload' => $item],
                ['version' => (int) ($existing['version'] ?? 1), 'payload' => $this->decodePayload($existing)]
            );
            if (($resolution['action'] ?? '') === 'reject_client') {
                $this->model->update($queueId, [
                    'status' => 'conflict',
                    'last_error' => (string) ($resolution['reason'] ?? 'server_newer'),
                ]);
                if ($existing !== null) {
                    $this->recordConflictRow(
                        $existing,
                        $item,
                        (string) ($resolution['reason'] ?? 'server_newer')
                    );
                }

                return ['status' => 'conflict', 'reason' => (string) ($resolution['reason'] ?? 'server_newer')];
            }
        }

        $action = (string) ($item['action'] ?? 'unknown');
        // Defer all domain actions to PosSyncBatchProcessorService → PosOfflineReplayService.
        if (in_array($action, PosOfflineReplayService::deferredActions(), true)) {
            return ['status' => 'pending'];
        }

        $this->model->update($queueId, [
            'status' => 'synced',
            'synced_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);

        return ['status' => 'synced'];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function decodePayload(array $row): array
    {
        $raw = $row['payload'] ?? '';
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }

    /** @param array<string, mixed> $queueRow @param array<string, mixed> $clientItem */
    private function recordConflictRow(array $queueRow, array $clientItem, string $reason): void
    {
        $companyId = (int) ($queueRow['company_id'] ?? 0);
        $queueId = (int) ($queueRow['id'] ?? 0);
        if ($companyId < 1 || $queueId < 1) {
            return;
        }

        $this->conflictStore->record(
            $companyId,
            $queueId,
            (string) ($queueRow['idempotency_key'] ?? ''),
            $reason,
            $this->normalizePayload($clientItem),
            $this->decodePayload($queueRow)
        );
    }
}

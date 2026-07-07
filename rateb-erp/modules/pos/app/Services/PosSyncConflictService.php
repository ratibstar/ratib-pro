<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosSyncConflict;
use Rateb\App\Pos\Models\PosSyncQueueItem;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;

final class PosSyncConflictService
{
    public function __construct(
        private PosSyncConflict $model = new PosSyncConflict(),
        private PosSyncQueueItem $queue = new PosSyncQueueItem(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    public function isAvailable(): bool
    {
        return Database::liveTableHasColumn('rateb_pos_sync_conflicts', 'id');
    }

    /** @param array<string, mixed> $clientPayload @param array<string, mixed>|null $serverPayload */
    public function record(
        int $companyId,
        int $queueId,
        string $idempotencyKey,
        string $reason,
        array $clientPayload,
        ?array $serverPayload = null,
    ): int {
        if (!$this->isAvailable() || $companyId < 1 || $queueId < 1) {
            return 0;
        }

        $existing = $this->model->queryOne(
            'SELECT id FROM rateb_pos_sync_conflicts
             WHERE company_id = :cid AND queue_id = :qid AND status = :st LIMIT 1',
            ['cid' => $companyId, 'qid' => $queueId, 'st' => 'open']
        );
        if ($existing !== null) {
            return (int) ($existing['id'] ?? 0);
        }

        return $this->model->create([
            'company_id' => $companyId,
            'queue_id' => $queueId,
            'idempotency_key' => mb_substr($idempotencyKey, 0, 64),
            'reason' => mb_substr($reason, 0, 64),
            'client_payload' => json_encode($clientPayload, JSON_UNESCAPED_UNICODE),
            'server_payload' => $serverPayload !== null
                ? json_encode($serverPayload, JSON_UNESCAPED_UNICODE)
                : null,
            'status' => 'open',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listOpen(int $limit = 50, ?int $companyId = null): array
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
            'SELECT c.*, q.terminal_id, q.status AS queue_status
             FROM rateb_pos_sync_conflicts c
             INNER JOIN rateb_pos_sync_queue q ON q.id = c.queue_id
             WHERE c.company_id = :cid AND c.status = :st
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT ' . $safeLimit,
            ['cid' => $companyId, 'st' => 'open']
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'queue_id' => (int) ($row['queue_id'] ?? 0),
                'terminal_id' => isset($row['terminal_id']) ? (int) $row['terminal_id'] : null,
                'idempotency_key' => (string) ($row['idempotency_key'] ?? ''),
                'reason' => (string) ($row['reason'] ?? ''),
                'queue_status' => (string) ($row['queue_status'] ?? ''),
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(int $conflictId, string $resolution, int $userId, ?int $companyId = null): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'migration_required'];
        }

        $companyId = $this->resolveCompanyId($companyId);
        $conflict = $this->model->find($conflictId);
        if ($conflict === null || (int) ($conflict['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (($conflict['status'] ?? '') !== 'open') {
            return ['ok' => false, 'error' => 'already_resolved'];
        }

        $queueId = (int) ($conflict['queue_id'] ?? 0);
        $queueRow = $this->queue->find($queueId);
        if ($queueRow === null) {
            return ['ok' => false, 'error' => 'queue_missing'];
        }

        $status = match ($resolution) {
            'accept_server' => 'resolved_server',
            'accept_client', 'merge' => 'resolved_client',
            default => null,
        };
        if ($status === null) {
            return ['ok' => false, 'error' => 'invalid_resolution'];
        }

        $now = date('Y-m-d H:i:s');
        $this->model->update($conflictId, [
            'status' => $status,
            'resolved_by' => $userId > 0 ? $userId : null,
            'resolved_at' => $now,
        ]);

        if ($resolution === 'accept_server') {
            $this->queue->update($queueId, [
                'status' => 'synced',
                'synced_at' => $now,
                'last_error' => null,
            ]);
        } else {
            $clientPayload = $this->decodeJson($conflict['client_payload'] ?? null);
            $this->queue->update($queueId, [
                'status' => 'pending',
                'payload' => json_encode($clientPayload, JSON_UNESCAPED_UNICODE),
                'version' => max(1, (int) ($clientPayload['version'] ?? ($queueRow['version'] ?? 1))),
                'last_error' => null,
                'retry_count' => 0,
            ]);
        }

        $this->audit->log('pos.sync.conflict_resolved', 'pos_sync_conflict', $conflictId, [
            'resolution' => $resolution,
            'queue_id' => $queueId,
        ]);

        $processResult = null;
        if ($resolution === 'accept_client' || $resolution === 'merge') {
            $processResult = (new PosSyncBatchProcessorService())->processPending($companyId, 1);
        }

        return [
            'ok' => true,
            'conflict_id' => $conflictId,
            'resolution' => $resolution,
            'process' => $processResult,
        ];
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $raw): array
    {
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
}

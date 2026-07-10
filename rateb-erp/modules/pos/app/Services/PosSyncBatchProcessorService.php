<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosSyncQueueItem;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;

/** Processes pending rows in rateb_pos_sync_queue — Phase 2B POS offline completion. */
final class PosSyncBatchProcessorService
{
    private const MAX_RETRIES = 5;

    public function __construct(
        private PosSyncQueueItem $model = new PosSyncQueueItem(),
        private PosSyncQueueService $queue = new PosSyncQueueService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
        private PosOfflineReplayService $replay = new PosOfflineReplayService(),
        private PosSyncConflictService $conflicts = new PosSyncConflictService(),
    ) {
    }

    /** @return array<string, int> */
    public function processPending(?int $companyId = null, int $limit = 50): array
    {
        if (!$this->queue->isAvailable()) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        }

        $safeLimit = max(1, min(50, $limit));
        $rows = $this->model->query(
            'SELECT * FROM rateb_pos_sync_queue
             WHERE company_id = :cid AND status IN (:pending, :failed) AND retry_count < :max_retry
             ORDER BY created_at ASC, id ASC
             LIMIT ' . $safeLimit,
            [
                'cid' => $companyId,
                'pending' => 'pending',
                'failed' => 'failed',
                'max_retry' => self::MAX_RETRIES,
            ]
        );

        $stats = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $result = $this->processRow($row);
            $stats['processed']++;
            $status = (string) ($result['status'] ?? 'skipped');
            if (isset($stats[$status])) {
                $stats[$status]++;
            } else {
                $stats['skipped']++;
            }
        }

        if ($stats['synced'] > 0) {
            $this->audit->log('pos.sync.batch_processed', 'pos_sync_queue', null, $stats);
        }

        return $stats;
    }

    /**
     * Process a single decoded queue item (for unit/integration tests).
     *
     * @param array<string, mixed> $row
     * @return array{status: string, error?: string, result?: array<string, mixed>}
     */
    public function processRow(array $row): array
    {
        $queueId = (int) ($row['id'] ?? 0);
        $decoded = $this->decodePayload($row);
        $action = (string) ($decoded['action'] ?? 'unknown');
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        $scope = $this->buildScope($row, $inner);
        $retryCount = (int) ($row['retry_count'] ?? 0);

        if (!in_array($action, PosOfflineReplayService::deferredActions(), true)) {
            if ($queueId > 0) {
                $this->markSynced($queueId);
            }

            return ['status' => 'synced'];
        }

        if ($scope['company_id'] < 1 || $scope['user_id'] < 1) {
            return $this->markFailed($queueId, $retryCount, 'missing_register_scope');
        }

        // Branch required for sale/ops; shift_open derives branch from terminal.
        if (!in_array($action, ['shift_open'], true) && $scope['branch_id'] < 1) {
            return $this->markFailed($queueId, $retryCount, 'missing_register_scope');
        }

        try {
            TenantContext::setCompanyId($scope['company_id']);
            $result = $this->replay->replay($action, $scope, $inner);
            if ($queueId > 0) {
                $this->markSynced($queueId);
            }

            return ['status' => 'synced', 'result' => is_array($result) ? $result : []];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($this->isConflictError($action, $message)) {
                return $this->markConflict($queueId, $row, $decoded, $message);
            }

            return $this->markFailed($queueId, $retryCount, $message);
        }
    }

    private function isConflictError(string $action, string $message): bool
    {
        $needle = strtolower($message);
        if ($action === 'shift_open' && (
            str_contains($needle, 'already_open')
            || str_contains($needle, 'shift_already')
            || str_contains($needle, 'pos_shift_already_open')
        )) {
            return true;
        }
        if (str_contains($needle, 'server_newer') || str_contains($needle, 'conflict')) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $decoded
     * @return array{status: string, error?: string}
     */
    private function markConflict(int $queueId, array $row, array $decoded, string $reason): array
    {
        if ($queueId > 0) {
            $this->model->update($queueId, [
                'status' => 'conflict',
                'last_error' => substr($reason, 0, 2000),
            ]);
            $this->conflicts->record(
                (int) ($row['company_id'] ?? 0),
                $queueId,
                (string) ($row['idempotency_key'] ?? ''),
                substr($reason, 0, 64) ?: 'conflict',
                $decoded,
                null
            );
        }

        return ['status' => 'conflicts', 'error' => $reason];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function buildScope(array $row, array $inner): array
    {
        $scopeMeta = is_array($inner['scope'] ?? null) ? $inner['scope'] : [];

        return [
            'company_id' => (int) ($row['company_id'] ?? 0),
            'branch_id' => (int) ($row['branch_id'] ?? $scopeMeta['branch_id'] ?? 0),
            'terminal_id' => (int) ($row['terminal_id'] ?? $scopeMeta['terminal_id'] ?? 0) ?: null,
            'shift_id' => (int) ($scopeMeta['shift_id'] ?? $inner['shift_id'] ?? 0) ?: null,
            'warehouse_id' => isset($scopeMeta['warehouse_id']) ? (int) $scopeMeta['warehouse_id'] : null,
            'session_id' => isset($scopeMeta['session_id']) ? (int) $scopeMeta['session_id'] : null,
            'user_id' => (int) ($scopeMeta['user_id'] ?? $inner['user_id'] ?? 0),
            'idempotency_key' => (string) ($row['idempotency_key'] ?? ''),
            'coupon_code' => trim((string) ($inner['coupon_code'] ?? '')),
            'points_redeem' => (float) ($inner['points_redeem'] ?? 0),
            'gift_receipt' => !empty($inner['gift_receipt']),
        ];
    }

    /** @return array{status: string, error?: string} */
    private function markFailed(int $queueId, int $retryCount, string $message): array
    {
        if ($queueId < 1) {
            return ['status' => 'failed', 'error' => $message];
        }
        $nextRetry = $retryCount + 1;
        $this->model->update($queueId, [
            'status' => $nextRetry >= self::MAX_RETRIES ? 'failed' : 'pending',
            'retry_count' => $nextRetry,
            'last_error' => substr($message, 0, 2000),
        ]);

        return ['status' => 'failed', 'error' => $message];
    }

    private function markSynced(int $queueId): void
    {
        $this->model->update($queueId, [
            'status' => 'synced',
            'synced_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);
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
}

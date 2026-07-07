<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Contracts\PosOfflineSyncTransportInterface;
use Rateb\App\Pos\Services\Drivers\NullPosOfflineSyncTransport;

final class PosOfflineSyncService
{
    public function __construct(
        private PosSyncQueueService $queue = new PosSyncQueueService(),
        private PosSyncBatchProcessorService $processor = new PosSyncBatchProcessorService(),
        private PosSyncConflictService $conflicts = new PosSyncConflictService(),
        private PosOfflineSyncTransportInterface $transport = new NullPosOfflineSyncTransport(),
    ) {
    }

    /** @return array<string, mixed> */
    public function status(?int $companyId = null): array
    {
        if (!$this->queue->isAvailable()) {
            return [
                'queue_depth' => 0,
                'last_sync' => null,
                'online' => true,
                'scaffold' => true,
                'migration_required' => true,
            ];
        }

        $summary = $this->queue->statusSummary($companyId);

        return [
            'queue_depth' => $summary['pending'] + $summary['failed'],
            'pending' => $summary['pending'],
            'synced' => $summary['synced'],
            'conflict' => $summary['conflict'],
            'failed' => $summary['failed'],
            'last_sync' => $summary['last_sync'],
            'online' => true,
            'scaffold' => false,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recentQueue(int $limit = 50, ?int $companyId = null): array
    {
        return $this->queue->listRecent($limit, $companyId);
    }

    /** @return array<int, array<string, mixed>> */
    public function openConflicts(int $limit = 50, ?int $companyId = null): array
    {
        return $this->conflicts->listOpen($limit, $companyId);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function pushQueue(array $items, array $context = []): array
    {
        if (!$this->queue->isAvailable()) {
            return $this->transport->push($items);
        }

        $result = $this->queue->enqueueBatch($items, $context);
        if (($result['accepted'] ?? 0) > 0) {
            $companyId = (int) ($context['company_id'] ?? 0);
            $result['process'] = $this->processor->processPending($companyId > 0 ? $companyId : null, 50);
        }

        return $result;
    }

    /** @return array<string, int> */
    public function processPending(?int $companyId = null, int $limit = 50): array
    {
        return $this->processor->processPending($companyId, $limit);
    }

    /** @return array<string, mixed> */
    public function resolveConflict(int $conflictId, string $resolution, int $userId, ?int $companyId = null): array
    {
        return $this->conflicts->resolve($conflictId, $resolution, $userId, $companyId);
    }
}

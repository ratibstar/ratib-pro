<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Background sync worker façade (Phase 2A).
 * Processes acknowledge-only queue items; no business module replay.
 */
final class OfflineBackgroundSync
{
    private ?OfflineQueueService $queue = null;
    private ?OfflineFeatureFlagService $flags = null;

    private function queue(): OfflineQueueService
    {
        return $this->queue ??= new OfflineQueueService();
    }

    private function flags(): OfflineFeatureFlagService
    {
        return $this->flags ??= new OfflineFeatureFlagService();
    }

    /** @return array<string, mixed> */
    public function process(?int $companyId = null, int $limit = 50): array
    {
        if (!$this->flags()->isMasterEnabled()) {
            return [
                'processed' => 0,
                'synced' => 0,
                'failed' => 0,
                'conflicts' => 0,
                'skipped' => 0,
                'disabled' => true,
            ];
        }

        return $this->queue()->processPending($companyId, $limit);
    }
}

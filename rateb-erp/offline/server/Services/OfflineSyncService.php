<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\Contracts\SyncTransportInterface;
use Rateb\App\Offline\Services\Drivers\NullOfflineSyncTransport;

/** High-level offline sync façade (Phase 2A). */
final class OfflineSyncService
{
    private ?OfflineQueueService $queue = null;
    private ?OfflineConflictService $conflicts = null;
    private ?OfflineBackgroundSync $background = null;
    private ?OfflineCursorService $cursors = null;
    private ?OfflineFeatureFlagService $flags = null;
    private ?SyncTransportInterface $transport = null;

    private function queue(): OfflineQueueService
    {
        return $this->queue ??= new OfflineQueueService();
    }

    private function conflicts(): OfflineConflictService
    {
        return $this->conflicts ??= new OfflineConflictService();
    }

    private function background(): OfflineBackgroundSync
    {
        return $this->background ??= new OfflineBackgroundSync();
    }

    private function cursors(): OfflineCursorService
    {
        return $this->cursors ??= new OfflineCursorService();
    }

    private function flags(): OfflineFeatureFlagService
    {
        return $this->flags ??= new OfflineFeatureFlagService();
    }

    private function transport(): SyncTransportInterface
    {
        return $this->transport ??= new NullOfflineSyncTransport();
    }

    /** @return array<string, mixed> */
    public function status(?int $companyId = null): array
    {
        $summary = $this->queue()->statusSummary($companyId);
        $summary['online'] = true;
        $summary['flags'] = $this->flags()->snapshot();
        $summary['queue_depth'] = (int) ($summary['pending'] ?? 0) + (int) ($summary['failed'] ?? 0);
        $summary['scaffold'] = !empty($summary['migration_required']);
        $summary['conflicts_migration_required'] = !$this->conflicts()->isAvailable();
        $summary['enabled'] = $this->flags()->isMasterEnabled();

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function pushQueue(array $items, array $context = []): array
    {
        if (!$this->flags()->isMasterEnabled()) {
            $rejectedKeys = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                foreach (['client_id', 'idempotency_key'] as $field) {
                    $value = trim((string) ($item[$field] ?? ''));
                    if ($value !== '') {
                        $rejectedKeys[] = substr($value, 0, 64);
                        break;
                    }
                }
            }

            return [
                'accepted' => 0,
                'duplicate' => 0,
                'conflict' => 0,
                'rejected' => count($items),
                'accepted_keys' => [],
                'duplicate_keys' => [],
                'conflict_keys' => [],
                'rejected_keys' => $rejectedKeys,
                'errors' => ['offline_disabled' => true],
            ];
        }

        if (!$this->queue()->isAvailable()) {
            return $this->transport()->push($items);
        }

        $result = $this->queue()->enqueueBatch($items, $context);
        $ack = (new OfflinePushAckContract())->evaluate($result);
        $result['clearable_keys'] = $ack['clearable_keys'];
        $result['ack_ok'] = $ack['ok'];

        // H-AUTHZ-001: enqueue ≠ company-wide replay. Auto-process only when caller opted in
        // with Sync Manage (controller sets context.auto_process after canManageSync()).
        $autoProcess = !empty($context['auto_process']);
        if ($autoProcess && ($result['accepted'] ?? 0) > 0) {
            $companyId = (int) ($context['company_id'] ?? 0);
            $result['process'] = $this->background()->process($companyId > 0 ? $companyId : null, 50);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function processPending(?int $companyId = null, int $limit = 50): array
    {
        return $this->background()->recoverAndProcess($companyId, $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function openConflicts(int $limit = 50, ?int $companyId = null): array
    {
        return $this->conflicts()->listOpen($limit, $companyId);
    }

    /** @return array<string, mixed> */
    public function resolveConflict(int $conflictId, string $resolution, int $userId, ?int $companyId = null): array
    {
        if (!$this->flags()->isMasterEnabled()) {
            return ['ok' => false, 'error' => 'offline_disabled'];
        }

        return $this->conflicts()->resolve($conflictId, $resolution, $userId, $companyId);
    }

    /** @return array<string, mixed> */
    public function delta(string $entity, ?int $companyId = null, ?int $branchId = null): array
    {
        $cursorToken = isset($_GET['cursor']) ? trim((string) $_GET['cursor']) : null;

        return $this->cursors()->getCursor($entity, $companyId, $branchId, $cursorToken !== '' ? $cursorToken : null);
    }
}

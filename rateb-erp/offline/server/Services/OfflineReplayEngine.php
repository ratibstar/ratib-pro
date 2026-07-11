<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\Contracts\OfflineReplayPort;

/**
 * Offline replay engine — acknowledge-only + Inventory Tier-1 when flagged.
 * Does not invoke HR/Procurement/ERP shell business services.
 */
final class OfflineReplayEngine implements OfflineReplayPort
{
    private ?OfflineFeatureFlagService $flags = null;
    private ?InventoryOfflineReplayService $inventory = null;

    private function flags(): OfflineFeatureFlagService
    {
        return $this->flags ??= new OfflineFeatureFlagService();
    }

    private function inventory(): InventoryOfflineReplayService
    {
        return $this->inventory ??= new InventoryOfflineReplayService();
    }

    /** @param array<string, mixed> $queueRow */
    public function replay(array $queueRow): array
    {
        $module = (string) ($queueRow['module'] ?? '');
        $action = (string) ($queueRow['action'] ?? '');

        if (in_array($action, ['offline.ack', 'offline.ping', 'ack', 'ping'], true)
            || $module === 'offline_meta') {
            return ['status' => 'synced'];
        }

        if ($module === 'inventory' || str_starts_with($action, 'inventory.')
            || in_array($action, InventoryOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.inventory.movements')) {
                return ['status' => 'skipped', 'error' => 'inventory_offline_disabled'];
            }

            return $this->inventory()->replayFromQueueRow($queueRow);
        }

        return ['status' => 'skipped', 'error' => 'replay_not_implemented'];
    }
}

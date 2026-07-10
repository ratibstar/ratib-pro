<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\Contracts\OfflineReplayPort;

/**
 * Phase 2A replay engine — acknowledge-only.
 * Does not invoke Inventory/HR/Procurement/ERP business services.
 */
final class OfflineReplayEngine implements OfflineReplayPort
{
    /** @param array<string, mixed> $queueRow */
    public function replay(array $queueRow): array
    {
        $module = (string) ($queueRow['module'] ?? '');
        $action = (string) ($queueRow['action'] ?? '');

        if (in_array($action, ['offline.ack', 'offline.ping', 'ack', 'ping'], true)
            || $module === 'offline_meta') {
            return ['status' => 'synced'];
        }

        return ['status' => 'skipped', 'error' => 'replay_not_implemented_phase_2a'];
    }
}

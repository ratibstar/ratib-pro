<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\Contracts\OfflineReplayPort;

/**
 * Offline replay engine — ack + Inventory + HR + Procurement + Recruitment + Accounting Tier-1 when flagged.
 * Does not invoke approvals, payroll, payments, or accounting posting.
 */
final class OfflineReplayEngine implements OfflineReplayPort
{
    private ?OfflineFeatureFlagService $flags = null;
    private ?InventoryOfflineReplayService $inventory = null;
    private ?HrOfflineReplayService $hr = null;
    private ?ProcurementOfflineReplayService $procurement = null;
    private ?RecruitmentOfflineReplayService $recruitment = null;
    private ?AccountingOfflineReplayService $accounting = null;

    private function flags(): OfflineFeatureFlagService
    {
        return $this->flags ??= new OfflineFeatureFlagService();
    }

    private function inventory(): InventoryOfflineReplayService
    {
        return $this->inventory ??= new InventoryOfflineReplayService();
    }

    private function hr(): HrOfflineReplayService
    {
        return $this->hr ??= new HrOfflineReplayService();
    }

    private function procurement(): ProcurementOfflineReplayService
    {
        return $this->procurement ??= new ProcurementOfflineReplayService();
    }

    private function recruitment(): RecruitmentOfflineReplayService
    {
        return $this->recruitment ??= new RecruitmentOfflineReplayService();
    }

    private function accounting(): AccountingOfflineReplayService
    {
        return $this->accounting ??= new AccountingOfflineReplayService();
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

        if ($module === 'hr' || str_starts_with($action, 'hr.')
            || in_array($action, HrOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.hr.attendance')) {
                return ['status' => 'skipped', 'error' => 'hr_offline_disabled'];
            }

            return $this->hr()->replayFromQueueRow($queueRow);
        }

        if ($module === 'procurement' || str_starts_with($action, 'procurement.')
            || in_array($action, ProcurementOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.procurement')) {
                return ['status' => 'skipped', 'error' => 'procurement_offline_disabled'];
            }

            return $this->procurement()->replayFromQueueRow($queueRow);
        }

        if ($module === 'recruitment' || str_starts_with($action, 'recruitment.')
            || in_array($action, RecruitmentOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.recruitment')) {
                return ['status' => 'skipped', 'error' => 'recruitment_offline_disabled'];
            }

            return $this->recruitment()->replayFromQueueRow($queueRow);
        }

        if ($module === 'accounting' || str_starts_with($action, 'accounting.')
            || in_array($action, AccountingOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.accounting')) {
                return ['status' => 'skipped', 'error' => 'accounting_offline_disabled'];
            }

            return $this->accounting()->replayFromQueueRow($queueRow);
        }

        return ['status' => 'skipped', 'error' => 'replay_not_implemented'];
    }
}

<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\Contracts\OfflineReplayPort;

/**
 * Offline replay engine — ack + Inventory + HR + Procurement + Recruitment + Accounting + CRM + Projects + Assets + Approval Tier-1 when flagged.
 * Does not invoke payroll, payments, or accounting posting. Approval decisions (final approve/reject) remain ONLINE ONLY.
 */
final class OfflineReplayEngine implements OfflineReplayPort
{
    private ?OfflineFeatureFlagService $flags = null;
    private ?InventoryOfflineReplayService $inventory = null;
    private ?HrOfflineReplayService $hr = null;
    private ?ProcurementOfflineReplayService $procurement = null;
    private ?RecruitmentOfflineReplayService $recruitment = null;
    private ?AccountingOfflineReplayService $accounting = null;
    private ?CrmOfflineReplayService $crm = null;
    private ?ProjectOfflineReplayService $projects = null;
    private ?AssetOfflineReplayService $assets = null;
    private ?ApprovalOfflineReplayService $approval = null;
    private ?ProcurementEnterpriseOfflineReplayService $procurementEnterprise = null;

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

    private function crm(): CrmOfflineReplayService
    {
        return $this->crm ??= new CrmOfflineReplayService();
    }

    private function projects(): ProjectOfflineReplayService
    {
        return $this->projects ??= new ProjectOfflineReplayService();
    }

    private function assets(): AssetOfflineReplayService
    {
        return $this->assets ??= new AssetOfflineReplayService();
    }

    private function approval(): ApprovalOfflineReplayService
    {
        return $this->approval ??= new ApprovalOfflineReplayService();
    }

    private function procurementEnterprise(): ProcurementEnterpriseOfflineReplayService
    {
        return $this->procurementEnterprise ??= new ProcurementEnterpriseOfflineReplayService();
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

        // Exclusive module dispatch — shared action names (workflow.transition, comment.create)
        // must not be stolen by earlier modules' deferredActions lists.
        if ($module === 'approval') {
            if (!$this->flags()->enabled('offline.approval')) {
                return ['status' => 'skipped', 'error' => 'approval_offline_disabled'];
            }

            return $this->approval()->replayFromQueueRow($queueRow);
        }

        if ($module === 'procurement_enterprise') {
            if (!$this->flags()->enabled('offline.procurement_enterprise')) {
                return ['status' => 'skipped', 'error' => 'procurement_enterprise_offline_disabled'];
            }

            return $this->procurementEnterprise()->replayFromQueueRow($queueRow);
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

        if ($module === 'crm' || str_starts_with($action, 'crm.')
            || in_array($action, CrmOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.crm')) {
                return ['status' => 'skipped', 'error' => 'crm_offline_disabled'];
            }

            return $this->crm()->replayFromQueueRow($queueRow);
        }

        if ($module === 'projects' || str_starts_with($action, 'projects.')
            || in_array($action, ProjectOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.projects')) {
                return ['status' => 'skipped', 'error' => 'projects_offline_disabled'];
            }

            return $this->projects()->replayFromQueueRow($queueRow);
        }

        if ($module === 'assets' || str_starts_with($action, 'assets.')
            || in_array($action, AssetOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.assets')) {
                return ['status' => 'skipped', 'error' => 'assets_offline_disabled'];
            }

            return $this->assets()->replayFromQueueRow($queueRow);
        }

        if ($module === 'approval' || str_starts_with($action, 'approval.')
            || in_array($action, ApprovalOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.approval')) {
                return ['status' => 'skipped', 'error' => 'approval_offline_disabled'];
            }

            return $this->approval()->replayFromQueueRow($queueRow);
        }

        if (str_starts_with($action, 'procurement_enterprise.')
            || in_array($action, ProcurementEnterpriseOfflineReplayService::deferredActions(), true)) {
            if (!$this->flags()->enabled('offline.procurement_enterprise')) {
                return ['status' => 'skipped', 'error' => 'procurement_enterprise_offline_disabled'];
            }

            return $this->procurementEnterprise()->replayFromQueueRow($queueRow);
        }

        return ['status' => 'skipped', 'error' => 'replay_not_implemented'];
    }
}

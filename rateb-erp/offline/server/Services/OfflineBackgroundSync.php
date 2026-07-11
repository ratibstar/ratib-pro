<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Background sync worker façade (Phase 5 + 15B + 16B).
 * Processes ack + Inventory + HR + Procurement + Recruitment + Accounting Tier-1 queue items when flags allow.
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
                'inventory_enabled' => false,
                'hr_enabled' => false,
                'procurement_enabled' => false,
                'recruitment_enabled' => false,
                'accounting_enabled' => false,
                'crm_enabled' => false,
                'projects_enabled' => false,
                'assets_enabled' => false,
                'approval_enabled' => false,
                'procurement_enterprise_enabled' => false,
                'manufacturing_enabled' => false,
                'hr_enterprise_enabled' => false,
                'payroll_enabled' => false,
                'quality_enabled' => false,
                'documents_enabled' => false,
                'bi_enabled' => false,
            ];
        }

        $stats = $this->queue()->processPending($companyId, $limit);
        $stats['inventory_enabled'] = $this->flags()->enabled('offline.inventory.movements');
        $stats['hr_enabled'] = $this->flags()->enabled('offline.hr.attendance');
        $stats['procurement_enabled'] = $this->flags()->enabled('offline.procurement');
        $stats['recruitment_enabled'] = $this->flags()->enabled('offline.recruitment');
        $stats['accounting_enabled'] = $this->flags()->enabled('offline.accounting');
        $stats['crm_enabled'] = $this->flags()->enabled('offline.crm');
        $stats['projects_enabled'] = $this->flags()->enabled('offline.projects');
        $stats['assets_enabled'] = $this->flags()->enabled('offline.assets');
        $stats['approval_enabled'] = $this->flags()->enabled('offline.approval');
        $stats['procurement_enterprise_enabled'] = $this->flags()->enabled('offline.procurement_enterprise');
        $stats['manufacturing_enabled'] = $this->flags()->enabled('offline.manufacturing');
        $stats['hr_enterprise_enabled'] = $this->flags()->enabled('offline.hr');
        $stats['payroll_enabled'] = $this->flags()->enabled('offline.payroll');
        $stats['quality_enabled'] = $this->flags()->enabled('offline.quality');
        $stats['documents_enabled'] = $this->flags()->enabled('offline.documents');
        $stats['bi_enabled'] = $this->flags()->enabled('offline.bi');

        return $stats;
    }
}

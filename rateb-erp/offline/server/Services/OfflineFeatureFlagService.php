<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\OfflineModule;

/** Resolves enterprise offline feature flags (default OFF). */
final class OfflineFeatureFlagService
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @return array<string, mixed> */
    private function config(): array
    {
        if (self::$config === null) {
            self::$config = OfflineModule::featureFlagsConfig();
        }

        return self::$config;
    }

    public function enabled(string $flag = 'offline.enabled'): bool
    {
        $cfg = $this->config();
        $defaults = is_array($cfg['defaults'] ?? null) ? $cfg['defaults'] : [];
        $envMap = is_array($cfg['env'] ?? null) ? $cfg['env'] : [];

        if (isset($envMap[$flag])) {
            $envName = (string) $envMap[$flag];
            $fromEnv = getenv($envName);
            if ($fromEnv !== false && $fromEnv !== '') {
                return $this->truthy($fromEnv);
            }
            if (isset($_ENV[$envName]) && (string) $_ENV[$envName] !== '') {
                return $this->truthy($_ENV[$envName]);
            }
        }

        return !empty($defaults[$flag]);
    }

    /** Master switch. */
    public function isMasterEnabled(): bool
    {
        return $this->enabled('offline.enabled');
    }

    /** Tier-1 inventory movements (requires master + sub-flag). */
    public function isInventoryMovementsEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.inventory.movements');
    }

    /** Tier-1 HR attendance (requires master + sub-flag). */
    public function isHrAttendanceEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.hr.attendance');
    }

    /** Tier-1 procurement drafts (requires master + sub-flag). */
    public function isProcurementEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.procurement');
    }

    /**
     * Phase 14.2 — PO goods receipt / GRN (requires procurement + goods_receipt flag).
     * Replays via ProcurementService::receiveOrder only.
     */
    public function isProcurementGoodsReceiptEnabled(): bool
    {
        return $this->isProcurementEnabled() && $this->enabled('offline.procurement.goods_receipt');
    }

    /** Phase 15B — Recruitment Tier-1 (requires master + offline.recruitment). */
    public function isRecruitmentEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.recruitment');
    }

    public function isRecruitmentCandidatesEnabled(): bool
    {
        return $this->isRecruitmentEnabled() && $this->enabled('offline.recruitment.candidates');
    }

    public function isRecruitmentWorkflowEnabled(): bool
    {
        return $this->isRecruitmentEnabled() && $this->enabled('offline.recruitment.workflow');
    }

    public function isRecruitmentAssignmentEnabled(): bool
    {
        return $this->isRecruitmentEnabled() && $this->enabled('offline.recruitment.assignment');
    }

    /** Phase 16B — Accounting Tier-1 drafts (requires master + offline.accounting). */
    public function isAccountingEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.accounting');
    }

    public function isAccountingJournalsEnabled(): bool
    {
        return $this->isAccountingEnabled() && $this->enabled('offline.accounting.journals');
    }

    public function isAccountingWorkflowEnabled(): bool
    {
        return $this->isAccountingEnabled() && $this->enabled('offline.accounting.workflow');
    }

    public function isAccountingMasterDataEnabled(): bool
    {
        return $this->isAccountingEnabled() && $this->enabled('offline.accounting.masterdata');
    }

    /** Phase 17B — CRM Tier-1 drafts (requires master + offline.crm). */
    public function isCrmEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.crm');
    }

    public function isCrmLeadsEnabled(): bool
    {
        return $this->isCrmEnabled() && $this->enabled('offline.crm.leads');
    }

    public function isCrmWorkflowEnabled(): bool
    {
        return $this->isCrmEnabled() && $this->enabled('offline.crm.workflow');
    }

    public function isCrmActivitiesEnabled(): bool
    {
        return $this->isCrmEnabled() && $this->enabled('offline.crm.activities');
    }

    public function isCrmMasterDataEnabled(): bool
    {
        return $this->isCrmEnabled() && $this->enabled('offline.crm.masterdata');
    }

    /** Phase 18B — Projects Tier-1 drafts (requires master + offline.projects). */
    public function isProjectsEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.projects');
    }

    public function isProjectsTasksEnabled(): bool
    {
        return $this->isProjectsEnabled() && $this->enabled('offline.projects.tasks');
    }

    public function isProjectsWorkflowEnabled(): bool
    {
        return $this->isProjectsEnabled() && $this->enabled('offline.projects.workflow');
    }

    public function isProjectsTimesheetsEnabled(): bool
    {
        return $this->isProjectsEnabled() && $this->enabled('offline.projects.timesheets');
    }

    public function isProjectsMasterDataEnabled(): bool
    {
        return $this->isProjectsEnabled() && $this->enabled('offline.projects.masterdata');
    }

    /** Phase 19B — Assets Tier-1 drafts (requires master + offline.assets). */
    public function isAssetsEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.assets');
    }

    public function isAssetsMaintenanceEnabled(): bool
    {
        return $this->isAssetsEnabled() && $this->enabled('offline.assets.maintenance');
    }

    public function isAssetsWorkflowEnabled(): bool
    {
        return $this->isAssetsEnabled() && $this->enabled('offline.assets.workflow');
    }

    public function isAssetsInspectionsEnabled(): bool
    {
        return $this->isAssetsEnabled() && $this->enabled('offline.assets.inspections');
    }

    public function isAssetsMasterDataEnabled(): bool
    {
        return $this->isAssetsEnabled() && $this->enabled('offline.assets.masterdata');
    }

    /** Phase 20B — Approval Tier-1 drafts (requires master + offline.approval). */
    public function isApprovalEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.approval');
    }

    public function isApprovalRequestsEnabled(): bool
    {
        return $this->isApprovalEnabled() && $this->enabled('offline.approval.requests');
    }

    public function isApprovalWorkflowEnabled(): bool
    {
        return $this->isApprovalEnabled() && $this->enabled('offline.approval.workflow');
    }

    public function isApprovalMasterDataEnabled(): bool
    {
        return $this->isApprovalEnabled() && $this->enabled('offline.approval.masterdata');
    }

    /** Phase 21B — EPROC Tier-1 drafts (requires master + offline.procurement_enterprise). */
    public function isProcurementEnterpriseEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.procurement_enterprise');
    }

    public function isProcurementEnterpriseSuppliersEnabled(): bool
    {
        return $this->isProcurementEnterpriseEnabled() && $this->enabled('offline.procurement_enterprise.suppliers');
    }

    public function isProcurementEnterpriseTendersEnabled(): bool
    {
        return $this->isProcurementEnterpriseEnabled() && $this->enabled('offline.procurement_enterprise.tenders');
    }

    public function isProcurementEnterpriseContractsEnabled(): bool
    {
        return $this->isProcurementEnterpriseEnabled() && $this->enabled('offline.procurement_enterprise.contracts');
    }

    public function isProcurementEnterpriseWorkflowEnabled(): bool
    {
        return $this->isProcurementEnterpriseEnabled() && $this->enabled('offline.procurement_enterprise.workflow');
    }

    public function isProcurementEnterpriseMasterDataEnabled(): bool
    {
        return $this->isProcurementEnterpriseEnabled() && $this->enabled('offline.procurement_enterprise.masterdata');
    }

    /** Ops monitoring dashboards (independent of master — read-only visibility). */
    public function isMonitoringEnabled(): bool
    {
        return $this->enabled('offline.monitoring');
    }

    /** Tier-2 ERP shell read cache (requires master + offline.read_cache). */
    public function isReadCacheEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.read_cache');
    }

    /**
     * Tier-3 ERP offline unlock (requires master + read_cache + auth.unlock).
     * Local shell unlock only — never creates a PHP session.
     */
    public function isAuthUnlockEnabled(): bool
    {
        return $this->isReadCacheEnabled() && $this->enabled('offline.auth.unlock');
    }

    /**
     * Tier-3 ERP offline RBAC/nav cache (requires master + read_cache + auth.unlock + rbac.cache).
     * UI cache only — never replaces server authorization.
     */
    public function isRbacCacheEnabled(): bool
    {
        return $this->isAuthUnlockEnabled() && $this->enabled('offline.rbac.cache');
    }

    /** Phase 13 — enterprise master-data delta (requires master + offline.master_data). */
    public function isMasterDataEnabled(): bool
    {
        return $this->isMasterEnabled() && $this->enabled('offline.master_data');
    }

    /**
     * Phase 14 — allowlisted ops page snapshots (requires master + read_cache + pilot.ops_pages).
     * Browse-only cache; does not expand write surface by itself.
     */
    public function isPilotOpsPagesEnabled(): bool
    {
        return $this->isReadCacheEnabled() && $this->enabled('offline.pilot.ops_pages');
    }

    /** Any Tier-1 write module flag under master (for layout SDK / form-hook injection). */
    public function isAnyTier1WriteEnabled(): bool
    {
        return $this->isInventoryMovementsEnabled()
            || $this->isHrAttendanceEnabled()
            || $this->isProcurementEnabled()
            || $this->isRecruitmentEnabled()
            || $this->isAccountingEnabled()
            || $this->isCrmEnabled()
            || $this->isProjectsEnabled();
    }

    /** @return array<string, bool> */
    public function snapshot(): array
    {
        $defaults = is_array($this->config()['defaults'] ?? null) ? $this->config()['defaults'] : [];
        $out = [];
        foreach (array_keys($defaults) as $flag) {
            $out[(string) $flag] = $this->enabled((string) $flag);
        }

        return $out;
    }

    private function truthy(mixed $value): bool
    {
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}

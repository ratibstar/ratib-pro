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
            || $this->isRecruitmentEnabled();
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

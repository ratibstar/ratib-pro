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

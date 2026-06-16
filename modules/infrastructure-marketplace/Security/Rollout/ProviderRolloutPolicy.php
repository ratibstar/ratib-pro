<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Security\Rollout;

use RATEB\InfrastructureMarketplace\Config\ModuleConfig;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;

final class ProviderRolloutPolicy
{
    public function canExecute(TenantContext $tenant, string $providerKey): bool
    {
        if (ModuleConfig::executionKillSwitch()) {
            return false;
        }
        if (ModuleConfig::dryRunMode()) {
            return false;
        }

        $allowlist = ModuleConfig::rolloutTenantAllowlist();
        if ($allowlist !== [] && $tenant->tenantId() !== null && !in_array($tenant->tenantId(), $allowlist, true)) {
            return false;
        }

        return ModuleConfig::providerLiveEnabled($providerKey) || ModuleConfig::providerSandboxEnabled($providerKey);
    }

    public function executionMode(string $providerKey): string
    {
        if (ModuleConfig::providerLiveEnabled($providerKey)) {
            return 'live';
        }
        return 'sandbox';
    }
}


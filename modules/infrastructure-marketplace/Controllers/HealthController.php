<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Controllers;

use RATEB\InfrastructureMarketplace\Config\ModuleConfig;

final class HealthController
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(): array
    {
        return [
            'ok' => true,
            'module' => 'infrastructure-marketplace',
            'layer' => 'operational-foundation',
            'enabled' => ModuleConfig::isModuleEnabled(),
            'queue_driver' => ModuleConfig::defaultQueueDriver(),
            'queue_max_attempts' => ModuleConfig::queueMaxAttempts(),
            'lock_ttl_seconds' => ModuleConfig::workerLockTtlSeconds(),
            'cpanel_configured' => ModuleConfig::cpanelWhmBaseUrl() !== null
                && ModuleConfig::cpanelWhmUsername() !== null
                && ModuleConfig::cpanelWhmToken() !== null,
            'state_machine' => 'enabled',
        ];
    }
}

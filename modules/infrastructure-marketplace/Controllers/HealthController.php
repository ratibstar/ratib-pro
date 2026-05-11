<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Controllers;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;

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
            'cpanel_configured' => ModuleConfig::cpanelWhmBaseUrl() !== null && ModuleConfig::cpanelWhmUsername() !== null,
        ];
    }
}

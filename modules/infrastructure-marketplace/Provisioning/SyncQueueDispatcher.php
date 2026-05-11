<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\Contracts\QueueDispatcherInterface;

/**
 * Default no-op queue: returns synthetic id only. Replace with resilient queue adapter later.
 */
final class SyncQueueDispatcher implements QueueDispatcherInterface
{
    public function enqueue(ProvisioningJob $job): string
    {
        unset($job);
        $driver = ModuleConfig::defaultQueueDriver();

        return 'sync+' . $driver . ':' . bin2hex(random_bytes(8));
    }
}

<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Domain\Contracts;

use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningJob;

/**
 * Swap sync execution for Redis/DB/worker-backed queues without changing callers.
 */
interface QueueDispatcherInterface
{
    /**
     * @return string Dispatched reference (job id, queue receipt, etc.).
     */
    public function enqueue(ProvisioningJob $job): string;
}

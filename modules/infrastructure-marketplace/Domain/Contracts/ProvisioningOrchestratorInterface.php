<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domain\Contracts;

use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningJob;

/**
 * Coordinates multi-step infra flows (domains + DNS + SSL + hosting) without embedding vendor SDKs here.
 */
interface ProvisioningOrchestratorInterface
{
    public function submit(ProvisioningJob $job): string;

    public function reconcile(string $jobId): array;
}

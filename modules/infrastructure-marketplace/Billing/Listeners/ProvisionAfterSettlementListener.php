<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Billing\Listeners;

use Ratib\InfrastructureMarketplace\Billing\Contracts\InfrastructureBillingSettlementHookInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningPayload;
use Ratib\InfrastructureMarketplace\Services\ProvisioningOrchestrator;

/**
 * Non-invasive settlement listener: callable from existing payment success hooks without modifying payment internals.
 */
final class ProvisionAfterSettlementListener implements InfrastructureBillingSettlementHookInterface
{
    public function __construct(
        private readonly ProvisioningOrchestrator $orchestrator,
        private readonly InfrastructureEventEmitter $events
    ) {}

    public function onInfrastructurePurchaseSettled(TenantContext $tenant, array $lineItem): void
    {
        $sku = (string) ($lineItem['sku'] ?? '');
        if ($sku === '') {
            return;
        }

        $job = new ProvisioningJob(
            $tenant,
            ['registrar', 'dns', 'ssl', 'hosting'],
            [
                'hosting' => new ProvisioningPayload('create_account', $lineItem),
            ],
            (string) ($lineItem['settlement_id'] ?? '')
        );
        $jobId = $this->orchestrator->submit($job);
        $this->events->provisioningReconcileRequested($jobId);
    }

    public function onInfrastructureRefundSettled(TenantContext $tenant, array $lineItem): void
    {
        $this->events->provisioningReconcileRequested((string) ($lineItem['settlement_id'] ?? 'refund'));
        unset($tenant);
    }
}


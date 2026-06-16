<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Billing\Listeners;

use RATEB\InfrastructureMarketplace\Billing\Contracts\InfrastructureBillingSettlementHookInterface;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;
use RATEB\InfrastructureMarketplace\Services\ProvisioningOrchestrator;

/**
 * Non-invasive settlement listener: callable from existing payment success hooks without modifying payment internals.
 */
final class ProvisionAfterSettlementListener implements InfrastructureBillingSettlementHookInterface
{
    private ProvisioningOrchestrator $orchestrator;
    private InfrastructureEventEmitter $events;

    public function __construct(ProvisioningOrchestrator $orchestrator, InfrastructureEventEmitter $events) {
        $this->orchestrator = $orchestrator;
        $this->events = $events;
    }


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


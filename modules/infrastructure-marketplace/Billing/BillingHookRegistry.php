<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Billing;

use Ratib\InfrastructureMarketplace\Billing\Contracts\InfrastructureBillingSettlementHookInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;

/**
 * Collects optional settlement hooks — wire from payment webhook handlers when ready without replacing them.
 */
final class BillingHookRegistry
{
    /** @var list<InfrastructureBillingSettlementHookInterface> */
    private array $hooks = [];

    public function register(InfrastructureBillingSettlementHookInterface $hook): void
    {
        $this->hooks[] = $hook;
    }

    /**
     * @param array<string, mixed> $lineItem
     */
    public function dispatchPurchase(TenantContext $tenant, array $lineItem): void
    {
        foreach ($this->hooks as $hook) {
            $hook->onInfrastructurePurchaseSettled($tenant, $lineItem);
        }
    }

    /**
     * @param array<string, mixed> $lineItem
     */
    public function dispatchRefund(TenantContext $tenant, array $lineItem): void
    {
        foreach ($this->hooks as $hook) {
            $hook->onInfrastructureRefundSettled($tenant, $lineItem);
        }
    }
}

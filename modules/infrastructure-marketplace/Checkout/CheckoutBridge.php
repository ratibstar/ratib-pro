<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Checkout;

use RATEB\InfrastructureMarketplace\Billing\BillingHookRegistry;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;

/**
 * Non-invasive integration bridge for existing payment events.
 */
final class CheckoutBridge
{
    private BillingHookRegistry $billingHooks;

    public function __construct(BillingHookRegistry $billingHooks) {
        $this->billingHooks = $billingHooks;
    }


    /**
     * @param array<string, mixed> $lineItem
     */
    public function afterSettlement(TenantContext $tenant, array $lineItem): void
    {
        $this->billingHooks->dispatchPurchase($tenant, $lineItem);
    }
}


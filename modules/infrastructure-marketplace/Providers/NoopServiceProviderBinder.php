<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Providers;

use Ratib\InfrastructureMarketplace\Billing\BillingHookRegistry;

/**
 * Future extension point analogous to Laravel service providers — registers hooks without Laravel.
 */
final class NoopServiceProviderBinder
{
    public function bindBillingHooks(BillingHookRegistry $registry): void
    {
        unset($registry);
    }
}

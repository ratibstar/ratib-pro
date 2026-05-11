<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Billing\Contracts;

use Ratib\InfrastructureMarketplace\Domain\TenantContext;

/**
 * Implemented by adapters that mirror line items into N-Genius orders, ledger journals, invoices, etc.
 * No calls from existing checkout paths until explicitly wired by operators.
 */
interface InfrastructureBillingSettlementHookInterface
{
    /**
     * @param array<string, mixed> $lineItem SKU, qty, currency, gateway metadata (no PAN/secrets).
     */
    public function onInfrastructurePurchaseSettled(TenantContext $tenant, array $lineItem): void;

    /**
     * @param array<string, mixed> $lineItem
     */
    public function onInfrastructureRefundSettled(TenantContext $tenant, array $lineItem): void;
}

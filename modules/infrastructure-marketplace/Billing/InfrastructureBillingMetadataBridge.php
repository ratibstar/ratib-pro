<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Billing;

final class InfrastructureBillingMetadataBridge
{
    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function invoiceMetadata(array $order): array
    {
        return [
            'infra_order_public_id' => (string) ($order['public_id'] ?? ''),
            'infra_sku' => (string) ($order['sku'] ?? ''),
            'provisioning_job_public_id' => (string) ($order['provisioning_job_public_id'] ?? ''),
            'cycle' => (string) ($order['billing_cycle'] ?? 'monthly'),
            'renewal_prepared' => true,
            'suspension_prepared' => true,
            'cancellation_prepared' => true,
        ];
    }
}


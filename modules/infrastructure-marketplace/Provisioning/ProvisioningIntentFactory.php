<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning;

use Ratib\InfrastructureMarketplace\Resources\ResourceIdentityManager;
use Ratib\InfrastructureMarketplace\State\StateNamespaceRegistry;

/**
 * Builds ProvisioningIntent from resolved commerce + order context (no provider I/O).
 */
final class ProvisioningIntentFactory
{
    /**
     * @param array<string, mixed> $order ratib_infra_orders row
     * @param array<string, mixed>|null $plan ratib_infra_plans row or null
     * @param array<string, mixed>|null $product ratib_infra_products row or null
     * @param list<string> $requestedCapabilities
     * @param array<string, mixed> $extraMetadata merged into metadata_json
     */
    public static function fromOrderResolution(
        array $order,
        ?array $plan,
        ?array $product,
        string $resourcePublicId,
        string $correlationId,
        ?string $traceId,
        string $provisioningPhase,
        string $providerTarget,
        array $requestedCapabilities = [],
        array $extraMetadata = []
    ): ProvisioningIntent {
        $warnings = StateNamespaceRegistry::validateProvisioningPhase($provisioningPhase);
        $intentId = ResourceIdentityManager::newIntentId();
        $meta = array_merge([
            'order_public_id' => (string) ($order['public_id'] ?? ''),
            'sku' => (string) ($order['sku'] ?? ''),
            'trace_id' => $traceId,
            'factory_warnings' => $warnings,
        ], $extraMetadata);
        $pid = null;
        $plid = null;
        if (is_array($product) && isset($product['id'])) {
            $pid = (int) $product['id'];
        }
        if (is_array($plan) && isset($plan['id'])) {
            $plid = (int) $plan['id'];
        }
        $tenantId = isset($order['tenant_id']) ? (int) $order['tenant_id'] : null;

        return new ProvisioningIntent(
            $intentId,
            $correlationId,
            $tenantId,
            $pid,
            $plid,
            $resourcePublicId,
            strtoupper(trim($provisioningPhase)),
            $providerTarget,
            array_values($requestedCapabilities),
            $meta,
            gmdate('c')
        );
    }
}

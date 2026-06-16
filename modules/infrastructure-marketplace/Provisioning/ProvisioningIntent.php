<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Provisioning;

/**
 * Business intent DTO — not a queue row and not a provider call.
 * provisioning_state uses **provisioning phase** vocabulary (see StateNamespaceRegistry), not job queue status.
 */
final class ProvisioningIntent
{
    /**
     * @param list<string> $requestedCapabilities
     * @param array<string, mixed> $metadataJson
     */
    public function __construct(
        private string $intentId,
        private string $correlationId,
        private ?int $tenantId,
        private ?int $productId,
        private ?int $planId,
        private string $resourcePublicId,
        private string $provisioningState,
        private string $providerTarget,
        private array $requestedCapabilities,
        private array $metadataJson,
        private string $createdAtIso
    ) {
    }

    public function intentId(): string
    {
        return $this->intentId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function productId(): ?int
    {
        return $this->productId;
    }

    public function planId(): ?int
    {
        return $this->planId;
    }

    public function resourcePublicId(): string
    {
        return $this->resourcePublicId;
    }

    public function provisioningState(): string
    {
        return $this->provisioningState;
    }

    public function providerTarget(): string
    {
        return $this->providerTarget;
    }

    /**
     * @return list<string>
     */
    public function requestedCapabilities(): array
    {
        return $this->requestedCapabilities;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadataJson(): array
    {
        return $this->metadataJson;
    }

    public function createdAtIso(): string
    {
        return $this->createdAtIso;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent_id' => $this->intentId,
            'correlation_id' => $this->correlationId,
            'tenant_id' => $this->tenantId,
            'product_id' => $this->productId,
            'plan_id' => $this->planId,
            'resource_public_id' => $this->resourcePublicId,
            'provisioning_state' => $this->provisioningState,
            'provider_target' => $this->providerTarget,
            'requested_capabilities' => $this->requestedCapabilities,
            'metadata_json' => $this->metadataJson,
            'created_at' => $this->createdAtIso,
        ];
    }
}

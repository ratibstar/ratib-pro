<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning;

/**
 * Normalized provisioning input; adapters map this to vendor payloads.
 *
 * @phpstan-type PayloadMap array<string, mixed>
 */
final class ProvisioningPayload
{
    /**
     * @param PayloadMap $attributes
     */
    public function __construct(
        private readonly string $operation,
        private readonly array $attributes = []
    ) {}

    public function operation(): string
    {
        return $this->operation;
    }

    /**
     * @return PayloadMap
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}

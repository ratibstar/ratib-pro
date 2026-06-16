<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Provisioning;

/**
 * Normalized provisioning input; adapters map this to vendor payloads.
 *
 * @phpstan-type PayloadMap array<string, mixed>
 */
final class ProvisioningPayload
{
    private string $operation;
    private array $attributes;

    /**
     * @param PayloadMap $attributes
     */
    public function __construct(string $operation, array $attributes = []) {
        $this->operation = $operation;
        $this->attributes = $attributes;
    }


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

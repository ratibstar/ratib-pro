<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domain;

/**
 * Carries tenant / agency identifiers for provisioning and billing correlation.
 * Compatible with isolation models that key off tenant id and/or agency id.
 */
final class TenantContext
{
    public function __construct(
        private readonly ?int $tenantId,
        private readonly ?int $agencyId,
        private readonly ?string $whiteLabelBrandKey = null
    ) {}

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function agencyId(): ?int
    {
        return $this->agencyId;
    }

    public function whiteLabelBrandKey(): ?string
    {
        return $this->whiteLabelBrandKey;
    }
}

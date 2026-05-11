<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domain;

/**
 * Carries tenant / agency identifiers for provisioning and billing correlation.
 * Compatible with isolation models that key off tenant id and/or agency id.
 */
final class TenantContext
{
    private ?int $tenantId;
    private ?int $agencyId;
    private ?string $whiteLabelBrandKey;

    public function __construct(?int $tenantId, ?int $agencyId, ?string $whiteLabelBrandKey = null) {
        $this->tenantId = $tenantId;
        $this->agencyId = $agencyId;
        $this->whiteLabelBrandKey = $whiteLabelBrandKey;
    }


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

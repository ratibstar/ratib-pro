<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Providers\Activation;

final class ScopedProviderResolver
{
    private ProviderActivationRegistry $registry;

    public function __construct(ProviderActivationRegistry $registry) {
        $this->registry = $registry;
    }


    /**
     * @return array<string, mixed>|null
     */
    public function pickPrimary(string $providerType, ?int $tenantId, ?int $agencyId): ?array
    {
        $active = $this->registry->activeForScope($providerType, $tenantId, $agencyId);
        return $active[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function failoverChain(string $providerType, ?int $tenantId, ?int $agencyId): array
    {
        return $this->registry->activeForScope($providerType, $tenantId, $agencyId);
    }
}


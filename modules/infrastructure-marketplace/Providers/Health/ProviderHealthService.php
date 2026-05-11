<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Providers\Health;

use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;

final class ProviderHealthService
{
    public function __construct(
        private readonly ProviderActivationRegistry $activations,
        private readonly InfrastructureMetrics $metrics
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function healthSnapshot(?int $tenantId, ?int $agencyId): array
    {
        $types = ['hosting', 'registrar', 'dns', 'ssl'];
        $snapshot = [];
        foreach ($types as $type) {
            $active = $this->activations->activeForScope($type, $tenantId, $agencyId);
            $status = count($active) > 0 ? 'available' : 'unavailable';
            $this->metrics->externalDependencyStatus('provider:' . $type, $status);
            $snapshot[] = [
                'provider_type' => $type,
                'status' => $status,
                'active_count' => count($active),
            ];
        }
        return $snapshot;
    }
}


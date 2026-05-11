<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Providers\Health;

use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;

final class ProviderHealthService
{
    private ProviderActivationRegistry $activations;
    private InfrastructureMetrics $metrics;

    public function __construct(ProviderActivationRegistry $activations, InfrastructureMetrics $metrics) {
        $this->activations = $activations;
        $this->metrics = $metrics;
    }


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


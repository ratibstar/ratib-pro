<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Providers\Capabilities;

use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;

final class CapabilityDiscoveryService
{
    public function __construct(
        private readonly ProviderActivationRegistry $activations
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function discover(string $providerType, ?int $tenantId, ?int $agencyId): array
    {
        $enabled = $this->activations->activeForScope($providerType, $tenantId, $agencyId);
        $out = [];
        foreach ($enabled as $row) {
            $class = (string) ($row['provider_class'] ?? '');
            $capability = [];
            $capError = null;
            if ($class !== '' && class_exists($class)) {
                try {
                    $instance = new $class();
                    if (method_exists($instance, 'getCapabilityMatrix')) {
                        $capability = (array) $instance->getCapabilityMatrix();
                    }
                } catch (\Throwable $e) {
                    $capError = 'capability_probe_failed';
                }
            }
            $entry = [
                'provider_code' => (string) ($row['provider_code'] ?? ''),
                'provider_class' => $class,
                'priority_weight' => (int) ($row['priority_weight'] ?? 0),
                'capabilities' => $capability,
            ];
            if ($capError !== null) {
                $entry['capability_error'] = $capError;
            }
            $out[] = $entry;
        }
        return $out;
    }
}


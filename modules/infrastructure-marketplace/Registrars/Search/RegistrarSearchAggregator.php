<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Registrars\Search;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;

final class RegistrarSearchAggregator
{
    private ?ProviderActivationRegistry $activations;

    public function __construct(?ProviderActivationRegistry $activations = null) {
        $this->activations = $activations;
    }


    /**
     * @param list<string> $tlds
     * @return list<array<string, mixed>>
     */
    public function searchAsyncPrepared(string $keyword, array $tlds, ?TenantContext $tenant = null): array
    {
        $keyword = strtolower(trim($keyword));
        $providers = $this->resolveActiveRegistrarProviders($tenant);
        $out = [];
        foreach ($tlds as $tld) {
            $fqdn = $keyword . '.' . ltrim(strtolower($tld), '.');
            $result = null;
            foreach ($providers as $provider) {
                $class = (string) ($provider['provider_class'] ?? '');
                if ($class === '' || !class_exists($class)) {
                    continue;
                }
                $instance = new $class();
                if (!method_exists($instance, 'checkAvailability')) {
                    continue;
                }
                $tenantContext = $tenant ?? new TenantContext(null, null);
                /** @var array<string, mixed> $providerResult */
                $providerResult = $instance->checkAvailability($tenantContext, $fqdn);
                $result = $providerResult;
                if (($providerResult['status'] ?? '') === 'ok') {
                    break;
                }
            }
            $out[] = [
                'fqdn' => $fqdn,
                'available' => $result['available'] ?? null,
                'status' => $result['status'] ?? 'pending_provider_query',
                'premium' => $result['premium'] ?? null,
                'provider' => $result['provider'] ?? null,
                'cached' => false,
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveActiveRegistrarProviders(?TenantContext $tenant): array
    {
        if ($this->activations === null) {
            return [[
                'provider_class' => 'RATEB\\InfrastructureMarketplace\\Registrars\\Adapters\\NamecheapRegistrarAdapter',
                'priority_weight' => 100,
            ]];
        }
        return $this->activations->activeForScope('registrar', $tenant?->tenantId(), $tenant?->agencyId());
    }
}


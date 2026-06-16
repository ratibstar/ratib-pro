<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Providers;

use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningIntent;
use RATEB\InfrastructureMarketplace\Services\ProviderRegistry;

/**
 * Binds intents to provider resolution targets without invoking adapters.
 */
final class ProviderExecutionBinder
{
    public function __construct(
        private \PDO $pdo,
        private ProviderRegistry $registry,
        private ?ProviderActivationRegistry $activations = null
    ) {
        if ($this->activations === null) {
            $this->activations = new ProviderActivationRegistry($pdo);
        }
    }

    /**
     * @param list<string> $capabilities
     * @return array{target: string, warnings: list<string>, capabilities_ok: bool}
     */
    public function resolveBindingsForCapabilities(array $capabilities, TenantContext $tenant): array
    {
        $warnings = [];
        $targetParts = [];
        foreach (['registrar', 'dns', 'ssl', 'hosting'] as $role) {
            $iface = $this->registryInterface($role);
            if ($iface === null) {
                continue;
            }
            $rows = $this->activations->activeForScope($role, $tenant->tenantId(), $tenant->agencyId());
            $code = '';
            if ($rows !== []) {
                $code = (string) ($rows[0]['provider_code'] ?? '');
            }
            $targetParts[] = $role . ':' . ($code !== '' ? $code : 'binding');
        }
        if ($targetParts === []) {
            $warnings[] = 'No provider roles resolved from ProviderRegistry (check RATEB_INFRA_PROVIDER_BINDINGS).';
        }
        $sb = getenv('RATEB_INFRA_PROVIDER_SANDBOX');
        $sandbox = is_string($sb) && in_array(strtolower(trim($sb)), ['1', 'true', 'yes', 'on'], true);
        if ($sandbox) {
            $warnings[] = 'Sandbox/provider flag overlay active — verify live compatibility before production execution.';
        }
        $capabilitiesOk = $this->validateCapabilities($capabilities);
        if (!$capabilitiesOk) {
            $warnings[] = 'Some requested_capabilities could not be aligned with active providers.';
        }

        return [
            'target' => implode('|', $targetParts),
            'warnings' => $warnings,
            'capabilities_ok' => $capabilitiesOk,
        ];
    }

    /**
     * @return array{target: string, warnings: list<string>, capabilities_ok: bool}
     */
    public function bindIntent(ProvisioningIntent $intent, TenantContext $tenant): array
    {
        return $this->resolveBindingsForCapabilities($intent->requestedCapabilities(), $tenant);
    }

    private function registryInterface(string $role): ?object
    {
        return match ($role) {
            'registrar' => $this->registry->registrar(),
            'dns' => $this->registry->dns(),
            'ssl' => $this->registry->ssl(),
            'hosting' => $this->registry->hosting(),
            default => null,
        };
    }

    /**
     * @param list<string> $requested
     */
    public function validateCapabilities(array $requested): bool
    {
        $ok = true;
        foreach ($requested as $r) {
            if (!is_string($r) || $r === '') {
                continue;
            }
            if (str_starts_with($r, 'service_type:')) {
                $t = substr($r, strlen('service_type:'));
                if ($t === 'domain' && $this->registry->registrar() === null) {
                    $ok = false;
                }
                if ($t === 'dns' && $this->registry->dns() === null) {
                    $ok = false;
                }
                if ($t === 'ssl' && $this->registry->ssl() === null) {
                    $ok = false;
                }
                if ($t === 'hosting' && $this->registry->hosting() === null) {
                    $ok = false;
                }
            }
        }

        return $ok;
    }

    public function validateProviderReadiness(TenantContext $tenant, string $providerType): bool
    {
        return $this->activations->activeForScope(strtolower($providerType), $tenant->tenantId(), $tenant->agencyId()) !== [];
    }
}

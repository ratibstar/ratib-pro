<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Security\Secrets;

use RATEB\InfrastructureMarketplace\Infrastructure\InfraEnvBootstrap;

/**
 * Keeps runtime-overrides and rateb_infra_provider_secrets aligned for Control Panel saves.
 */
final class InfraProviderSecretsSync
{
    private const NAMECHEAP_SCOPE = 'rateb_infra_namecheap';
    private const CPANEL_SCOPE = 'rateb_infra_cpanel';

    private ProviderSecretStore $store;

    public function __construct(\PDO $pdo, ?ProviderSecretStore $store = null)
    {
        $this->store = $store ?? new ProviderSecretStore($pdo);
    }

    /**
     * @param array<string, mixed> $overrides Full runtime-overrides payload after merge.
     * @return array<string, mixed> sync summary
     */
    public function syncFromRuntimeOverrides(array $overrides, string $actor = 'control-update'): array
    {
        InfraEnvBootstrap::load();
        if (!InfraEnvBootstrap::hasSecretKey()) {
            return ['skipped' => true, 'reason' => 'RATEB_INFRA_SECRET_KEY missing'];
        }

        $out = ['namecheap' => 'skipped', 'cpanel' => 'skipped'];
        $nc = is_array($overrides['registrar_secrets']['namecheap'] ?? null)
            ? $overrides['registrar_secrets']['namecheap']
            : [];
        if ($this->syncNamecheap($nc, $actor)) {
            $out['namecheap'] = 'synced';
        }

        $cpanelToken = trim((string) ($overrides['cpanel_api_token'] ?? ''));
        if ($cpanelToken !== '') {
            $this->store->upsert(self::CPANEL_SCOPE, 'API_TOKEN', $cpanelToken, null, null, $actor);
            $out['cpanel'] = 'synced';
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $nc
     */
    private function syncNamecheap(array $nc, string $actor): bool
    {
        $map = [
            'api_user' => 'API_USER',
            'api_key' => 'API_KEY',
            'username' => 'USERNAME',
            'client_ip' => 'CLIENT_IP',
        ];
        $written = false;
        foreach ($map as $rtKey => $secretKey) {
            if (!isset($nc[$rtKey]) || !is_string($nc[$rtKey]) || trim($nc[$rtKey]) === '') {
                continue;
            }
            $this->store->upsert(self::NAMECHEAP_SCOPE, $secretKey, trim($nc[$rtKey]), null, null, $actor);
            $written = true;
        }

        return $written;
    }
}

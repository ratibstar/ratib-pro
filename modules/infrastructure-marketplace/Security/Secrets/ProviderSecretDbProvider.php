<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Security\Secrets;

final class ProviderSecretDbProvider implements SecretProviderInterface
{
    private ProviderSecretStore $store;
    private ?int $tenantId;
    private ?int $agencyId;

    public function __construct(ProviderSecretStore $store, ?int $tenantId = null, ?int $agencyId = null)
    {
        $this->store = $store;
        $this->tenantId = $tenantId;
        $this->agencyId = $agencyId;
    }

    public function get(string $scope, string $key): ?string
    {
        return $this->store->get($scope, $key, $this->tenantId, $this->agencyId);
    }
}

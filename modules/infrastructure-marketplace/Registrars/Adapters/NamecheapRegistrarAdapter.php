<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Registrars\Adapters;

use Ratib\InfrastructureMarketplace\Domain\Contracts\RegistrarProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use Ratib\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningPayload;
use Ratib\InfrastructureMarketplace\Security\Rollout\ProviderRolloutPolicy;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

final class NamecheapRegistrarAdapter implements RegistrarProviderInterface
{
    private HttpClientInterface $http;
    private SecretManager $secrets;
    private ProviderRolloutPolicy $rollout;

    public function __construct(
        ?HttpClientInterface $http = null,
        ?SecretManager $secrets = null,
        ?ProviderRolloutPolicy $rollout = null
    ) {
        $this->http = $http ?? new CurlHttpClient();
        $this->secrets = $secrets ?? SecretManager::withEnvProvider();
        $this->rollout = $rollout ?? new ProviderRolloutPolicy();
    }

    public function registerDomain(TenantContext $tenant, ProvisioningPayload $payload): array
    {
        unset($payload);
        if (!$this->rollout->canExecute($tenant, 'namecheap')) {
            return ['provider' => 'namecheap', 'state' => 'disabled_by_rollout'];
        }
        return ['provider' => 'namecheap', 'state' => 'purchase_not_enabled'];
    }

    public function renewDomain(TenantContext $tenant, string $externalReference, int $years): array
    {
        unset($externalReference, $years);
        if (!$this->rollout->canExecute($tenant, 'namecheap')) {
            return ['provider' => 'namecheap', 'state' => 'disabled_by_rollout'];
        }
        return ['provider' => 'namecheap', 'state' => 'renew_not_enabled'];
    }

    public function getCapabilityMatrix(): array
    {
        return ['provider' => 'namecheap', 'ready' => true, 'live_availability' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkAvailability(TenantContext $tenant, string $fqdn): array
    {
        if (!$this->rollout->canExecute($tenant, 'namecheap')) {
            return ['provider' => 'namecheap', 'fqdn' => $fqdn, 'status' => 'disabled_by_rollout'];
        }

        $apiUser = $this->secrets->getSecret('RATIB_INFRA_NAMECHEAP', 'API_USER') ?? getenv('RATIB_INFRA_NAMECHEAP_API_USER');
        $apiKey = $this->secrets->getSecret('RATIB_INFRA_NAMECHEAP', 'API_KEY') ?? getenv('RATIB_INFRA_NAMECHEAP_API_KEY');
        $user = $this->secrets->getSecret('RATIB_INFRA_NAMECHEAP', 'USERNAME') ?? getenv('RATIB_INFRA_NAMECHEAP_USERNAME');
        $clientIp = getenv('RATIB_INFRA_NAMECHEAP_CLIENT_IP');
        if (!is_string($apiUser) || !is_string($apiKey) || !is_string($user) || !is_string($clientIp) || $apiUser === '' || $apiKey === '' || $user === '' || $clientIp === '') {
            return ['provider' => 'namecheap', 'fqdn' => $fqdn, 'status' => 'missing_credentials'];
        }

        $parts = explode('.', strtolower(trim($fqdn)), 2);
        if (count($parts) !== 2) {
            return ['provider' => 'namecheap', 'fqdn' => $fqdn, 'status' => 'invalid_domain'];
        }

        $base = $this->rollout->executionMode('namecheap') === 'live'
            ? 'https://api.namecheap.com/xml.response'
            : 'https://api.sandbox.namecheap.com/xml.response';

        $resp = $this->http->get($base, ['Accept' => 'application/xml'], [
            'ApiUser' => $apiUser,
            'ApiKey' => $apiKey,
            'UserName' => $user,
            'ClientIp' => $clientIp,
            'Command' => 'namecheap.domains.check',
            'DomainList' => $parts[0] . '.' . $parts[1],
        ]);
        if (!$resp->isSuccess()) {
            return ['provider' => 'namecheap', 'fqdn' => $fqdn, 'status' => 'provider_unreachable', 'retryable' => true];
        }

        $body = $resp->body();
        $available = stripos($body, 'Available="true"') !== false;
        $premium = stripos($body, 'IsPremiumName="true"') !== false;

        return [
            'provider' => 'namecheap',
            'fqdn' => $fqdn,
            'available' => $available,
            'premium' => $premium,
            'status' => 'ok',
            'retryable' => false,
        ];
    }
}


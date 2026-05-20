<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Registrars\Adapters;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\Contracts\RegistrarProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use Ratib\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use Ratib\InfrastructureMarketplace\Observability\ProviderEventBus;
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
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        unset($payload);
        if (!$this->rollout->canExecute($tenant, 'namecheap')) {
            $result = ['provider' => 'namecheap', 'state' => 'disabled_by_rollout'];
            $this->logProviderEvent('create', $tenant, $started, $requestId, $result);
            return $result;
        }
        $result = ['provider' => 'namecheap', 'state' => 'purchase_not_enabled'];
        $this->logProviderEvent('create', $tenant, $started, $requestId, $result);
        return $result;
    }

    public function renewDomain(TenantContext $tenant, string $externalReference, int $years): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        unset($externalReference, $years);
        if (!$this->rollout->canExecute($tenant, 'namecheap')) {
            $result = ['provider' => 'namecheap', 'state' => 'disabled_by_rollout'];
            $this->logProviderEvent('renewals', $tenant, $started, $requestId, $result);
            return $result;
        }
        $result = ['provider' => 'namecheap', 'state' => 'renew_not_enabled'];
        $this->logProviderEvent('renewals', $tenant, $started, $requestId, $result);
        return $result;
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
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        if (!$this->rollout->canExecute($tenant, 'namecheap')) {
            $result = ['provider' => 'namecheap', 'fqdn' => $fqdn, 'status' => 'disabled_by_rollout'];
            $this->logProviderEvent('health_check', $tenant, $started, $requestId, $result);
            return $result;
        }

        $apiUser = ModuleConfig::namecheapSecretFromRuntime('api_user')
            ?: ($this->secrets->getSecret('RATIB_INFRA_NAMECHEAP', 'API_USER') ?? getenv('RATIB_INFRA_NAMECHEAP_API_USER'));
        $apiKey = ModuleConfig::namecheapSecretFromRuntime('api_key')
            ?: ($this->secrets->getSecret('RATIB_INFRA_NAMECHEAP', 'API_KEY') ?? getenv('RATIB_INFRA_NAMECHEAP_API_KEY'));
        $user = ModuleConfig::namecheapSecretFromRuntime('username')
            ?: ($this->secrets->getSecret('RATIB_INFRA_NAMECHEAP', 'USERNAME') ?? getenv('RATIB_INFRA_NAMECHEAP_USERNAME'));
        $clientIp = ModuleConfig::namecheapSecretFromRuntime('client_ip') ?: getenv('RATIB_INFRA_NAMECHEAP_CLIENT_IP');
        if (!is_string($apiUser) || !is_string($apiKey) || !is_string($user) || !is_string($clientIp) || $apiUser === '' || $apiKey === '' || $user === '' || $clientIp === '') {
            $result = ['provider' => 'namecheap', 'fqdn' => $fqdn, 'status' => 'missing_credentials'];
            $this->logProviderEvent('health_check', $tenant, $started, $requestId, $result);
            return $result;
        }

        $parts = explode('.', strtolower(trim($fqdn)), 2);
        if (count($parts) !== 2) {
            $result = ['provider' => 'namecheap', 'fqdn' => $fqdn, 'status' => 'invalid_domain'];
            $this->logProviderEvent('health_check', $tenant, $started, $requestId, $result);
            return $result;
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
            $result = ['provider' => 'namecheap', 'fqdn' => $fqdn, 'status' => 'provider_unreachable', 'retryable' => true];
            $this->logProviderEvent('health_check', $tenant, $started, $requestId, $result);
            return $result;
        }

        $body = $resp->body();
        $available = stripos($body, 'Available="true"') !== false;
        $premium = stripos($body, 'IsPremiumName="true"') !== false;

        $result = [
            'provider' => 'namecheap',
            'fqdn' => $fqdn,
            'available' => $available,
            'premium' => $premium,
            'status' => 'ok',
            'retryable' => false,
        ];
        $this->logProviderEvent('health_check', $tenant, $started, $requestId, $result);

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function logProviderEvent(string $eventName, TenantContext $tenant, float $startedAt, string $requestId, array $result): void
    {
        $state = strtolower((string) ($result['state'] ?? $result['status'] ?? 'unknown'));
        $status = in_array($state, ['ok'], true)
            ? 'success'
            : (!empty($result['retryable']) ? 'retry' : 'failed');
        ProviderEventBus::log('registrar', 'namecheap', $eventName, [
            'request_id' => $requestId,
            'operation_name' => $eventName,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'tenant_id' => $tenant->tenantId(),
            'agency_id' => $tenant->agencyId(),
            'payload' => $result,
            'error_message' => $status === 'success' ? null : $state,
        ]);
    }
}


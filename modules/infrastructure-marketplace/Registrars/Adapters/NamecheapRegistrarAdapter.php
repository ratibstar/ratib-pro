<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Registrars\Adapters;

use RATEB\InfrastructureMarketplace\Config\ModuleConfig;
use RATEB\InfrastructureMarketplace\Domain\Contracts\RegistrarProviderInterface;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use RATEB\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use RATEB\InfrastructureMarketplace\Observability\ProviderEventBus;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;
use RATEB\InfrastructureMarketplace\Security\Rollout\ProviderRolloutPolicy;
use RATEB\InfrastructureMarketplace\Security\Secrets\SecretManager;

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
        $this->secrets = $secrets ?? SecretManager::withDefaultProvidersLazy();
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

        $apiUser = ModuleConfig::namecheapCredential('api_user');
        $apiKey = ModuleConfig::namecheapCredential('api_key');
        $user = ModuleConfig::namecheapCredential('username');
        $clientIp = ModuleConfig::namecheapCredential('client_ip');
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


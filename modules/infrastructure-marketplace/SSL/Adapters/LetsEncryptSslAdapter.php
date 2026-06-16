<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\SSL\Adapters;

use RATEB\InfrastructureMarketplace\Domain\Contracts\SslProviderInterface;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use RATEB\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use RATEB\InfrastructureMarketplace\Observability\ProviderEventBus;
use RATEB\InfrastructureMarketplace\Security\Rollout\ProviderRolloutPolicy;

final class LetsEncryptSslAdapter implements SslProviderInterface
{
    private HttpClientInterface $http;
    private ProviderRolloutPolicy $rollout;

    public function __construct(
        ?HttpClientInterface $http = null,
        ?ProviderRolloutPolicy $rollout = null
    ) {
        $this->http = $http ?? new CurlHttpClient();
        $this->rollout = $rollout ?? new ProviderRolloutPolicy();
    }

    public function provisionCertificate(TenantContext $tenant, string $fqdn, array $options = []): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        if (!$this->rollout->canExecute($tenant, 'letsencrypt_ssl')) {
            $result = ['provider' => 'letsencrypt_ssl', 'state' => 'disabled_by_rollout'];
            $this->logProviderEvent('ssl_renewals', $tenant, $started, $requestId, $result);
            return $result;
        }
        $directory = $this->directoryUrl();
        $resp = $this->http->get($directory, ['Accept' => 'application/json']);
        if (!$resp->isSuccess()) {
            $result = [
                'provider' => 'letsencrypt_ssl',
                'state' => 'acme_unreachable',
                'retryable' => true,
                'error_class' => 'provider_unavailable',
            ];
            $this->logProviderEvent('ssl_renewals', $tenant, $started, $requestId, $result);
            return $result;
        }
        $result = [
            'provider' => 'letsencrypt_ssl',
            'state' => 'validation_prepared',
            'mode' => $this->rollout->executionMode('letsencrypt_ssl'),
            'fqdn' => strtolower($fqdn),
            'challenge_strategy' => (string) ($options['challenge'] ?? 'dns-01'),
            'acme_directory' => $directory,
            'async' => true,
        ];
        $this->logProviderEvent('ssl_renewals', $tenant, $started, $requestId, $result);

        return $result;
    }

    public function revokeCertificate(TenantContext $tenant, string $externalReference): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        if (!$this->rollout->canExecute($tenant, 'letsencrypt_ssl')) {
            $result = ['provider' => 'letsencrypt_ssl', 'state' => 'disabled_by_rollout'];
            $this->logProviderEvent('ssl_renewals', $tenant, $started, $requestId, $result);
            return $result;
        }
        $result = [
            'provider' => 'letsencrypt_ssl',
            'state' => 'revoke_prepared',
            'reference' => $externalReference,
            'async' => true,
        ];
        $this->logProviderEvent('ssl_renewals', $tenant, $started, $requestId, $result);

        return $result;
    }

    public function getCapabilityMatrix(): array
    {
        return [
            'provider' => 'letsencrypt_ssl',
            'ready' => true,
            'supports' => [
                'dns_01' => true,
                'http_01' => true,
                'renewal_tracking' => true,
                'reconciliation' => true,
            ],
        ];
    }

    private function directoryUrl(): string
    {
        $custom = getenv('RATEB_INFRA_LE_ACME_DIRECTORY');
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }
        if ($this->rollout->executionMode('letsencrypt_ssl') === 'live') {
            return 'https://acme-v02.api.letsencrypt.org/directory';
        }
        return 'https://acme-staging-v02.api.letsencrypt.org/directory';
    }

    /**
     * @param array<string, mixed> $result
     */
    private function logProviderEvent(string $eventName, TenantContext $tenant, float $startedAt, string $requestId, array $result): void
    {
        $state = strtolower((string) ($result['state'] ?? 'unknown'));
        $status = in_array($state, ['validation_prepared', 'revoke_prepared'], true)
            ? 'success'
            : (!empty($result['retryable']) ? 'retry' : 'failed');
        ProviderEventBus::log('ssl', 'letsencrypt_ssl', $eventName, [
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

